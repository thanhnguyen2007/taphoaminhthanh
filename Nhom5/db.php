<?php
// Cấu hình kết nối cơ sở dữ liệu MySQL
$host     = 'localhost';
$dbname   = 'inventory_shop';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]);
} catch (PDOException $e) {
    // Nếu chưa tạo CSDL, thử kết nối không dbname để hướng dẫn hoặc tự tạo
    try {
        $temp_pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS inventory_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        header("Refresh:0");
        exit();
    } catch (PDOException $ex) {
        die("<div style='font-family: Arial; padding: 20px; background: #ffe6e6; border-left: 5px solid #dc3545;'>
            <h3 style='color: #dc3545; margin-top:0;'>Không thể kết nối cơ sở dữ liệu MySQL!</h3>
            <p>Vui lòng đảm bảo:</p>
            <ul>
                <li>Đã mở <strong>XAMPP Control Panel</strong> và bấm <strong>Start MySQL & Apache</strong>.</li>
                <li>Đã import file <code>database.sql</code> vào phpMyAdmin (Database: <code>inventory_shop</code>).</li>
            </ul>
            <small style='color: #666;'>Chi tiết lỗi: " . htmlspecialchars($e->getMessage()) . "</small>
        </div>");
    }
}

// Cấu hình thông tin ngân hàng phục vụ tạo mã VietQR tự động
define('BANK_ID', 'MB');             // Mã ngân hàng (VD: MB, VCB, ICB, ACB, TPB)
define('ACCOUNT_NO', '0901234567');   // Số tài khoản người nhận
define('ACCOUNT_NAME', 'NGUYEN VAN ADMIN'); // Tên chủ tài khoản
define('SHOP_NAME', 'Cửa Hàng Tiện Ích MiniShop');
define('SHOP_PHONE', '0901.234.567');
define('SHOP_ADDRESS', '123 Đường Điện Biên Phủ, Phường Đa Kao, Quận 1, TP.HCM');

// Hàm làm sạch dữ liệu hiển thị (XSS protection)
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
