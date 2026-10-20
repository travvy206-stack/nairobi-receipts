<?php
/**
 * Trader Destination Account Settings API
 * Nairobi Traders Association (NTA-Pay)
 * Allows counter traders and administrators to dynamically configure destination Till / Paybill numbers.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$auth = new AuthService();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please authenticate.']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$targetUserId = $currentUser['id'];
if ($auth->isAdmin() && !empty($input['user_id'])) {
    $targetUserId = (int)$input['user_id'];
}

// GET Request: Retrieve active account settings
if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($input['action'])) {
    $stmt = $db->prepare("SELECT id, full_name, store_name, mpesa_shortcode, mpesa_type, mpesa_passkey FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User account not found.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'store_name' => $user['store_name'] ?? 'Stall #104',
            'mpesa_shortcode' => $user['mpesa_shortcode'] ?? '174379',
            'mpesa_type' => $user['mpesa_type'] ?? 'till',
            'has_custom_passkey' => !empty($user['mpesa_passkey'])
        ]
    ]);
    exit;
}

// POST Request: Update destination account settings
$shortcode = preg_replace('/[^0-9]/', '', trim($input['mpesa_shortcode'] ?? ''));
$type = strtolower(trim($input['mpesa_type'] ?? 'till')) === 'paybill' ? 'paybill' : 'till';
$storeName = trim($input['store_name'] ?? 'Stall #104');
$customPasskey = !empty($input['mpesa_passkey']) ? trim($input['mpesa_passkey']) : null;

if (empty($shortcode) || strlen($shortcode) < 5) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid M-Pesa Shortcode (5 to 7 digits) is required.']);
    exit;
}

try {
    $stmt = $db->prepare("UPDATE users 
                          SET mpesa_shortcode = ?, 
                              mpesa_type = ?, 
                              store_name = ?, 
                              mpesa_passkey = COALESCE(?, mpesa_passkey) 
                          WHERE id = ?");
    $stmt->execute([$shortcode, $type, $storeName, $customPasskey, $targetUserId]);

    $auth->logAudit(
        $currentUser['id'], 
        "Updated M-Pesa Destination Account for User #$targetUserId: $type #$shortcode ($storeName)",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    );

    echo json_encode([
        'status' => 'success',
        'message' => "Destination account updated successfully to $type #$shortcode ($storeName).",
        'shortcode' => $shortcode,
        'type' => $type,
        'store_name' => $storeName
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update destination account: ' . $e->getMessage()]);
}
