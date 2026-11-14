<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$errors = [];
$form = [
    'username' => '',
    'fullname' => '',
    'email'    => '',
    'phone'    => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $fullname        = trim($_POST['fullname'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $password        = $_POST['password'] ?? '';
    $password_repeat = $_POST['password_repeat'] ?? '';

    $form = compact('username', 'fullname', 'email', 'phone');

    // Kiểm tra ràng buộc dữ liệu (Validation)
    if (empty($username) || strlen($username) < 3) {
        $errors[] = "Tên đăng nhập phải có ít nhất 3 ký tự.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Tên đăng nhập chỉ chứa chữ cái, số và dấu gạch dưới.";
    }

    if (empty($fullname)) {
        $errors[] = "Họ và tên không được để trống.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Địa chỉ email không hợp lệ.";
    }

    if (!empty($phone) && !preg_match('/^[0-9]{9,12}$/', $phone)) {
        $errors[] = "Số điện thoại phải chứa từ 9 đến 12 chữ số.";
    }

    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Mật khẩu phải có độ dài tối thiểu 6 ký tự.";
    }

    if ($password !== $password_repeat) {
        $errors[] = "Mật khẩu xác nhận không khớp.";
    }

    // Kiểm tra trùng username hoặc email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $errors[] = "Tên đăng nhập hoặc Email này đã tồn tại trong hệ thống.";
        }
    }

    // Nếu không có lỗi, tạo tài khoản
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $role = 'user'; // Mặc định đăng ký là khách hàng / user

        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, phone, role) VALUES (:u, :p, :f, :e, :ph, :r)");
        $success = $stmt->execute([
            ':u'  => $username,
            ':p'  => $hashed_password,
            ':f'  => $fullname,
            ':e'  => $email,
            ':ph' => $phone,
            ':r'  => $role
        ]);

        if ($success) {
            header("Location: login.php?msg=" . urlencode("Đăng ký thành công! Hãy đăng nhập bằng tài khoản mới."));
            exit();
        } else {
            $errors[] = "Có lỗi xảy ra khi lưu tài khoản. Vui lòng thử lại.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản | Inventory Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reg-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card reg-card bg-white p-4 p-md-5">
                <div class="text-center mb-3">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 55px; height: 55px;">
                        <i class="bi bi-person-plus-fill fs-3"></i>
                    </div>
                    <h3 class="fw-bold text-dark">Tạo Tài Khoản Mới</h3>
                    <p class="text-muted small">Điền thông tin để đăng ký thành viên</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" novalidate>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Tên đăng nhập <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control form-control-sm" value="<?= e($form['username']) ?>" placeholder="Ví dụ: nguyenvana" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control form-control-sm" value="<?= e($form['fullname']) ?>" placeholder="Ví dụ: Nguyễn Văn A" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Địa chỉ Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm" value="<?= e($form['email']) ?>" placeholder="email@example.com" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Số điện thoại</label>
                        <input type="tel" name="phone" class="form-control form-control-sm" value="<?= e($form['phone']) ?>" placeholder="09xxxxxxxx">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Tối thiểu 6 ký tự" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Nhập lại MK <span class="text-danger">*</span></label>
                            <input type="password" name="password_repeat" class="form-control form-control-sm" placeholder="Xác nhận mật khẩu" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> HOÀN TẤT ĐĂNG KÝ
                    </button>
                </form>

                <div class="text-center mt-3 small">
                    <span class="text-muted">Đã có tài khoản?</span>
                    <a href="login.php" class="fw-bold text-decoration-none">Đăng nhập tại đây</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
