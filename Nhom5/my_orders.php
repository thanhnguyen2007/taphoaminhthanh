<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_login();

$user_id = $_SESSION['user_id'];

// Lấy danh sách đơn hàng của khách hàng hiện tại
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE user_id = :uid 
    ORDER BY id DESC
");
$stmt->execute([':uid' => $user_id]);
$my_orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-bag-check-fill text-primary me-2"></i>Lịch Sử Đơn Hàng Của Tôi</h3>
            <p class="text-muted small mb-0">Theo dõi tiến độ giao hàng và thông tin thanh toán</p>
        </div>
        <a href="index.php" class="btn btn-primary"><i class="bi bi-cart-plus me-1"></i>Tiếp Tục Mua Sắm</a>
    </div>

    <?php if (empty($my_orders)): ?>
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <i class="bi bi-bag-x display-3 text-secondary mb-3 d-inline-block"></i>
                <h5 class="fw-bold">Bạn chưa có đơn hàng nào!</h5>
                <p class="text-muted">Khám phá các sản phẩm tiện ích tại cửa hàng và đặt hàng ngay.</p>
                <a href="index.php" class="btn btn-primary px-4">Xem Sản Phẩm</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã Đơn Hàng</th>
                            <th>Ngày Đặt</th>
                            <th>Người Nhận & Địa Chỉ</th>
                            <th>Tổng Tiền</th>
                            <th>Thanh Toán</th>
                            <th>Trạng Thái</th>
                            <th class="text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_orders as $ord): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">#<?= e($ord['order_code']) ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($ord['customer_name']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 200px;" title="<?= e($ord['customer_address']) ?>">
                                        <?= e($ord['customer_address']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger"><?= number_format($ord['total_amount'], 0, ',', '.') ?> đ</span>
                                </td>
                                <td>
                                    <div class="small">PT: <?= e($ord['payment_method']) ?></div>
                                    <span class="badge bg-<?= $ord['payment_status'] === 'Paid' ? 'success' : 'secondary' ?>">
                                        <?= $ord['payment_status'] === 'Paid' ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = match($ord['status']) {
                                        'Completed'  => 'success',
                                        'Shipping'   => 'info text-dark',
                                        'Processing' => 'primary',
                                        'Cancelled'  => 'danger',
                                        default      => 'warning text-dark',
                                    };
                                    ?>
                                    <span class="badge bg-<?= $badge_class ?>"><?= e($ord['status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="order_invoice.php?order_code=<?= urlencode($ord['order_code']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-file-earmark-text me-1"></i>Hóa Đơn / QR
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
