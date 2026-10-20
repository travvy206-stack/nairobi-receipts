-- Nairobi Traders Association (NTA-Pay) Production Database Schema
-- ACID-compliant relational schema for SQLite / MySQL

PRAGMA foreign_keys = ON;

-- 1. Users Table (System Administrators & Counter Traders)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(120) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(10) CHECK(role IN ('admin', 'trader')) NOT NULL DEFAULT 'trader',
    status VARCHAR(10) CHECK(status IN ('active', 'disabled')) NOT NULL DEFAULT 'active',
    store_name VARCHAR(100) DEFAULT 'Stall #104',
    mpesa_shortcode VARCHAR(20) DEFAULT '174379',
    mpesa_type VARCHAR(20) DEFAULT 'till',
    mpesa_passkey VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    total_spent DECIMAL(10, 2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Receipts Table (Core Transaction Ledger)
CREATE TABLE IF NOT EXISTS receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_number VARCHAR(32) NOT NULL UNIQUE,
    trader_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status VARCHAR(15) CHECK(payment_status IN ('pending', 'completed', 'failed', 'cancelled', 'timed_out')) DEFAULT 'pending',
    mpesa_checkout_id VARCHAR(64),
    mpesa_receipt_number VARCHAR(32),
    qr_code_path VARCHAR(255),
    pdf_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trader_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- 4. Receipt Items Table (Line item audit trail)
CREATE TABLE IF NOT EXISTS receipt_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    receipt_id INTEGER NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE
);

-- 5. Audit Log Table (Forensic staff action tracking)
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action_performed VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Optimization Indexes
CREATE INDEX IF NOT EXISTS idx_receipts_number ON receipts(receipt_number);
CREATE INDEX IF NOT EXISTS idx_receipts_status ON receipts(payment_status);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone_number);
CREATE INDEX IF NOT EXISTS idx_receipts_trader ON receipts(trader_id);
CREATE INDEX IF NOT EXISTS idx_receipts_checkout ON receipts(mpesa_checkout_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_timestamp ON audit_log(timestamp);
