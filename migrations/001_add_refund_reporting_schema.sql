
USE beanson_pos;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS permissions VARCHAR(255)
        NOT NULL DEFAULT '[]' AFTER role;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_method ENUM('cash', 'gcash', 'maya')
        NOT NULL DEFAULT 'cash' AFTER change_amount;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS status ENUM('completed', 'refunded')
        NOT NULL DEFAULT 'completed' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS refunded_at TIMESTAMP NULL DEFAULT NULL
        AFTER status;

ALTER TABLE orders
    ADD INDEX IF NOT EXISTS idx_orders_status_created_at (status, created_at);

CREATE TABLE IF NOT EXISTS refunds (
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
