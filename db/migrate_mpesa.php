<?php
require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance()->getConnection();

try {
    echo "Running migration for trader M-Pesa destination accounts...\n";

    // 1. Check users table columns
    $userCols = array_column($db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    
    if (!in_array('mpesa_shortcode', $userCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN mpesa_shortcode VARCHAR(20) DEFAULT '174379';");
        echo " - Added mpesa_shortcode to users table.\n";
    }
    if (!in_array('mpesa_type', $userCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN mpesa_type VARCHAR(20) DEFAULT 'till';");
        echo " - Added mpesa_type to users table.\n";
    }
    if (!in_array('store_name', $userCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN store_name VARCHAR(100) DEFAULT 'Stall #104';");
        echo " - Added store_name to users table.\n";
    }
    if (!in_array('mpesa_passkey', $userCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN mpesa_passkey VARCHAR(255);");
        echo " - Added mpesa_passkey to users table.\n";
    }

    // 2. Check receipts table for mpesa_receipt_number
    $receiptCols = array_column($db->query("PRAGMA table_info(receipts)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('mpesa_receipt_number', $receiptCols)) {
        $db->exec("ALTER TABLE receipts ADD COLUMN mpesa_receipt_number VARCHAR(32);");
        echo " - Added mpesa_receipt_number to receipts table.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration notice: " . $e->getMessage() . "\n";
}
