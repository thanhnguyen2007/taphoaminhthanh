<?php
session_start();
require_once 'db.php';

$order_code = trim($_GET['order_code'] ?? '');
$order_id   = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_code !== '') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = :code LIMIT 1");
    $stmt->execute([':code' => $order_code]);
    $order = $stmt->fetch();
} elseif ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch();
} else {
    die("Không tìm thấy thông tin đơn hàng!");
}

if (!$order) {
    die("Đơn hàng không tồn tại trong hệ thống!");
}

// Lấy danh sách sản phẩm trong đơn hàng
$stmt_items = $pdo->prepare("
    SELECT od.*, p.name AS product_name, p.code AS product_code
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    WHERE od.order_id = :oid
");
$stmt_items->execute([':oid' => $order['id']]);
$items = $stmt_items->fetchAll();

// Tạo URL mã VietQR động theo chuẩn Napas 24/7
$bank_id      = BANK_ID;
$account_no   = ACCOUNT_NO;
$account_name = urlencode(ACCOUNT_NAME);
$amount       = (int)$order['total_amount'];
$add_info     = urlencode($order['order_code']);

// VietQR API Quick Link
$qr_url = "https://img.vietqr.io/image/{$bank_id}-{$account_no}-compact2.png?amount={$amount}&addInfo={$add_info}&accountName={$account_name}";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa Đơn #<?= e($order['order_code']) ?> | <?= SHOP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            color: #333;
        }
        .invoice-box {
            max-width: 850px;
            margin: 30px auto;
            padding: 35px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        @media print {
            body {
                background: #fff !important;
            }
            .invoice-box {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 10px !important;
                max-width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Thanh công cụ (ẩn khi in) -->
<div class="no-print bg-dark text-white py-2 mb-3">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-light me-2"><i class="bi bi-arrow-left"></i> Trang Chủ</a>
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                <a href="orders.php" class="btn btn-sm btn-outline-warning">Quản lý đơn hàng</a>
            <?php elseif (isset($_SESSION['user_id'])): ?>
                <a href="my_orders.php" class="btn btn-sm btn-outline-info">Đơn hàng của tôi</a>
            <?php endif; ?>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-sm btn-success fw-bold">
                <i class="bi bi-printer-fill me-1"></i> In Hóa Đơn (Print Bill)
            </button>
        </div>
    </div>
</div>

<div class="invoice-box">
    <?php if (isset($_GET['placed'])): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 no-print mb-4">
            <i class="bi bi-check-circle-fill fs-4"></i>
            <div>
                <strong>Đặt hàng thành công!</strong> Đơn hàng của bạn đã được ghi nhận vào hệ thống kho.
            </div>
        </div>
    <?php endif; ?>

    <!-- Header hóa đơn -->
    <div class="row align-items-center border-bottom pb-4 mb-4">
        <div class="col-sm-7">
            <h3 class="fw-bold text-primary mb-1"><i class="bi bi-box-seam me-2"></i><?= SHOP_NAME ?></h3>
            <p class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><?= SHOP_ADDRESS ?></p>
            <p class="text-muted small mb-0"><i class="bi bi-telephone me-1"></i>Hotline: <strong><?= SHOP_PHONE ?></strong></p>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <h4 class="fw-bold text-uppercase text-secondary mb-1">HÓA ĐƠN BÁN HÀNG</h4>
            <div class="text-danger fw-bold fs-5">#<?= e($order['order_code']) ?></div>
            <div class="text-muted small">Ngày lập: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
        </div>
    </div>

    <!-- Thông tin khách hàng & Giao hàng -->
    <div class="row mb-4">
        <div class="col-sm-6">
            <h6 class="fw-bold text-dark text-uppercase small border-bottom pb-1 mb-2">Thông Tin Khách Hàng:</h6>
            <div class="fw-bold"><?= e($order['customer_name']) ?></div>
            <div>SĐT: <?= e($order['customer_phone']) ?></div>
            <div>Địa chỉ: <?= e($order['customer_address']) ?></div>
            <?php if (!empty($order['notes'])): ?>
                <div class="text-muted small mt-1"><em>Ghi chú: <?= e($order['notes']) ?></em></div>
            <?php endif; ?>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <h6 class="fw-bold text-dark text-uppercase small border-bottom pb-1 mb-2">Trạng Thái & Thanh Toán:</h6>
            <div>Phương thức: <strong><?= e($order['payment_method']) ?></strong></div>
            <div>Trạng thái đơn: 
                <span class="badge bg-<?= 
                    $order['status'] === 'Completed' ? 'success' : 
                    ($order['status'] === 'Cancelled' ? 'danger' : 
                    ($order['status'] === 'Shipping' ? 'info text-dark' : 'warning text-dark')) 
                ?>">
                    <?= e($order['status']) ?>
                </span>
            </div>
            <div class="mt-1">Thanh toán: 
                <span class="badge bg-<?= $order['payment_status'] === 'Paid' ? 'success' : 'secondary' ?>">
                    <?= $order['payment_status'] === 'Paid' ? 'Đã Thanh Toán' : 'Chưa Thanh Toán' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Danh sách sản phẩm -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr class="text-center">
                    <th style="width: 50px;">STT</th>
                    <th>Tên Sản Phẩm</th>
                    <th style="width: 130px;">Đơn Giá</th>
                    <th style="width: 90px;">Số Lượng</th>
                    <th style="width: 140px;" class="text-end">Thành Tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php $stt = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $stt++ ?></td>
                        <td>
                            <strong><?= e($item['product_name']) ?></strong>
                            <br><small class="text-muted">Mã: <?= e($item['product_code']) ?></small>
                        </td>
                        <td class="text-center"><?= number_format($item['price'], 0, ',', '.') ?> đ</td>
                        <td class="text-center fw-bold"><?= $item['quantity'] ?></td>
                        <td class="text-end fw-bold text-primary">
                            <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?> đ
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end fw-bold fs-6">TỔNG CỘNG TIỀN THANH TOÁN:</td>
                    <td class="text-end text-danger fw-bold fs-5">
                        <?= number_format($order['total_amount'], 0, ',', '.') ?> đ
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Khối Quét Mã QR Thanh Toán (VietQR Online Payment) -->
    <div class="card border-0 bg-light p-3 mb-4 rounded">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h6 class="fw-bold text-primary mb-1"><i class="bi bi-qr-code me-1"></i>Thanh Toán Nhanh Bằng VietQR</h6>
                <p class="text-muted small mb-2">
                    Mở ứng dụng Ngân hàng (MBBank, Vietcombank, Vietinbank, TPBank, MoMo...) và quét mã để thanh toán đúng số tiền và nội dung đơn hàng.
                </p>
                <ul class="list-unstyled small mb-0 text-secondary">
                    <li>- Ngân hàng: <strong><?= BANK_ID ?></strong></li>
                    <li>- Số tài khoản: <strong><?= ACCOUNT_NO ?></strong></li>
                    <li>- Chủ tài khoản: <strong><?= ACCOUNT_NAME ?></strong></li>
                    <li>- Nội dung CK: <strong class="text-danger"><?= e($order['order_code']) ?></strong></li>
                    <li>- Số tiền: <strong class="text-danger"><?= number_format($order['total_amount'], 0, ',', '.') ?> đ</strong></li>
                </ul>
            </div>
            <div class="col-md-5 text-center mt-3 mt-md-0">
                <div class="p-2 bg-white d-inline-block rounded shadow-sm">
                    <img src="<?= $qr_url ?>" alt="Mã VietQR Thanh Toán" class="img-fluid" style="max-height: 200px;">
                    <div class="small text-muted mt-1"><i class="bi bi-shield-check text-success"></i> Quét mã Napas 24/7</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lời cảm ơn và ký nhận -->
    <div class="row text-center mt-4 pt-3 border-top">
        <div class="col-6">
            <p class="fw-bold mb-1">Người Mua Hàng</p>
            <p class="small text-muted">(Ký và ghi rõ họ tên)</p>
        </div>
        <div class="col-6">
            <p class="fw-bold mb-1">Đại Diện Cửa Hàng</p>
            <p class="small text-muted">(Ký, đóng dấu)</p>
        </div>
    </div>

    <div class="text-center text-muted small mt-5 pt-3">
        <em>Cảm ơn Quý khách đã mua sắm tại <?= SHOP_NAME ?>! Mọi thắc mắc xin gọi hotline: <?= SHOP_PHONE ?></em>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
