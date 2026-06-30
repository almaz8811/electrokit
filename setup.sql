-- ElectroKit Database Setup for MariaDB
-- Выполните этот скрипт в phpMyAdmin или через командную строку

-- Создать базу данных
CREATE DATABASE IF NOT EXISTS electrokit
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE electrokit;

-- Создать пользователя (измените пароль!)
CREATE USER IF NOT EXISTS 'electrokit_user'@'localhost' IDENTIFIED BY 'your_secure_password';

-- Дать права пользователю
GRANT ALL PRIVILEGES ON electrokit.* TO 'electrokit_user'@'localhost';
FLUSH PRIVILEGES;

-- Таблица настроек
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица проектов
CREATE TABLE IF NOT EXISTS projects (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'apartment',
    data JSON NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_saved (saved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
