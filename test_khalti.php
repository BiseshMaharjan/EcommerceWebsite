<?php
// ============================================
// KHALTI API KEY TESTER
// ============================================

require_once 'includes/khalti_config.php';

echo "<h2>🔐 Khalti API Test</h2>";

$config = getKhaltiConfig();

echo "<pre>";
echo "Environment: " . ($config['is_sandbox'] ? '🟢 Sandbox' : '🔴 Production') . "\n";
echo "API URL: " . $config['url'] . "\n";
echo "Secret Key: " . substr($config['secret_key'], 0, 10) . "..." . substr($config['secret_key'], -5) . "\n\n";

// Test payload
$payload = [
    'return_url' => 'http://localhost/ecom-1day/test_callback.php',
    'website_url' => 'http://localhost/ecom-1day/',
    'amount' => 1000, // Rs. 10 in paisa
    'purchase_order_id' => 'TEST-' . time(),
    'purchase_order_name' => 'Test Payment',
    'customer_info' => [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '9800000000'
    ]
];

echo "📦 Test Payload:\n";
print_r($payload);
echo "\n";

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
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "📊 HTTP Status Code: " . $http_code . "\n\n";

if ($http_code == 200 || $http_code == 201) {
    echo "✅ SUCCESS! Your API key is working!\n\n";
    $result = json_decode($response, true);
    echo "📝 Response:\n";
    print_r($result);
    echo "\n";
    if (isset($result['payment_url'])) {
        echo "🔗 Payment URL: " . $result['payment_url'] . "\n";
    }
    echo "\n🎉 You can now use Khalti payment in your checkout!";
} else {
    echo "❌ ERROR! Your API key is not working.\n\n";
    echo "Error: " . $error . "\n";
    echo "Response: " . $response . "\n\n";
    echo "🔍 Possible issues:\n";
    echo "1. Invalid secret key\n";
    echo "2. Secret key doesn't have 'test_secret_key_' prefix\n";
    echo "3. You're using production URL with test key\n";
    echo "4. The secret key is from wrong environment\n";
    echo "\n💡 Solution:\n";
    echo "1. Go to https://test-admin.khalti.com\n";
    echo "2. Login to your merchant account\n";
    echo "3. Go to Dashboard → API Keys\n";
    echo "4. Copy your test_secret_key\n";
    echo "5. Update it in includes/khalti_config.php\n";
}
echo "</pre>";
?>