<?php
session_start();
require_once 'db.php';

$success_msg = '';
$error_msg   = '';

// Xử lý tạo đơn hàng từ giỏ/mua ngay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy_now') {
    $product_id       = (int)($_POST['product_id'] ?? 0);
    $buy_quantity     = (int)($_POST['quantity'] ?? 1);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_phone   = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $payment_method   = $_POST['payment_method'] ?? 'COD';
    $notes            = trim($_POST['notes'] ?? '');
    $user_id          = $_SESSION['user_id'] ?? null;

    if ($buy_quantity <= 0) {
        $error_msg = "Số lượng mua phải lớn hơn 0!";
    } elseif (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
        $error_msg = "Vui lòng nhập đầy đủ tên, số điện thoại và địa chỉ giao hàng!";
    } else {
        try {
            $pdo->beginTransaction();

            // Khóa dòng sản phẩm để kiểm tra tồn kho chính xác (chống race condition)
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $product_id]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception("Sản phẩm không tồn tại trong hệ thống!");
            }

            if ($product['stock_quantity'] < $buy_quantity) {
                throw new Exception("Số lượng trong kho không đủ (Hiện còn: {$product['stock_quantity']})!");
            }

            $item_price   = (float)$product['price'];
            $total_amount = $item_price * $buy_quantity;
            $order_code   = 'DH' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // 1. Tạo đơn hàng
            $stmt_order = $pdo->prepare("
                INSERT INTO orders (order_code, user_id, customer_name, customer_phone, customer_address, total_amount, status, payment_method, payment_status, notes)
                VALUES (:code, :uid, :cname, :cphone, :caddr, :total, 'Pending', :pmethod, 'Unpaid', :notes)
            ");
            $stmt_order->execute([
                ':code'    => $order_code,
                ':uid'     => $user_id,
                ':cname'   => $customer_name,
                ':cphone'  => $customer_phone,
                ':caddr'   => $customer_address,
                ':total'   => $total_amount,
                ':pmethod' => $payment_method,
                ':notes'   => $notes
            ]);
            $order_id = $pdo->lastInsertId();

            // 2. Thêm vào chi tiết đơn hàng
            $stmt_detail = $pdo->prepare("
                INSERT INTO order_details (order_id, product_id, quantity, price)
                VALUES (:oid, :pid, :qty, :price)
            ");
            $stmt_detail->execute([
                ':oid'   => $order_id,
                ':pid'   => $product_id,
                ':qty'   => $buy_quantity,
                ':price' => $item_price
            ]);

            // 3. Trừ tồn kho tự động trong bảng products
            $stmt_stock = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - :qty 
                WHERE id = :pid
            ");
            $stmt_stock->execute([
                ':qty' => $buy_quantity,
                ':pid' => $product_id
            ]);

            $pdo->commit();

            // Chuyển hướng ngay đến trang xem và in hóa đơn (kèm mã QR)
            header("Location: order_invoice.php?order_code=" . urlencode($order_code) . "&placed=1");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = $e->getMessage();
        }
    }
}

// Tìm kiếm & Lọc danh mục
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE :s1 OR code LIKE :s2 OR category LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if ($category !== '') {
    $sql .= " AND category = :cat";
    $params[':cat'] = $category;
}

$sql .= " ORDER BY (stock_quantity > 0) DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lấy danh mục để hiển thị bộ lọc
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);

