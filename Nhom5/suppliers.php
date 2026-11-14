<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_staff_or_admin();

$errors = [];
$success_msg = '';
$edit_mode = false;

$form_data = [
    'id'           => '',
    'name'         => '',
    'contact_name' => '',
    'phone'        => '',
    'email'        => '',
    'address'      => ''
];

// Xử lý Xóa nhà cung cấp
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $check_import = $pdo->prepare("SELECT COUNT(*) FROM import_receipts WHERE supplier_id = :id");
        $check_import->execute([':id' => $del_id]);
        if ($check_import->fetchColumn() > 0) {
            $errors[] = "Không thể xóa nhà cung cấp này vì đã có phiếu nhập hàng liên kết!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
            $stmt->execute([':id' => $del_id]);
            $success_msg = "Đã xóa nhà cung cấp thành công!";
        }
    } catch (PDOException $e) {
        $errors[] = "Lỗi khi xóa nhà cung cấp: " . $e->getMessage();
    }
}

// Xử lý Thêm / Sửa nhà cung cấp
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
    $name         = trim($_POST['name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    $form_data = compact('id', 'name', 'contact_name', 'phone', 'email', 'address');
    if ($id) $edit_mode = true;

    // Validation
    if (empty($name)) {
        $errors[] = "Tên nhà cung cấp không được để trống.";
    }
    if (empty($phone)) {
        $errors[] = "Số điện thoại nhà cung cấp không được để trống.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Địa chỉ email không đúng định dạng.";
    }

    if (empty($errors)) {
        try {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = :name, contact_name = :cname, phone = :phone, email = :email, address = :addr 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name'  => $name,
                    ':cname' => $contact_name,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':addr'  => $address,
                    ':id'    => $id
                ]);
                $success_msg = "Cập nhật thông tin nhà cung cấp thành công!";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (name, contact_name, phone, email, address) 
                    VALUES (:name, :cname, :phone, :email, :addr)
                ");
                $stmt->execute([
                    ':name'  => $name,
                    ':cname' => $contact_name,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':addr'  => $address
                ]);
                $success_msg = "Thêm nhà cung cấp mới thành công!";
            }
            $edit_mode = false;
            $form_data = ['id' => '', 'name' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'address' => ''];
        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}

// Lấy dữ liệu sửa
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit_id']]);
    if ($supp = $stmt->fetch()) {
        $edit_mode = true;
        $form_data = $supp;
    }
}

// Lấy danh sách nhà cung cấp kèm số lượng phiếu nhập
$suppliers = $pdo->query("
    SELECT s.*, COUNT(ir.id) AS import_count
    FROM suppliers s
    LEFT JOIN import_receipts ir ON s.id = ir.supplier_id
    GROUP BY s.id
    ORDER BY s.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Nhà Cung Cấp | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-truck text-primary me-2"></i>Quản Lý Thông Tin Nhà Cung Cấp</h3>
            <p class="text-muted small mb-0">Quản lý đối tác phân phối hàng hóa và lịch sử cung ứng</p>
        </div>
        <div>
            <a href="imports.php" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-down me-1"></i>Lịch Sử Nhập Hàng</a>
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

    <div class="row g-4">
        <!-- FORM THÊM / SỬA -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                <div class="card-header <?= $edit_mode ? 'bg-warning text-dark' : 'bg-primary text-white' ?> fw-bold">
                    <i class="bi <?= $edit_mode ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-2"></i>
                    <?= $edit_mode ? 'Sửa Nhà Cung Cấp' : 'Thêm Nhà Cung Cấp Mới' ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="suppliers.php">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?= e($form_data['id']) ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tên đơn vị / Công ty <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="VD: Công ty TNHH Á Châu" value="<?= e($form_data['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Người liên hệ</label>
                            <input type="text" name="contact_name" class="form-control form-control-sm" placeholder="VD: Anh Nam (Sale)" value="<?= e($form_data['contact_name']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="VD: 0901234567" value="<?= e($form_data['phone']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Email liên hệ</label>
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="nhacungcap@email.com" value="<?= e($form_data['email']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Địa chỉ kho / trụ sở</label>
                            <textarea name="address" class="form-control form-control-sm" rows="2" placeholder="Số nhà, đường, quận/huyện..."><?= e($form_data['address']) ?></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn <?= $edit_mode ? 'btn-warning fw-bold' : 'btn-primary fw-bold' ?>">
                                <i class="bi bi-save me-1"></i><?= $edit_mode ? 'Cập Nhật' : 'Thêm Nhà Cung Cấp' ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="suppliers.php" class="btn btn-outline-secondary btn-sm">Hủy Bỏ Sửa</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- BẢNG DANH SÁCH -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span>Danh Sách Đối Tác Cung Ứng (<?= count($suppliers) ?>)</span>
                    <a href="imports.php" class="btn btn-sm btn-success"><i class="bi bi-plus-circle me-1"></i>Tạo Phiếu Nhập Hàng</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nhà Cung Cấp</th>
                                <th>Liên Hệ</th>
                                <th>Địa Chỉ</th>
                                <th class="text-center">Số Lần Nhập</th>
                                <th class="text-center">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($suppliers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Chưa có nhà cung cấp nào!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= e($s['name']) ?></strong>
                                            <?php if ($s['contact_name']): ?>
                                                <div class="small text-muted"><i class="bi bi-person me-1"></i><?= e($s['contact_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><i class="bi bi-telephone text-primary me-1"></i><?= e($s['phone']) ?></div>
                                            <?php if ($s['email']): ?>
                                                <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($s['email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted text-truncate" style="max-width: 200px;" title="<?= e($s['address']) ?>">
                                            <?= e($s['address'] ?: 'Chưa cập nhật') ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark"><?= $s['import_count'] ?> phiếu</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="suppliers.php?edit_id=<?= $s['id'] ?>" class="btn btn-outline-primary" title="Sửa"><i class="bi bi-pencil"></i></a>
                                                <a href="suppliers.php?delete_id=<?= $s['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa đối tác này?')" title="Xóa"><i class="bi bi-trash"></i></a>
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
