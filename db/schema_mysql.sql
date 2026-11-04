-- Nairobi Traders Association (NTA-Pay) MySQL Schema
-- Production schema for MySQL 8+ / MariaDB / Cloud MySQL (Aiven, TiDB Cloud, etc.)

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (System Administrators & Counter Traders)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(120) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'trader') NOT NULL DEFAULT 'trader',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    store_name VARCHAR(100) DEFAULT 'Stall #104',
    mpesa_shortcode VARCHAR(20) DEFAULT '174379',
    mpesa_type VARCHAR(20) DEFAULT 'till',
    mpesa_passkey VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    total_spent DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Receipts Table (Core Transaction Ledger)
CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(32) NOT NULL UNIQUE,
    trader_id INT NOT NULL,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'cancelled', 'timed_out') DEFAULT 'pending',
    mpesa_checkout_id VARCHAR(64),
    mpesa_receipt_number VARCHAR(32),
    qr_code_path VARCHAR(255),
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_receipts_number (receipt_number),
    INDEX idx_receipts_status (payment_status),
    INDEX idx_receipts_trader (trader_id),
    INDEX idx_receipts_checkout (mpesa_checkout_id),
    CONSTRAINT fk_receipts_trader FOREIGN KEY (trader_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_receipts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Receipt Items Table (Line item audit trail)
CREATE TABLE IF NOT EXISTS receipt_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    INDEX idx_receipt_items_receipt (receipt_id),
    CONSTRAINT fk_items_receipt FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Audit Log Table (Forensic staff action tracking)
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_performed VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_log_timestamp (timestamp),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

