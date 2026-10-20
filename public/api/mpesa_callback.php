<?php
/**
 * Safaricom M-Pesa Daraja Callback Webhook Listener
 * Nairobi Traders Association (NTA-Pay)
 * Receives live confirmation when customer enters PIN on mobile phone
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$rawBody = file_get_contents('php://input');

// Log raw callback for forensic audit & inspection
$logDir = dirname(__DIR__, 2) . '/service_pdf/storage';
if (is_dir($logDir)) {
    file_put_contents($logDir . '/mpesa_callbacks.log', "[" . date('Y-m-d H:i:s') . "] " . $rawBody . PHP_EOL, FILE_APPEND);
}

$callbackData = json_decode($rawBody, true);
$stkCallback = $callbackData['Body']['stkCallback'] ?? null;

if (!$stkCallback) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid M-Pesa payload']);
    exit;
}

$checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
$resultCode = (int)($stkCallback['ResultCode'] ?? -1);
$resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown status';

$db = Database::getInstance()->getConnection();
$auth = new AuthService();

try {
    $stmt = $db->prepare("SELECT r.*, u.full_name AS trader_name, c.phone_number AS customer_phone, c.id AS customer_table_id 
                          FROM receipts r 
                          JOIN users u ON r.trader_id = u.id 
                          JOIN customers c ON r.customer_id = c.id 
                          WHERE r.mpesa_checkout_id = ?");
    $stmt->execute([$checkoutRequestId]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        // Acknowledge Safaricom even if local record is missing
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Checkout reference not found locally']);
        exit;
    }

    // Handle Payment Success (ResultCode == 0)
    if ($resultCode === 0) {
        $metaItems = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $mpesaReceiptNo = null;
        $paidAmount = $receipt['total_amount'];

        foreach ($metaItems as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $mpesaReceiptNo = $item['Value'] ?? null;
            } elseif (($item['Name'] ?? '') === 'Amount') {
                $paidAmount = (float)($item['Value'] ?? $paidAmount);
            }
        }

        // Fetch line items for Python PDF microservice
        $stmtItems = $db->prepare("SELECT item_name AS name, quantity, unit_price, line_total FROM receipt_items WHERE receipt_id = ?");
        $stmtItems->execute([(int)$receipt['id']]);
        $items = $stmtItems->fetchAll();

        // Trigger Python PDF Microservice (Port 5000)
        $pythonServiceUrl = Database::getInstance()->getEnv('PDF_SERVICE_URL', 'http://localhost:5000/generate-receipt');
        $payload = [
            'receipt_number' => $receipt['receipt_number'],
            'trader_name' => $receipt['trader_name'] ?? 'Counter Operator',
            'customer_phone' => $receipt['customer_phone'],
            'items' => array_map(function($i) {
                return [
                    'name' => $i['name'],
                    'quantity' => (int)$i['quantity'],
                    'unit_price' => (float)$i['unit_price'],
                    'line_total' => (float)$i['line_total']
                ];
            }, $items),
            'total_amount' => (float)$paidAmount,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $ch = curl_init($pythonServiceUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        curl_close($ch);

        $pdfUrl = null;
        $qrUrl = null;
        if ($resData = json_decode($response, true)) {
            $pdfUrl = $resData['pdf_url'] ?? null;
            $qrUrl = $resData['qr_url'] ?? null;
        }

        // Atomically update receipt record
        $db->beginTransaction();

        $stmtUp = $db->prepare("UPDATE receipts 
                                SET payment_status = 'completed', 
                                    mpesa_receipt_number = ?, 
                                    pdf_path = ?, 
                                    qr_code_path = ? 
                                WHERE id = ?");
        $stmtUp->execute([$mpesaReceiptNo, $pdfUrl, $qrUrl, (int)$receipt['id']]);

        // Increment customer cumulative total
        $stmtCust = $db->prepare("UPDATE customers SET total_spent = total_spent + ? WHERE id = ?");
        $stmtCust->execute([(float)$paidAmount, (int)$receipt['customer_table_id']]);

        // Forensic audit
        $auth->logAudit(
            (int)$receipt['trader_id'], 
            "Live Safaricom Payment Verified: KES " . number_format($paidAmount, 2) . " received [M-Pesa Ref: $mpesaReceiptNo] for Receipt #{$receipt['receipt_number']}",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );

        $db->commit();

    } else {
        // Payment Failed, Cancelled, or Expired
        $stmtFail = $db->prepare("UPDATE receipts SET payment_status = 'failed' WHERE id = ?");
        $stmtFail->execute([(int)$receipt['id']]);

        $auth->logAudit(
            (int)$receipt['trader_id'], 
            "Safaricom Payment Failed: [$resultCode] $resultDesc for Checkout #{$receipt['receipt_number']}",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
    }

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback processed successfully']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("M-Pesa callback exception: " . $e->getMessage());
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged with internal warning']);
}
