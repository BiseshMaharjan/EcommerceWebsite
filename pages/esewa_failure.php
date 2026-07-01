<?php
// ============================================
// eSEWA FAILURE CALLBACK
// ============================================

session_start();

// Log the failure
$encoded_data = $_POST['data'] ?? null;
if ($encoded_data) {
    $decoded_data = base64_decode($encoded_data);
    file_put_contents('esewa_log.txt', "Failure Callback: " . $decoded_data . "\n\n", FILE_APPEND);
}

// Clear session data
unset($_SESSION['esewa_order_id']);
unset($_SESSION['esewa_transaction_uuid']);
unset($_SESSION['esewa_total_amount']);

// Redirect to checkout with error
header('Location: checkout.php?error=Payment was cancelled or failed. Please try again.');
exit;
?>