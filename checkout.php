<?php
// ============================================
// SOLEMATCH - Complete Checkout
// FIXED: Guest checkout with proper details
// ============================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
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

// ============================================
// HELPER FUNCTIONS
// ============================================
function escapeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function generateOrderNumber() {
    return 'STRD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function getCartCount() {
    if (isLoggedIn()) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    } else {
        if (isset($_SESSION['cart'])) {
            $count = 0;
            foreach ($_SESSION['cart'] as $item) {
                $count += $item['quantity'];
            }
            return $count;
        }
        return 0;
    }
}

// ============================================
// GET CART ITEMS
// ============================================
$cart_items = [];
$subtotal = 0;
$cart_count = 0;

if (isLoggedIn()) {
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

// If cart is empty, redirect to shop
if (empty($cart_items)) {
    header('Location: pages/shop.php');
    exit;
}

// Calculate totals
$shipping = ($subtotal > 10000) ? 0 : 500;
$total = $subtotal + $shipping;

// Get user details if logged in
$user = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// ============================================
// HANDLE ORDER PLACEMENT
// ============================================
$order_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal = trim($_POST['postal'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    $errors = [];
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    if (empty($phone)) $errors[] = 'Phone number is required';
    if (empty($address)) $errors[] = 'Address is required';
    if (empty($city)) $errors[] = 'City is required';
    
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            $order_number = generateOrderNumber();
            
            // Build shipping address with customer name
            $shipping_address = "$full_name, $address, $city";
            if ($postal) $shipping_address .= ", $postal";
            
            // Handle user_id for guest checkout
            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            
            if (!$user_id) {
                // Check if user exists with this email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existing_user = $stmt->fetch();
                
                if ($existing_user) {
                    $user_id = $existing_user['id'];
                } else {
                    // Create a proper user with actual details
                    $guest_password = password_hash('guest123', PASSWORD_DEFAULT);
                    $username = 'guest_' . time();
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, full_name, phone, role, status) 
                        VALUES (?, ?, ?, ?, ?, 'customer', 'active')
                    ");
                    $stmt->execute([
                        $username,
                        $email,
                        $guest_password,
                        $full_name,
                        $phone
                    ]);
                    $user_id = $pdo->lastInsertId();
                }
            }
            
            // Insert order
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, total_amount, 
                    shipping_address, shipping_city, shipping_postal,
                    payment_method, payment_status, order_status, notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())
            ");
            
            $stmt->execute([
                $order_number,
                $user_id,
                $total,
                $shipping_address,
                $city,
                $postal,
                $payment_method,
                $notes
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Insert order items
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, variant_id, quantity, price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $item['quantity'],
                    $item['price']
                ]);
            }
            
            // Clear cart
            if (isLoggedIn()) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } else {
                unset($_SESSION['cart']);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect based on payment method
            if ($payment_method === 'khalti') {
                // Khalti integration will go here
                header("Location: success.php?order_id=" . $order_id);
                exit;
            } else {
                // COD - redirect to success
                header("Location: pages/success.php?order_id=" . $order_id);
                exit;
            }
            
        } catch (PDOException $e) {
            // Rollback only if transaction is active
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $order_error = 'Failed to place order: ' . $e->getMessage();
        }
    } else {
        $order_error = implode('<br>', $errors);
    }
}

