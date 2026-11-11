<?php
/**
 * Step B & C: Payment Verification & Receipt Minting Engine
 * Nairobi Traders Association (NTA-Pay)
 * Checks real Safaricom webhook callback status or simulation delay
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$auth = new AuthService();
$db = Database::getInstance()->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST ?: $_GET;

$checkoutRequestId = $input['checkout_request_id'] ?? null;
$receiptId = $input['receipt_id'] ?? null;
$receiptNum = $input['receipt_number'] ?? null;
$action = $input['action'] ?? null; // 'cancel', 'timeout', or null

if (!$checkoutRequestId && !$receiptId && !$receiptNum) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid checkout_request_id, receipt_id, or receipt_number required.']);
    exit;
}

try {
    // 1. Fetch current transaction record
    if ($receiptId) {
        $stmt = $db->prepare("SELECT r.*, u.full_name AS trader_name, c.phone_number AS customer_phone, c.id AS customer_table_id 
                              FROM receipts r 
                              JOIN users u ON r.trader_id = u.id 
                              JOIN customers c ON r.customer_id = c.id 
                              WHERE r.id = ?");
        $stmt->execute([(int)$receiptId]);
    } elseif ($checkoutRequestId) {
        $stmt = $db->prepare("SELECT r.*, u.full_name AS trader_name, c.phone_number AS customer_phone, c.id AS customer_table_id 
                              FROM receipts r 
                              JOIN users u ON r.trader_id = u.id 
                              JOIN customers c ON r.customer_id = c.id 
                              WHERE r.mpesa_checkout_id = ?");
        $stmt->execute([$checkoutRequestId]);
    } else {
        $stmt = $db->prepare("SELECT r.*, u.full_name AS trader_name, c.phone_number AS customer_phone, c.id AS customer_table_id 
                              FROM receipts r 
                              JOIN users u ON r.trader_id = u.id 
                              JOIN customers c ON r.customer_id = c.id 
                              WHERE r.receipt_number = ?");
        $stmt->execute([$receiptNum]);
    }

    $receipt = $stmt->fetch();
    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Pending checkout transaction not found.']);
        exit;
    }

    // A. Check if already settled (e.g. by real Safaricom webhook or prior poll)
    if ($receipt['payment_status'] === 'completed') {
        echo json_encode([
            'status' => 'completed',
            'message' => 'Payment verified and official receipt ready.',
            'receipt_id' => (int)$receipt['id'],
            'receipt_number' => $receipt['receipt_number'],
            'mpesa_receipt_number' => $receipt['mpesa_receipt_number'] ?? null,
            'total_amount' => (float)$receipt['total_amount'],
            'pdf_url' => $receipt['pdf_path'],
            'qr_url' => $receipt['qr_code_path']
        ]);
        exit;
    }

    // B. Check if already failed or timed out
    if (in_array($receipt['payment_status'], ['failed', 'cancelled', 'timed_out'])) {
        echo json_encode([
            'status' => $receipt['payment_status'],
            'message' => 'Payment was unsuccessful or cancelled. No receipt was issued.',
            'receipt_number' => $receipt['receipt_number']
        ]);
        exit;
    }

    // C. Handle Operator Cancellation
    if ($action === 'cancel') {
        $stmtCancel = $db->prepare("UPDATE receipts SET payment_status = 'cancelled' WHERE id = ?");
        $stmtCancel->execute([(int)$receipt['id']]);
        
        $auth->logAudit(
            (int)$receipt['trader_id'], 
            "Transaction Cancelled: Operator aborted pending checkout #{$receipt['receipt_number']}",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );

        echo json_encode([
            'status' => 'cancelled',
            'message' => 'Transaction was cancelled by operator. No receipt generated.',
            'receipt_number' => $receipt['receipt_number']
        ]);
        exit;
    }

    // D. Handle Timeout (60 seconds)
    $createdTime = strtotime($receipt['created_at']);
    $elapsedSeconds = time() - $createdTime;

    if ($action === 'timeout' || $elapsedSeconds > 65) {
        $stmtTimeout = $db->prepare("UPDATE receipts SET payment_status = 'timed_out' WHERE id = ?");
        $stmtTimeout->execute([(int)$receipt['id']]);

        $auth->logAudit(
            (int)$receipt['trader_id'], 
            "M-Pesa Timeout: PIN prompt expired without customer entry for checkout #{$receipt['receipt_number']}",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );

        echo json_encode([
            'status' => 'timed_out',
            'message' => 'Payment timed out. Customer did not enter M-Pesa PIN within 60 seconds.',
            'receipt_number' => $receipt['receipt_number']
        ]);
        exit;
    }

    // E. Determine Simulation vs. Real Callback Mode
    $isSimulator = filter_var(Database::getInstance()->getEnv('MPESA_SIMULATOR_MODE', 'true'), FILTER_VALIDATE_BOOLEAN);

    // In Real Mode: Await Safaricom Webhook Callback
    if (!$isSimulator) {
        echo json_encode([
            'status' => 'pending',
            'message' => 'Awaiting customer PIN entry on mobile phone (Safaricom Daraja)...',
            'elapsed_seconds' => $elapsedSeconds,
            'receipt_number' => $receipt['receipt_number']
        ]);
        exit;
    }

    // In Simulator Mode: Hold for 2.5s then simulate confirmation
    if ($elapsedSeconds < 2) {
        echo json_encode([
            'status' => 'pending',
            'message' => 'Awaiting M-Pesa PIN entry on customer phone...',
            'elapsed_seconds' => $elapsedSeconds,
            'receipt_number' => $receipt['receipt_number']
        ]);
        exit;
    }

    // F. Simulator Minting Fallback (triggers Python Flask on port 5000)
    $stmtItems = $db->prepare("SELECT item_name AS name, quantity, unit_price, line_total FROM receipt_items WHERE receipt_id = ?");
    $stmtItems->execute([(int)$receipt['id']]);
    $items = $stmtItems->fetchAll();

    $pythonServiceUrl = Database::getInstance()->getEnv('PDF_SERVICE_URL', 'http://localhost:5000/generate-receipt');
    $payload = [
        'receipt_number' => $receipt['receipt_number'],
        'trader_name' => $receipt['trader_name'] ?? 'Authorized Trader',
        'customer_phone' => $receipt['customer_phone'],
        'items' => array_map(function($i) {
            return [
                'name' => $i['name'],
                'quantity' => (int)$i['quantity'],
                'unit_price' => (float)$i['unit_price'],
                'line_total' => (float)$i['line_total']
            ];
        }, $items),
        'total_amount' => (float)$receipt['total_amount'],
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

    if ($httpCode === 200 && ($resData = json_decode($response, true)) && ($resData['status'] ?? '') === 'success') {
        $pdfUrl = $resData['pdf_url'];
        $qrUrl = $resData['qr_url'];

        $db->beginTransaction();
        $stmtUp = $db->prepare("UPDATE receipts SET payment_status = 'completed', pdf_path = ?, qr_code_path = ? WHERE id = ?");
        $stmtUp->execute([$pdfUrl, $qrUrl, (int)$receipt['id']]);

        $stmtCust = $db->prepare("UPDATE customers SET total_spent = total_spent + ? WHERE id = ?");
        $stmtCust->execute([(float)$receipt['total_amount'], (int)$receipt['customer_table_id']]);

        $auth->logAudit(
            (int)$receipt['trader_id'], 
            "Payment Settled & Receipt Minted: #{$receipt['receipt_number']} (KES " . number_format($receipt['total_amount'], 2) . ")",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        $db->commit();

        echo json_encode([
            'status' => 'completed',
            'message' => 'Payment settled successfully and digital receipt minted.',
            'receipt_id' => (int)$receipt['id'],
            'receipt_number' => $receipt['receipt_number'],
            'total_amount' => (float)$receipt['total_amount'],
            'pdf_url' => $pdfUrl,
            'qr_url' => $qrUrl
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            'status' => 'error',
            'message' => 'Receipt generation microservice error: ' . ($response ?: "HTTP $httpCode")
        ]);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
