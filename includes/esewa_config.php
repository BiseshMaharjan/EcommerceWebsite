<?php
// ============================================
// eSEWA PAYMENT GATEWAY CONFIGURATION
// ============================================

// eSewa Configuration
define('ESEWA_SANDBOX', true); // Set to false for production

// Sandbox (Testing) URLs
define('ESEWA_SANDBOX_URL', 'https://rc-epay.esewa.com.np/api/epay/main/v2/form');
define('ESEWA_SANDBOX_STATUS_URL', 'https://rc.esewa.com.np/api/epay/transaction/status/');

// Production (Live) URLs
define('ESEWA_LIVE_URL', 'https://epay.esewa.com.np/api/epay/main/v2/form');
define('ESEWA_LIVE_STATUS_URL', 'https://esewa.com.np/api/epay/transaction/status/');

// Test Merchant Code (UAT)
define('ESEWA_TEST_PRODUCT_CODE', 'EPAYTEST');

// Live Merchant Code (Get from eSewa)
define('ESEWA_LIVE_PRODUCT_CODE', 'YOUR_LIVE_PRODUCT_CODE');

// Secret Key for signature generation (UAT)
define('ESEWA_TEST_SECRET_KEY', '8gBm/:&EnhH.1/q(');

// Live Secret Key (Get from eSewa)
define('ESEWA_LIVE_SECRET_KEY', 'YOUR_LIVE_SECRET_KEY');

// Site URL
define('SITE_URL', 'http://localhost/ecom-1day/');

function getEsewaConfig() {
    if (ESEWA_SANDBOX) {
        return [
            'payment_url' => ESEWA_SANDBOX_URL,
            'status_url' => ESEWA_SANDBOX_STATUS_URL,
            'product_code' => ESEWA_TEST_PRODUCT_CODE,
            'secret_key' => ESEWA_TEST_SECRET_KEY,
            'is_sandbox' => true
        ];
    } else {
        return [
            'payment_url' => ESEWA_LIVE_URL,
            'status_url' => ESEWA_LIVE_STATUS_URL,
            'product_code' => ESEWA_LIVE_PRODUCT_CODE,
            'secret_key' => ESEWA_LIVE_SECRET_KEY,
            'is_sandbox' => false
        ];
    }
}

// Function to generate eSewa signature
function generateEsewaSignature($total_amount, $transaction_uuid, $product_code, $secret_key) {
    // Create the message string
    $message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
    
    // Generate HMAC SHA256 hash
    $hash = hash_hmac('sha256', $message, $secret_key, true);
    
    // Convert to base64
    $signature = base64_encode($hash);
    
    return $signature;
}

// Function to verify eSewa callback signature
function verifyEsewaSignature($data, $signature, $secret_key) {
    // Create signed field names array
    $signed_field_names = explode(',', $data['signed_field_names'] ?? '');
    
    // Build message from signed fields
    $message_parts = [];
    foreach ($signed_field_names as $field) {
        if (isset($data[$field])) {
            $message_parts[] = $field . '=' . $data[$field];
        }
    }
    $message = implode(',', $message_parts);
    
    // Generate signature
    $hash = hash_hmac('sha256', $message, $secret_key, true);
    $generated_signature = base64_encode($hash);
    
    // Compare signatures
    return $generated_signature === $signature;
}

// Function to check transaction status
function checkEsewaStatus($product_code, $transaction_uuid, $total_amount) {
    $config = getEsewaConfig();
    
    $url = $config['status_url'] . '?product_code=' . urlencode($product_code) . 
           '&total_amount=' . urlencode($total_amount) . 
           '&transaction_uuid=' . urlencode($transaction_uuid);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return json_decode($response, true);
    } else {
        return ['status' => 'ERROR', 'http_code' => $http_code];
    }
}
?>