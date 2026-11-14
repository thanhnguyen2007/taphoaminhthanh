<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_staff_or_admin();

$errors = [];
$success_msg = '';
$edit_mode = false;

$form_data = [
    'id'              => '',
    'code'            => '',
    'name'            => '',
    'category'        => '',
    'price'           => '',
    'cost_price'      => '',
    'stock_quantity'  => 0,
    'min_stock_level' => 5
];

// 1. XỬ LÝ XÓA SẢN PHẨM
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        // Kiểm tra xem sản phẩm đã có trong đơn hàng hay phiếu nhập chưa
        $check_order = $pdo->prepare("SELECT COUNT(*) FROM order_details WHERE product_id = :id");
        $check_order->execute([':id' => $del_id]);
        if ($check_order->fetchColumn() > 0) {
            $errors[] = "Không thể xóa sản phẩm này vì đã tồn tại trong lịch sử đơn hàng!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $del_id]);
            $success_msg = "Đã xóa sản phẩm thành công khỏi kho!";
        }
    } catch (PDOException $e) {
        $errors[] = "Lỗi khi xóa sản phẩm: " . $e->getMessage();
    }
}

// 2. XỬ LÝ THÊM HOẶC CẬP NHẬT SẢN PHẨM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id              = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
    $code            = strtoupper(trim($_POST['code'] ?? ''));
    $name            = trim($_POST['name'] ?? '');
    $category        = trim($_POST['category'] ?? '');
    $price           = $_POST['price'] ?? '';
    $cost_price      = $_POST['cost_price'] ?? '';
    $stock_quantity  = $_POST['stock_quantity'] ?? '';
    $min_stock_level = $_POST['min_stock_level'] ?? '';

    $form_data = compact('id', 'code', 'name', 'category', 'price', 'cost_price', 'stock_quantity', 'min_stock_level');
    if ($id) $edit_mode = true;

    // Validation chặt chẽ
    if (empty($code)) {
        $errors[] = "Mã sản phẩm không được để trống.";
    }
    if (empty($name)) {
        $errors[] = "Tên sản phẩm không được để trống.";
    }
    if (!is_numeric($cost_price) || (float)$cost_price < 0) {
        $errors[] = "Giá vốn (nhập) phải là số >= 0.";
    }
    if (!is_numeric($price) || (float)$price < 0) {
        $errors[] = "Giá bán ra phải là số >= 0.";
    }
    if ($stock_quantity === '' || filter_var($stock_quantity, FILTER_VALIDATE_INT) === false || (int)$stock_quantity < 0) {
        $errors[] = "Số lượng tồn kho phải là số nguyên >= 0.";
    }
    if ($min_stock_level === '' || filter_var($min_stock_level, FILTER_VALIDATE_INT) === false || (int)$min_stock_level < 0) {
        $errors[] = "Ngưỡng cảnh báo tối thiểu phải là số nguyên >= 0.";
    }

    // Kiểm tra trùng mã sản phẩm
    if (empty($errors)) {
        if ($id) {
            $stmt_chk = $pdo->prepare("SELECT id FROM products WHERE code = :code AND id != :id LIMIT 1");
            $stmt_chk->execute([':code' => $code, ':id' => $id]);
        } else {
            $stmt_chk = $pdo->prepare("SELECT id FROM products WHERE code = :code LIMIT 1");
            $stmt_chk->execute([':code' => $code]);
        }
        if ($stmt_chk->fetch()) {
            $errors[] = "Mã sản phẩm '$code' đã tồn tại. Vui lòng chọn mã khác!";
        }
    }

    if (empty($errors)) {
        try {
            if ($id) {
                // UPDATE
                $sql = "UPDATE products 
                        SET code = :code, name = :name, category = :category, 
                            price = :price, cost_price = :cost_price, 
                            stock_quantity = :stock_quantity, min_stock_level = :min_stock_level 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':code'            => $code,
                    ':name'            => $name,
                    ':category'        => $category,
                    ':price'           => (float)$price,
                    ':cost_price'      => (float)$cost_price,
                    ':stock_quantity'  => (int)$stock_quantity,
                    ':min_stock_level' => (int)$min_stock_level,
                    ':id'              => $id
                ]);
                $success_msg = "Cập nhật thông tin sản phẩm '$name' thành công!";
            } else {
                // INSERT
                $sql = "INSERT INTO products (code, name, category, price, cost_price, stock_quantity, min_stock_level) 
                        VALUES (:code, :name, :category, :price, :cost_price, :stock_quantity, :min_stock_level)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':code'            => $code,
                    ':name'            => $name,
                    ':category'        => $category,
                    ':price'           => (float)$price,
                    ':cost_price'      => (float)$cost_price,
                    ':stock_quantity'  => (int)$stock_quantity,
                    ':min_stock_level' => (int)$min_stock_level
                ]);
                $success_msg = "Thêm sản phẩm mới vào kho thành công!";
            }
            $edit_mode = false;
            $form_data = ['id' => '', 'code' => '', 'name' => '', 'category' => '', 'price' => '', 'cost_price' => '', 'stock_quantity' => 0, 'min_stock_level' => 5];
        } catch (PDOException $e) {
            $errors[] = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
        }
    }
}

