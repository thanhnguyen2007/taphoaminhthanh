#!/bin/bash
set -e

# Khởi động dịch vụ MariaDB
service mariadb start

# Khởi tạo CSDL và import database.sql nếu chưa có
mysql -e "CREATE DATABASE IF NOT EXISTS inventory_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if [ -f "/var/www/html/database.sql" ]; then
    mysql inventory_shop < /var/www/html/database.sql
fi

# Cấu hình tài khoản root không mật khẩu để kết nối localhost
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY ''; FLUSH PRIVILEGES;" 2>/dev/null || true

# Tự động đồng bộ cổng theo biến môi trường PORT của Render (nếu có)
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf
fi

# Chạy Apache trên foreground
exec apache2-foreground
