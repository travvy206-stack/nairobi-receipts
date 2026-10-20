<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$auth = new AuthService();
if (!$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Administrator privileges required.']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';

try {
    if ($action === 'create_trader') {
        $fullName = trim($input['full_name'] ?? '');
        $phone = preg_replace('/[^0-9+]/', '', trim($input['phone_number'] ?? ''));
        $email = !empty($input['email']) ? strtolower(trim($input['email'])) : null;
        $password = trim($input['password'] ?? '');
        $shortcode = preg_replace('/[^0-9]/', '', trim($input['mpesa_shortcode'] ?? '174379'));
        $type = strtolower(trim($input['mpesa_type'] ?? 'till')) === 'paybill' ? 'paybill' : 'till';
        $storeName = trim($input['store_name'] ?? 'Stall #104');

        if (empty($fullName) || empty($phone) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Full Name, Phone Number, and Password are required.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
            exit;
        }

        // Check phone duplicate
        $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'A staff member with this phone number already exists.']);
            exit;
        }

        // Check email duplicate if provided
        if ($email) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'A staff member with this email already exists.']);
                exit;
            }
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (full_name, phone_number, email, password_hash, role, status, mpesa_shortcode, mpesa_type, store_name) 
                              VALUES (?, ?, ?, ?, 'trader', 'active', ?, ?, ?)");
        $stmt->execute([$fullName, $phone, $email, $hash, $shortcode, $type, $storeName]);
        $newId = (int)$db->lastInsertId();

        $auth->logAudit($currentUser['id'], "Administrator created trader account: $fullName ($phone) routing to $type #$shortcode ($storeName)");
        echo json_encode(['status' => 'success', 'message' => 'Trader account registered successfully.', 'id' => $newId]);
        exit;
    }

    if ($action === 'toggle_status') {
        $traderId = (int)($input['trader_id'] ?? 0);
        $newStatus = ($input['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'trader'");
        $stmt->execute([$newStatus, $traderId]);

        $auth->logAudit($currentUser['id'], "Administrator changed status of trader #$traderId to $newStatus");
        echo json_encode(['status' => 'success', 'message' => "Trader account status updated to $newStatus."]);
        exit;
    }

    if ($action === 'reset_password') {
        $traderId = (int)($input['trader_id'] ?? 0);
        $newPassword = trim($input['new_password'] ?? '');

        if (empty($newPassword) || strlen($newPassword) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters in length.']);
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'trader'");
        $stmt->execute([$hash, $traderId]);

        $auth->logAudit($currentUser['id'], "Administrator reset password for trader #$traderId");
        echo json_encode(['status' => 'success', 'message' => 'Password reset successfully for trader account.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unrecognized API action.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
