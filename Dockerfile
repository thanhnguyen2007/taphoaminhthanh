FROM php:8.2-apache

# Cài đặt MariaDB server và client
RUN apt-get update && apt-get install -y mariadb-server mariadb-client && rm -rf /var/lib/apt/lists/*

# Cài extension PDO MySQL cho PHP
RUN docker-php-ext-install pdo pdo_mysql

# Bật mod_rewrite cho Apache
RUN a2enmod rewrite

# Copy toàn bộ mã nguồn vào thư mục web của Apache
WORKDIR /var/www/html
COPY . /var/www/html/

# Copy script khởi động
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Mở port 80
EXPOSE 80

CMD ["/usr/local/bin/entrypoint.sh"]
