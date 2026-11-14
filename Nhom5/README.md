# Cửa Hàng Tiện Ích MiniShop - Hệ Thống Quản Lý Kho & Bán Hàng (Nhóm 5)

Dự án website quản lý kho hàng và đặt hàng trực tuyến, xây dựng bằng PHP & MySQL, hỗ trợ thanh toán COD và mã VietQR tự động.

---

## 🚀 Tính Năng Chính

- **Trang Cửa Hàng (Shop)**:
  - Xem danh sách sản phẩm, lọc theo danh mục, tìm kiếm theo tên hoặc mã.
  - Hiển thị tình trạng tồn kho theo thời gian thực (Còn hàng, Sắp hết, Hết hàng).
  - Đặt hàng nhanh (Mua ngay) kèm chọn phương thức thanh toán COD hoặc Chuyển khoản VietQR.
  - Tự động trừ tồn kho khi tạo đơn thành công (chống race-condition với transaction).
  - Xem và in hóa đơn đơn hàng ngay sau khi đặt hàng.

- **Quản Lý Tồn Kho (Admin / Staff)**:
  - Quản lý danh mục hàng hóa, giá nhập, giá bán, số lượng tồn kho.
  - Cảnh báo sản phẩm chạm ngưỡng tồn kho tối thiểu.
  - Quản lý nhập hàng và nhà cung cấp.

- **Bảo Mật & Phân Quyền**:
  - Phân quyền người dùng: `admin`, `staff`, `user`.
  - Bảo vệ chống tấn công XSS và SQL Injection qua PDO Prepared Statements.

---

## 🛠️ Công Nghệ Sử Dụng

- **Backend**: PHP (PDO MySQL)
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5.3, Bootstrap Icons
- **Database**: MySQL / MariaDB (XAMPP)

---

## 📋 Hướng Dẫn Cài Đặt & Chạy Demo

### 1. Yêu cầu hệ thống
- Đã cài đặt [XAMPP](https://www.apachefriends.org/) (hoặc WampServer / Laragon).
- PHP phiên bản 7.4 trở lên (khuyến nghị 8.0+).
- MySQL 5.7+ hoặc MariaDB 10.4+.

### 2. Cài đặt CSDL
1. Mở **XAMPP Control Panel** và nhấn **Start** cho cả **Apache** và **MySQL**.
2. Truy cập trình duyệt vào `http://localhost/phpmyadmin`.
3. Tạo cơ sở dữ liệu mới tên: `inventory_shop` (chọn bảng mã `utf8mb4_unicode_ci`).
4. Nhấn tab **Import** (Nhập), chọn tệp `database.sql` trong thư mục dự án và nhấn **Import / Go**.

### 3. Cấu hình kết nối (Nếu cần)
Nếu MySQL của bạn có mật khẩu hoặc cổng khác mặc định:
- Mở tệp `db.php` và điều chỉnh:
  ```php
  $host     = 'localhost';
  $dbname   = 'inventory_shop';
  $username = 'root';
  $password = ''; // Điền mật khẩu nếu có
  ```

### 4. Khởi chạy
- Đặt thư mục dự án tại đường dẫn: `c:/xampp/htdocs/Nhom5/`
- Truy cập trình duyệt:
  ```
  http://localhost/Nhom5
  ```

---

## 👥 Nhóm Thực Hiện
- **Nhóm 5**
