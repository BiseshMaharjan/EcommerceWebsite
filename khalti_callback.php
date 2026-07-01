<?php
// ============================================
// KHALTI PAYMENT CALLBACK HANDLER
// ============================================

session_start();
require_once 'includes/db.php';
require_once 'includes/khalti_config.php';

// Get callback parameters from Khalti
$pidx = $_GET['pidx'] ?? null;
$status = $_GET['status'] ?? null;
$transaction_id = $_GET['transaction_id'] ?? $_GET['tidx'] ?? null;
$amount = $_GET['amount'] ?? null;
$total_amount = $_GET['total_amount'] ?? null;
$mobile = $_GET['mobile'] ?? null;
$purchase_order_id = $_GET['purchase_order_id'] ?? null;

// Log the callback for debugging
$log_data = [
    'time' => date('Y-m-d H:i:s'),
    'pidx' => $pidx,
    'status' => $status,
    'transaction_id' => $transaction_id,
    'amount' => $amount,
    'mobile' => $mobile,
    'purchase_order_id' => $purchase_order_id,
    'get_params' => $_GET,
    'session' => $_SESSION
];
file_put_contents('khalti_log.txt', print_r($log_data, true) . "\n\n", FILE_APPEND);

// Check if we have the pidx
if (!$pidx) {
    header('Location: checkout.php?error=Invalid callback - No pidx');
    exit;
}

// Lookup payment status from Khalti for verification
$payment_data = lookupKhaltiPayment($pidx);

file_put_contents('khalti_log.txt', "Lookup Response: " . print_r($payment_data, true) . "\n\n", FILE_APPEND);

// Get order_id from session
$order_id = $_SESSION['khalti_order_id'] ?? null;

// If not in session, try to extract from purchase_order_id
if (!$order_id && $purchase_order_id) {
    $parts = explode('-', $purchase_order_id);
    if (isset($parts[1])) {
        $order_id = intval($parts[1]);
    }
}

// Check payment status
if ($payment_data && isset($payment_data['status'])) {
    if ($payment_data['status'] === 'Completed') {
        // Payment successful!
        if ($order_id) {
            try {
                // Update order status
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'paid', 
                        order_status = 'confirmed',
                        transaction_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $transaction_id ?? $payment_data['transaction_id'] ?? null,
                    $order_id
                ]);
                
                // Clear session data
                unset($_SESSION['khalti_pidx']);
                unset($_SESSION['khalti_order_id']);
                
                // Redirect to success page
                header("Location: pages/success.php?order_id=" . $order_id . "&payment=khalti");
                exit;
                
            } catch (PDOException $e) {
                file_put_contents('khalti_log.txt', "Database Error: " . $e->getMessage() . "\n\n", FILE_APPEND);
                header('Location: checkout.php?error=Database error. Please contact support.');
                exit;
            }
        } else {
            file_put_contents('khalti_log.txt', "Order ID not found\n\n", FILE_APPEND);
            header('Location: checkout.php?error=Order not found. Please contact support.');
            exit;
        }
    } elseif ($payment_data['status'] === 'Pending') {
        // Transaction is pending
        header('Location: checkout.php?error=Payment pending. Please check later.');
        exit;
    } else {
        // Payment failed, canceled, or expired
        $error_msg = $payment_data['status'] ?? 'Payment not completed';
        header('Location: checkout.php?error=Payment ' . $error_msg . '. Please try again.');
        exit;
    }
} else {
    file_put_contents('khalti_log.txt', "Invalid payment data\n\n", FILE_APPEND);
    header('Location: checkout.php?error=Invalid payment response. Please try again.');
    exit;
}
?>