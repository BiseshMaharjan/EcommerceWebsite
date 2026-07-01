<?php
// ============================================
// SOLEMATCH - Complete Cart Page
// FIXED: No Login Required for Adding to Cart
// ============================================

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$db_host = 'localhost';
$db_name = 'sole_match';
$db_user = 'root';
$db_pass = '';


try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
function escapeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ============================================
// HANDLE AJAX REQUESTS (Add/Update/Remove)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $cart_id = $_POST['cart_id'] ?? null;
    $product_id = $_POST['product_id'] ?? null;
    $variant_id = $_POST['variant_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    // Handle based on login status
    if (isLoggedIn()) {
        // ===== DATABASE CART for Logged-in Users =====
        try {
            if ($action === 'add') {
                // Add to cart
                $stmt = $pdo->prepare("
                    SELECT id, quantity FROM cart 
                    WHERE user_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))
                ");
                $stmt->execute([$_SESSION['user_id'], $product_id, $variant_id, $variant_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $new_qty = $existing['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_qty, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO cart (user_id, product_id, variant_id, quantity) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $product_id, $variant_id, $quantity]);
                }
                $response = ['success' => true, 'action' => 'add'];
                
            } elseif ($action === 'increase' || $action === 'decrease') {
                // Get current quantity
                $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $_SESSION['user_id']]);
                $current = $stmt->fetch();
                
                if ($current) {
                    $new_qty = $action === 'increase' ? $current['quantity'] + 1 : $current['quantity'] - 1;
                    if ($new_qty > 0) {
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_qty, $cart_id]);
                        $response = ['success' => true, 'new_quantity' => $new_qty];
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
                        $stmt->execute([$cart_id]);
                        $response = ['success' => true, 'removed' => true];
                    }
                }
            } elseif ($action === 'remove') {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $_SESSION['user_id']]);
                $response = ['success' => true, 'removed' => true];
            }
            
            // Get updated cart count
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            $response['cart_count'] = $result['total'] ?? 0;
            
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error'];
        }
        
    } else {
        // ===== SESSION CART for Guest Users =====
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if ($action === 'add') {
            // Add to session cart
            $cart_key = $product_id . '_' . ($variant_id ?? '0');
            
            if (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
            } else {
                // Get product details from database
                try {
                    $stmt = $pdo->prepare("SELECT name, price, image_url FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $cart_item = [
                            'product_id' => $product_id,
                            'variant_id' => $variant_id,
                            'quantity' => $quantity,
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'image_url' => $product['image_url'],
                            'variant_name' => null,
                            'size' => null,
                            'color' => null
                        ];
                        
                        // Get variant details if exists
                        if ($variant_id) {
                            $stmt = $pdo->prepare("SELECT variant_name, size, color FROM product_variants WHERE id = ?");
                            $stmt->execute([$variant_id]);
                            $variant = $stmt->fetch();
                            if ($variant) {
                                $cart_item['variant_name'] = $variant['variant_name'];
                                $cart_item['size'] = $variant['size'];
                                $cart_item['color'] = $variant['color'];
                            }
                        }
                        
                        $_SESSION['cart'][$cart_key] = $cart_item;
                    }
                } catch (PDOException $e) {
                    // Handle error
                }
            }
            $response = ['success' => true, 'action' => 'add'];
            
        } elseif ($action === 'increase' || $action === 'decrease') {
            // Update session cart quantity
            if (isset($_SESSION['cart'][$cart_id])) {
                if ($action === 'increase') {
                    $_SESSION['cart'][$cart_id]['quantity']++;
                } else {
                    $_SESSION['cart'][$cart_id]['quantity']--;
                    if ($_SESSION['cart'][$cart_id]['quantity'] <= 0) {
                        unset($_SESSION['cart'][$cart_id]);
                        $response = ['success' => true, 'removed' => true];
                    }
                }
                if (!isset($response['removed'])) {
                    $response = ['success' => true, 'new_quantity' => $_SESSION['cart'][$cart_id]['quantity']];
                }
            }
        } elseif ($action === 'remove') {
            if (isset($_SESSION['cart'][$cart_id])) {
                unset($_SESSION['cart'][$cart_id]);
                $response = ['success' => true, 'removed' => true];
            }
        }
        
        // Get updated cart count
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        $response['cart_count'] = $count;
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// GET CART ITEMS
// ============================================
$cart_items = [];
$subtotal = 0;
$cart_count = 0;

