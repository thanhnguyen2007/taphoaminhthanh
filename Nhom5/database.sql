-- =====================================================================
-- HỆ THỐNG QUẢN LÝ KHO & ĐƠN HÀNG DÀNH CHO CỬA HÀNG NHỎ
-- ĐỒ ÁN: INVENTORY & ORDER MANAGEMENT SYSTEM
-- =====================================================================

CREATE DATABASE IF NOT EXISTS inventory_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_shop;

-- 1. BẢNG NGƯỜI DÙNG (Phân quyền: admin, staff, user)
DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS import_details;
DROP TABLE IF EXISTS import_receipts;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('admin', 'staff', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG NHÀ CUNG CẤP
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG SẢN PHẨM / HÀNG HÓA
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) DEFAULT 'Khác',
    price DECIMAL(12,2) NOT NULL DEFAULT 0,       -- Giá bán ra
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,  -- Giá vốn / giá nhập
    stock_quantity INT NOT NULL DEFAULT 0,        -- Số lượng tồn kho hiện tại
    min_stock_level INT NOT NULL DEFAULT 5,       -- Ngưỡng cảnh báo sắp hết hàng
    image_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG PHIẾU NHẬP HÀNG (Lịch sử nhập kho)
CREATE TABLE import_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_code VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    user_id INT NOT NULL,
    total_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG CHI TIẾT PHIẾU NHẬP
CREATE TABLE import_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    import_price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (receipt_id) REFERENCES import_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BẢNG ĐƠN HÀNG
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(50) NOT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Pending', 'Processing', 'Shipping', 'Completed', 'Cancelled') DEFAULT 'Pending',
    payment_method VARCHAR(50) DEFAULT 'COD',
    payment_status ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG CHI TIẾT ĐƠN HÀNG
CREATE TABLE order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DỮ LIỆU MẪU (SEED DATA)
-- Mật khẩu mặc định:
-- admin: admin123  (băm bcrypt: $2y$10$kz8MGlroXZalkjL3zFy.9O.hzT3mC3eqdSfGsmCE90mrVmi6c.nj2)
-- staff: staff123  (băm bcrypt: $2y$10$zEathaLHURZaSspDL5VcEed1/A7nP6Orn8Vu5SdRdALJqej3UCOXm)
-- khachhang: 123456 (băm bcrypt: $2y$10$FPUHzUwWqrnqV5LnwZ7aluovw7lmFcCgXdCyDGPOReCpIPXMJtwIO)
-- =====================================================================

INSERT INTO users (username, password, fullname, email, phone, role) VALUES
('admin', '$2y$10$kz8MGlroXZalkjL3zFy.9O.hzT3mC3eqdSfGsmCE90mrVmi6c.nj2', 'Quản Trị Viên Shop', 'admin@shopmini.com', '0123456789', 'admin'),
('staff', '$2y$10$zEathaLHURZaSspDL5VcEed1/A7nP6Orn8Vu5SdRdALJqej3UCOXm', 'Nhân Viên Thủ Kho', 'staff@shopmini.com', '1111111111', 'staff'),
('khachhang', '$2y$10$FPUHzUwWqrnqV5LnwZ7aluovw7lmFcCgXdCyDGPOReCpIPXMJtwIO', 'Nguyễn Văn An', 'khachhang@gmail.com', '0000000000', 'user');

INSERT INTO suppliers (name, contact_name, phone, email, address) VALUES
('Công Ty TNHH Phân Phối Á Châu', 'Trần Văn Bình', '02838383838', 'achau@supplier.vn', 'Số 120 Đường 3/2, Quận 10, TP.HCM'),
('Tổng Kho Linh Kiện & Phụ Kiện Nam Long', 'Lê Thị Cúc', '02439998888', 'namlong@supplier.vn', '45 Hoàng Hoa Thám, Ba Đình, Hà Nội'),
('Công Ty Cung Ứng Gia Dụng Thông Minh', 'Phạm Hoàng Long', '0909888777', 'giadung@supplier.vn', '88 Nguyễn Trãi, Thanh Xuân, Hà Nội');