// Lấy thông tin user hiện tại nếu đã đăng nhập để tự điền form
$user_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt_u = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt_u->execute([':id' => $_SESSION['user_id']]);
    $user_info = $stmt_u->fetch();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cửa Hàng Trực Tuyến | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .hero-banner {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            padding: 40px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        .product-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        .price-text {
            color: #d9230f;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .stock-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<!-- Hero Banner -->
<div class="hero-banner shadow-sm">
    <div class="container text-center">
        <h1 class="fw-bold mb-2"><i class="bi bi-cart4 me-2"></i>Cửa Hàng Tiện Ích MiniShop</h1>
        <p class="lead mb-0 text-white-50">Hệ thống phân phối & Quản lý tồn kho hàng hóa </p>
    </div>
</div>

<div class="container mb-5">
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Thanh tìm kiếm & bộ lọc -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Tìm sản phẩm theo tên, mã sản phẩm..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">-- Tất cả danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Lọc</button>
                    <?php if ($search !== '' || $category !== ''): ?>
                        <a href="index.php" class="btn btn-outline-secondary" title="Đặt lại"><i class="bi bi-arrow-clockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách sản phẩm -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0 text-dark">
            <i class="bi bi-grid-fill text-primary me-2"></i>Danh Sách Hàng Hóa 
            <small class="text-muted fs-6">(<?= count($products) ?> sản phẩm)</small>
        </h4>
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
            <a href="inventory.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square me-1"></i>Chỉnh sửa kho hàng
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-info text-center py-5 shadow-sm">
            <i class="bi bi-inbox fs-1 d-block text-secondary mb-2"></i>
            <h5>Không tìm thấy sản phẩm nào phù hợp!</h5>
            <p class="text-muted">Vui lòng thử tìm kiếm với từ khóa khác.</p>
            <a href="index.php" class="btn btn-sm btn-primary">Xem toàn bộ sản phẩm</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <?php $is_out_of_stock = ($p['stock_quantity'] <= 0); ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 product-card shadow-sm bg-white">
                        <div class="p-4 text-center bg-light border-bottom position-relative">
                            <span class="badge bg-secondary position-absolute top-0 start-0 m-2"><?= e($p['code']) ?></span>
                            
                            <?php if ($is_out_of_stock): ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">Hết hàng</span>
                            <?php elseif ($p['stock_quantity'] <= $p['min_stock_level']): ?>
                                <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">Sắp hết (Còn <?= $p['stock_quantity'] ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-success position-absolute top-0 end-0 m-2">Còn <?= $p['stock_quantity'] ?></span>
                            <?php endif; ?>

                            <i class="bi bi-box-seam display-4 text-primary opacity-75 my-3 d-inline-block"></i>
                            <div class="text-muted small"><?= e($p['category'] ?: 'Khác') ?></div>
                        </div>

                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title fw-bold text-dark mb-2" style="min-height: 40px;" title="<?= e($p['name']) ?>">
                                <?= e($p['name']) ?>
                            </h6>
                            <div class="price-text mb-3">
                                <?= number_format($p['price'], 0, ',', '.') ?> đ
                            </div>

                            <div class="mt-auto">
                                <?php if ($is_out_of_stock): ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="bi bi-x-circle me-1"></i>Tạm Hết Hàng
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary w-100 fw-bold btn-buy" 
                                            data-id="<?= $p['id'] ?>"
                                            data-code="<?= e($p['code']) ?>"
                                            data-name="<?= e($p['name']) ?>"
                                            data-price="<?= number_format($p['price'], 0, ',', '.') ?>"
                                            data-stock="<?= $p['stock_quantity'] ?>">
                                        <i class="bi bi-bag-plus me-1"></i>Đặt Mua Ngay
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Đặt Hàng Nhanh -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-bag-check-fill me-2"></i>Xác Nhận Đặt Hàng</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="buy_now">
                    <input type="hidden" name="product_id" id="modal_product_id">

                    <!-- Thông tin tóm tắt sản phẩm -->
                    <div class="alert alert-light border p-3 mb-3">
                        <div class="fw-bold text-primary" id="modal_product_name">Tên sản phẩm</div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-danger fw-bold fs-5" id="modal_product_price">0 đ</span>
                            <span class="badge bg-secondary" id="modal_product_stock">Kho: 0</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Số lượng mua <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="modal_quantity" class="form-control" value="1" min="1" max="99" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Họ tên người nhận <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" class="form-control" value="<?= e($user_info['fullname'] ?? '') ?>" placeholder="Nguyễn Văn A" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Số điện thoại liên hệ <span class="text-danger">*</span></label>
                        <input type="tel" name="customer_phone" class="form-control" value="<?= e($user_info['phone'] ?? '') ?>" placeholder="09xxxxxxxx" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Địa chỉ giao hàng <span class="text-danger">*</span></label>
                        <textarea name="customer_address" class="form-control" rows="2" placeholder="Số nhà, đường, phường/xã, quận/huyện..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phương thức thanh toán</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check border rounded p-2 ps-4">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="COD" checked>
                                    <label class="form-check-label" for="pay_cod">
                                        <strong>COD</strong><br><small class="text-muted">Tiền mặt khi nhận</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check border rounded p-2 ps-4">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_qr" value="Chuyển khoản QR">
                                    <label class="form-check-label" for="pay_qr">
                                        <strong>Chuyển khoản</strong><br><small class="text-muted">Quét mã VietQR</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-muted">Ghi chú đơn hàng (nếu có)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="VD: Giao giờ hành chính, gọi trước khi giao...">
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-check2-circle me-1"></i>Xác Nhận Đặt Hàng
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-1 fw-bold"><?= SHOP_NAME ?> - Hệ Thống Quản Lý Kho & Bán Hàng</p>
        <p class="small text-white-50 mb-0">Địa chỉ: Nhóm 5 | Hotline: 123456789</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-buy').forEach(button => {
    button.addEventListener('click', function() {
        const id    = this.getAttribute('data-id');
        const name  = this.getAttribute('data-name');
        const price = this.getAttribute('data-price');
        const stock = this.getAttribute('data-stock');

        document.getElementById('modal_product_id').value = id;
        document.getElementById('modal_product_name').textContent = name;
        document.getElementById('modal_product_price').textContent = price + ' đ';
        document.getElementById('modal_product_stock').textContent = 'Kho còn: ' + stock;
        
        const qtyInput = document.getElementById('modal_quantity');
        qtyInput.max = stock;
        qtyInput.value = 1;

        const modal = new bootstrap.Modal(document.getElementById('orderModal'));
        modal.show();
    });
});
</script>
</body>
</html>
