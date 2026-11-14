<?php
session_start();
require_once 'db.php';

// Nếu đã đăng nhập thì điều hướng theo vai trò
if (isset($_SESSION['user_id'])) {
    if (in_array($_SESSION['role'], ['admin', 'staff'])) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error = '';
$success = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u1 OR email = :u2 LIMIT 1");
        $stmt->execute([':u1' => $username, ':u2' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['email']    = $user['email'];

            if (in_array($user['role'], ['admin', 'staff'])) {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Tài khoản hoặc mật khẩu không chính xác!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập | Quản Lý Kho & Đơn Hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card bg-white p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px;">
                        <i class="bi bi-box-seam-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold text-dark">HỆ THỐNG QUẢN LÝ KHO</h3>
                    <p class="text-muted small">Đăng nhập tài khoản để vào hệ thống</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <div><?= e($success) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên đăng nhập hoặc Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" id="inp_username" class="form-control" placeholder="Nhập username hoặc email" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="inp_password" class="form-control" placeholder="Nhập mật khẩu" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i>ĐĂNG NHẬP
                    </button>
                </form>

                <div class="mt-3 text-center">
                    <span class="text-muted">Chưa có tài khoản?</span>
                    <a href="register.php" class="fw-bold text-decoration-none">Đăng ký tài khoản</a>
                </div>
                <div class="text-center mt-2">
                    <a href="index.php" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Quay lại trang chủ mua hàng</a>
                </div>

                <!-- Tài khoản mẫu để chấm đồ án nhanh -->
                <div class="mt-4 p-3 bg-light rounded border">
                    <h6 class="fw-bold mb-2 small text-secondary"><i class="bi bi-key-fill text-warning me-1"></i>Tài khoản Demo kiểm thử:</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-xs btn-outline-danger btn-sm" onclick="fillAcc('admin', 'admin123')">
                            Admin (admin123)
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-warning text-dark btn-sm" onclick="fillAcc('staff', 'staff123')">
                            Thủ Kho (staff123)
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-primary btn-sm" onclick="fillAcc('khachhang', '123456')">
                            Khách Hàng (123456)
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function fillAcc(u, p) {
    document.getElementById('inp_username').value = u;
    document.getElementById('inp_password').value = p;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
