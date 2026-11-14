<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Đếm số sản phẩm cảnh báo sắp hết kho cho nhân viên / admin
$nav_low_stock = 0;
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])) {
    try {
        $nav_low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level")->fetchColumn();
    } catch (Exception $e) {
        $nav_low_stock = 0;
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <span class="badge bg-primary p-2"><i class="bi bi-box-seam-fill"></i></span>
            <span>SHOP INVENTORY</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'index.php' ? 'active fw-bold' : '' ?>" href="index.php">
                        <i class="bi bi-shop me-1"></i> Gian Hàng
                    </a>
                </li>

                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'admin_dashboard.php' ? 'active fw-bold' : '' ?>" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative <?= $current_page === 'inventory.php' ? 'active fw-bold' : '' ?>" href="inventory.php">
                            <i class="bi bi-boxes me-1"></i> Quản Lý Kho
                            <?php if ($nav_low_stock > 0): ?>
                                <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger" title="Cảnh báo: Có <?= $nav_low_stock ?> mặt hàng sắp hết!">
                                    <?= $nav_low_stock ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'suppliers.php' ? 'active fw-bold' : '' ?>" href="suppliers.php">
                            <i class="bi bi-truck me-1"></i> Nhà Cung Cấp
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'imports.php' ? 'active fw-bold' : '' ?>" href="imports.php">
                            <i class="bi bi-box-arrow-in-down me-1"></i> Nhập Kho
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'orders.php' ? 'active fw-bold' : '' ?>" href="orders.php">
                            <i class="bi bi-receipt me-1"></i> Đơn Hàng Shop
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page === 'users.php' ? 'active fw-bold' : '' ?>" href="users.php">
                                <i class="bi bi-people me-1"></i> Tài Khoản
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'my_orders.php' ? 'active fw-bold' : '' ?>" href="my_orders.php">
                            <i class="bi bi-bag-check me-1"></i> Đơn Hàng Của Tôi
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <span><?= e($_SESSION['fullname'] ?? $_SESSION['username']) ?></span>
                            <span class="badge bg-<?= $_SESSION['role'] === 'admin' ? 'danger' : ($_SESSION['role'] === 'staff' ? 'warning text-dark' : 'info text-dark') ?>">
                                <?= strtoupper($_SESSION['role']) ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header">Tài khoản: <?= e($_SESSION['username']) ?></h6></li>
                            <?php if ($_SESSION['role'] === 'user'): ?>
                                <li><a class="dropdown-item" href="my_orders.php"><i class="bi bi-bag-check me-2"></i>Đơn hàng của tôi</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Bảng điều khiển</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Đăng Nhập</a>
                    <a href="register.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Đăng Ký</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
