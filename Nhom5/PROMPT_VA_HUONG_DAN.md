# HỆ THỐNG QUẢN LÝ KHO & ĐƠN HÀNG DÀNH CHO CỬA HÀNG NHỎ
## (Inventory & Order Management System)
> **Mã nguồn đồ án PHP thuần & CSDL MySQL hoàn chỉnh đặt tại thư mục: `c:\xampp\htdocs\doan5`**

---

## PHẦN 1: BỘ PROMPT CHUẨN ĐỒ ÁN (DÙNG ĐỂ NẠP CHO AI / VIẾT BÁO CÁO)

Dưới đây là các Prompt kỹ thuật chuyên sâu được biên soạn chuẩn chỉ theo yêu cầu đồ án trong 2 ảnh mô tả:

### 🎯 Prompt 1: Prompt Đặc Tả Toàn Bộ Hệ Thống (Master Architecture Prompt)
```text
Bạn là một Kỹ sư phần mềm cao cấp và Chuyên gia giải pháp full-stack PHP/MySQL.
Hãy thiết kế và xây dựng "Hệ Thống Quản Lý Kho & Đơn Hàng Dành Cho Cửa Hàng Nhỏ" (Inventory & Order Management System) chạy trên XAMPP (PHP 8.x + MySQL) với các yêu cầu kỹ thuật sau:

1. Phạm vi chức năng cốt lõi (Core Requirements):
   - Quản lý kho hàng & Xuất-Nhập-Tồn: Báo cáo tồn kho thời gian thực, cảnh báo đỏ khi mặt hàng chạm ngưỡng tối thiểu (min_stock_level), quản lý danh mục, giá vốn và giá bán lẻ.
   - Quản lý nhà cung cấp (Suppliers): CRUD thông tin đối tác cung ứng, liên kết lịch sử các lần nhập hàng.
   - Quản lý nhập kho (Stock In / Import Receipts): Tạo phiếu nhập hàng, tự động cộng dồn số lượng vào tồn kho và cập nhật giá vốn mới.
   - Quản lý đơn hàng & Bán hàng (Order Management & POS): Khách đặt mua online hoặc tạo đơn tại quầy, sử dụng PDO Transaction và khóa dòng (SELECT ... FOR UPDATE) để trừ kho an toàn, tránh race condition khi nhiều người mua cùng lúc.
   - Quản lý người dùng & Phân quyền (RBAC): Đăng nhập, đăng ký thành viên với validation chặt chẽ (username, email duy nhất, regex SĐT, mật khẩu tối thiểu 6 ký tự băm bằng bcrypt). Phân quyền 3 vai trò: Admin (toàn quyền), Staff (thủ kho / xử lý đơn), User (khách hàng mua sắm).
   - Thống kê doanh thu & lợi nhuận: Tính tổng doanh thu đơn hoàn thành, lợi nhuận thuần (Doanh thu - Giá vốn), top sản phẩm bán chạy nhất, vẽ biểu đồ trực quan 7 ngày bằng Chart.js.

2. Tính năng cộng điểm (Bonus Features):
   - Tích hợp thanh toán trực tuyến VietQR chuẩn Napas 24/7: Tự động render mã QR chứa chính xác số tài khoản ngân hàng, số tiền đơn hàng và mã hóa đơn để khách quét ứng dụng ngân hàng thanh toán ngay lập tức.
   - In hóa đơn bán lẻ chuyên nghiệp (Print Bill / Invoice): Trang in chuẩn định dạng A4/A5, tự động căn chỉnh và ẩn các thanh điều hướng khi in (CSS @media print).
   - Cảnh báo tồn kho thời gian thực (Real-time alert): Badge thông báo số lượng mặt hàng sắp hết trên thanh điều hướng Navbar và bảng cảnh báo ưu tiên trên Dashboard.

3. Chuẩn mã nguồn:
   - Sử dụng PHP PDO chuẩn bảo mật, chống SQL Injection (Prepared Statements), chống XSS (htmlspecialchars).
   - Giao diện hiện đại sử dụng Bootstrap 5.3 + Bootstrap Icons, responsive trên cả máy tính và điện thoại.
```

