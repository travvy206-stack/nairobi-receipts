<?php
/**
 * Safaricom Daraja Lipa Na M-Pesa Online (STK Push) Service
 * Nairobi Traders Association (NTA-Pay)
 * Supports dynamic trader Till/Paybill numbers and seamless Live/Sandbox switching
 */

require_once __DIR__ . '/../../config/db.php';

class MpesaDarajaService {
    private string $env;
    private bool $isSimulator;
    private string $consumerKey;
    private string $consumerSecret;
    private string $globalPasskey;
    private string $globalShortcode;
    private string $callbackUrl;
    private ?string $cachedToken = null;
    private int $tokenExpiry = 0;

    public function __construct() {
        $db = Database::getInstance();
        $this->env = strtolower($db->getEnv('MPESA_ENVIRONMENT', 'sandbox'));
        $this->isSimulator = filter_var($db->getEnv('MPESA_SIMULATOR_MODE', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->consumerKey = $db->getEnv('MPESA_CONSUMER_KEY', '');
        $this->consumerSecret = $db->getEnv('MPESA_CONSUMER_SECRET', '');
        $this->globalPasskey = $db->getEnv('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // Safaricom standard sandbox passkey
        $this->globalShortcode = $db->getEnv('MPESA_SHORTCODE', '174379');
        $this->callbackUrl = $db->getEnv('MPESA_CALLBACK_URL', 'https://nairobi-receipts.onrender.com/api/mpesa_callback.php');
    }

    private function getBaseUrl(): string {
        return $this->env === 'live' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Request OAuth Access Token from Safaricom Daraja
     */
    public function getAccessToken(): ?string {
        if ($this->cachedToken && time() < $this->tokenExpiry) {
            return $this->cachedToken;
        }

        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            return null;
        }

        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $data = json_decode($response, true)) {
            $this->cachedToken = $data['access_token'];
            $this->tokenExpiry = time() + (int)($data['expires_in'] ?? 3599) - 60;
            return $this->cachedToken;
        }

        return null;
    }

    /**
     * Trigger Lipa Na M-Pesa STK Push
     * Routes money to the Trader's configured Till or Paybill shortcode
     */
    public function initiateStkPush(array $params): array {
        $amount = (int)ceil($params['amount']); // M-Pesa expects integer amount in KES
        $phone = preg_replace('/[^0-9]/', '', $params['phone']);
        $receiptNumber = $params['receipt_number'] ?? ('NTA-' . strtoupper(bin2hex(random_bytes(4))));
        $storeName = $params['store_name'] ?? 'Stall #104';

        // Resolve trader destination shortcode
        $shortcode = !empty($params['trader_shortcode']) ? trim($params['trader_shortcode']) : $this->globalShortcode;
        $type = strtolower($params['trader_type'] ?? 'till');
        $passkey = !empty($params['trader_passkey']) ? trim($params['trader_passkey']) : $this->globalPasskey;

        // In Simulator Mode (or if keys are unconfigured)
        if ($this->isSimulator || empty($this->consumerKey) || empty($this->consumerSecret)) {
            $mockCheckoutId = "ws_CO_" . date('YmdHis') . "_" . rand(10000, 99999);
            return [
                'success' => true,
                'mode' => 'simulator',
                'checkout_request_id' => $mockCheckoutId,
                'shortcode' => $shortcode,
                'type' => $type,
                'customer_message' => "Success: Simulated STK push targeted to $type #$shortcode for KES $amount."
            ];
        }

        // Live / Sandbox Daraja API Execution
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Unable to authenticate with Safaricom Daraja API. Please verify Consumer Key and Secret.'
            ];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);
        $transactionType = ($type === 'paybill') ? 'CustomerPayBillOnline' : 'CustomerBuyGoodsOnline';

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $transactionType,
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => substr($storeName, 0, 12),
            'TransactionDesc' => "NTA $receiptNumber"
        ];

        $url = $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'message' => 'Connection to Safaricom failed: ' . $curlErr];
        }

        $resData = json_decode($response, true);
        if ($httpCode === 200 && isset($resData['ResponseCode']) && $resData['ResponseCode'] == '0') {
            return [
                'success' => true,
                'mode' => 'daraja_' . $this->env,
                'checkout_request_id' => $resData['CheckoutRequestID'],
                'shortcode' => $shortcode,
                'type' => $type,
                'customer_message' => $resData['CustomerMessage'] ?? 'STK push prompt sent to customer phone.'
            ];
        }

        $errMsg = $resData['errorMessage'] ?? $resData['ResponseDescription'] ?? ($response ?: "HTTP $httpCode error from Safaricom");
        return [
            'success' => false,
            'message' => 'Safaricom Daraja Error: ' . $errMsg
        ];
    }
}
