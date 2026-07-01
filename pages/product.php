<?php
// ============================================
// SOLEMATCH - Complete Product Detail Page
// FIXED: Add to Cart works without login
// All-in-One File: PHP + HTML + CSS + JS
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

// Check if status column exists
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
    $has_status = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $has_status = false;
}

// ============================================
// GET PRODUCT DETAILS
// ============================================
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header('Location: shop.php');
    exit;
}

// Get product details
$sql = "
    SELECT p.*, b.name as brand_name, b.id as brand_id, c.name as category_name 
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
";

if ($has_status) {
    $sql .= " AND p.status = 'active'";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: shop.php');
    exit;
}

// Get product variants (sizes/colors)
$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();

// Get related products
$sql = "
    SELECT p.*, b.name as brand_name 
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.category_id = ? AND p.id != ?
";

if ($has_status) {
    $sql .= " AND p.status = 'active'";
}

$sql .= " LIMIT 4";

$stmt = $pdo->prepare($sql);
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();

// ============================================
// GET CART COUNT
// ============================================
$cart_count = 0;
if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $cart_count = $result['total'] ?? 0;
    } catch (PDOException $e) {
        $cart_count = 0;
    }
} else {
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += $item['quantity'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($product['name']); ?> | SOLEMATCH</title>
    
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

        /* ========== PRODUCT DETAIL ========== */
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            padding: 32px 48px 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Gallery */
        .product-gallery {
            background: #121212;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            position: sticky;
            top: 100px;
        }

        .main-image-wrapper {
            position: relative;
            overflow: hidden;
            background: #1a1a1a;
            height: 500px;
        }

        .main-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.6s ease;
            padding: 20px;
        }

        .main-image-wrapper:hover img {
            transform: scale(1.05);
        }

        .thumbnail-row {
            display: flex;
            gap: 12px;
            padding: 16px;
            background: #0f0f0f;
            border-top: 1px solid #2a2a2a;
            overflow-x: auto;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 16px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex-shrink: 0;
            background: #1a1a1a;
        }

        .thumbnail.active {
            border-color: #d98c5f;
        }

        .thumbnail:hover {
            border-color: #d98c5f;
            transform: scale(1.05);
        }

        /* Product Info */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .product-brand {
            font-size: 0.75rem;
            letter-spacing: 2px;
            font-weight: 600;
            color: #d98c5f;
            text-transform: uppercase;
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1.2;
            color: #ffffff;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-rating .stars {
            color: #facc15;
            font-size: 1.1rem;
        }

        .product-rating .review-count {
            color: #8a8a8a;
            font-size: 0.85rem;
        }

        .product-description {
            color: #b0b0b0;
            line-height: 1.8;
            font-size: 1rem;
            border-left: 3px solid #d98c5f;
            padding-left: 20px;
            margin: 4px 0 8px;
        }

        .product-price {
            font-size: 2.2rem;
            font-weight: 700;
            color: #d98c5f;
        }

        .product-price .original-price {
            font-size: 1.2rem;
            color: #6a6a6a;
            text-decoration: line-through;
            font-weight: 400;
            margin-left: 12px;
        }

        .product-stock {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 60px;
            font-size: 0.8rem;
            font-weight: 600;
            width: fit-content;
        }

        .product-stock.in-stock {
            background: #1e3a2f;
            color: #4ade80;
            border: 1px solid #2e7d5e;
        }

        .product-stock.out-of-stock {
            background: #3a1e1e;
            color: #f87171;
            border: 1px solid #b91c1c;
        }

        .product-stock.low-stock {
            background: #3a3a1e;
            color: #facc15;
            border: 1px solid #7d7e2e;
        }

        /* Variants */
        .variant-section {
            background: #141414;
            padding: 20px;
            border-radius: 20px;
            border: 1px solid #2e2e2e;
        }

        .variant-title {
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 0.9rem;
            color: #ddd;
        }

        .variant-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .variant-btn {
            background: #1e1e1e;
            border: 2px solid #3a3a3a;
            padding: 10px 20px;
            border-radius: 40px;
            color: #eee;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            font-size: 0.85rem;
        }

        .variant-btn:hover {
            border-color: #d98c5f;
            color: #ffffff;
        }

        .variant-btn.active {
            background: #d98c5f;
            color: #0a0a0a;
            border-color: #d98c5f;
        }

        .variant-btn .variant-stock {
            font-size: 0.65rem;
            color: #6a6a6a;
            margin-left: 6px;
        }

        .variant-btn.active .variant-stock {
            color: #0a0a0a;
        }

        /* Quantity */
        .quantity-section {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #141414;
            padding: 8px 16px;
            border-radius: 60px;
            border: 1px solid #2e2e2e;
        }

        .quantity-btn {
            background: #2a2a2a;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            color: white;
            transition: all 0.2s ease;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #d98c5f;
            color: #0a0a0a;
        }

        .quantity-value {
            font-size: 1.3rem;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .btn-add-to-cart {
            background: #d98c5f;
            border: none;
            padding: 16px 40px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            font-family: inherit;
            color: #0a0a0a;
            flex: 1;
            justify-content: center;
            min-width: 200px;
        }

        .btn-add-to-cart:hover:not(:disabled) {
            background: #ffffff;
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(217, 140, 95, 0.3);
        }

        .btn-add-to-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-add-to-cart.added {
            background: #4ade80;
        }

        .btn-add-to-cart.loading {
            background: #666;
            cursor: wait;
        }

        .btn-wishlist {
            background: transparent;
            border: 2px solid #3a3a3a;
            padding: 14px 24px;
            border-radius: 60px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            color: #b0b0b0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-wishlist:hover {
            border-color: #d98c5f;
            color: #d98c5f;
        }

        /* Product Features */
        .product-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 8px;
        }

        .feature-item {
            background: #141414;
            padding: 16px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid #2a2a2a;
        }

        .feature-item i {
            font-size: 1.5rem;
            color: #d98c5f;
            margin-bottom: 6px;
        }

        .feature-item .label {
            font-size: 0.75rem;
            color: #8a8a8a;
        }

        .feature-item .value {
            font-weight: 600;
            color: #ffffff;
            font-size: 0.85rem;
        }

        /* ========== CART NOTIFICATION ========== */
        .cart-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #121212;
            border: 1px solid #4ade80;
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

        /* ========== RELATED PRODUCTS ========== */
        .related-section {
            padding: 0 48px 60px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .related-section h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .related-section h3 i {
            color: #d98c5f;
            margin-right: 12px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
        }

        .related-card {
            background: #121212;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            transition: all 0.4s ease;
            cursor: pointer;
        }

        .related-card:hover {
            transform: translateY(-6px);
            border-color: #d98c5f;
            box-shadow: 0 12px 40px rgba(217, 140, 95, 0.1);
        }

        .related-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #1a1a1a;
        }

        .related-card .info {
            padding: 16px 18px 20px;
        }

        .related-card .info .brand {
            font-size: 0.65rem;
            letter-spacing: 1px;
            font-weight: 600;
            color: #d98c5f;
            text-transform: uppercase;
        }

        .related-card .info .name {
            font-weight: 600;
            color: #ffffff;
            margin: 4px 0 8px;
        }

        .related-card .info .price {
            font-size: 1.1rem;
            font-weight: 700;
            color: #d98c5f;
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
            .product-detail {
                grid-template-columns: 1fr;
                padding: 24px 24px 40px;
                gap: 32px;
            }

            .product-gallery {
                position: static;
            }

            .main-image-wrapper {
                height: 400px;
            }

            .product-title {
                font-size: 2rem;
            }

            .related-section {
                padding: 0 24px 40px;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
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

            .product-detail {
                padding: 16px 20px 32px;
            }

            .main-image-wrapper {
                height: 300px;
            }

            .product-title {
                font-size: 1.6rem;
            }

            .product-price {
                font-size: 1.8rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-add-to-cart {
                min-width: 100%;
            }

            .related-grid {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }

            .product-features {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .related-grid {
                grid-template-columns: 1fr;
            }

            .thumbnail-row {
                gap: 8px;
            }

            .thumbnail {
                width: 60px;
                height: 60px;
            }

            .product-features {
                grid-template-columns: 1fr;
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
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge" id="cartBadge"><?php echo $cart_count; ?></span>
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
        <span style="color: #b0b0b0;"><?php echo escapeHtml($product['name']); ?></span>
    </div>

    <!-- ============================================ -->
    <!-- PRODUCT DETAIL -->
    <!-- ============================================ -->
    <div class="product-detail">
        <!-- Gallery -->
        <div class="product-gallery">
            <div class="main-image-wrapper">
                <img id="mainImage" 
                     src="<?php echo $product['image_url'] ?? 'https://placehold.co/600x600/1a1a1a/d98c5f?text=Shoe'; ?>" 
                     alt="<?php echo escapeHtml($product['name']); ?>"
                     onerror="this.src='https://placehold.co/600x600/1a1a1a/d98c5f?text=Shoe'">
            </div>
            <div class="thumbnail-row" id="thumbnailRow">
                <img class="thumbnail active" 
                     src="<?php echo $product['image_url'] ?? 'https://placehold.co/600x600/1a1a1a/d98c5f?text=Shoe'; ?>" 
                     data-img="<?php echo $product['image_url'] ?? 'https://placehold.co/600x600/1a1a1a/d98c5f?text=Shoe'; ?>"
                     alt="Main image">
                <!-- Additional thumbnails for demo -->
                <img class="thumbnail" 
                     src="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=100&h=100&fit=crop" 
                     data-img="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=600&h=600&fit=crop"
                     alt="Alternate view">
                <img class="thumbnail" 
                     src="https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=100&h=100&fit=crop" 
                     data-img="https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=600&h=600&fit=crop"
                     alt="Alternate view">
                <img class="thumbnail" 
                     src="https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=100&h=100&fit=crop" 
                     data-img="https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&h=600&fit=crop"
                     alt="Alternate view">
            </div>
        </div>

        <!-- Product Info -->
        <div class="product-info">
            <div class="product-brand">
                <i class="fas fa-tag"></i> <?php echo escapeHtml($product['brand_name'] ?? 'Premium'); ?>
            </div>
            
            <h1 class="product-title"><?php echo escapeHtml($product['name']); ?></h1>
            
            <div class="product-rating">
                <div class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                </div>
                <span class="review-count">4.5 (128 reviews)</span>
            </div>
            
            <div class="product-price">
                Rs. <?php echo number_format($product['price'], 0); ?>
            </div>
            
            <div class="product-stock <?php 
                echo $product['stock'] > 0 ? ($product['stock'] < 10 ? 'low-stock' : 'in-stock') : 'out-of-stock'; 
            ?>">
                <?php if ($product['stock'] > 0): ?>
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    <?php if ($product['stock'] < 10): ?>
                        Only <?php echo $product['stock']; ?> left in stock!
                    <?php else: ?>
                        In Stock
                    <?php endif; ?>
                <?php else: ?>
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    Out of Stock
                <?php endif; ?>
            </div>
            
            <p class="product-description">
                <?php echo escapeHtml($product['description'] ?? 'Premium quality footwear designed for comfort and style. Perfect for everyday wear.'); ?>
            </p>

            <!-- ============================================ -->
            <!-- VARIANTS (Sizes/Colors) -->
            <!-- ============================================ -->
            <?php if (!empty($variants)): ?>
                <div class="variant-section">
                    <div class="variant-title"><i class="fas fa-palette"></i> Select Variant</div>
                    <div class="variant-options" id="variantOptions">
                        <?php foreach ($variants as $index => $variant): ?>
                            <button type="button" 
                                    class="variant-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                                    data-id="<?php echo $variant['id']; ?>"
                                    data-stock="<?php echo $variant['stock']; ?>"
                                    onclick="selectVariant(this, <?php echo $variant['id']; ?>, <?php echo $variant['stock']; ?>)">
                                <?php echo escapeHtml($variant['variant_name']); ?>
                                <?php if ($variant['size']): ?>
                                    - <?php echo escapeHtml($variant['size']); ?>
                                <?php endif; ?>
                                <?php if ($variant['color']): ?>
                                    (<?php echo escapeHtml($variant['color']); ?>)
                                <?php endif; ?>
                                <span class="variant-stock">
                                    <?php if ($variant['stock'] <= 0): ?>
                                        ❌ Out of Stock
                                    <?php elseif ($variant['stock'] < 10): ?>
                                        ⚡ Only <?php echo $variant['stock']; ?> left
                                    <?php else: ?>
                                        ✅ In Stock
                                    <?php endif; ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="selectedVariant" value="<?php echo $variants[0]['id'] ?? ''; ?>">
                    <input type="hidden" id="variantStock" value="<?php echo $variants[0]['stock'] ?? 0; ?>">
                </div>
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- QUANTITY & ADD TO CART - FIXED -->
            <!-- ============================================ -->
            <div class="quantity-section">
                <div class="quantity-selector">
                    <button type="button" class="quantity-btn" onclick="updateQuantity(-1)">−</button>
                    <span class="quantity-value" id="quantityValue">1</span>
                    <button type="button" class="quantity-btn" onclick="updateQuantity(1)">+</button>
                    <input type="hidden" name="quantity" id="quantityInput" value="1">
                </div>
                
                <?php if (!empty($variants)): ?>
                    <input type="hidden" name="variant_id" id="variantInput" value="<?php echo $variants[0]['id'] ?? ''; ?>">
                <?php endif; ?>
                
                <input type="hidden" name="product_id" id="productIdInput" value="<?php echo $product['id']; ?>">
            </div>

            <div class="action-buttons">
                <button type="button" class="btn-add-to-cart" id="addToCartBtn" onclick="addToCartFromProduct()">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <button type="button" class="btn-wishlist" onclick="alert('❤️ Added to wishlist! (Coming soon)')">
                    <i class="far fa-heart"></i> Wishlist
                </button>
            </div>

            <!-- ============================================ -->
            <!-- PRODUCT FEATURES -->
            <!-- ============================================ -->
            <div class="product-features">
                <div class="feature-item">
                    <i class="fas fa-truck-fast"></i>
                    <div class="label">Shipping</div>
                    <div class="value">Free over Rs. 10,000</div>
                </div>
                <div class="feature-item">
                    <i class="fas fa-arrow-rotate-right"></i>
                    <div class="label">Returns</div>
                    <div class="value">30-Day Guarantee</div>
                </div>
                <div class="feature-item">
                    <i class="fas fa-shield"></i>
                    <div class="label">Authenticity</div>
                    <div class="value">100% Genuine</div>
                </div>
                <div class="feature-item">
                    <i class="fas fa-headset"></i>
                    <div class="label">Support</div>
                    <div class="value">24/7 Customer Care</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RELATED PRODUCTS -->
    <!-- ============================================ -->
    <?php if (!empty($related_products)): ?>
        <div class="related-section">
            <h3><i class="fas fa-bolt"></i> You May Also Like</h3>
            <div class="related-grid">
                <?php foreach ($related_products as $related): ?>
                    <div class="related-card" onclick="window.location.href='product.php?id=<?php echo $related['id']; ?>'">
                        <img src="<?php echo $related['image_url'] ?? 'https://placehold.co/400x400/1a1a1a/d98c5f?text=Shoe'; ?>" 
                             alt="<?php echo escapeHtml($related['name']); ?>"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/400x400/1a1a1a/d98c5f?text=Shoe'">
                        <div class="info">
                            <div class="brand"><?php echo escapeHtml($related['brand_name'] ?? 'Premium'); ?></div>
                            <div class="name"><?php echo escapeHtml($related['name']); ?></div>
                            <div class="price">Rs. <?php echo number_format($related['price'], 0); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- CART NOTIFICATION -->
    <!-- ============================================ -->
    <div id="cartNotification" class="cart-notification">
        <i class="fas fa-check-circle"></i>
        <div class="message">
            Added to cart!
            <small>Item has been added successfully</small>
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
        // IMAGE GALLERY
        // =============================================
        const mainImage = document.getElementById('mainImage');
        const thumbnails = document.querySelectorAll('.thumbnail');
        
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                const imgSrc = this.getAttribute('data-img');
                if (imgSrc) {
                    mainImage.src = imgSrc;
                }
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // =============================================
        // VARIANT SELECTION
        // =============================================
        function selectVariant(btn, variantId, stock) {
            document.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('selectedVariant').value = variantId;
            document.getElementById('variantInput').value = variantId;
            document.getElementById('variantStock').value = stock;
            
            // Update stock display
            const stockElement = document.querySelector('.product-stock');
            const addBtn = document.getElementById('addToCartBtn');
            
            if (stock <= 0) {
                stockElement.className = 'product-stock out-of-stock';
                stockElement.innerHTML = '<i class="fas fa-circle" style="font-size: 0.5rem;"></i> Out of Stock';
            } else if (stock < 10) {
                stockElement.className = 'product-stock low-stock';
                stockElement.innerHTML = '<i class="fas fa-circle" style="font-size: 0.5rem;"></i> Only ' + stock + ' left in stock!';
            } else {
                stockElement.className = 'product-stock in-stock';
                stockElement.innerHTML = '<i class="fas fa-circle" style="font-size: 0.5rem;"></i> In Stock';
            }
        }

        // =============================================
        // QUANTITY
        // =============================================
        let quantity = 1;
        const maxStock = <?php echo $product['stock']; ?>;
        
        function updateQuantity(delta) {
            const newQty = quantity + delta;
            const maxQty = Math.min(maxStock, 99);
            
            if (newQty >= 1 && newQty <= maxQty) {
                quantity = newQty;
                document.getElementById('quantityValue').textContent = quantity;
                document.getElementById('quantityInput').value = quantity;
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
        // ADD TO CART - WORKS WITHOUT LOGIN!
        // =============================================
        function addToCartFromProduct() {
            const btn = document.getElementById('addToCartBtn');
            const productId = document.getElementById('productIdInput').value;
            const variantId = document.getElementById('variantInput')?.value || '';
            const quantity = parseInt(document.getElementById('quantityInput').value) || 1;
            
            // Check if variant is out of stock
            const variantStock = parseInt(document.getElementById('variantStock')?.value || maxStock);
            if (variantStock <= 0 && document.getElementById('variantStock')) {
                alert('Sorry, this variant is out of stock!');
                return;
            }
            
            // Show loading state
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            btn.classList.add('loading');
            btn.disabled = true;
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', productId);
            if (variantId) formData.append('variant_id', variantId);
            formData.append('quantity', quantity);
            
            // Send to cart.php
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
                        badge.textContent = data.cart_count;
                    } else {
                        const cartWrapper = document.querySelector('.cart-wrapper');
                        if (cartWrapper) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'cart-badge';
                            newBadge.id = 'cartBadge';
                            newBadge.textContent = data.cart_count;
                            cartWrapper.appendChild(newBadge);
                        }
                    }
                    
                    // Show success
                    btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                    btn.classList.remove('loading');
                    btn.classList.add('added');
                    btn.disabled = false;
                    
                    showNotification('Added to cart!', 'Item has been added successfully');
                    
                    // Reset after 2 seconds
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('added');
                    }, 2000);
                } else {
                    btn.innerHTML = '<i class="fas fa-times"></i> Failed';
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    alert(data.message || 'Error adding to cart');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.innerHTML = '<i class="fas fa-times"></i> Error';
                btn.classList.remove('loading');
                btn.disabled = false;
                alert('Error adding to cart. Please try again.');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 2000);
            });
        }

        console.log('👟 SOLEMATCH - Product Detail');
        console.log('📦 Product: <?php echo escapeHtml($product['name']); ?>');
        console.log('💡 No login required to add items to cart!');
        console.log('🛒 Add to cart works without login!');
    </script>

</body>
</html>