---

### 🎯 Prompt 2: Prompt Thiết Kế Cơ Sở Dữ Liệu Quan Hệ (Database Design Prompt)
```text
Hãy thiết kế cơ sở dữ liệu quan hệ MySQL cho hệ thống quản lý kho & đơn hàng mang tên `inventory_shop` với mã hóa UTF8MB4.
Các bảng bao gồm:
1. `users`: id, username, password (bcrypt), fullname, email, phone, role (admin, staff, user), created_at.
2. `suppliers`: id, name, contact_name, phone, email, address, created_at.
3. `products`: id, code (unique), name, category, price, cost_price, stock_quantity, min_stock_level, created_at.
4. `import_receipts`: id, receipt_code, supplier_id, user_id, total_cost, note, created_at (khóa ngoại đến suppliers và users).
5. `import_details`: id, receipt_id, product_id, quantity, import_price.
6. `orders`: id, order_code, user_id (nullable), customer_name, customer_phone, customer_address, total_amount, status (Pending, Processing, Shipping, Completed, Cancelled), payment_method, payment_status (Unpaid, Paid), notes, created_at.
7. `order_details`: id, order_id, product_id, quantity, price.
Hãy kèm theo các lệnh INSERT dữ liệu mẫu đầy đủ cho tài khoản Admin, Staff, Khách hàng, Nhà cung cấp, Hàng hóa, Phiếu nhập và Đơn hàng mẫu để hệ thống hiển thị biểu đồ và số liệu ngay lập tức.
```

---

### 🎯 Prompt 3: Prompt Viết Báo Cáo Đồ Án & Kịch Bản Thuyết Trình (Defense Presentation Prompt)
```text
Hãy soạn thảo dàn ý báo cáo đồ án tốt nghiệp/môn học và kịch bản thuyết trình bảo vệ đồ án "Hệ Thống Quản Lý Kho & Đơn Hàng Cho Cửa Hàng Nhỏ bằng PHP và MySQL".
Cấu trúc báo cáo bao gồm:
- Chương 1: Giới thiệu đề tài, lý do chọn đề tài và mục tiêu giải quyết bài toán quản lý hàng tồn kho, tránh thất thoát cho các shop nhỏ.
- Chương 2: Phân tích yêu cầu nghiệp vụ: Sơ đồ Use Case, luồng nghiệp vụ Xuất - Nhập - Tồn, xử lý đơn hàng.
- Chương 3: Thiết kế hệ thống: Sơ đồ thực thể quan hệ CSDL (ERD), kiến trúc module.
- Chương 4: Cài đặt và thực nghiệm: Các tính năng cốt lõi (CRUD, Phân quyền RBAC, PDO Transaction kiểm tra tồn kho, tích hợp VietQR, in hóa đơn, biểu đồ Chart.js).
- Chương 5: Đánh giá kết quả và hướng phát triển tương lai (tích hợp barcode scanner, API vận chuyển GHTK/GHN).
- Kịch bản demo 5 phút gây ấn tượng với hội đồng chấm thi.
```

---

## PHẦN 2: HƯỚNG DẪN CÀI ĐẶT & CHẠY ỨNG DỤNG TRÊN XAMPP

### Bước 1: Khởi động XAMPP
1. Mở ứng dụng **XAMPP Control Panel**.
2. Nhấn nút **Start** ở cả 2 dịch vụ: **Apache** và **MySQL** (cả hai chuyển sang màu xanh lá).

