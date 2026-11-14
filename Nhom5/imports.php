<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_staff_or_admin();

$errors = [];
$success_msg = '';

// Xử lý tạo Phiếu Nhập Hàng Mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_import') {
    $supplier_id  = (int)($_POST['supplier_id'] ?? 0);
    $product_id   = (int)($_POST['product_id'] ?? 0);
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $import_price = (float)($_POST['import_price'] ?? 0);
    $note         = trim($_POST['note'] ?? '');
    $user_id      = $_SESSION['user_id'];

    if ($supplier_id <= 0) {
        $errors[] = "Vui lòng chọn nhà cung cấp!";
    }
    if ($product_id <= 0) {
        $errors[] = "Vui lòng chọn sản phẩm cần nhập!";
    }
    if ($quantity <= 0) {
        $errors[] = "Số lượng nhập phải lớn hơn 0!";
    }
    if ($import_price <= 0) {
        $errors[] = "Giá nhập (đơn giá vốn) phải lớn hơn 0!";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $total_cost   = $quantity * $import_price;
            $receipt_code = 'PN' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // 1. Thêm phiếu nhập
            $stmt_rec = $pdo->prepare("
                INSERT INTO import_receipts (receipt_code, supplier_id, user_id, total_cost, note) 
                VALUES (:code, :sup_id, :uid, :total, :note)
            ");
            $stmt_rec->execute([
                ':code'   => $receipt_code,
                ':sup_id' => $supplier_id,
                ':uid'    => $user_id,
                ':total'  => $total_cost,
                ':note'   => $note
            ]);
            $receipt_id = $pdo->lastInsertId();

            // 2. Thêm chi tiết phiếu nhập
            $stmt_det = $pdo->prepare("
                INSERT INTO import_details (receipt_id, product_id, quantity, import_price) 
                VALUES (:rid, :pid, :qty, :iprice)
            ");
            $stmt_det->execute([
                ':rid'    => $receipt_id,
                ':pid'    => $product_id,
                ':qty'    => $quantity,
                ':iprice' => $import_price
            ]);

            // 3. Tự động CỘNG DỒN TỒN KHO & CẬP NHẬT GIÁ VỐN
            $stmt_stock = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + :qty, 
                    cost_price = :iprice 
                WHERE id = :pid
            ");
            $stmt_stock->execute([
                ':qty'    => $quantity,
                ':iprice' => $import_price,
                ':pid'    => $product_id
            ]);

            $pdo->commit();
            $success_msg = "Tạo phiếu nhập $receipt_code thành công! Đã cộng thêm $quantity sản phẩm vào kho.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Lỗi khi tạo phiếu nhập kho: " . $e->getMessage();
        }
    }
}

