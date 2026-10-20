<?php
/**
 * Safe Asset Server for Generated PDFs and QR Codes
 * Maps /storage.php?file=xyz or direct storage access
 */

$file = basename($_GET['file'] ?? $_SERVER['PATH_INFO'] ?? '');
if (empty($file)) {
    http_response_code(400);
    die("File parameter missing.");
}

$storageDir = realpath(__DIR__ . '/../service_pdf/storage');
$filePath = realpath($storageDir . '/' . $file);

if (!$filePath || !str_starts_with($filePath, $storageDir) || !file_exists($filePath)) {
    http_response_code(404);
    die("File not found.");
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if ($ext === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
} elseif ($ext === 'png') {
    header('Content-Type: image/png');
} else {
    header('Content-Type: application/octet-stream');
}

header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
