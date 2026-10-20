<?php
require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance()->getConnection();

try {
    $sql = $db->query("SELECT sql FROM sqlite_master WHERE name = 'receipts'")->fetchColumn();
    echo "Current schema:\n$sql\n";

    if (!str_contains($sql, "'cancelled'")) {
        echo "Updating receipts table check constraint to support cancelled and timed_out...\n";
        $db->exec("PRAGMA foreign_keys = OFF;");
        $db->beginTransaction();

        $db->exec("CREATE TABLE receipts_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            receipt_number VARCHAR(32) NOT NULL UNIQUE,
            trader_id INTEGER NOT NULL,
            customer_id INTEGER NOT NULL,
            total_amount DECIMAL(10, 2) NOT NULL,
            payment_status VARCHAR(15) CHECK(payment_status IN ('pending', 'completed', 'failed', 'cancelled', 'timed_out')) DEFAULT 'pending',
            mpesa_checkout_id VARCHAR(64),
            qr_code_path VARCHAR(255),
            pdf_path VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trader_id) REFERENCES users(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        );");

        $db->exec("INSERT INTO receipts_new SELECT * FROM receipts;");
        $db->exec("DROP TABLE receipts;");
        $db->exec("ALTER TABLE receipts_new RENAME TO receipts;");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_receipts_number ON receipts(receipt_number);");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_receipts_status ON receipts(payment_status);");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_receipts_trader ON receipts(trader_id);");

        $db->commit();
        $db->exec("PRAGMA foreign_keys = ON;");
        echo "Receipts table updated successfully.\n";
    } else {
        echo "Receipts table already has extended check constraints.\n";
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Migration error: " . $e->getMessage() . "\n";
}
