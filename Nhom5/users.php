<?php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_admin(); // Chỉ Quản trị viên cao nhất (Admin) mới có quyền truy cập

$msg = '';
$err = '';

// 1. XỬ LÝ ĐỔI VAI TRÒ (ROLE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_role') {
    $target_uid = (int)($_POST['user_id'] ?? 0);
    $new_role   = $_POST['role'] ?? 'user';

    if ($target_uid === (int)$_SESSION['user_id'] && $new_role !== 'admin') {
        $err = "Bạn không thể tự hạ quyền Admin của chính mình!";
    } elseif (in_array($new_role, ['admin', 'staff', 'user'])) {
        $stmt = $pdo->prepare("UPDATE users SET role = :r WHERE id = :id");
        $stmt->execute([':r' => $new_role, ':id' => $target_uid]);
        $msg = "Đã cập nhật vai trò người dùng thành công!";
    }
}

// 2. XỬ LÝ THÊM TÀI KHOẢN MỚI TỪ ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'staff';

    if (empty($username) || empty($fullname) || empty($email) || empty($password)) {
        $err = "Vui lòng điền đầy đủ các trường bắt buộc!";
    } elseif (strlen($password) < 6) {
        $err = "Mật khẩu phải tối thiểu 6 ký tự!";
    } else {
        // Kiểm tra trùng lặp
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e");
        $check->execute([':u' => $username, ':e' => $email]);
        if ($check->fetch()) {
            $err = "Tên đăng nhập hoặc Email này đã tồn tại trong hệ thống!";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, fullname, email, phone, password, role) VALUES (:u, :f, :e, :p, :pwd, :r)");
            $stmt->execute([
                ':u'   => $username,
                ':f'   => $fullname,
                ':e'   => $email,
                ':p'   => $phone,
                ':pwd' => $hashed,
                ':r'   => $role
            ]);
            $msg = "Thêm tài khoản [$username] thành công!";
        }
    }
}

// 3. XỬ LÝ XÓA TÀI KHOẢN
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id === (int)$_SESSION['user_id']) {
        $err = "Bạn không thể tự xóa tài khoản đang đăng nhập!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $del_id]);
        $msg = "Đã xóa tài khoản thành công!";
    }
}

// Danh sách người dùng
$users_list = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng & Phân Quyền | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-danger me-2"></i>Quản Lý Tài Khoản & Phân Quyền (RBAC)</h3>
            <p class="text-muted small mb-0">Quản trị viên (Admin) cấp phát quyền truy cập cho Nhân viên (Staff) và Khách hàng (User)</p>
        </div>
        <div>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-1"></i>Thêm Người Dùng Mới
            </button>
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

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Tài Khoản (Username)</th>
                        <th>Họ Và Tên</th>
                        <th>Email & SĐT</th>
                        <th>Vai Trò (Role)</th>
                        <th>Ngày Tạo</th>
                        <th class="text-center">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $u): ?>
                        <tr>
                            <td>#<?= $u['id'] ?></td>
                            <td>
                                <strong class="text-dark"><?= e($u['username']) ?></strong>
                                <?php if ($u['id'] === (int)$_SESSION['user_id']): ?>
                                    <span class="badge bg-secondary ms-1">Bạn</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($u['fullname']) ?></td>
                            <td>
                                <div><i class="bi bi-envelope text-muted me-1"></i><?= e($u['email']) ?></div>
                                <small class="text-muted"><i class="bi bi-telephone text-muted me-1"></i><?= e($u['phone'] ?: 'N/A') ?></small>
                            </td>
                            <td>
                                <form method="POST" action="users.php" class="d-flex align-items-center gap-1">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin (Quản trị)</option>
                                        <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff (Thủ kho)</option>
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User (Khách mua)</option>
                                    </select>
                                </form>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-center">
                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <a href="users.php?delete_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?')" title="Xóa tài khoản">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Đang đăng nhập</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm Người Dùng -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Thêm Tài Khoản Người Dùng Mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tên đăng nhập (Username) <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="nguyenvana" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control" placeholder="Nguyễn Văn A" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Địa chỉ Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="email@domain.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Số điện thoại</label>
                        <input type="tel" name="phone" class="form-control" placeholder="09xxxxxxxx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mật khẩu khởi tạo <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phân quyền vai trò (Role)</label>
                        <select name="role" class="form-select">
                            <option value="staff">Staff - Nhân viên quản lý kho</option>
                            <option value="user">User - Khách hàng mua sắm</option>
                            <option value="admin">Admin - Quản trị viên toàn quyền</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-check2-circle me-1"></i>Tạo Tài Khoản
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
