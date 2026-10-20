<?php
/**
 * All-in-one Payment & Receipt Processing Script (Direct Processor)
 * Nairobi Traders Association (NTA-Pay)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$auth = new AuthService();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items']) || empty($input['customer_phone'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters: customer_phone and items required.']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    // 1. Resolve or Create Customer
    $stmt = $db->prepare("SELECT id FROM customers WHERE phone_number = ?");
    $stmt->execute([$input['customer_phone']]);
    $customer = $stmt->fetch();

    if (!$customer) {
        $stmt = $db->prepare("INSERT INTO customers (full_name, phone_number, total_spent) VALUES (?, ?, 0.00)");
        $stmt->execute(['Walk-in Customer', $input['customer_phone']]);
        $customerId = (int)$db->lastInsertId();
    } else {
        $customerId = (int)$customer['id'];
    }

    // 2. Calculate Totals
    $totalAmount = 0.0;
    foreach ($input['items'] as $item) {
        $totalAmount += (int)$item['quantity'] * (float)$item['unit_price'];
    }

    // 3. Create Pending Receipt
    $receiptNum = "NTA-" . strtoupper(bin2hex(random_bytes(4)));
    $mockCheckoutId = "ws_CO_" . date('YmdHis') . "_" . rand(10000, 99999);
    $stmt = $db->prepare("INSERT INTO receipts (receipt_number, trader_id, customer_id, total_amount, payment_status, mpesa_checkout_id, created_at) 
                          VALUES (?, ?, ?, ?, 'pending', ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$receiptNum, $currentUser['id'], $customerId, $totalAmount, $mockCheckoutId]);
    $receiptId = (int)$db->lastInsertId();

    // 4. Insert Items
    $stmtItem = $db->prepare("INSERT INTO receipt_items (receipt_id, item_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
    foreach ($input['items'] as $item) {
        $lineTotal = (int)$item['quantity'] * (float)$item['unit_price'];
        $stmtItem->execute([$receiptId, $item['name'], (int)$item['quantity'], (float)$item['unit_price'], $lineTotal]);
    }

    $db->commit();

    // 5. Trigger PDF Microservice via cURL
    $pythonServiceUrl = Database::getInstance()->getEnv('PDF_SERVICE_URL', 'http://localhost:5000/generate-receipt');
    $payload = [
        'receipt_number' => $receiptNum,
        'trader_name' => $currentUser['full_name'],
        'customer_phone' => $input['customer_phone'],
        'items' => array_map(function($i) {
            return [
                'name' => $i['name'],
                'quantity' => (int)$i['quantity'],
                'unit_price' => (float)$i['unit_price'],
                'line_total' => (int)$i['quantity'] * (float)$i['unit_price']
            ];
        }, $input['items']),
        'total_amount' => $totalAmount,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $ch = curl_init($pythonServiceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && ($resData = json_decode($response, true))) {
        // Update database to completed status with asset paths
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE receipts SET payment_status = 'completed', pdf_path = ?, qr_code_path = ? WHERE id = ?");
        $stmt->execute([$resData['pdf_url'], $resData['qr_url'], $receiptId]);

        $stmtCust = $db->prepare("UPDATE customers SET total_spent = total_spent + ? WHERE id = ?");
        $stmtCust->execute([$totalAmount, $customerId]);

        $auth->logAudit($currentUser['id'], "Direct settlement & receipt minted: #$receiptNum (KES " . number_format($totalAmount, 2) . ")");
        $db->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Payment settled and receipt generated',
            'receipt_number' => $receiptNum,
            'pdf_url' => $resData['pdf_url'],
            'qr_url' => $resData['qr_url']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'PDF microservice execution failed']);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
