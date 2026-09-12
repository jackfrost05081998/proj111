CREATE DATABASE IF NOT EXISTS beanson_pos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE beanson_pos;
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(128) NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    permissions VARCHAR(255) NOT NULL DEFAULT '[]',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    hot_iced ENUM('both', 'hot', 'iced') NOT NULL DEFAULT 'both',
    iced_extra DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    size_16_extra DECIMAL(10,2) NOT NULL DEFAULT 20.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(40) NOT NULL UNIQUE,
    cashier_id INT UNSIGNED NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    cash_received DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'gcash', 'maya') NOT NULL DEFAULT 'cash',
    status ENUM('completed', 'refunded') NOT NULL DEFAULT 'completed',
    refunded_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_cashier
        FOREIGN KEY (cashier_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_orders_created_at (created_at),
    INDEX idx_orders_status_created_at (status, created_at),
    INDEX idx_orders_cashier (cashier_id)
) ENGINE=InnoDB;
CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(120) NOT NULL,
    temperature ENUM('hot', 'iced') NOT NULL,
    drink_size ENUM('12 Oz', '16 Oz') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB;
CREATE TABLE refunds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    refund_no VARCHAR(48) NOT NULL UNIQUE,
    order_id BIGINT UNSIGNED NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    original_payment_method ENUM('cash', 'gcash', 'maya') NOT NULL,
    reason_code VARCHAR(40) NOT NULL,
    reason_note VARCHAR(500) NOT NULL,
    authorized_by INT UNSIGNED NOT NULL,
    authorized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_refunds_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_refunds_authorized_by
        FOREIGN KEY (authorized_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT uq_refunds_order UNIQUE (order_id),
    INDEX idx_refunds_authorized_at (authorized_at),
    INDEX idx_refunds_authorized_by (authorized_by)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB;
INSERT INTO products
    (name, base_price, hot_iced, iced_extra, size_16_extra)
VALUES
    ('Iced Americano', 79.00, 'iced', 10.00, 20.00),
    ('Cafe Latte', 99.00, 'both', 10.00, 20.00),
    ('Cappuccino', 95.00, 'both', 10.00, 20.00),
    ('Caramel Macchiato', 115.00, 'both', 10.00, 20.00),
    ('Spanish Latte', 109.00, 'both', 10.00, 20.00),
    ('Hot Chocolate', 89.00, 'hot', 0.00, 20.00),
    ('Chocolate Frappe', 125.00, 'iced', 0.00, 20.00),
    ('Caramel Frappe', 130.00, 'iced', 0.00, 20.00),
    ('Blueberry Cheesecake', 95.00, 'both', 0.00, 0.00),
    ('Chocolate Chip Cookie', 45.00, 'both', 0.00, 0.00);