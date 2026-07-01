<?php
// ============================================
// eSEWA SUCCESS CALLBACK
// ============================================

session_start();
require_once '../includes/db.php';
require_once '../includes/esewa_config.php';

// Get the encoded response from eSewa
$encoded_data = $_POST['data'] ?? null;

if (!$encoded_data) {
    header('Location: checkout.php?error=Invalid response from eSewa');
    exit;
}

// Decode the base64 response
$decoded_data = base64_decode($encoded_data);
$response = json_decode($decoded_data, true);

// Log the response
file_put_contents('esewa_log.txt', "Success Callback: " . print_r($response, true) . "\n\n", FILE_APPEND);

// Get order info from session
$order_id = $_SESSION['esewa_order_id'] ?? null;
$transaction_uuid = $_SESSION['esewa_transaction_uuid'] ?? null;
$total_amount = $_SESSION['esewa_total_amount'] ?? null;

if (!$order_id || !$transaction_uuid) {
    header('Location: checkout.php?error=Invalid session data');
    exit;
}

// Verify the signature
$config = getEsewaConfig();
$is_valid = verifyEsewaSignature($response, $response['signature'] ?? '', $config['secret_key']);

if (!$is_valid) {
    file_put_contents('esewa_log.txt', "Invalid signature!\n\n", FILE_APPEND);
    header('Location: checkout.php?error=Invalid signature');
    exit;
}

// Check if payment was successful
if ($response['status'] === 'COMPLETE') {
    try {
        // Update order status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                order_status = 'confirmed',
                transaction_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$response['transaction_code'] ?? null, $order_id]);
        
        // Clear session data
        unset($_SESSION['esewa_order_id']);
        unset($_SESSION['esewa_transaction_uuid']);
        unset($_SESSION['esewa_total_amount']);
        
        // Redirect to success page
        header("Location: success.php?order_id=" . $order_id . "&payment=esewa");
        exit;
        
    } catch (PDOException $e) {
        file_put_contents('esewa_log.txt', "Database Error: " . $e->getMessage() . "\n\n", FILE_APPEND);
        header('Location: checkout.php?error=Database error. Please contact support.');
        exit;
    }
} else {
    // Payment failed
    file_put_contents('esewa_log.txt', "Payment not complete. Status: " . ($response['status'] ?? 'Unknown') . "\n\n", FILE_APPEND);
    header('Location: checkout.php?error=Payment was not successful. Please try again.');
    exit;
}
?>