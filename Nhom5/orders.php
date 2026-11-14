<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_staff_or_admin();

$msg = '';
$err = '';

// Cập nhật trạng thái đơn hàng & thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $order_id       = (int)($_POST['order_id'] ?? 0);
    $new_status     = $_POST['status'] ?? 'Pending';
    $payment_status = $_POST['payment_status'] ?? 'Unpaid';

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = :st, payment_status = :pst WHERE id = :id");
        $stmt->execute([
            ':st'  => $new_status,
            ':pst' => $payment_status,
            ':id'  => $order_id
        ]);
        $msg = "Đã cập nhật trạng thái đơn hàng #$order_id thành công!";
    } catch (PDOException $e) {
        $err = "Lỗi khi cập nhật đơn hàng: " . $e->getMessage();
    }
}

// Bộ lọc trạng thái & tìm kiếm
$status_filter = trim($_GET['status'] ?? '');
$search        = trim($_GET['search'] ?? '');

$sql = "
    SELECT o.*, u.username,
           (SELECT COUNT(*) FROM order_details WHERE order_id = o.id) AS total_items
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($status_filter !== '') {
    $sql .= " AND o.status = :st";
    $params[':st'] = $status_filter;
}

if ($search !== '') {
    $sql .= " AND (o.order_code LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_phone LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

$sql .= " ORDER BY o.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders_list = $stmt->fetchAll();

// Thống kê số lượng đơn theo trạng thái
$count_all        = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$count_pending    = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();
$count_processing = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Processing'")->fetchColumn();
$count_shipping   = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Shipping'")->fetchColumn();
$count_completed  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Completed'")->fetchColumn();
$count_cancelled  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Cancelled'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đơn Hàng | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 py-4">
    <!-- Tiêu đề -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-receipt text-primary me-2"></i>Quản Lý Đơn Hàng & Vận Chuyển</h3>
            <p class="text-muted small mb-0">Theo dõi tiến độ xử lý đơn hàng, xuất hóa đơn và đối soát thanh toán QR</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-primary"><i class="bi bi-cart-plus me-1"></i>Tạo Đơn Hàng Mới</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Bộ lọc tab trạng thái -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="btn-group btn-group-sm flex-wrap">
                    <a href="orders.php" class="btn <?= $status_filter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Tất Cả (<?= $count_all ?>)
                    </a>
                    <a href="orders.php?status=Pending" class="btn <?= $status_filter === 'Pending' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">
                        Chờ Duyệt (<?= $count_pending ?>)
                    </a>
                    <a href="orders.php?status=Processing" class="btn <?= $status_filter === 'Processing' ? 'btn-info text-dark' : 'btn-outline-info text-dark' ?>">
                        Đang Xử Lý (<?= $count_processing ?>)
                    </a>
                    <a href="orders.php?status=Shipping" class="btn <?= $status_filter === 'Shipping' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Đang Giao (<?= $count_shipping ?>)
                    </a>
                    <a href="orders.php?status=Completed" class="btn <?= $status_filter === 'Completed' ? 'btn-success' : 'btn-outline-success' ?>">
                        Hoàn Thành (<?= $count_completed ?>)
                    </a>
                    <a href="orders.php?status=Cancelled" class="btn <?= $status_filter === 'Cancelled' ? 'btn-danger' : 'btn-outline-danger' ?>">
                        Đã Hủy (<?= $count_cancelled ?>)
                    </a>
                </div>

                <form method="GET" action="orders.php" class="d-flex gap-2">
                    <?php if ($status_filter !== ''): ?><input type="hidden" name="status" value="<?= e($status_filter) ?>"><?php endif; ?>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Tìm mã đơn, tên, SĐT..." value="<?= e($search) ?>" style="min-width: 200px;">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if ($search !== ''): ?>
                        <a href="orders.php<?= $status_filter ? '?status=' . urlencode($status_filter) : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Bảng danh sách đơn hàng -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã Đơn Hàng</th>
                        <th>Khách Hàng & Liên Hệ</th>
                        <th>Địa Chỉ Giao</th>
                        <th class="text-end">Tổng Tiền</th>
                        <th>PT Thanh Toán</th>
                        <th>Trạng Thái TT</th>
                        <th>Ngày Đặt</th>
                        <th style="min-width: 250px;">Cập Nhật Tiến Độ</th>
                        <th class="text-center">Hóa Đơn / QR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Không tìm thấy đơn hàng nào!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders_list as $ord): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary">#<?= e($ord['order_code']) ?></strong>
                                    <?php if ($ord['notes']): ?>
                                        <div class="small text-muted" title="<?= e($ord['notes']) ?>"><i class="bi bi-chat-left-dots me-1"></i><?= e($ord['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ord['customer_name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($ord['customer_phone']) ?></div>
                                    <?php if ($ord['username']): ?>
                                        <span class="badge bg-light text-secondary border">User: <?= e($ord['username']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Khách vãng lai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted text-truncate" style="max-width: 220px;" title="<?= e($ord['customer_address']) ?>">
                                    <?= e($ord['customer_address']) ?>
                                </td>
                                <td class="text-end fw-bold text-danger fs-6">
                                    <?= number_format($ord['total_amount'], 0, ',', '.') ?> đ
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e($ord['payment_method']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $ord['payment_status'] === 'Paid' ? 'success' : 'secondary' ?>">
                                        <?= $ord['payment_status'] === 'Paid' ? 'Đã thanh toán' : 'Chưa TT' ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?>
                                </td>
                                <td>
                                    <form method="POST" action="orders.php<?= $status_filter ? '?status=' . urlencode($status_filter) : '' ?>" class="d-flex align-items-center gap-1">
                                        <input type="hidden" name="action" value="update_order">
                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">

                                        <select name="status" class="form-select form-select-sm" style="font-size: 0.85rem;">
                                            <option value="Pending" <?= $ord['status'] === 'Pending' ? 'selected' : '' ?>>Chờ Duyệt (Pending)</option>
                                            <option value="Processing" <?= $ord['status'] === 'Processing' ? 'selected' : '' ?>>Đang Xử Lý (Processing)</option>
                                            <option value="Shipping" <?= $ord['status'] === 'Shipping' ? 'selected' : '' ?>>Đang Giao (Shipping)</option>
                                            <option value="Completed" <?= $ord['status'] === 'Completed' ? 'selected' : '' ?>>Hoàn Thành (Completed)</option>
                                            <option value="Cancelled" <?= $ord['status'] === 'Cancelled' ? 'selected' : '' ?>>Hủy Đơn (Cancelled)</option>
                                        </select>

                                        <select name="payment_status" class="form-select form-select-sm" style="width: 105px; font-size: 0.85rem;">
                                            <option value="Unpaid" <?= $ord['payment_status'] === 'Unpaid' ? 'selected' : '' ?>>Chưa TT</option>
                                            <option value="Paid" <?= $ord['payment_status'] === 'Paid' ? 'selected' : '' ?>>Đã TT</option>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-primary" title="Lưu thay đổi">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <a href="order_invoice.php?order_code=<?= urlencode($ord['order_code']) ?>" class="btn btn-sm btn-outline-dark" target="_blank" title="In Hóa Đơn & Quét QR">
                                        <i class="bi bi-printer me-1"></i>In / QR
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