// Lấy danh sách sản phẩm và nhà cung cấp cho Form
$products_list  = $pdo->query("SELECT id, code, name, cost_price, stock_quantity FROM products ORDER BY name ASC")->fetchAll();
$suppliers_list = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Lấy danh sách lịch sử nhập kho
$import_receipts = $pdo->query("
    SELECT ir.*, s.name AS supplier_name, u.fullname AS creator_name,
           p.name AS product_name, p.code AS product_code, idet.quantity AS imported_qty, idet.import_price
    FROM import_receipts ir
    JOIN suppliers s ON ir.supplier_id = s.id
    JOIN users u ON ir.user_id = u.id
    LEFT JOIN import_details idet ON ir.id = idet.receipt_id
    LEFT JOIN products p ON idet.product_id = p.id
    ORDER BY ir.id DESC
")->fetchAll();

$quick_p_id = isset($_GET['quick_product_id']) ? (int)$_GET['quick_product_id'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch Sử Nhập Hàng | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-box-arrow-in-down text-success me-2"></i>Quản Lý Lịch Sử Nhập Hàng (Stock In)</h3>
            <p class="text-muted small mb-0">Theo dõi nguồn gốc nhập hàng, kiểm soát chi phí giá vốn và biến động kho</p>
        </div>
        <div>
            <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-plus-circle me-1"></i>Tạo Phiếu Nhập Hàng Mới
            </button>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($success_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Lỗi:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- BẢNG DANH SÁCH PHIẾU NHẬP -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold py-3">
            <i class="bi bi-clock-history me-1"></i>Nhật Ký Nhập Kho Gần Đây (<?= count($import_receipts) ?> phiếu)
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã Phiếu</th>
                        <th>Nhà Cung Cấp</th>
                        <th>Sản Phẩm Nhập</th>
                        <th class="text-center">Số Lượng</th>
                        <th class="text-end">Đơn Giá Nhập</th>
                        <th class="text-end">Tổng Tiền</th>
                        <th>Người Nhập</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($import_receipts)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Chưa có phiếu nhập hàng nào!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($import_receipts as $r): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= e($r['receipt_code']) ?></span></td>
                                <td><strong class="text-dark"><?= e($r['supplier_name']) ?></strong></td>
                                <td>
                                    <strong><?= e($r['product_name']) ?></strong>
                                    <div class="small text-muted">Mã: <?= e($r['product_code']) ?></div>
                                </td>
                                <td class="text-center fw-bold text-success">+<?= $r['imported_qty'] ?></td>
                                <td class="text-end text-muted"><?= number_format($r['import_price'], 0, ',', '.') ?> đ</td>
                                <td class="text-end fw-bold text-danger"><?= number_format($r['total_cost'], 0, ',', '.') ?> đ</td>
                                <td class="small"><?= e($r['creator_name']) ?></td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- MODAL TẠO PHIẾU NHẬP HÀNG -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i>Tạo Phiếu Nhập Hàng Vào Kho</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="imports.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_import">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nhà cung cấp đối tác <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Chọn nhà cung cấp --</option>
                            <?php foreach ($suppliers_list as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= e($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><a href="suppliers.php" target="_blank">+ Thêm nhà cung cấp mới</a></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sản phẩm cần nhập <span class="text-danger">*</span></label>
                        <select name="product_id" id="sel_product" class="form-select" required onchange="onSelectProduct(this)">
                            <option value="">-- Chọn sản phẩm --</option>
                            <?php foreach ($products_list as $prod): ?>
                                <option value="<?= $prod['id'] ?>" 
                                        data-cost="<?= $prod['cost_price'] ?>"
                                        <?= $quick_p_id === (int)$prod['id'] ? 'selected' : '' ?>>
                                    [<?= e($prod['code']) ?>] <?= e($prod['name']) ?> (Tồn hiện tại: <?= $prod['stock_quantity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Số lượng nhập <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="quantity" id="import_qty" class="form-control" value="10" required oninput="calcTotalCost()">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Đơn giá vốn nhập (đ) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" min="0" name="import_price" id="import_cost" class="form-control" placeholder="0" required oninput="calcTotalCost()">
                        </div>
                    </div>

                    <div class="alert alert-light border p-2 mb-3 d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Tổng chi phí nhập:</span>
                        <strong class="text-danger fs-5" id="total_cost_preview">0 đ</strong>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-muted">Ghi chú phiếu nhập</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="VD: Nhập đợt 1 tháng 11, thanh toán công nợ..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-check2-circle me-1"></i>Lưu & Tăng Tồn Kho
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function onSelectProduct(sel) {
    const opt = sel.options[sel.selectedIndex];
    const cost = opt.getAttribute('data-cost');
    if (cost) {
        document.getElementById('import_cost').value = cost;
    }
    calcTotalCost();
}

function calcTotalCost() {
    const qty = parseFloat(document.getElementById('import_qty').value) || 0;
    const cost = parseFloat(document.getElementById('import_cost').value) || 0;
    const total = qty * cost;
    document.getElementById('total_cost_preview').textContent = total.toLocaleString('vi-VN') + ' đ';
}

// Nếu có quick_product_id thì mở modal luôn
<?php if ($quick_p_id > 0): ?>
document.addEventListener("DOMContentLoaded", function() {
    const modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
    const sel = document.getElementById('sel_product');
    onSelectProduct(sel);
});
<?php endif; ?>
</script>
</body>
</html>
