<?php
/**
 * Payment Health Check — tests connectivity to Safaricom Daraja sandbox
 * GET /api/payment_health.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

$db = Database::getInstance();
$results = [];

// 1. Check env vars (don't leak values)
$consumerKey    = $db->getEnv('MPESA_CONSUMER_KEY', '');
$consumerSecret = $db->getEnv('MPESA_CONSUMER_SECRET', '');
$passkey        = $db->getEnv('MPESA_PASSKEY', '');
$shortcode      = $db->getEnv('MPESA_SHORTCODE', '174379');
$simMode        = $db->getEnv('MPESA_SIMULATOR_MODE', 'true');
$environment    = $db->getEnv('MPESA_ENVIRONMENT', 'sandbox');
$callbackUrl    = $db->getEnv('MPESA_CALLBACK_URL', '');

$results['env_vars'] = [
    'MPESA_CONSUMER_KEY'    => !empty($consumerKey) ? 'SET (' . strlen($consumerKey) . ' chars)' : 'MISSING',
    'MPESA_CONSUMER_SECRET' => !empty($consumerSecret) ? 'SET (' . strlen($consumerSecret) . ' chars)' : 'MISSING',
    'MPESA_PASSKEY'         => !empty($passkey) ? 'SET (' . strlen($passkey) . ' chars)' : 'MISSING (will use default sandbox key)',
    'MPESA_SHORTCODE'       => $shortcode,
    'MPESA_SIMULATOR_MODE'  => $simMode,
    'MPESA_ENVIRONMENT'     => $environment,
    'MPESA_CALLBACK_URL'    => !empty($callbackUrl) ? $callbackUrl : 'NOT SET',
];

// 2. Test TCP connectivity to sandbox.safaricom.co.ke
$host = ($environment === 'live') ? 'api.safaricom.co.ke' : 'sandbox.safaricom.co.ke';
$connectStart = microtime(true);
$fp = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 10);
$connectTime = round((microtime(true) - $connectStart) * 1000);

if ($fp) {
    fclose($fp);
    $results['connectivity'] = [
        'host'    => $host,
        'status'  => 'REACHABLE',
        'latency' => $connectTime . 'ms',
    ];
} else {
    $results['connectivity'] = [
        'host'   => $host,
        'status' => 'UNREACHABLE',
        'error'  => "$errstr (code $errno)",
        'time'   => $connectTime . 'ms',
    ];
}

// 3. Try OAuth token fetch (only if credentials exist and host is reachable)
if (!empty($consumerKey) && !empty($consumerSecret) && isset($fp) === false || ($results['connectivity']['status'] === 'REACHABLE')) {
    if (!empty($consumerKey) && !empty($consumerSecret)) {
        $url = "https://$host/oauth/v1/generate?grant_type=client_credentials";
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $tokenStart = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        $tokenTime = round((microtime(true) - $tokenStart) * 1000);
        curl_close($ch);

        if ($curlErr) {
            $results['oauth_token'] = ['status' => 'CURL_ERROR', 'error' => $curlErr, 'time' => $tokenTime . 'ms'];
        } elseif ($httpCode === 200) {
            $data = json_decode($response, true);
            $results['oauth_token'] = [
                'status'     => 'SUCCESS',
                'token_hint' => substr($data['access_token'] ?? '', 0, 10) . '...',
                'expires_in' => ($data['expires_in'] ?? '?') . 's',
                'time'       => $tokenTime . 'ms',
            ];
        } else {
            $results['oauth_token'] = [
                'status'    => 'FAILED',
                'http_code' => $httpCode,
                'body'      => substr($response, 0, 200),
                'time'      => $tokenTime . 'ms',
            ];
        }
    }
}

// 4. DB connectivity
try {
    $conn = $db->getConnection();
    $conn->query("SELECT 1");
    $results['database'] = 'OK';
} catch (Exception $e) {
    $results['database'] = 'ERROR: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
