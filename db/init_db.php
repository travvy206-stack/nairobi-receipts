<?php
/**
 * Database Initializer & Seeder for Nairobi Traders Association
 */

$dbPath = __DIR__ . '/nairobi_traders.sqlite';
$schemaPath = __DIR__ . '/schema.sql';

if (!file_exists($schemaPath)) {
    die("Error: schema.sql not found at $schemaPath\n");
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Execute schema
    $sql = file_get_contents($schemaPath);
    $pdo->exec($sql);
    echo "Database schema initialized successfully.\n";

    // Seed default users if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $traderPass = password_hash('trader123', PASSWORD_BCRYPT);

        $insertUser = $pdo->prepare("INSERT INTO users (full_name, phone_number, password_hash, role, status) VALUES (?, ?, ?, ?, 'active')");
        $insertUser->execute(['System Administrator', '0700000000', $adminPass, 'admin']);
        $insertUser->execute(['J. Maina', '0712345678', $traderPass, 'trader']);
        $insertUser->execute(['A. Kamau', '0722334455', $traderPass, 'trader']);

        echo "Default users seeded:\n";
        echo " - Admin:  0700000000 / admin123\n";
        echo " - Trader: 0712345678 / trader123 (J. Maina)\n";
        echo " - Trader: 0722334455 / trader123 (A. Kamau)\n";

        // Seed sample customer
        $insertCust = $pdo->prepare("INSERT INTO customers (full_name, phone_number, total_spent) VALUES (?, ?, ?)");
        $insertCust->execute(['Grace Wanjiku', '0799887766', 740.00]);
        $custId = $pdo->lastInsertId();

        // Seed a sample completed receipt from wireframe
        $receiptNum = 'NTA-9A21';
        $insertReceipt = $pdo->prepare("INSERT INTO receipts (receipt_number, trader_id, customer_id, total_amount, payment_status, mpesa_checkout_id, pdf_path, qr_code_path) VALUES (?, 2, ?, 740.00, 'completed', 'ws_CO_DEMO9A21', '/storage/receipt_NTA-9A21.pdf', '/storage/qr_NTA-9A21.png')");
        $insertReceipt->execute([$receiptNum, $custId]);
        $receiptId = $pdo->lastInsertId();

        // Seed receipt items
        $insertItem = $pdo->prepare("INSERT INTO receipt_items (receipt_id, item_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
        $insertItem->execute([$receiptId, 'Maize Flour 2kg', 2, 210.00, 420.00]);
        $insertItem->execute([$receiptId, 'Cooking Oil 1L', 1, 320.00, 320.00]);

        // Seed audit log
        $insertLog = $pdo->prepare("INSERT INTO audit_log (user_id, action_performed, ip_address) VALUES (?, ?, ?)");
        $insertLog->execute([1, 'System schema initialization and seed completed', '127.0.0.1']);
        $insertLog->execute([2, 'J. Maina issued receipt #NTA-9A21 from Terminal #1', '192.168.1.45']);
        $insertLog->execute([3, 'A. Kamau logged into Terminal #3', '192.168.1.12']);

        echo "Sample data and audit logs seeded successfully.\n";
    } else {
        echo "Database already contains users. Skipping seed.\n";
    }
} catch (PDOException $e) {
    echo "Database initialization error: " . $e->getMessage() . "\n";
    exit(1);
}
