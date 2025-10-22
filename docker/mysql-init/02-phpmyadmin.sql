-- phpMyAdmin database and user setup
CREATE DATABASE IF NOT EXISTS phpmyadmin;
CREATE USER IF NOT EXISTS 'admin'@'%' IDENTIFIED BY 'phpmyadmin_secure_password_123';
GRANT ALL PRIVILEGES ON phpmyadmin.* TO 'admin'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON *.* TO 'admin'@'%';
FLUSH PRIVILEGES;