// 3. LẤY DỮ LIỆU ĐỂ EDIT
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit_id']]);
    if ($prod = $stmt->fetch()) {
        $edit_mode = true;
        $form_data = $prod;
    }
}

// 4. TÌM KIẾM & BỘ LỌC KHO
$filter_low = isset($_GET['filter']) && $_GET['filter'] === 'low';
$search_kw  = trim($_GET['kw'] ?? '');

$query_sql = "SELECT * FROM products WHERE 1=1";
$query_params = [];

if ($filter_low) {
    $query_sql .= " AND stock_quantity <= min_stock_level";
}
if ($search_kw !== '') {
    $query_sql .= " AND (name LIKE :kw1 OR code LIKE :kw2 OR category LIKE :kw3)";
    $query_params[':kw1'] = "%$search_kw%";
    $query_params[':kw2'] = "%$search_kw%";
    $query_params[':kw3'] = "%$search_kw%";
}
$query_sql .= " ORDER BY (stock_quantity <= min_stock_level) DESC, stock_quantity ASC, id DESC";

$stmt_list = $pdo->prepare($query_sql);
$stmt_list->execute($query_params);
$products_list = $stmt_list->fetchAll();

// Tổng số lượng tồn kho & giá trị tồn kho
$stock_stats = $pdo->query("
    SELECT COUNT(*) AS total_items, 
           COALESCE(SUM(stock_quantity), 0) AS total_units,
           COALESCE(SUM(stock_quantity * cost_price), 0) AS total_stock_value
    FROM products
")->fetch();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Kho Hàng | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <!-- Tiêu đề & Thống kê nhanh kho -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-boxes text-primary me-2"></i>Quản Lý Kho Hàng & Tồn Kho Thời Gian Thực</h3>
            <p class="text-muted small mb-0">Theo dõi xuất-nhập-tồn và nhận cảnh báo khi mặt hàng chạm ngưỡng tối thiểu</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="imports.php" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i>Nhập Hàng Vào Kho</a>
        </div>
    </div>

    <!-- 3 Thẻ chỉ số kho -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white p-3">
                <span class="text-muted small">Tổng Số Mặt Hàng</span>
                <h4 class="fw-bold text-primary mb-0"><?= $stock_stats['total_items'] ?> sản phẩm</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white p-3">
                <span class="text-muted small">Tổng Đơn Vị Tồn Kho</span>
                <h4 class="fw-bold text-success mb-0"><?= number_format($stock_stats['total_units']) ?> cái / chiếc</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white p-3">
                <span class="text-muted small">Tổng Giá Trị Hàng Tồn (Giá Vốn)</span>
                <h4 class="fw-bold text-danger mb-0"><?= number_format($stock_stats['total_stock_value'], 0, ',', '.') ?> đ</h4>
            </div>
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
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Có lỗi xảy ra:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- CỘT TRÁI: FORM THÊM / SỬA SẢN PHẨM -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                <div class="card-header <?= $edit_mode ? 'bg-warning text-dark' : 'bg-primary text-white' ?> fw-bold">
                    <i class="bi <?= $edit_mode ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-2"></i>
                    <?= $edit_mode ? 'Cập Nhật Mặt Hàng #' . e($form_data['code']) : 'Thêm Mặt Hàng Mới' ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="inventory.php">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?= e($form_data['id']) ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Mã sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control form-control-sm text-uppercase" placeholder="VD: SP009, KB01..." value="<?= e($form_data['code']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="VD: Chuột Không Dây..." value="<?= e($form_data['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Danh mục hàng hóa</label>
                            <input type="text" name="category" class="form-control form-control-sm" placeholder="VD: Phụ kiện máy tính, Âm thanh..." value="<?= e($form_data['category']) ?>">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Giá vốn nhập (đ) <span class="text-danger">*</span></label>
                                <input type="number" step="1000" min="0" name="cost_price" class="form-control form-control-sm" placeholder="0" value="<?= e($form_data['cost_price']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Giá bán lẻ (đ) <span class="text-danger">*</span></label>
                                <input type="number" step="1000" min="0" name="price" class="form-control form-control-sm" placeholder="0" value="<?= e($form_data['price']) ?>" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Tồn kho <span class="text-danger">*</span></label>
                                <input type="number" min="0" name="stock_quantity" class="form-control form-control-sm" value="<?= e($form_data['stock_quantity']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Ngưỡng tối thiểu <span class="text-danger">*</span></label>
                                <input type="number" min="0" name="min_stock_level" class="form-control form-control-sm" value="<?= e($form_data['min_stock_level']) ?>" title="Hệ thống sẽ cảnh báo khi tồn kho <= số này" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn <?= $edit_mode ? 'btn-warning fw-bold' : 'btn-primary fw-bold' ?>">
                                <i class="bi bi-save me-1"></i><?= $edit_mode ? 'Lưu Cập Nhật' : 'Lưu Sản Phẩm Mới' ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="inventory.php" class="btn btn-outline-secondary btn-sm">Hủy Bỏ Sửa</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: BẢNG DANH SÁCH TỒN KHO -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <form method="GET" action="inventory.php" class="input-group input-group-sm">
                                <?php if ($filter_low): ?><input type="hidden" name="filter" value="low"><?php endif; ?>
                                <input type="text" name="kw" class="form-control" placeholder="Tìm theo tên, mã hoặc danh mục..." value="<?= e($search_kw) ?>">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                                <?php if ($search_kw !== ''): ?>
                                    <a href="inventory.php<?= $filter_low ? '?filter=low' : '' ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="btn-group btn-group-sm">
                                <a href="inventory.php" class="btn <?= !$filter_low ? 'btn-primary' : 'btn-outline-primary' ?>">Tất Cả (<?= $stock_stats['total_items'] ?>)</a>
                                <a href="inventory.php?filter=low" class="btn <?= $filter_low ? 'btn-danger' : 'btn-outline-danger' ?>">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Sắp Hết Hàng
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã SP</th>
                                <th>Tên Mặt Hàng</th>
                                <th>Danh Mục</th>
                                <th class="text-end">Giá Vốn / Bán</th>
                                <th class="text-center">Tồn / Min</th>
                                <th class="text-center">Trạng Thái</th>
                                <th class="text-center">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products_list)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                        Không tìm thấy sản phẩm nào!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products_list as $row): ?>
                                    <?php 
                                        $is_low = ($row['stock_quantity'] <= $row['min_stock_level']); 
                                        $is_zero = ($row['stock_quantity'] == 0);
                                    ?>
                                    <tr class="<?= $is_zero ? 'table-danger' : ($is_low ? 'table-warning' : '') ?>">
                                        <td><span class="badge bg-secondary"><?= e($row['code']) ?></span></td>
                                        <td>
                                            <strong class="text-dark"><?= e($row['name']) ?></strong>
                                        </td>
                                        <td><small class="text-muted"><?= e($row['category'] ?: 'Chưa phân loại') ?></small></td>
                                        <td class="text-end">
                                            <small class="text-muted">Vốn: <?= number_format($row['cost_price'], 0, ',', '.') ?>đ</small><br>
                                            <strong class="text-primary">Bán: <?= number_format($row['price'], 0, ',', '.') ?>đ</strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="fs-6 fw-bold <?= $is_low ? 'text-danger' : 'text-success' ?>">
                                                <?= $row['stock_quantity'] ?>
                                            </span>
                                            <span class="text-muted small">/ <?= $row['min_stock_level'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($is_zero): ?>
                                                <span class="badge bg-danger">Hết hàng</span>
                                            <?php elseif ($is_low): ?>
                                                <span class="badge bg-warning text-dark">Sắp hết</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Đủ hàng</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="inventory.php?edit_id=<?= $row['id'] ?>" class="btn btn-outline-primary" title="Sửa thông tin"><i class="bi bi-pencil"></i></a>
                                                <a href="imports.php?quick_product_id=<?= $row['id'] ?>" class="btn btn-outline-success" title="Nhập thêm hàng"><i class="bi bi-box-arrow-in-down"></i></a>
                                                <a href="inventory.php?delete_id=<?= $row['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')" title="Xóa"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
