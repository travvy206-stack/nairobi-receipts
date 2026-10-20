<?php
/**
 * Step A: Order & Payment Initiation Endpoint
 * Nairobi Traders Association (NTA-Pay)
 * Routes Lipa Na M-Pesa STK Push directly to the Trader's configured Till or Paybill number.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../src/Payment/MpesaDarajaService.php';

$auth = new AuthService();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please authenticate to initiate payments.']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items']) || empty($input['customer_phone'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters: customer_phone and items array are required.']);
    exit;
}

// Clean and normalize phone number
$rawPhone = trim($input['customer_phone']);
$cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
if (str_starts_with($cleanPhone, '0')) {
    $formattedPhone = '254' . substr($cleanPhone, 1);
} elseif (str_starts_with($cleanPhone, '254')) {
    $formattedPhone = $cleanPhone;
} else {
    $formattedPhone = '254' . $cleanPhone;
}

if (strlen($formattedPhone) !== 12) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Kenyan mobile number. Expected format: 07XX XXX XXX or 2547XX XXX XXX.']);
    exit;
}

$items = $input['items'];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cart cannot be empty. Please add at least one line item.']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    // 1. Fetch Trader's Destination M-Pesa Account Details
    $stmtTrader = $db->prepare("SELECT mpesa_shortcode, mpesa_type, store_name, mpesa_passkey FROM users WHERE id = ?");
    $stmtTrader->execute([$currentUser['id']]);
    $traderSettings = $stmtTrader->fetch() ?: [];

    $destinationShortcode = $traderSettings['mpesa_shortcode'] ?? '174379';
    $destinationType = $traderSettings['mpesa_type'] ?? 'till';
    $storeName = $traderSettings['store_name'] ?? 'Stall #104';
    $destinationPasskey = $traderSettings['mpesa_passkey'] ?? null;

    // 2. Resolve or Create Customer Record
    $stmt = $db->prepare("SELECT id, full_name FROM customers WHERE phone_number = ?");
    $stmt->execute([$formattedPhone]);
    $customer = $stmt->fetch();

    if (!$customer) {
        $customerName = trim($input['customer_name'] ?? 'Walk-in Customer');
        if (empty($customerName)) {
            $customerName = 'Walk-in Customer';
        }
        $stmt = $db->prepare("INSERT INTO customers (full_name, phone_number, total_spent) VALUES (?, ?, 0.00)");
        $stmt->execute([$customerName, $formattedPhone]);
        $customerId = (int)$db->lastInsertId();
    } else {
        $customerId = (int)$customer['id'];
    }

    // 3. Validate Line Items and Calculate Grand Total
    $totalAmount = 0.0;
    $validatedItems = [];
    foreach ($items as $item) {
        $name = trim($item['name'] ?? 'Item');
        $qty = max(1, (int)($item['quantity'] ?? 1));
        $unitPrice = max(0.0, (float)($item['unit_price'] ?? 0.0));
        $lineTotal = round($qty * $unitPrice, 2);
        $totalAmount += $lineTotal;

        $validatedItems[] = [
            'name' => $name,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal
        ];
    }

    if ($totalAmount <= 0) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Order total must be greater than zero.']);
        exit;
    }

    // 4. Generate Unique Receipt Identifier
    $receiptNum = "NTA-" . strtoupper(bin2hex(random_bytes(4)));

    // 5. Dispatch Lipa Na M-Pesa STK Push via Daraja Service
    $darajaService = new MpesaDarajaService();
    $stkResponse = $darajaService->initiateStkPush([
        'amount' => $totalAmount,
        'phone' => $formattedPhone,
        'receipt_number' => $receiptNum,
        'store_name' => $storeName,
        'trader_shortcode' => $destinationShortcode,
        'trader_type' => $destinationType,
        'trader_passkey' => $destinationPasskey
    ]);

    if (!$stkResponse['success']) {
        $db->rollBack();
        http_response_code(502);
        echo json_encode(['status' => 'error', 'message' => $stkResponse['message']]);
        exit;
    }

    $checkoutRequestId = $stkResponse['checkout_request_id'];

    // 6. Record Pending Transaction in DB
    $stmt = $db->prepare("INSERT INTO receipts (receipt_number, trader_id, customer_id, total_amount, payment_status, mpesa_checkout_id, created_at) 
                          VALUES (?, ?, ?, ?, 'pending', ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$receiptNum, $currentUser['id'], $customerId, $totalAmount, $checkoutRequestId]);
    $receiptId = (int)$db->lastInsertId();

    // 7. Store Itemized Records
    $stmtItem = $db->prepare("INSERT INTO receipt_items (receipt_id, item_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
    foreach ($validatedItems as $item) {
        $stmtItem->execute([$receiptId, $item['name'], $item['quantity'], $item['unit_price'], $item['line_total']]);
    }

    // 8. Forensic Audit Log
    $auth->logAudit(
        $currentUser['id'], 
        "STK Push Sent: KES " . number_format($totalAmount, 2) . " to +$formattedPhone routing to $destinationType #$destinationShortcode (Pending #$receiptNum)",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    );

    $db->commit();

    echo json_encode([
        'status' => 'pending',
        'message' => $stkResponse['customer_message'] ?? 'M-Pesa STK Push dispatched to customer mobile terminal.',
        'checkout_request_id' => $checkoutRequestId,
        'receipt_id' => $receiptId,
        'receipt_number' => $receiptNum,
        'total_amount' => $totalAmount,
        'customer_phone' => '+' . $formattedPhone,
        'destination' => [
            'shortcode' => $destinationShortcode,
            'type' => $destinationType,
            'store_name' => $storeName
        ],
        'timeout_seconds' => 60
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Payment initialization failed: ' . $e->getMessage()]);
}
