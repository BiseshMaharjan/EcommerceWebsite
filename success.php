<?php
// ============================================
// SOLEMATCH - Order Success Page (FIXED PATHS)
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

// Helper functions
function escapeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
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
// GET ORDER DETAILS
// ============================================
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    header('Location: ../index.php');
    exit;
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ../index.php');
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image_url,
           pv.variant_name, pv.size, pv.color
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Check payment method
$is_khalti = $order['payment_method'] === 'khalti';
$is_cod = $order['payment_method'] === 'cod';



// Get cart count for badge
$cart_count = getCartCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success | SOLEMATCH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Space Grotesk', sans-serif; 
            background: #0a0a0a; 
            color: #e5e5e5; 
            min-height: 100vh;
        }
        a { text-decoration: none; color: inherit; }

        /* NAVBAR */
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
        }
        .login-btn:hover { background: #ffffff; transform: scale(1.05); }

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

        /* ============================================ */
        /* SUCCESS PAGE */
        /* ============================================ */
        .success-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .success-card {
            background: #121212;
            border-radius: 32px;
            border: 1px solid #2a2a2a;
            overflow: hidden;
            padding: 48px 40px;
            text-align: center;
        }

        /* Success Icon */
        .success-icon {
            font-size: 4.5rem;
            color: #4ade80;
            background: #1e3a2f;
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 24px;
            border: 2px solid #2e7d5e;
            animation: pop-in 0.6s ease;
        }

        @keyframes pop-in {
            0% { transform: scale(0); opacity: 0; }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-card h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .success-card .subtitle {
            color: #8a8a8a;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .order-number {
            color: #d98c5f;
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 24px;
            display: block;
        }

        .payment-method-badge {
            display: inline-block;
            padding: 6px 20px;
            border-radius: 60px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .payment-cod {
            background: #3a3a1e;
            color: #facc15;
            border: 1px solid #7d7e2e;
        }
        .payment-khalti {
            background: #1e3a5f;
            color: #60a5fa;
            border: 1px solid #2e5d7e;
        }

        /* Order Details */
        .order-details {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 24px;
            margin: 16px 0 24px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #2a2a2a;
        }
        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #8a8a8a;
            font-weight: 500;
        }
        .detail-value {
            font-weight: 600;
            color: #ffffff;
        }

        .order-items {
            margin: 16px 0;
        }

        .order-item {
            display: flex;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #1a1a1a;
            align-items: center;
        }
        .order-item:last-child {
            border-bottom: none;
        }

        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 12px;
            background: #1a1a1a;
        }

        .order-item .item-info {
            flex: 1;
        }
        .order-item .item-info .name {
            font-weight: 600;
            color: #ffffff;
        }
        .order-item .item-info .variant {
            font-size: 0.8rem;
            color: #8a8a8a;
        }
        .order-item .item-info .qty {
            font-size: 0.8rem;
            color: #8a8a8a;
        }
        .order-item .item-total {
            color: #d98c5f;
            font-weight: 600;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding-top: 16px;
            margin-top: 16px;
            border-top: 2px solid #2a2a2a;
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff;
        }
        .total-row .amount {
            color: #d98c5f;
        }

        /* COD Instructions */
        .cod-instructions {
            background: rgba(250, 204, 21, 0.05);
            border: 1px solid rgba(250, 204, 21, 0.2);
            border-radius: 16px;
            padding: 16px 20px;
            margin-top: 16px;
            text-align: left;
        }
        .cod-instructions h4 {
            color: #facc15;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .cod-instructions p {
            color: #b0b0b0;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .cod-instructions i {
            color: #facc15;
            margin-right: 8px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 32px;
        }

        .btn-primary {
            background: #d98c5f;
            color: #0a0a0a;
            border: none;
            padding: 14px 36px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-primary:hover {
            background: #ffffff;
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(217, 140, 95, 0.3);
        }

        .btn-secondary {
            background: transparent;
            border: 1.5px solid #2a2a2a;
            color: #b0b0b0;
            padding: 14px 36px;
            border-radius: 60px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-secondary:hover {
            border-color: #d98c5f;
            color: #ffffff;
        }

        /* ============================================ */
        /* FOOTER */
        /* ============================================ */
        footer {
            border-top: 1px solid #2a2a2a;
            padding: 40px 48px 28px;
            margin-top: 40px;
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
        .footer-brand p { color: #8a8a8a; line-height: 1.6; max-width: 280px; font-size: 0.9rem; }
        .footer-col h4 { color: #ffffff; font-weight: 600; margin-bottom: 14px; font-size: 0.95rem; }
        .footer-col a { display: block; color: #8a8a8a; margin-bottom: 8px; transition: all 0.3s ease; font-size: 0.85rem; }
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

        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; }
            .nav-links a:not(.cart-wrapper):not(.login-btn):not(.logout-btn) { display: none; }
            
            .success-container { padding: 0 16px; margin: 20px auto; }
            .success-card { padding: 32px 20px; }
            .success-card h2 { font-size: 1.8rem; }
            .success-icon { width: 80px; height: 80px; font-size: 3.5rem; }
            
            .order-item { flex-wrap: wrap; }
            .order-item img { width: 50px; height: 50px; }
            .order-item .item-total { width: 100%; text-align: right; }
            
            .action-buttons { flex-direction: column; align-items: stretch; }
            .btn-primary, .btn-secondary { justify-content: center; }
            
            .footer-grid { grid-template-columns: 1fr; gap: 24px; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }

        @media (max-width: 480px) {
            .detail-row { flex-direction: column; gap: 4px; }
            .detail-value { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

    <!-- ============================================ -->
    <!-- NAVBAR - FIXED PATHS -->
    <!-- ============================================ -->
    <nav class="navbar" id="navbar">
        <div class="logo">
            <a href="../index.php">
                <h1><i class="fas fa-shoe-prints"></i> SOLEMATCH</h1>
            </a>
        </div>
        <div class="nav-links">
            <a href="../index.php">Home</a>
            <a href="shop.php">Shop</a>
            <a href="#">New</a>
            <a href="#">Community</a>
            
            <?php if (isLoggedIn()): ?>
                <a href="#" style="color: #d98c5f; font-size: 0.85rem;">
                    <i class="fas fa-user"></i> <?php echo escapeHtml($_SESSION['full_name'] ?? 'User'); ?>
                </a>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="../auth/login.php">
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
    <!-- SUCCESS CONTENT -->
    <!-- ============================================ -->
    <div class="success-container">
        <div class="success-card">

            <!-- Success Icon -->
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>

            <!-- Title -->
            <h2>Order Confirmed! 🎉</h2>
            <p class="subtitle">Thank you for your purchase, <?php echo escapeHtml($order['full_name'] ?? 'Customer'); ?>!</p>
            
            <span class="order-number">
                Order #<?php echo $order['order_number']; ?>
            </span>

            <!-- Payment Badge -->
            <span class="payment-method-badge <?php echo $is_cod ? 'payment-cod' : 'payment-khalti'; ?>">
                <i class="fas <?php echo $is_cod ? 'fa-money-bill-wave' : 'fa-mobile-alt'; ?>"></i>
                <?php echo ucfirst($order['payment_method']); ?>
            </span>

            <!-- COD Instructions -->
            <?php if ($is_cod): ?>
                <div class="cod-instructions">
                    <h4><i class="fas fa-info-circle"></i> Cash on Delivery</h4>
                    <p>
                        Please keep the exact cash ready when our delivery partner arrives. 
                        You will receive a confirmation call before delivery.
                    </p>
                    <p style="margin-top: 8px; color: #8a8a8a; font-size: 0.85rem;">
                        <i class="fas fa-clock"></i> Estimated delivery: 2-4 business days
                    </p>
                </div>
            <?php endif; ?>

            <!-- Order Details -->
            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Order Number</span>
                    <span class="detail-value">#<?php echo $order['order_number']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value"><?php echo date('F d, Y', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value"><?php echo ucfirst($order['payment_method']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Shipping Address</span>
                    <span class="detail-value"><?php echo escapeHtml($order['shipping_address']); ?></span>
                </div>
            </div>

            <!-- Order Items -->
            <div style="text-align: left; margin-top: 16px;">
                <h4 style="color: #d98c5f; margin-bottom: 12px;">Order Items</h4>
                <div class="order-items">
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo $item['image_url'] ?? 'https://placehold.co/60x60/1a1a1a/d98c5f?text=Shoe'; ?>" 
                                 alt="<?php echo escapeHtml($item['name']); ?>"
                                 onerror="this.src='https://placehold.co/60x60/1a1a1a/d98c5f?text=Shoe'">
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
                                <div class="qty">Qty: <?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="item-total">Rs. <?php echo number_format($item['price'] * $item['quantity'], 0); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="total-row">
                    <span>Total</span>
                    <span class="amount">Rs. <?php echo number_format($order['total_amount'], 0); ?></span>
                </div>
            </div>

            <!-- Action Buttons - FIXED -->
            <div class="action-buttons">
                <a href="shop.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="#" class="btn-secondary">
                        <i class="fas fa-user"></i> My Orders
                    </a>
                <?php endif; ?>
            </div>

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
        // Navbar scroll effect
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

        // Confetti effect on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎉 Order placed successfully!');
            console.log('📦 Order #<?php echo $order['order_number']; ?>');
            
            // Confetti (simple version)
            const colors = ['#d98c5f', '#4ade80', '#60a5fa', '#facc15', '#f87171'];
            for (let i = 0; i < 30; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: fixed;
                        width: 8px;
                        height: 8px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        left: ${Math.random() * 100}vw;
                        top: -10px;
                        border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
                        transform: rotate(${Math.random() * 360}deg);
                        z-index: 9999;
                        pointer-events: none;
                        transition: all ${2 + Math.random() * 2}s ease;
                    `;
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => {
                        confetti.style.transform = `translateY(${window.innerHeight + 100}px) rotate(${Math.random() * 720}deg)`;
                        confetti.style.opacity = '0';
                    }, 100);
                    
                    setTimeout(() => {
                        confetti.remove();
                    }, 4000);
                }, i * 100);
            }
        });
    </script>

</body>
</html>