<?php
// ============================================
// KHALTI PAYMENT GATEWAY CONFIGURATION
// ============================================

// Khalti API Configuration
define('KHALTI_SANDBOX', true); // Set to true for testing

// Sandbox Credentials (Get from https://test-admin.khalti.com)
define('KHALTI_SANDBOX_URL', 'https://dev.khalti.com/api/v2/');
// !!! REPLACE THIS WITH YOUR ACTUAL TEST SECRET KEY !!!
define('KHALTI_SANDBOX_SECRET_KEY', 'test_secret_key_2c2643d1ecbd4071b63a450e569725f8'); // <-- CHANGE THIS

// Production Credentials (Get from https://admin.khalti.com)
define('KHALTI_LIVE_URL', 'https://khalti.com/api/v2/');
define('KHALTI_LIVE_SECRET_KEY', 'live_secret_key_1234567890');

// Site URL
define('SITE_URL', 'http://localhost/ecom-1day/');

// Get current environment configuration
function getKhaltiConfig() {
    if (KHALTI_SANDBOX) {
        return [
            'url' => KHALTI_SANDBOX_URL,
            'secret_key' => KHALTI_SANDBOX_SECRET_KEY,
            'is_sandbox' => true
        ];
    } else {
        return [
            'url' => KHALTI_LIVE_URL,
            'secret_key' => KHALTI_LIVE_SECRET_KEY,
            'is_sandbox' => false
        ];
    }
}

// Function to initiate Khalti payment
function initiateKhaltiPayment($order_id, $amount, $purchase_order_name, $customer_info = []) {
    $config = getKhaltiConfig();
    
    // Amount in paisa (multiply by 100)
    $amount_in_paisa = $amount * 100;
    
    // Generate unique purchase_order_id
    $purchase_order_id = 'ORDER-' . $order_id . '-' . time();
    
    // Prepare return URL
    $return_url = SITE_URL . 'khalti_callback.php';
    $website_url = SITE_URL;
    
    // Prepare customer info
    $customer_data = [
        'name' => $customer_info['name'] ?? 'Customer',
        'email' => $customer_info['email'] ?? 'customer@example.com',
        'phone' => $customer_info['phone'] ?? '9800000000'
    ];
    
    // Prepare payload
    $payload = [
        'return_url' => $return_url,
        'website_url' => $website_url,
        'amount' => $amount_in_paisa,
        'purchase_order_id' => $purchase_order_id,
        'purchase_order_name' => $purchase_order_name,
        'customer_info' => $customer_data,
        'merchant_extra' => json_encode(['order_id' => $order_id])
    ];
    
    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url'] . 'epayment/initiate/');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . $config['secret_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for testing
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'CURL Error: ' . $error
        ];
    }
    
    if ($http_code == 200 || $http_code == 201) {
        $result = json_decode($response, true);
        if ($result && isset($result['pidx'])) {
            return [
                'success' => true,
                'pidx' => $result['pidx'],
                'payment_url' => $result['payment_url'],
                'expires_at' => $result['expires_at'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Invalid response from Khalti',
                'response' => $response
            ];
        }
    } else {
        return [
            'success' => false,
            'error' => 'HTTP Error: ' . $http_code . ' - ' . $response
        ];
    }
}

// Function to lookup/verify payment
function lookupKhaltiPayment($pidx) {
    $config = getKhaltiConfig();
    
    $payload = ['pidx' => $pidx];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url'] . 'epayment/lookup/');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . $config['secret_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return json_decode($response, true);
    } else {
        return ['status' => 'Error', 'http_code' => $http_code];
    }
}
?>