### Bước 2: Import CSDL vào phpMyAdmin
1. Mở trình duyệt web, truy cập: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Nhấp vào tab **Import** (hoặc tạo Database tên `inventory_shop` rồi chọn Import).
3. Bấm **Choose File** (Chọn tệp) và trỏ tới file: `C:\xampp\htdocs\doan5\database.sql`.
4. Bấm nút **Import** ở cuối trang để hoàn tất nạp cơ sở dữ liệu.

### Bước 3: Truy cập trang web
- Địa chỉ trang web: [http://localhost/doan5](http://localhost/doan5) hoặc [http://localhost/doan5/index.php](http://localhost/doan5/index.php)

---

## PHẦN 3: TÀI KHOẢN ĐĂNG NHẬP MẶC ĐỊNH (DEMO ACCOUNTS)

Hệ thống đã tích hợp sẵn nút bấm **điền nhanh 1-click** ngay tại màn hình đăng nhập:

| Vai trò | Tên đăng nhập | Mật khẩu | Quyền hạn trong hệ thống |
| :--- | :--- | :--- | :--- |
| **Quản Trị Viên (Admin)** | `admin` | `admin123` | Toàn quyền hệ thống: Dashboard thống kê doanh thu & lợi nhuận, biểu đồ Chart.js, quản lý kho, nhập kho, nhà cung cấp, đơn hàng, phân quyền tài khoản người dùng (`users.php`). |
| **Nhân Viên Thủ Kho (Staff)** | `staff` | `staff123` | Quản lý kho hàng, tạo phiếu nhập hàng, quản lý thông tin nhà cung cấp, duyệt và cập nhật trạng thái giao đơn hàng, in hóa đơn. |
| **Khách Hàng (User)** | `khachhang` | `123456` | Xem sản phẩm, đặt mua hàng online, chọn thanh toán COD/VietQR, xem lịch sử đơn hàng cá nhân (`my_orders.php`), xem hóa đơn và quét mã QR. |

---

## PHẦN 4: CẤU TRÚC VÀ CHỨC NĂNG CÁC TỆP TRONG FOLDER `doan5`

```
c:\xampp\htdocs\doan5\
│
├── database.sql           # File cấu trúc CSDL 7 bảng chuẩn & dữ liệu mẫu
├── db.php                 # Kết nối CSDL MySQL bằng PDO, cấu hình VietQR, thông tin Shop
├── auth_check.php         # Kiểm tra phân quyền truy cập (Admin / Staff / User)
├── navbar.php             # Menu điều hướng chung có Badge cảnh báo hàng sắp hết thời gian thực
├── login.php              # Đăng nhập bảo mật (có nút bấm điền tài khoản demo)
├── register.php           # Đăng ký tài khoản mới (Validation chặt chẽ client & server)
├── logout.php             # Đăng xuất an toàn, hủy session
│
├── index.php              # Trang chủ gian hàng dành cho khách mua sắm (Tìm kiếm, Lọc, Mua ngay)
├── order_invoice.php      # Trang In Hóa Đơn (Print Bill) tự động tạo mã VietQR Napas
├── my_orders.php          # Quản lý lịch sử đơn hàng của khách hàng
│
├── admin_dashboard.php    # Bảng điều khiển Quản trị: Doanh thu, Lợi nhuận, Biểu đồ Chart.js, Top bán chạy
├── inventory.php          # Quản lý kho hàng (CRUD sản phẩm, Lọc hàng sắp hết, Tồn kho/Min)
├── suppliers.php          # Quản lý danh sách nhà cung cấp (CRUD thông tin đối tác)
├── imports.php            # Quản lý nhập kho (Tạo phiếu nhập -> tự động cộng tồn kho & giá vốn)
├── orders.php             # Quản lý danh sách đơn hàng shop (Lọc trạng thái, Cập nhật tiến độ, In hóa đơn)
├── users.php              # Quản lý người dùng & phân quyền vai trò (Chỉ dành cho Admin)
│
└── PROMPT_VA_HUONG_DAN.md # Tài liệu Prompt và hướng dẫn vận hành chi tiết
```
