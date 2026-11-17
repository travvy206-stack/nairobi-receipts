<?php
/**
 * Quick fix: update all users' mpesa_type to 'paybill' for sandbox shortcode 174379.
 * DELETE after running.
 */
$secret = $_GET['token'] ?? '';
if ($secret !== 'nta-migrate-2024') {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

require_once __DIR__ . '/../../config/db.php';
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("UPDATE users SET mpesa_type = 'paybill' WHERE mpesa_shortcode = '174379'");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo json_encode(['status' => 'done', 'updated_rows' => $count]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
