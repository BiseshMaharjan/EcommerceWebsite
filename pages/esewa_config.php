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
    $message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
    $hash = hash_hmac('sha256', $message, $secret_key, true);
    return base64_encode($hash);
}

// Function to verify eSewa callback signature
function verifyEsewaSignature($data, $signature, $secret_key) {
    $signed_field_names = explode(',', $data['signed_field_names'] ?? '');
    $message_parts = [];
    foreach ($signed_field_names as $field) {
        if (isset($data[$field])) {
            $message_parts[] = $field . '=' . $data[$field];
        }
    }
    $message = implode(',', $message_parts);
    $hash = hash_hmac('sha256', $message, $secret_key, true);
    $generated_signature = base64_encode($hash);
    return $generated_signature === $signature;
}
?>