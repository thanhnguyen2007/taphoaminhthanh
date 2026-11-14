<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_current_user_role() {
    return $_SESSION['role'] ?? 'guest';
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php?msg=" . urlencode("Vui lòng đăng nhập để tiếp tục!"));
        exit();
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        die("<div style='font-family: Arial; padding: 30px; text-align: center;'>
            <h2 style='color: #dc3545;'>403 - Quyền truy cập bị từ chối!</h2>
            <p>Trang này chỉ dành riêng cho Quản trị viên (Admin).</p>
            <a href='index.php' style='display:inline-block; padding:8px 16px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px;'>Về Trang Chủ</a>
        </div>");
    }
}

function require_staff_or_admin() {
    require_login();
    if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
        die("<div style='font-family: Arial; padding: 30px; text-align: center;'>
            <h2 style='color: #dc3545;'>403 - Quyền truy cập bị từ chối!</h2>
            <p>Trang này chỉ dành cho Nhân viên hoặc Quản trị viên.</p>
            <a href='index.php' style='display:inline-block; padding:8px 16px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px;'>Về Trang Chủ</a>
        </div>");
    }
}
?>
