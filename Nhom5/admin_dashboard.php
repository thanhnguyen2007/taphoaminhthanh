<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_staff_or_admin();

// Cập nhật trạng thái đơn hàng nhanh
$alert_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id       = (int)($_POST['order_id'] ?? 0);
    $status         = $_POST['status'] ?? 'Pending';
    $payment_status = $_POST['payment_status'] ?? 'Unpaid';

    $stmt = $pdo->prepare("UPDATE orders SET status = :status, payment_status = :pstatus WHERE id = :id");
    $stmt->execute([
        ':status'  => $status,
        ':pstatus' => $payment_status,
        ':id'      => $order_id
    ]);
    $alert_msg = "Đã cập nhật trạng thái đơn hàng #$order_id thành công!";
}

// 1. Thống kê tổng doanh thu đơn đã hoàn thành
$total_revenue = $pdo->query("
    SELECT COALESCE(SUM(total_amount), 0) 
    FROM orders 
    WHERE status = 'Completed'
")->fetchColumn();

// 2. Thống kê tổng lợi nhuận (Doanh thu - Giá vốn) từ các đơn hoàn thành
$total_profit = $pdo->query("
    SELECT COALESCE(SUM((od.price - p.cost_price) * od.quantity), 0)
    FROM order_details od
    JOIN orders o ON od.order_id = o.id
    JOIN products p ON od.product_id = p.id
    WHERE o.status = 'Completed'
")->fetchColumn();

// 3. Số đơn hàng chờ xử lý
$pending_orders_count = $pdo->query("
    SELECT COUNT(*) FROM orders WHERE status IN ('Pending', 'Processing')
")->fetchColumn();

// 4. Số sản phẩm cảnh báo sắp hết hàng
$low_stock_products = $pdo->query("
    SELECT * FROM products 
    WHERE stock_quantity <= min_stock_level 
    ORDER BY stock_quantity ASC
")->fetchAll();
$low_stock_count = count($low_stock_products);

// 5. Top 5 mặt hàng bán chạy nhất
$best_sellers = $pdo->query("
    SELECT p.code, p.name, p.category, 
           SUM(od.quantity) AS total_sold, 
           SUM(od.quantity * od.price) AS total_revenue
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    JOIN orders o ON od.order_id = o.id
    WHERE o.status != 'Cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

// 6. Dữ liệu biểu đồ doanh thu 7 ngày gần nhất
$chart_query = $pdo->query("
    SELECT DATE(created_at) as order_date, COALESCE(SUM(total_amount), 0) as daily_revenue
    FROM orders
    WHERE status = 'Completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
")->fetchAll();

$chart_labels = [];
$chart_values = [];
// Tạo mốc 7 ngày
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($d));
    $found_val = 0;
    foreach ($chart_query as $row) {
        if ($row['order_date'] === $d) {
            $found_val = (float)$row['daily_revenue'];
            break;
        }
    }
    $chart_values[] = $found_val;
}

// 7. Đơn hàng gần đây
$recent_orders = $pdo->query("
    SELECT o.*, u.username 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.id DESC 
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Điều Khiển Quản Trị | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 12px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .pulse-warning {
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
    </style>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <!-- Tiêu đề trang -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Tổng Quan Hệ Thống Quản Lý Kho & Đơn Hàng</h3>
            <p class="text-muted small mb-0">Theo dõi doanh thu, tồn kho thời gian thực và quản lý đơn bán</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="imports.php" class="btn btn-outline-success"><i class="bi bi-box-arrow-in-down me-1"></i>Nhập Kho</a>
            <a href="inventory.php" class="btn btn-primary"><i class="bi bi-boxes me-1"></i>Quản Lý Kho</a>
        </div>
    </div>

    <?php if ($alert_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($alert_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 4 THẺ THỐNG KÊ KPI -->
    <div class="row g-3 mb-4">
        <!-- Doanh thu -->
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white-50 text-uppercase fw-semibold small">Tổng Doanh Thu</span>
                        <i class="bi bi-currency-dollar fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-0"><?= number_format($total_revenue, 0, ',', '.') ?> đ</h3>
                    <small class="text-white-50">Đơn hàng hoàn thành</small>
                </div>
            </div>
        </div>

        <!-- Lợi nhuận -->
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white-50 text-uppercase fw-semibold small">Lợi Nhuận Thuần</span>
                        <i class="bi bi-graph-up-arrow fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-0"><?= number_format($total_profit, 0, ',', '.') ?> đ</h3>
                    <small class="text-white-50">Doanh thu - Giá vốn</small>
                </div>
            </div>
        </div>

        <!-- Đơn hàng cần xử lý -->
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-dark-50 text-uppercase fw-semibold small">Đơn Chờ Xử Lý</span>
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-0"><?= $pending_orders_count ?> đơn</h3>
                    <a href="orders.php" class="text-dark small text-decoration-underline">Xử lý ngay &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Cảnh báo sắp hết hàng -->
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm bg-danger text-white <?= $low_stock_count > 0 ? 'pulse-warning' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white-50 text-uppercase fw-semibold small">Cảnh Báo Hết Hàng</span>
                        <i class="bi bi-exclamation-octagon-fill fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-0"><?= $low_stock_count ?> mặt hàng</h3>
                    <a href="inventory.php?filter=low" class="text-white small text-decoration-underline">Xem kho hàng cảnh báo &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- BIỂU ĐỒ DOANH THU & TOP BÁN CHẠY -->
    <div class="row g-4 mb-4">
        <!-- Biểu đồ doanh thu 7 ngày -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-bar-chart-fill text-primary me-2"></i>Doanh Thu 7 Ngày Gần Nhất (VNĐ)</span>
                    <span class="badge bg-light text-dark border">Thời gian thực</span>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" style="max-height: 280px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Mặt hàng bán chạy nhất -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-trophy-fill text-warning me-2"></i>Top 5 Mặt Hàng Bán Chạy Nhất
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($best_sellers)): ?>
                            <li class="list-group-item text-center py-4 text-muted">Chưa có dữ liệu bán hàng!</li>
                        <?php else: ?>
                            <?php foreach ($best_sellers as $idx => $bs): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-circle bg-<?= $idx === 0 ? 'warning text-dark' : ($idx === 1 ? 'secondary' : 'light text-dark border') ?>" style="width: 25px; height: 25px; line-height: 18px;">
                                            <?= $idx + 1 ?>
                                        </span>
                                        <div>
                                            <div class="fw-bold small text-truncate" style="max-width: 220px;" title="<?= e($bs['name']) ?>">
                                                <?= e($bs['name']) ?>
                                            </div>
                                            <small class="text-muted"><?= e($bs['category']) ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success"><?= $bs['total_sold'] ?> đã bán</div>
                                        <small class="text-muted"><?= number_format($bs['total_revenue'], 0, ',', '.') ?>đ</small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- DANH SÁCH SẢN PHẨM CẢNH BÁO SẮP HẾT HÀNG (REAL-TIME ALERT) -->
    <?php if ($low_stock_count > 0): ?>
        <div class="card shadow-sm border-0 mb-4 border-start border-danger border-4">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-bell-fill me-2"></i>Cảnh Báo: <?= $low_stock_count ?> Sản Phẩm Tồn Kho Đang Dưới Ngưỡng Tối Thiểu!</span>
                <a href="imports.php" class="btn btn-sm btn-light text-danger fw-bold"><i class="bi bi-plus-circle me-1"></i>Tạo Phiếu Nhập Kho</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên Sản Phẩm</th>
                            <th>Danh Mục</th>
                            <th class="text-center">Tồn Kho Hiện Tại</th>
                            <th class="text-center">Ngưỡng Tối Thiểu</th>
                            <th class="text-center">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_products as $lp): ?>
                            <tr class="table-danger">
                                <td><span class="badge bg-secondary"><?= e($lp['code']) ?></span></td>
                                <td class="fw-bold"><?= e($lp['name']) ?></td>
                                <td><?= e($lp['category']) ?></td>
                                <td class="text-center fw-bold text-danger fs-6"><?= $lp['stock_quantity'] ?></td>
                                <td class="text-center text-muted"><?= $lp['min_stock_level'] ?></td>
                                <td class="text-center">
                                    <a href="imports.php?quick_product_id=<?= $lp['id'] ?>" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-box-arrow-in-down me-1"></i>Nhập Thêm
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- DANH SÁCH ĐƠN HÀNG GẦN ĐÂY -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center py-3">
            <span><i class="bi bi-receipt-cutoff text-primary me-2"></i>Đơn Hàng Gần Đây</span>
            <a href="orders.php" class="btn btn-sm btn-outline-primary">Xem tất cả đơn hàng &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã Đơn</th>
                        <th>Khách Hàng</th>
                        <th>Tổng Tiền</th>
                        <th>Phương Thức</th>
                        <th>Thanh Toán</th>
                        <th>Ngày Đặt</th>
                        <th>Trạng Thái & Thao Tác</th>
                        <th class="text-center">In Hóa Đơn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $ord): ?>
                        <tr>
                            <td><strong class="text-primary">#<?= e($ord['order_code']) ?></strong></td>
                            <td>
                                <div class="fw-semibold"><?= e($ord['customer_name']) ?></div>
                                <small class="text-muted"><?= e($ord['customer_phone']) ?></small>
                            </td>
                            <td class="fw-bold text-danger"><?= number_format($ord['total_amount'], 0, ',', '.') ?> đ</td>
                            <td><?= e($ord['payment_method']) ?></td>
                            <td>
                                <span class="badge bg-<?= $ord['payment_status'] === 'Paid' ? 'success' : 'secondary' ?>">
                                    <?= $ord['payment_status'] === 'Paid' ? 'Đã thanh toán' : 'Chưa TT' ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></td>
                            <td>
                                <form method="POST" class="d-flex align-items-center gap-1">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                    <select name="status" class="form-select form-select-sm w-auto">
                                        <?php foreach (['Pending', 'Processing', 'Shipping', 'Completed', 'Cancelled'] as $st): ?>
                                            <option value="<?= $st ?>" <?= $ord['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="payment_status" class="form-select form-select-sm w-auto">
                                        <option value="Unpaid" <?= $ord['payment_status'] === 'Unpaid' ? 'selected' : '' ?>>Chưa TT</option>
                                        <option value="Paid" <?= $ord['payment_status'] === 'Paid' ? 'selected' : '' ?>>Đã TT</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" title="Lưu thay đổi"><i class="bi bi-check-lg"></i></button>
                                </form>
                            </td>
                            <td class="text-center">
                                <a href="order_invoice.php?order_code=<?= urlencode($ord['order_code']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Xem & In Hóa đơn">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Vẽ biểu đồ doanh thu Chart.js
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Doanh Thu (VNĐ)',
            data: <?= json_encode($chart_values) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.75)',
            borderColor: 'rgb(13, 110, 253)',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('vi-VN') + ' đ';
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>
