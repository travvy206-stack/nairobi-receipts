<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$db = Database::getInstance();
$keys = ['MPESA_CONSUMER_KEY','MPESA_CONSUMER_SECRET','MPESA_SIMULATOR_MODE','MPESA_CALLBACK_URL'];
$out = [];
foreach ($keys as $k) {
    $out[$k] = [
        'env' => getenv($k) ?: null,
        'db_getEnv' => $db->getEnv($k),
        'isset' => isset($_ENV[$k]) ? true : false,
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT);
?>