INSERT INTO products (code, name, category, price, cost_price, stock_quantity, min_stock_level) VALUES
('SP001', 'Chuột Không Dây Bluetooth Silent M350', 'Phụ kiện máy tính', 280000, 180000, 25, 5),
('SP002', 'Bàn Phím Cơ Không Dây K87 RGB', 'Phụ kiện máy tính', 750000, 520000, 12, 5),
('SP003', 'Tai Nghe Bluetooth True Wireless TWS T12', 'Âm thanh', 390000, 230000, 4, 6), -- Sắp hết hàng
('SP004', 'Củ Sạc Nhanh 65W GaN Type-C 3 Cổng', 'Củ sạc - Cáp sạc', 350000, 220000, 18, 5),
('SP005', 'Cáp Sạc Nhanh C to C 100W Bọc Dù 2m', 'Củ sạc - Cáp sạc', 120000, 65000, 3, 5),  -- Sắp hết hàng
('SP006', 'Loa Bluetooth Mini Bass Căng Chống Nước', 'Âm thanh', 480000, 310000, 15, 5),
('SP007', 'Giá Đỡ Laptop Nhôm Tản Nhiệt Xếp Gọn', 'Phụ kiện', 190000, 110000, 8, 5),
('SP008', 'Quạt Tản Nhiệt Điện Thoại Sò Lạnh', 'Phụ kiện gaming', 250000, 160000, 2, 5); -- Sắp hết hàng

INSERT INTO import_receipts (receipt_code, supplier_id, user_id, total_cost, note) VALUES
('PN20261101-01', 1, 1, 7500000, 'Nhập lô chuột M350 và bàn phím cơ K87'),
('PN20261105-02', 2, 2, 3850000, 'Nhập lô củ sạc GaN và cáp sạc 100W');

INSERT INTO import_details (receipt_id, product_id, quantity, import_price) VALUES
(1, 1, 20, 180000),
(1, 2, 7, 520000),
(2, 4, 15, 220000),
(2, 5, 10, 65000);

INSERT INTO orders (order_code, user_id, customer_name, customer_phone, customer_address, total_amount, status, payment_method, payment_status, notes) VALUES
('DH1001', 3, 'Nguyễn Văn An', '0988776655', '12 Lê Duẩn, Quận 1, TP.HCM', 1030000, 'Completed', 'Chuyển khoản QR', 'Paid', 'Giao giờ hành chính'),
('DH1002', 3, 'Nguyễn Văn An', '0988776655', '12 Lê Duẩn, Quận 1, TP.HCM', 350000, 'Completed', 'COD', 'Paid', 'Đóng gói cẩn thận'),
('DH1003', NULL, 'Trần Minh Khang', '0918112233', '88 Cầu Giấy, Hà Nội', 480000, 'Shipping', 'Chuyển khoản QR', 'Paid', 'Khách mua vãng lai'),
('DH1004', NULL, 'Lê Thị Thu', '0933445566', '45 Trần Phú, Đà Nẵng', 750000, 'Processing', 'COD', 'Unpaid', 'Khách hẹn giao buổi chiều'),
('DH1005', 3, 'Nguyễn Văn An', '0988776655', '12 Lê Duẩn, Quận 1, TP.HCM', 670000, 'Pending', 'COD', 'Unpaid', 'Đơn hàng mới tạo chờ xác nhận');

INSERT INTO order_details (order_id, product_id, quantity, price) VALUES
(1, 1, 1, 280000),
(1, 2, 1, 750000),
(2, 4, 1, 350000),
(3, 6, 1, 480000),
(4, 2, 1, 750000),
(5, 1, 1, 280000),
(5, 3, 1, 390000);