if (isLoggedIn()) {
    // Get cart from database
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.image_url,
                   pv.variant_name, pv.size, pv.color
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN product_variants pv ON c.variant_id = pv.id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_items = $stmt->fetchAll();
        
        foreach ($cart_items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
            $cart_count += $item['quantity'];
        }
    } catch (PDOException $e) {
        // Handle error
    }
} else {
    // Get cart from session
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $key => $item) {
            $cart_items[] = [
                'id' => $key,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'image_url' => $item['image_url'] ?? null,
                'variant_name' => $item['variant_name'] ?? null,
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
            ];
            $subtotal += $item['price'] * $item['quantity'];
            $cart_count += $item['quantity'];
        }
    }
}

// Calculate totals
$shipping = ($subtotal > 10000) ? 0 : 500;
$total = $subtotal + $shipping;
$cart_count_display = $cart_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | SOLEMATCH</title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ========== GLOBAL RESET & BASE ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #0a0a0a;
            color: #e5e5e5;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ========== SCROLLBAR ========== */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0a0a;
        }
        ::-webkit-scrollbar-thumb {
            background: #d98c5f;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #f0a87a;
        }

        /* ========== NAVBAR ========== */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 48px;
            background: rgba(10, 10, 10, 0.92);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(42, 42, 42, 0.5);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            flex-wrap: wrap;
            gap: 12px;
        }

        .navbar.scrolled {
            background: rgba(10, 10, 10, 0.98);
            padding: 12px 48px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #d98c5f 80%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -1px;
        }

        .logo h1 i {
            color: #d98c5f;
        }

        .logo a {
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            font-weight: 500;
            color: #b0b0b0;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #d98c5f;
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: #ffffff;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a.active {
            color: #ffffff;
        }

        .nav-links a.active::after {
            width: 100%;
        }

        .nav-icon {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .nav-icon:hover {
            transform: scale(1.1);
        }

        .cart-wrapper {
            position: relative;
        }

        .cart-badge {
            background: #d98c5f;
            color: #0a0a0a;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.6rem;
            font-weight: 700;
            position: absolute;
            top: -8px;
            right: -12px;
            min-width: 18px;
            text-align: center;
            animation: pulse-badge 2s infinite;
        }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .login-btn {
            background: #d98c5f;
            color: #0a0a0a;
            padding: 8px 24px;
            border-radius: 60px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .login-btn:hover {
            background: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(217, 140, 95, 0.3);
        }

        .logout-btn {
            background: transparent;
            border: 1px solid #444;
            color: #b0b0b0;
            padding: 6px 18px;
            border-radius: 60px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            border-color: #d98c5f;
            color: #ffffff;
        }

        /* ========== BREADCRUMB ========== */
        .breadcrumb {
            padding: 20px 48px 0;
            font-size: 0.85rem;
            color: #6a6a6a;
        }

        .breadcrumb a {
            color: #d98c5f;
            transition: 0.3s;
        }

        .breadcrumb a:hover {
            color: #ffffff;
        }

        .breadcrumb span {
            margin: 0 8px;
        }

        /* ========== CART SECTION ========== */
        .cart-section {
            padding: 24px 48px 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a2a;
        }

        .cart-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
        }

        .cart-header h1 i {
            color: #d98c5f;
            margin-right: 12px;
        }

        .cart-header .cart-count {
            color: #8a8a8a;
            font-size: 0.95rem;
        }

        .cart-header .cart-count strong {
            color: #ffffff;
        }

        /* ========== CART GRID ========== */
        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
        }

        /* Cart Items */
        .cart-items {
            background: #121212;
            border-radius: 24px;
            border: 1px solid #2a2a2a;
            padding: 20px;
        }

        .cart-item {
            display: flex;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid #2a2a2a;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item.removing {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.3s ease;
        }

        .cart-item-image {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 16px;
            background: #1a1a1a;
            flex-shrink: 0;
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-info .name {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .cart-item-info .variant {
            font-size: 0.8rem;
            color: #8a8a8a;
        }

        .cart-item-info .variant i {
            margin-right: 4px;
        }

        .cart-item-info .price {
            font-size: 1.1rem;
            font-weight: 700;
            color: #d98c5f;
            margin-top: 6px;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #1a1a1a;
            border-radius: 40px;
            padding: 4px 8px;
            border: 1px solid #2a2a2a;
        }

        .qty-btn {
            background: none;
            border: none;
            color: #e5e5e5;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 6px 10px;
            transition: all 0.2s ease;
            font-family: inherit;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: #d98c5f;
            color: #0a0a0a;
        }

        .qty-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .qty-num {
            font-weight: 600;
            min-width: 28px;
            text-align: center;
            font-size: 0.95rem;
        }

        .remove-btn {
            background: none;
            border: none;
            color: #6a6a6a;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-btn:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .item-total {
            font-weight: 600;
            color: #ffffff;
            min-width: 80px;
            text-align: right;
        }

        /* ========== EMPTY CART ========== */
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-cart i {
            font-size: 4rem;
            color: #2a2a2a;
            margin-bottom: 20px;
        }

        .empty-cart h3 {
            font-size: 1.8rem;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .empty-cart p {
            color: #8a8a8a;
            margin-bottom: 32px;
        }

        .empty-cart .btn-shop {
            background: #d98c5f;
            color: #0a0a0a;
            padding: 14px 36px;
            border-radius: 60px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }

        .empty-cart .btn-shop:hover {
            background: #ffffff;
            transform: scale(1.02);
        }

        /* ========== ORDER SUMMARY ========== */
        .order-summary {
            background: #121212;
            border-radius: 24px;
            border: 1px solid #2a2a2a;
            padding: 28px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .order-summary h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #2a2a2a;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            color: #b0b0b0;
            font-size: 0.95rem;
        }

        .summary-row .amount {
            font-weight: 600;
            color: #ffffff;
        }

        .summary-row.total {
            border-top: 2px solid #2a2a2a;
            padding-top: 20px;
            margin-top: 8px;
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff;
        }

        .summary-row.total .amount {
            color: #d98c5f;
            font-size: 1.4rem;
        }

        .summary-row .free-shipping {
            color: #4ade80;
            font-weight: 600;
        }

        .checkout-btn {
            width: 100%;
            background: #d98c5f;
            color: #0a0a0a;
            border: none;
            padding: 16px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .checkout-btn:hover:not(:disabled) {
            background: #ffffff;
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(217, 140, 95, 0.3);
        }

        .checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .continue-shopping {
            display: block;
            text-align: center;
            color: #8a8a8a;
            text-decoration: none;
            margin-top: 16px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .continue-shopping:hover {
            color: #d98c5f;
        }

        .continue-shopping i {
            margin-right: 8px;
        }

        /* ========== CART NOTIFICATION ========== */
        .cart-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #121212;
            border: 1px solid #d98c5f;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.5s ease;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 280px;
        }

        .cart-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .cart-notification i {
            font-size: 1.5rem;
            color: #4ade80;
        }

        .cart-notification .message {
            color: #ffffff;
            font-weight: 500;
        }

        .cart-notification .message small {
            display: block;
            color: #8a8a8a;
            font-weight: 400;
            font-size: 0.8rem;
        }

        /* ========== FOOTER ========== */
        footer {
            border-top: 1px solid #2a2a2a;
            padding: 40px 48px 28px;
            margin-top: 20px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 28px;
        }

        .footer-brand h3 {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #d98c5f 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            margin-bottom: 10px;
        }

        .footer-brand p {
            color: #8a8a8a;
            line-height: 1.6;
            max-width: 280px;
            font-size: 0.9rem;
        }

        .footer-col h4 {
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 14px;
            font-size: 0.95rem;
        }

        .footer-col a {
            display: block;
            color: #8a8a8a;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .footer-col a:hover {
            color: #d98c5f;
            padding-left: 4px;
        }

        .footer-bottom {
            border-top: 1px solid #2a2a2a;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .footer-bottom p {
            color: #6a6a6a;
            font-size: 0.75rem;
        }

        .footer-social {
            display: flex;
            gap: 14px;
        }

        .footer-social a {
            color: #6a6a6a;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .footer-social a:hover {
            color: #d98c5f;
            transform: translateY(-2px);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .cart-grid {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 12px 20px;
            }

            .nav-links a:not(.cart-wrapper):not(.login-btn):not(.logout-btn) {
                display: none;
            }

            .breadcrumb {
                padding: 16px 20px 0;
            }

            .cart-section {
                padding: 16px 20px 40px;
            }

            .cart-header h1 {
                font-size: 1.8rem;
            }

            .cart-item {
                flex-wrap: wrap;
                gap: 12px;
            }

            .cart-item-image {
                width: 70px;
                height: 70px;
            }

            .cart-item-actions {
                width: 100%;
                justify-content: space-between;
                padding-top: 8px;
                border-top: 1px solid #2a2a2a;
            }

            .item-total {
                text-align: left;
                min-width: auto;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .cart-item-info .name {
                font-size: 0.9rem;
            }

            .qty-control {
                gap: 4px;
            }

            .qty-btn {
                width: 28px;
                height: 28px;
                font-size: 0.9rem;
            }

            .qty-num {
                min-width: 24px;
                font-size: 0.85rem;
            }

            .remove-btn {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================ -->
    <!-- NAVBAR -->
    <!-- ============================================ -->
    <nav class="navbar" id="navbar">
        <div class="logo">
            <a href="index.php">
                <h1><i class="fas fa-shoe-prints"></i> SOLEMATCH</h1>
            </a>
        </div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="shop.php">Shop</a>
            <a href="#">New</a>
            <a href="#">Community</a>
            
            <?php if (isLoggedIn()): ?>
                <a href="#" style="color: #d98c5f; font-size: 0.85rem;">
                    <i class="fas fa-user"></i> <?php echo escapeHtml($_SESSION['full_name'] ?? 'User'); ?>
                </a>
                <a href="auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="auth/login.php">
                    <button class="login-btn"><i class="fas fa-user"></i> Login</button>
                </a>
            <?php endif; ?>
            
            <a href="cart.php" class="cart-wrapper">
                <i class="fas fa-bag-shopping nav-icon"></i>
                <?php if ($cart_count_display > 0): ?>
                    <span class="cart-badge" id="cartBadge"><?php echo $cart_count_display; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <!-- ============================================ -->
    <!-- BREADCRUMB -->
    <!-- ============================================ -->
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <span>›</span>
        <a href="shop.php">Shop</a>
        <span>›</span>
        <span style="color: #b0b0b0;">Your Cart</span>
    </div>

    <!-- ============================================ -->
    <!-- CART SECTION -->
    <!-- ============================================ -->
    <div class="cart-section">
        <div class="cart-header">
            <h1><i class="fas fa-shopping-bag"></i> Your Cart</h1>
            <span class="cart-count">
                <strong id="cartItemCount"><?php echo $cart_count; ?></strong> 
                <?php echo $cart_count === 1 ? 'item' : 'items'; ?>
            </span>
        </div>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <i class="fas fa-shopping-bag"></i>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items yet. Start shopping now!</p>
                <a href="shop.php" class="btn-shop">
                    <i class="fas fa-arrow-left"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Grid -->
            <div class="cart-grid">
                <!-- Cart Items -->
                <div class="cart-items" id="cartItems">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-id="<?php echo $item['id']; ?>" data-product-id="<?php echo $item['product_id']; ?>">
                            <img class="cart-item-image" 
                                 src="<?php echo $item['image_url'] ?? 'https://placehold.co/90x90/1a1a1a/d98c5f?text=Shoe'; ?>" 
                                 alt="<?php echo escapeHtml($item['name']); ?>"
                                 onerror="this.src='https://placehold.co/90x90/1a1a1a/d98c5f?text=Shoe'">
                            
                            <div class="cart-item-info">
                                <div class="name"><?php echo escapeHtml($item['name']); ?></div>
                                <?php if ($item['variant_name'] || $item['size'] || $item['color']): ?>
                                    <div class="variant">
                                        <i class="fas fa-tag"></i>
                                        <?php 
                                        $variant_parts = [];
                                        if ($item['variant_name']) $variant_parts[] = $item['variant_name'];
                                        if ($item['size']) $variant_parts[] = 'Size: ' . $item['size'];
                                        if ($item['color']) $variant_parts[] = $item['color'];
                                        echo implode(' | ', $variant_parts);
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <div class="price">Rs. <?php echo number_format($item['price'], 0); ?></div>
                            </div>
                            
                            <div class="cart-item-actions">
                                <div class="qty-control">
                                    <button class="qty-btn qty-decrease" data-id="<?php echo $item['id']; ?>">−</button>
                                    <span class="qty-num" id="qty-<?php echo $item['id']; ?>"><?php echo $item['quantity']; ?></span>
                                    <button class="qty-btn qty-increase" data-id="<?php echo $item['id']; ?>">+</button>
                                </div>
                                <button class="remove-btn" data-id="<?php echo $item['id']; ?>" title="Remove">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <div class="item-total">
                                    Rs. <?php echo number_format($item['price'] * $item['quantity'], 0); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Order Summary -->
                <div class="order-summary" id="orderSummary">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal (<span id="itemCountSummary"><?php echo $cart_count; ?></span> items)</span>
                        <span class="amount" id="subtotalDisplay">Rs. <?php echo number_format($subtotal, 0); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span class="amount" id="shippingDisplay">
                            <?php if ($shipping == 0): ?>
                                <span class="free-shipping">FREE</span>
                            <?php else: ?>
                                Rs. <?php echo number_format($shipping, 0); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span class="amount" id="totalDisplay">Rs. <?php echo number_format($total, 0); ?></span>
                    </div>
                    
                    <button class="checkout-btn" id="checkoutBtn" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>
                        <i class="fas fa-lock"></i> Proceed to Checkout
                    </button>
                    
                    <a href="shop.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- CART NOTIFICATION -->
    <!-- ============================================ -->
    <div id="cartNotification" class="cart-notification">
        <i class="fas fa-check-circle"></i>
        <div class="message">
            Cart updated!
            <small>Item quantity has been updated</small>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FOOTER -->
    <!-- ============================================ -->
    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <h3>SOLEMATCH</h3>
                <p>Premium footwear for those who never settle for less. Engineered for comfort, designed for style.</p>
            </div>
            <div class="footer-col">
                <h4>Shop</h4>
                <a href="shop.php">All Products</a>
                <a href="#">New Arrivals</a>
                <a href="#">Best Sellers</a>
                <a href="#">Sale</a>
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <a href="#">Help Center</a>
                <a href="#">Returns</a>
                <a href="#">Shipping</a>
                <a href="#">Contact Us</a>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 SOLEMATCH. All rights reserved.</p>
            <div class="footer-social">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </footer>

    <!-- ============================================ -->
    <!-- JAVASCRIPT -->
    <!-- ============================================ -->
    <script>
        // =============================================
        // NAVBAR SCROLL EFFECT
        // =============================================
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (navbar) {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }
        });

        // =============================================
        // UPDATE CART (AJAX)
        // =============================================
        function updateCart(cartId, action, quantity = null) {
            const formData = new FormData();
            formData.append('cart_id', cartId);
            formData.append('action', action);
            if (quantity) {
                formData.append('quantity', quantity);
            }

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart badge
                    const badge = document.getElementById('cartBadge');
                    if (badge) {
                        if (data.cart_count > 0) {
                            badge.textContent = data.cart_count;
                        } else {
                            badge.remove();
                        }
                    }

                    // If item was removed, reload page
                    if (data.removed) {
                        location.reload();
                        return;
                    }

                    // Update quantity display
                    if (data.new_quantity !== undefined) {
                        const qtySpan = document.getElementById('qty-' + cartId);
                        if (qtySpan) {
                            qtySpan.textContent = data.new_quantity;
                        }
                        
                        // Recalculate totals
                        calculateTotals();
                        
                        // Show notification
                        showNotification('Cart updated!', 'Item quantity updated');
                    }
                } else {
                    alert(data.message || 'Error updating cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating cart. Please try again.');
            });
        }

        // =============================================
        // CALCULATE TOTALS
        // =============================================
        function calculateTotals() {
            const items = document.querySelectorAll('.cart-item');
            let subtotal = 0;
            let totalItems = 0;

            items.forEach(item => {
                const qtySpan = item.querySelector('.qty-num');
                const priceText = item.querySelector('.price')?.textContent || '0';
                const price = parseFloat(priceText.replace(/[^0-9]/g, ''));
                const qty = parseInt(qtySpan?.textContent || 0);
                
                // Update item total
                const totalSpan = item.querySelector('.item-total');
                if (totalSpan && !isNaN(price) && !isNaN(qty)) {
                    totalSpan.textContent = 'Rs. ' + (price * qty).toLocaleString();
                }
                
                subtotal += price * qty;
                totalItems += qty;
            });

            const shipping = subtotal > 10000 ? 0 : 500;
            const total = subtotal + shipping;

            // Update displays
            document.getElementById('subtotalDisplay').textContent = 'Rs. ' + subtotal.toLocaleString();
            document.getElementById('itemCountSummary').textContent = totalItems;
            document.getElementById('cartItemCount').textContent = totalItems;
            
            const shippingDisplay = document.getElementById('shippingDisplay');
            if (shipping === 0) {
                shippingDisplay.innerHTML = '<span class="free-shipping">FREE</span>';
            } else {
                shippingDisplay.textContent = 'Rs. ' + shipping.toLocaleString();
            }
            
            document.getElementById('totalDisplay').textContent = 'Rs. ' + total.toLocaleString();

            // Update checkout button
            const checkoutBtn = document.getElementById('checkoutBtn');
            if (checkoutBtn) {
                if (totalItems === 0) {
                    checkoutBtn.disabled = true;
                } else {
                    checkoutBtn.disabled = false;
                }
            }
        }

        // =============================================
        // SHOW NOTIFICATION
        // =============================================
        let notificationTimeout;

        function showNotification(title, message) {
            const notification = document.getElementById('cartNotification');
            if (!notification) return;
            
            const titleEl = notification.querySelector('.message');
            const messageEl = titleEl?.querySelector('small');
            
            if (titleEl) {
                // Clear existing text nodes
                while (titleEl.firstChild) {
                    titleEl.removeChild(titleEl.firstChild);
                }
                titleEl.appendChild(document.createTextNode(title));
                if (messageEl) {
                    titleEl.appendChild(messageEl);
                    messageEl.textContent = message;
                }
            }
            
            notification.classList.add('show');
            
            clearTimeout(notificationTimeout);
            notificationTimeout = setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // =============================================
        // ADD TO CART FROM ANYWHERE
        // =============================================
        function addToCart(productId, variantId = null, quantity = 1) {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', productId);
            formData.append('variant_id', variantId || '');
            formData.append('quantity', quantity);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update badge
                    const badge = document.getElementById('cartBadge');
                    if (badge) {
                        badge.textContent = data.cart_count;
                    } else {
                        // Create badge if it doesn't exist
                        const cartWrapper = document.querySelector('.cart-wrapper');
                        if (cartWrapper) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'cart-badge';
                            newBadge.id = 'cartBadge';
                            newBadge.textContent = data.cart_count;
                            cartWrapper.appendChild(newBadge);
                        }
                    }
                    
                    showNotification('Added to cart!', 'Item has been added successfully');
                    
                    // Update button feedback if on product page
                    const addBtn = document.getElementById('addToCartBtn');
                    if (addBtn) {
                        addBtn.innerHTML = '<i class="fas fa-check"></i> Added!';
                        addBtn.classList.add('added');
                        setTimeout(() => {
                            addBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                            addBtn.classList.remove('added');
                        }, 2000);
                    }
                } else {
                    alert(data.message || 'Error adding to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding to cart. Please try again.');
            });
        }

        // =============================================
        // EVENT LISTENERS
        // =============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Increase quantity
            document.querySelectorAll('.qty-increase').forEach(btn => {
                btn.addEventListener('click', function() {
                    const cartId = this.getAttribute('data-id');
                    updateCart(cartId, 'increase');
                });
            });

            // Decrease quantity
            document.querySelectorAll('.qty-decrease').forEach(btn => {
                btn.addEventListener('click', function() {
                    const cartId = this.getAttribute('data-id');
                    const currentQty = parseInt(document.getElementById('qty-' + cartId).textContent);
                    if (currentQty > 1) {
                        updateCart(cartId, 'decrease');
                    } else {
                        if (confirm('Remove this item from cart?')) {
                            updateCart(cartId, 'remove');
                        }
                    }
                });
            });

            // Remove item
            document.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Remove this item from your cart?')) {
                        const cartId = this.getAttribute('data-id');
                        updateCart(cartId, 'remove');
                    }
                });
            });

            // Checkout button
            const checkoutBtn = document.getElementById('checkoutBtn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    if (!this.disabled) {
                        window.location.href = 'checkout.php';
                    }
                });
            }

            // Add to cart buttons on product cards (for quick add)
            document.querySelectorAll('.quick-add').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const productId = this.getAttribute('data-product-id') || this.closest('.product-card')?.dataset?.productId;
                    if (productId) {
                        addToCart(productId);
                    }
                });
            });
        });

        console.log('🛒 SOLEMATCH - Shopping Cart');
        console.log('📦 ' + <?php echo $cart_count; ?> + ' items in cart');
        console.log('💡 No login required to add items to cart!');
        console.log('🔄 Cart is saved in session or database');
        console.log('✨ Use addToCart(productId) to add items programmatically');
    </script>

</body>
</html>