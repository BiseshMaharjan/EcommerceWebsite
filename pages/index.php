<?php
// ============================================
// SOLEMATCH - Complete Landing Page
// ALL ERRORS FIXED - Single File
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

function getCartCount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ============================================
// GET DATA FROM DATABASE
// ============================================

// Get categories - FIXED: removed 'status' column if it doesn't exist
try {
    // Check if status column exists
    $check_stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'status'");
    $has_status = $check_stmt->rowCount() > 0;
    
    if ($has_status) {
        $stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' LIMIT 6");
    } else {
        $stmt = $pdo->query("SELECT * FROM categories LIMIT 6");
    }
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get featured products - FIXED: removed 'status' column if it doesn't exist
try {
    // Check if status column exists in products
    $check_stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
    $has_status = $check_stmt->rowCount() > 0;
    
    $sql = "
        SELECT p.*, b.name as brand_name, c.name as category_name 
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
    ";
    if ($has_status) {
        $sql .= " WHERE p.status = 'active' ";
    }
    $sql .= " ORDER BY p.created_at DESC LIMIT 8";
    
    $stmt = $pdo->query($sql);
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Get new arrivals - FIXED: removed 'status' column if it doesn't exist
try {
    $sql = "
        SELECT p.*, b.name as brand_name 
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
    ";
    if ($has_status) {
        $sql .= " WHERE p.status = 'active' ";
    }
    $sql .= " ORDER BY p.created_at DESC LIMIT 4";
    
    $stmt = $pdo->query($sql);
    $new_arrivals = $stmt->fetchAll();
} catch (PDOException $e) {
    $new_arrivals = [];
}

// Get cart count if logged in
$cart_count = 0;
if (isLoggedIn()) {
    $cart_count = getCartCount($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOLEMATCH - Premium Footwear</title>
    
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
            padding: 20px 48px;
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
            padding: 14px 48px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        .logo h1 {
            font-size: 2rem;
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

        .nav-links {
            display: flex;
            gap: 28px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            font-weight: 500;
            color: #b0b0b0;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.95rem;
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
            font-size: 1.3rem;
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
            font-size: 0.65rem;
            font-weight: 700;
            position: absolute;
            top: -8px;
            right: -12px;
            min-width: 20px;
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
            padding: 10px 28px;
            border-radius: 60px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            font-size: 0.9rem;
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
            padding: 8px 20px;
            border-radius: 60px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .logout-btn:hover {
            border-color: #d98c5f;
            color: #ffffff;
        }

        /* ========== HERO SECTION ========== */
        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            padding: 60px 48px 80px;
            min-height: 90vh;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(217, 140, 95, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(217, 140, 95, 0.15);
            color: #d98c5f;
            padding: 8px 20px;
            border-radius: 60px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 24px;
            border: 1px solid rgba(217, 140, 95, 0.2);
        }

        .hero h1 {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -2px;
        }

        .hero h1 .highlight {
            background: linear-gradient(135deg, #d98c5f, #f0a87a);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .hero p {
            font-size: 1.2rem;
            color: #9a9a9a;
            line-height: 1.8;
            max-width: 480px;
            margin-bottom: 36px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #d98c5f;
            color: #0a0a0a;
            padding: 16px 40px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .btn-primary:hover {
            background: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(217, 140, 95, 0.35);
        }

        .btn-secondary {
            background: transparent;
            color: #e5e5e5;
            padding: 16px 40px;
            border-radius: 60px;
            font-weight: 600;
            font-size: 1rem;
            border: 1.5px solid #3a3a3a;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .btn-secondary:hover {
            border-color: #d98c5f;
            color: #ffffff;
            transform: translateY(-3px);
        }

        .hero-stats {
            display: flex;
            gap: 48px;
            margin-top: 48px;
            padding-top: 48px;
            border-top: 1px solid #2a2a2a;
            flex-wrap: wrap;
        }

        .hero-stats .stat {
            text-align: left;
        }

        .hero-stats .stat .number {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
        }

        .hero-stats .stat .label {
            font-size: 0.8rem;
            color: #8a8a8a;
            margin-top: 4px;
        }

        .hero-image {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            filter: drop-shadow(0 20px 60px rgba(217, 140, 95, 0.2));
            animation: float 6s ease-in-out infinite;
            border-radius: 20px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Floating elements */
        .floating-badge {
            position: absolute;
            background: rgba(20, 20, 20, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: float-badge 4s ease-in-out infinite;
        }

        .floating-badge-1 {
            top: 10%;
            right: -10%;
            animation-delay: 0s;
        }

        .floating-badge-2 {
            bottom: 20%;
            left: -10%;
            animation-delay: 2s;
        }

        @keyframes float-badge {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating-badge i {
            font-size: 1.5rem;
            color: #d98c5f;
        }

        .floating-badge .text {
            font-size: 0.8rem;
        }

        .floating-badge .text strong {
            display: block;
            color: #ffffff;
            font-size: 0.9rem;
        }

        /* ========== SECTION TITLES ========== */
        .section {
            padding: 80px 48px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 48px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .section-header h2 i {
            color: #d98c5f;
            margin-right: 12px;
        }

        .section-header a {
            color: #d98c5f;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header a:hover {
            color: #ffffff;
            gap: 16px;
        }

        /* ========== PRODUCT GRID ========== */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 32px;
        }

        .product-card {
            background: #121212;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-8px);
            border-color: #d98c5f;
            box-shadow: 0 20px 60px rgba(217, 140, 95, 0.15);
        }

        .product-card .image-wrapper {
            position: relative;
            overflow: hidden;
            background: #1a1a1a;
            height: 280px;
        }

        .product-card .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .product-card:hover .image-wrapper img {
            transform: scale(1.05);
        }

        .product-card .badge {
            position: absolute;
            top: 16px;
            left: 16px;
            background: #d98c5f;
            color: #0a0a0a;
            padding: 4px 16px;
            border-radius: 60px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-card .badge.sold-out {
            background: #ef4444;
            color: #ffffff;
        }

        .product-card .info {
            padding: 20px 24px 24px;
        }

        .product-card .info .brand {
            font-size: 0.7rem;
            letter-spacing: 1px;
            font-weight: 600;
            color: #d98c5f;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .product-card .info .name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .product-card .info .category {
            font-size: 0.75rem;
            color: #8a8a8a;
            margin-bottom: 12px;
        }

        .product-card .info .price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #d98c5f;
        }

        .product-card .quick-add {
            position: absolute;
            bottom: -60px;
            right: 24px;
            background: #d98c5f;
            color: #0a0a0a;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
        }

        .product-card:hover .quick-add {
            bottom: 24px;
            opacity: 1;
        }

        .product-card .quick-add:hover {
            transform: scale(1.1);
            background: #ffffff;
        }

        /* ========== CATEGORIES ========== */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 24px;
        }

        .category-card {
            background: #121212;
            border-radius: 24px;
            padding: 32px 24px;
            text-align: center;
            border: 1px solid #2a2a2a;
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .category-card:hover {
            transform: translateY(-6px);
            border-color: #d98c5f;
            box-shadow: 0 12px 40px rgba(217, 140, 95, 0.1);
        }

        .category-card i {
            font-size: 2.5rem;
            color: #d98c5f;
            margin-bottom: 12px;
        }

        .category-card h4 {
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
        }

        .category-card p {
            color: #8a8a8a;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        /* ========== NEWSLETTER ========== */
        .newsletter {
            background: linear-gradient(135deg, #121212, #1a1a1a);
            border-radius: 32px;
            padding: 64px 48px;
            text-align: center;
            border: 1px solid #2a2a2a;
            margin: 0 48px 80px;
        }

        .newsletter h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .newsletter p {
            color: #8a8a8a;
            margin-bottom: 32px;
        }

        .newsletter-form {
            display: flex;
            gap: 16px;
            max-width: 500px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .newsletter-form input {
            flex: 1;
            min-width: 200px;
            padding: 16px 24px;
            border-radius: 60px;
            border: 1px solid #2a2a2a;
            background: #0a0a0a;
            color: #e5e5e5;
            font-family: inherit;
            font-size: 0.95rem;
        }

        .newsletter-form input:focus {
            outline: none;
            border-color: #d98c5f;
        }

        .newsletter-form button {
            padding: 16px 32px;
            border-radius: 60px;
            border: none;
            background: #d98c5f;
            color: #0a0a0a;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            white-space: nowrap;
        }

        .newsletter-form button:hover {
            background: #ffffff;
        }

        /* ========== FOOTER ========== */
        footer {
            border-top: 1px solid #2a2a2a;
            padding: 48px 48px 32px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 32px;
        }

        .footer-brand h3 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #d98c5f 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            margin-bottom: 12px;
        }

        .footer-brand p {
            color: #8a8a8a;
            line-height: 1.6;
            max-width: 300px;
        }

        .footer-col h4 {
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .footer-col a {
            display: block;
            color: #8a8a8a;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .footer-col a:hover {
            color: #d98c5f;
            padding-left: 4px;
        }

        .footer-bottom {
            border-top: 1px solid #2a2a2a;
            padding-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-bottom p {
            color: #6a6a6a;
            font-size: 0.8rem;
        }

        .footer-social {
            display: flex;
            gap: 16px;
        }

        .footer-social a {
            color: #6a6a6a;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .footer-social a:hover {
            color: #d98c5f;
            transform: translateY(-2px);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .hero h1 {
                font-size: 3.5rem;
            }
        }

        @media (max-width: 992px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
                padding: 40px 24px 60px;
                min-height: auto;
            }

            .hero p {
                margin: 0 auto 36px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-image img {
                max-width: 80%;
            }

            .floating-badge {
                display: none;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 16px 20px;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .section {
                padding: 40px 20px;
            }

            .section-header h2 {
                font-size: 1.8rem;
            }

            .product-grid {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            .newsletter {
                margin: 0 20px 40px;
                padding: 40px 24px;
            }

            .newsletter-form {
                flex-direction: column;
                align-items: stretch;
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
            .product-grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .nav-links a:not(.cart-wrapper):not(.login-btn):not(.logout-btn) {
                display: none;
            }

            .btn-primary, .btn-secondary {
                padding: 14px 28px;
                font-size: 0.9rem;
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
            <a href="index.php" class="active">Home</a>
            <a href="shop.php">Shop</a>
            <a href="#">New</a>
            <a href="#">Community</a>
            
            <?php if (isLoggedIn()): ?>
                <a href="#" style="color: #d98c5f;">
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
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <!-- ============================================ -->
    <!-- HERO SECTION -->
    <!-- ============================================ -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-star"></i> New Collection 2026
            </div>
            <h1>
                Step Into<br>
                <span class="highlight">Premium Comfort</span>
            </h1>
            <p>
                Discover the perfect blend of style and performance. 
                Engineered for those who never settle for less.
            </p>
            <div class="hero-buttons">
                <a href="shop.php" class="btn-primary">
                    <i class="fas fa-arrow-right"></i> Shop Now
                </a>
                <a href="#" class="btn-secondary">
                    <i class="fas fa-play-circle"></i> Explore
                </a>
            </div>
            <div class="hero-stats">
                <div class="stat">
                    <div class="number">50+</div>
                    <div class="label">Premium Brands</div>
                </div>
                <div class="stat">
                    <div class="number">10K+</div>
                    <div class="label">Happy Customers</div>
                </div>
                <div class="stat">
                    <div class="number">100%</div>
                    <div class="label">Authentic Products</div>
                </div>
            </div>
        </div>
        <div class="hero-image">
            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&h=600&fit=crop" alt="Premium Shoes">
            
            <div class="floating-badge floating-badge-1">
                <i class="fas fa-truck-fast"></i>
                <div class="text">
                    <strong>Free Shipping</strong>
                    On orders over Rs. 10,000
                </div>
            </div>
            <div class="floating-badge floating-badge-2">
                <i class="fas fa-arrow-rotate-right"></i>
                <div class="text">
                    <strong>30-Day Returns</strong>
                    No questions asked
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================ -->
    <!-- CATEGORIES SECTION -->
    <!-- ============================================ -->
    <?php if (!empty($categories)): ?>
    <section class="section">
        <div class="section-header">
            <h2><i class="fas fa-grid-2"></i> Shop by Category</h2>
            <a href="shop.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="categories-grid">
            <?php
            $icons = ['fa-running', 'fa-tshirt', 'fa-mountain', 'fa-clock-rotate-left', 'fa-tree', 'fa-bolt'];
            $i = 0;
            foreach ($categories as $cat): 
            ?>
                <a href="shop.php?category=<?php echo urlencode($cat['name']); ?>" class="category-card">
                    <i class="fas <?php echo $icons[$i % count($icons)]; ?>"></i>
                    <h4><?php echo escapeHtml($cat['name']); ?></h4>
                    <p><?php echo escapeHtml($cat['description'] ?? 'Explore collection'); ?></p>
                </a>
            <?php $i++; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- FEATURED PRODUCTS -->
    <!-- ============================================ -->
    <?php if (!empty($featured_products)): ?>
    <section class="section">
        <div class="section-header">
            <h2><i class="fas fa-fire"></i> Featured Products</h2>
            <a href="shop.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach ($featured_products as $product): ?>
                <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                    <div class="image-wrapper">
                        <img src="<?php echo $product['image_url'] ?? 'https://placehold.co/400x400/1a1a1a/d98c5f?text=Shoe'; ?>" 
                             alt="<?php echo escapeHtml($product['name']); ?>"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/400x400/1a1a1a/d98c5f?text=Shoe'">
                        <?php if (isset($product['stock']) && $product['stock'] <= 0): ?>
                            <span class="badge sold-out">Sold Out</span>
                        <?php elseif (isset($product['stock']) && $product['stock'] < 10): ?>
                            <span class="badge">Low Stock</span>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <div class="brand"><?php echo escapeHtml($product['brand_name'] ?? 'Premium'); ?></div>
                        <div class="name"><?php echo escapeHtml($product['name']); ?></div>
                        <div class="category"><?php echo escapeHtml($product['category_name'] ?? 'Footwear'); ?></div>
                        <div class="price">Rs. <?php echo number_format($product['price'] ?? 0, 0); ?></div>
                    </div>
                    <button class="quick-add" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>);">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- NEW ARRIVALS -->
    <!-- ============================================ -->
    <?php if (!empty($new_arrivals)): ?>
    <section class="section" style="background: rgba(20, 20, 20, 0.3); border-radius: 32px; margin: 0 48px;">
        <div class="section-header">
            <h2><i class="fas fa-clock"></i> New Arrivals</h2>
            <a href="shop.php?sort=new">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach ($new_arrivals as $product): ?>
                <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                    <div class="image-wrapper">
                        <img src="<?php echo $product['image_url'] ?? 'https://placehold.co/400x400/1a1a1a/d98c5f?text=Shoe'; ?>" 
                             alt="<?php echo escapeHtml($product['name']); ?>"
                             loading="lazy"
                             onerror="this.src='https://placehold.co/400x400/1a1a1a/d98c5f?text=Shoe'">
                        <span class="badge" style="background: #4ade80; color: #0a0a0a;">New</span>
                    </div>
                    <div class="info">
                        <div class="brand"><?php echo escapeHtml($product['brand_name'] ?? 'Premium'); ?></div>
                        <div class="name"><?php echo escapeHtml($product['name']); ?></div>
                        <div class="price">Rs. <?php echo number_format($product['price'] ?? 0, 0); ?></div>
                    </div>
                    <button class="quick-add" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>);">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- NEWSLETTER -->
    <!-- ============================================ -->
    <section class="newsletter">
        <h2><i class="fas fa-envelope" style="color: #d98c5f;"></i> Stay in the Loop</h2>
        <p>Subscribe to get special offers, new arrivals, and exclusive deals.</p>
        <form class="newsletter-form" onsubmit="event.preventDefault(); alert('Thank you for subscribing!'); this.querySelector('input').value='';">
            <input type="email" placeholder="Enter your email address" required>
            <button type="submit">Subscribe <i class="fas fa-arrow-right"></i></button>
        </form>
    </section>

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
        // ADD TO CART FUNCTION
        // =============================================
        function addToCart(productId) {
            <?php if (!isLoggedIn()): ?>
                if (confirm('Please login to add items to cart. Go to login page?')) {
                    window.location.href = 'auth/login.php';
                }
                return;
            <?php endif; ?>
            
            const btn = event.target.closest('.quick-add');
            if (!btn) return;
            
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&action=add&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart badge
                    const badge = document.querySelector('.cart-badge');
                    if (badge) {
                        badge.textContent = data.cart_count;
                    } else {
                        const cartLink = document.querySelector('.cart-wrapper');
                        if (cartLink) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'cart-badge';
                            newBadge.textContent = data.cart_count;
                            cartLink.appendChild(newBadge);
                        }
                    }
                    
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.style.background = '#4ade80';
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                        btn.style.background = '#d98c5f';
                        btn.disabled = false;
                    }, 2000);
                } else {
                    alert(data.message || 'Failed to add to cart');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                alert('Error adding to cart. Please try again.');
            });
        }

        console.log('🚀 SOLEMATCH - Premium Footwear');
        console.log('👟 Welcome to the ultimate shoe shopping experience!');
    </script>

</body>
</html>