// ============================================
// GET CART COUNT FOR BADGE
// ============================================
$cart_count_display = getCartCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | SOLEMATCH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Space Grotesk', sans-serif; background: #0a0a0a; color: #e5e5e5; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0a0a0a; }
        ::-webkit-scrollbar-thumb { background: #d98c5f; border-radius: 10px; }

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
        .navbar.scrolled { background: rgba(10, 10, 10, 0.98); padding: 12px 48px; box-shadow: 0 4px 30px rgba(0,0,0,0.3); }
        .logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #d98c5f 80%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -1px;
        }
        .logo h1 i { color: #d98c5f; }
        .logo a { text-decoration: none; }

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
        .nav-links a:hover { color: #ffffff; }
        .nav-links a:hover::after { width: 100%; }
        .nav-links a.active { color: #ffffff; }
        .nav-links a.active::after { width: 100%; }

        .nav-icon { font-size: 1.2rem; transition: transform 0.3s ease; }
        .nav-icon:hover { transform: scale(1.1); }

        .cart-wrapper { position: relative; }
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
        .login-btn:hover { background: #ffffff; transform: scale(1.05); box-shadow: 0 8px 30px rgba(217,140,95,0.3); }

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
        .logout-btn:hover { border-color: #d98c5f; color: #ffffff; }

        .breadcrumb {
            padding: 20px 48px 0;
            font-size: 0.85rem;
            color: #6a6a6a;
        }
        .breadcrumb a { color: #d98c5f; transition: 0.3s; }
        .breadcrumb a:hover { color: #ffffff; }
        .breadcrumb span { margin: 0 8px; }

        .checkout-section {
            padding: 24px 48px 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .checkout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a2a;
        }
        .checkout-header h1 { font-size: 2.2rem; font-weight: 700; }
        .checkout-header h1 i { color: #d98c5f; margin-right: 12px; }
        .checkout-header .step { color: #8a8a8a; font-size: 0.95rem; }
        .checkout-header .step strong { color: #d98c5f; }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
        }

        .form-section {
            background: #121212;
            border-radius: 24px;
            border: 1px solid #2a2a2a;
            padding: 32px;
        }
        .form-section h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 24px;
            color: #ffffff;
        }
        .form-section h2 i { color: #d98c5f; margin-right: 10px; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #d98c5f;
        }
        .form-group label .required { color: #ef4444; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border-radius: 28px;
            border: 1px solid #2a2a2a;
            background: #1a1a1a;
            font-family: inherit;
            font-size: 0.95rem;
            color: #e5e5e5;
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #d98c5f;
            box-shadow: 0 0 0 2px rgba(217,140,95,0.2);
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
            border-radius: 16px;
        }
        .form-group .input-icon { position: relative; }
        .form-group .input-icon i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #6a6a6a;
        }
        .form-group .input-icon input { padding-left: 48px; }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 4px;
        }
        .payment-method {
            background: #1a1a1a;
            border: 2px solid #2a2a2a;
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method:hover { border-color: #d98c5f; }
        .payment-method.active {
            border-color: #d98c5f;
            background: rgba(217,140,95,0.1);
        }
        .payment-method input[type="radio"] { display: none; }
        .payment-method i {
            font-size: 2rem;
            color: #d98c5f;
            display: block;
            margin-bottom: 6px;
        }
        .payment-method .method-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #ffffff;
        }
        .payment-method .method-desc {
            font-size: 0.65rem;
            color: #8a8a8a;
        }

        .khalti-badge {
            display: inline-block;
            background: #5c2d91;
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            margin-left: 4px;
        }

        .khalti-info {
            background: rgba(92, 45, 145, 0.1);
            border: 1px solid #5c2d91;
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 8px;
            display: none;
        }
        .khalti-info.show { display: block; }
        .khalti-info p {
            color: #b0b0b0;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .khalti-info p i { color: #5c2d91; }

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
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #2a2a2a;
        }
        .order-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #2a2a2a;
        }
        .order-item:last-child { border-bottom: none; }
        .order-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 12px;
            background: #1a1a1a;
        }
        .order-item .item-info { flex: 1; }
        .order-item .item-info .name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #ffffff;
        }
        .order-item .item-info .variant {
            font-size: 0.7rem;
            color: #8a8a8a;
        }
        .order-item .item-info .qty-price {
            font-size: 0.8rem;
            color: #d98c5f;
            font-weight: 600;
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

        .place-order-btn {
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
        .place-order-btn:hover:not(:disabled) {
            background: #ffffff;
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(217,140,95,0.3);
        }
        .place-order-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .place-order-btn .loader { display: none; }
        .place-order-btn.loading .loader {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .back-to-cart {
            display: block;
            text-align: center;
            color: #8a8a8a;
            text-decoration: none;
            margin-top: 16px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .back-to-cart:hover { color: #d98c5f; }
        .back-to-cart i { margin-right: 8px; }

        .error-message {
            background: #3a1e1e;
            color: #f87171;
            padding: 14px 20px;
            border-radius: 16px;
            border: 1px solid #b91c1c;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .error-message i { font-size: 1.2rem; }

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
        .footer-col a:hover { color: #d98c5f; padding-left: 4px; }
        .footer-bottom {
            border-top: 1px solid #2a2a2a;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .footer-bottom p { color: #6a6a6a; font-size: 0.75rem; }
        .footer-social { display: flex; gap: 14px; }
        .footer-social a { color: #6a6a6a; font-size: 1.1rem; transition: all 0.3s ease; }
        .footer-social a:hover { color: #d98c5f; transform: translateY(-2px); }

        @media (max-width: 992px) {
            .checkout-grid { grid-template-columns: 1fr; }
            .order-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
            .payment-methods { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; }
            .nav-links a:not(.cart-wrapper):not(.login-btn):not(.logout-btn) { display: none; }
            .breadcrumb { padding: 16px 20px 0; }
            .checkout-section { padding: 16px 20px 40px; }
            .checkout-header h1 { font-size: 1.8rem; }
            .form-section { padding: 20px; }
            .payment-methods { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; gap: 24px; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar" id="navbar">
        <div class="logo">
            <a href="pages/index.php">
                <h1><i class="fas fa-shoe-prints"></i> SOLEMATCH</h1>
            </a>
        </div>
        <div class="nav-links">
            <a href="pages/index.php">Home</a>
            <a href="pages/shop.php">Shop</a>
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
            
            <a href="pages/cart.php" class="cart-wrapper">
                <i class="fas fa-bag-shopping nav-icon"></i>
                <?php if ($cart_count_display > 0): ?>
                    <span class="cart-badge" id="cartBadge"><?php echo $cart_count_display; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="pages/index.php"><i class="fas fa-home"></i> Home</a>
        <span>›</span>
        <a href="pages/shop.php">Shop</a>
        <span>›</span>
        <a href="pages/cart.php">Cart</a>
        <span>›</span>
        <span style="color: #b0b0b0;">Checkout</span>
    </div>

    <!-- CHECKOUT SECTION -->
    <div class="checkout-section">
        <div class="checkout-header">
            <h1><i class="fas fa-credit-card"></i> Checkout</h1>
            <span class="step">Step <strong>1</strong> of 1</span>
        </div>

        <?php if ($order_error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $order_error; ?></span>
            </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <!-- FORM SECTION -->
            <div class="form-section">
                <h2><i class="fas fa-user"></i> Shipping Information</h2>
                
                <form method="POST" id="checkoutForm" onsubmit="return validateForm()">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" name="full_name" 
                                       value="<?php echo escapeHtml($user['full_name'] ?? ''); ?>" 
                                       placeholder="John Doe" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" 
                                       value="<?php echo escapeHtml($user['email'] ?? ''); ?>" 
                                       placeholder="john@example.com" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="phone" 
                                       value="<?php echo escapeHtml($user['phone'] ?? ''); ?>"
                                       placeholder="98XXXXXXXX" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>City <span class="required">*</span></label>
                            <div class="input-icon">
                                <i class="fas fa-city"></i>
                                <input type="text" name="city" 
                                       placeholder="Kathmandu" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Shipping Address <span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" name="address" 
                                   placeholder="Street, building, apartment number" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Postal Code (Optional)</label>
                            <div class="input-icon">
                                <i class="fas fa-mailbox"></i>
                                <input type="text" name="postal" placeholder="44600">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Order Notes (Optional)</label>
                            <div class="input-icon">
                                <i class="fas fa-pen"></i>
                                <textarea name="notes" placeholder="Special delivery instructions..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- PAYMENT METHOD -->
                    <h2 style="margin-top: 16px;"><i class="fas fa-wallet"></i> Payment Method</h2>
                    
                    <div class="payment-methods">
                        <label class="payment-method active" onclick="toggleKhaltiInfo(false)">
                            <input type="radio" name="payment_method" value="cod" checked>
                            <i class="fas fa-money-bill-wave"></i>
                            <div class="method-name">Cash on Delivery</div>
                            <div class="method-desc">Pay when you receive</div>
                        </label>
                        
                        <label class="payment-method" onclick="toggleKhaltiInfo(true)">
                            <input type="radio" name="payment_method" value="khalti">
                            <i class="fas fa-mobile-alt"></i>
                            <div class="method-name">Khalti <span class="khalti-badge">Popular</span></div>
                            <div class="method-desc">Pay with Khalti wallet</div>
                        </label>
                        
                        <label class="payment-method" onclick="toggleKhaltiInfo(false)">
                            <input type="radio" name="payment_method" value="card">
                            <i class="fas fa-credit-card"></i>
                            <div class="method-name">Card Payment</div>
                            <div class="method-desc">Credit / Debit card</div>
                        </label>
                    </div>

                    <!-- Khalti Info -->
                    <div class="khalti-info" id="khaltiInfo">
                        <p>
                            <i class="fas fa-info-circle"></i>
                            You will be redirected to Khalti to complete your payment securely.
                        </p>
                        <p style="margin-top: 4px; font-size: 0.7rem; color: #6a6a6a;">
                            <i class="fas fa-lock"></i> 
                            Test credentials: Phone: 9800000000 | MPIN: 1111 | OTP: 987654
                        </p>
                    </div>

                    <input type="hidden" name="place_order" value="1">
                </form>
            </div>

            <!-- ORDER SUMMARY -->
            <div class="order-summary">
                <h3>Order Summary</h3>
                
                <div style="max-height: 300px; overflow-y: auto; margin-bottom: 16px;">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo $item['image_url'] ?? 'https://placehold.co/50x50/1a1a1a/d98c5f?text=Shoe'; ?>" 
                                 alt="<?php echo escapeHtml($item['name']); ?>"
                                 onerror="this.src='https://placehold.co/50x50/1a1a1a/d98c5f?text=Shoe'">
                            <div class="item-info">
                                <div class="name"><?php echo escapeHtml($item['name']); ?></div>
                                <?php if ($item['variant_name'] || $item['size']): ?>
                                    <div class="variant">
                                        <?php 
                                        $parts = [];
                                        if ($item['variant_name']) $parts[] = $item['variant_name'];
                                        if ($item['size']) $parts[] = 'Size: ' . $item['size'];
                                        echo implode(' | ', $parts);
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <div class="qty-price">
                                    <?php echo $item['quantity']; ?> × Rs. <?php echo number_format($item['price'], 0); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-row">
                    <span>Subtotal (<span id="itemCountSummary"><?php echo $cart_count; ?></span> items)</span>
                    <span class="amount">Rs. <?php echo number_format($subtotal, 0); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Shipping</span>
                    <span class="amount">
                        <?php if ($shipping == 0): ?>
                            <span class="free-shipping">FREE</span>
                        <?php else: ?>
                            Rs. <?php echo number_format($shipping, 0); ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="summary-row total">
                    <span>Total</span>
                    <span class="amount">Rs. <?php echo number_format($total, 0); ?></span>
                </div>
                
                <button type="submit" form="checkoutForm" class="place-order-btn" id="placeOrderBtn">
                    <span class="loader"><i class="fas fa-spinner"></i></span>
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
                
                <a href="pages/cart.php" class="back-to-cart">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <h3>SOLEMATCH</h3>
                <p>Premium footwear for those who never settle for less.</p>
            </div>
            <div class="footer-col">
                <h4>Shop</h4>
                <a href="pages/shop.php">All Products</a>
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

    <script>
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

        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        function toggleKhaltiInfo(show) {
            const info = document.getElementById('khaltiInfo');
            if (show) {
                info.classList.add('show');
            } else {
                info.classList.remove('show');
            }
        }

        function validateForm() {
            const btn = document.getElementById('placeOrderBtn');
            const form = document.getElementById('checkoutForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            
            btn.classList.add('loading');
            btn.innerHTML = '<span class="loader"><i class="fas fa-spinner"></i></span> Processing...';
            btn.disabled = true;
            
            return true;
        }

        console.log('📦 SOLEMATCH - Checkout');
        console.log('💰 Total: Rs. <?php echo number_format($total, 0); ?>');
        console.log('📦 Items: <?php echo $cart_count; ?>');
    </script>

</body>
</html>