<?php
// ============================================
// SOLEMATCH - Complete Shop Page
// FIXED: Add to Cart works without login
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
// GET FILTER PARAMETERS
// ============================================
$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'default';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// ============================================
// BUILD QUERY
// ============================================
$sql = "SELECT p.*, b.name as brand_name, c.name as category_name 
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id";

$where_conditions = [];
$params = [];

// Add status filter if column exists
if ($has_status) {
    $where_conditions[] = "p.status = 'active'";
}

// Category filter
if ($category !== 'all' && !empty($category)) {
    $where_conditions[] = "c.name = ?";
    $params[] = $category;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR b.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply WHERE conditions
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Sorting
switch ($sort) {
    case 'price-asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price-desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name-asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'name-desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    case 'new':
        $sql .= " ORDER BY p.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY p.id DESC";
}

// ============================================
// GET TOTAL COUNT FOR PAGINATION
// ============================================
$count_sql = "SELECT COUNT(*) as total FROM products p 
              LEFT JOIN brands b ON p.brand_id = b.id 
              LEFT JOIN categories c ON p.category_id = c.id";
if (!empty($where_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_products / $limit);

// ============================================
// GET PRODUCTS FOR CURRENT PAGE
// ============================================
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ============================================
// GET CATEGORIES FOR FILTER
// ============================================
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get cart count for display
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
    // Count session cart
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
    <title>Shop | SOLEMATCH - Premium Footwear</title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Space Grotesk', sans-serif; background: #0a0a0a; color: #e5e5e5; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0a0a0a; }
        ::-webkit-scrollbar-thumb { background: #d98c5f; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #f0a87a; }

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

        /* SHOP HEADER */
        .shop-header {
            padding: 40px 48px 20px;
            border-bottom: 1px solid #2a2a2a;
        }
        .shop-header h1 { font-size: 2.8rem; font-weight: 700; letter-spacing: -1px; }
        .shop-header h1 i { color: #d98c5f; margin-right: 12px; }
        .shop-header p { color: #8a8a8a; margin-top: 8px; font-size: 1rem; }

        /* FILTERS */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 20px 48px;
            background: rgba(20, 20, 20, 0.5);
            border-bottom: 1px solid #2a2a2a;
            position: sticky;
            top: 72px;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        .filter-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .filter-group select, .filter-group input {
            background: #1a1a1a;
            border: 1px solid #3a3a3a;
            padding: 10px 18px;
            border-radius: 40px;
            color: #e5e5e5;
            font-family: inherit;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: #d98c5f;
            box-shadow: 0 0 0 2px rgba(217,140,95,0.2);
        }
        .filter-group select option { background: #1a1a1a; color: #e5e5e5; }

        .search-box { display: flex; gap: 8px; align-items: center; }
        .search-box input {
            background: #1a1a1a;
            border: 1px solid #3a3a3a;
            padding: 10px 18px;
            border-radius: 40px;
            color: #e5e5e5;
            font-family: inherit;
            font-size: 0.85rem;
            width: 220px;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            outline: none;
            border-color: #d98c5f;
            box-shadow: 0 0 0 2px rgba(217,140,95,0.2);
        }
        .search-box button {
            background: #d98c5f;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            color: #0a0a0a;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            white-space: nowrap;
        }
        .search-box button:hover { background: #ffffff; transform: scale(1.05); }

        .results-count { color: #8a8a8a; font-size: 0.85rem; padding: 0 10px; }

        /* PRODUCT GRID */
        .container { padding: 40px 48px 60px; max-width: 1400px; margin: 0 auto; }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: #121212;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-8px);
            border-color: #d98c5f;
            box-shadow: 0 20px 60px rgba(217,140,95,0.12);
        }
        .product-card .image-wrapper {
            position: relative;
            overflow: hidden;
            background: #1a1a1a;
            height: 260px;
        }
        .product-card .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .product-card:hover .image-wrapper img { transform: scale(1.05); }

        .product-card .badge {
            position: absolute;
            top: 14px;
            left: 14px;
            background: #d98c5f;
            color: #0a0a0a;
            padding: 4px 14px;
            border-radius: 60px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .product-card .badge.sold-out { background: #ef4444; color: #ffffff; }
        .product-card .badge.new { background: #4ade80; color: #0a0a0a; }

        .product-card .info { padding: 18px 20px 22px; }
        .product-card .info .brand {
            font-size: 0.65rem;
            letter-spacing: 1px;
            font-weight: 600;
            color: #d98c5f;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .product-card .info .name {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .product-card .info .category {
            font-size: 0.7rem;
            color: #8a8a8a;
            margin-bottom: 10px;
        }
        .product-card .info .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .product-card .info .price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #d98c5f;
        }
        .product-card .info .stock {
            font-size: 0.7rem;
            color: #6a6a6a;
        }
        .product-card .info .stock.in-stock { color: #4ade80; }

        .product-card .quick-add {
            position: absolute;
            bottom: -60px;
            right: 20px;
            background: #d98c5f;
            color: #0a0a0a;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
        }
        .product-card:hover .quick-add {
            bottom: 20px;
            opacity: 1;
        }
        .product-card .quick-add:hover {
            transform: scale(1.1);
            background: #ffffff;
        }
        .product-card .quick-add.added {
            background: #4ade80;
        }
        .product-card .quick-add.loading {
            background: #666;
            cursor: wait;
        }

        /* NO RESULTS */
        .no-results {
            text-align: center;
            padding: 80px 20px;
            grid-column: 1 / -1;
        }
        .no-results i { font-size: 4rem; color: #2a2a2a; margin-bottom: 20px; }
        .no-results h3 { font-size: 1.8rem; color: #ffffff; margin-bottom: 12px; }
        .no-results p { color: #8a8a8a; margin-bottom: 24px; }
        .no-results .btn-clear {
            background: #d98c5f;
            color: #0a0a0a;
            border: none;
            padding: 12px 32px;
            border-radius: 60px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        .no-results .btn-clear:hover { background: #ffffff; }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 48px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            height: 44px;
            padding: 0 16px;
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 60px;
            color: #e5e5e5;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .pagination a:hover { border-color: #d98c5f; color: #ffffff; }
        .pagination a.active { background: #d98c5f; color: #0a0a0a; border-color: #d98c5f; }
        .pagination a.disabled { opacity: 0.4; cursor: not-allowed; }
        .pagination a.disabled:hover { border-color: #2a2a2a; color: #e5e5e5; }
        .pagination .page-info { color: #8a8a8a; font-size: 0.85rem; padding: 0 10px; }

        /* CART NOTIFICATION */
        .cart-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #121212;
            border: 1px solid #4ade80;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.5s ease;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 280px;
        }
        .cart-notification.show { transform: translateY(0); opacity: 1; }
        .cart-notification i { font-size: 1.5rem; color: #4ade80; }
        .cart-notification .message { color: #ffffff; font-weight: 500; }
        .cart-notification .message small { display: block; color: #8a8a8a; font-weight: 400; font-size: 0.8rem; }

        /* FOOTER */
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

        @media (max-width: 992px) {
            .shop-header { padding: 30px 24px 16px; }
            .shop-header h1 { font-size: 2.2rem; }
            .filters-bar { padding: 16px 24px; top: 68px; }
            .container { padding: 24px 24px 40px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; }
            .nav-links a:not(.cart-wrapper):not(.login-btn):not(.logout-btn) { display: none; }
            .filters-bar { flex-direction: column; align-items: stretch; top: 62px; padding: 12px 20px; gap: 10px; }
            .filter-group { justify-content: center; }
            .search-box { justify-content: center; }
            .search-box input { width: 100%; }
            .product-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
            .shop-header h1 { font-size: 1.8rem; }
            .footer-grid { grid-template-columns: 1fr; gap: 24px; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }
        @media (max-width: 480px) {
            .product-grid { grid-template-columns: 1fr; }
            .filter-group select { min-width: 100px; font-size: 0.75rem; padding: 8px 14px; }
            .pagination a, .pagination span { min-width: 36px; height: 36px; font-size: 0.8rem; padding: 0 12px; }
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
            <a href="shop.php" class="active">Shop</a>
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
    <!-- SHOP HEADER -->
    <!-- ============================================ -->
    <div class="shop-header">
        <h1><i class="fas fa-store"></i> Shop All Footwear</h1>
        <p>Discover premium shoes crafted for comfort, style, and performance.</p>
    </div>

    <!-- ============================================ -->
    <!-- FILTERS BAR -->
    <!-- ============================================ -->
    <div class="filters-bar" id="filtersBar">
        <div class="filter-group">
            <select id="categoryFilter" onchange="applyFilters()">
                <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo escapeHtml($cat['name']); ?>" <?php echo $category === $cat['name'] ? 'selected' : ''; ?>>
                        <?php echo escapeHtml($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select id="sortFilter" onchange="applyFilters()">
                <option value="default" <?php echo $sort === 'default' ? 'selected' : ''; ?>>Sort by: Featured</option>
                <option value="new" <?php echo $sort === 'new' ? 'selected' : ''; ?>>Newest First</option>
                <option value="price-asc" <?php echo $sort === 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price-desc" <?php echo $sort === 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="name-asc" <?php echo $sort === 'name-asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                <option value="name-desc" <?php echo $sort === 'name-desc' ? 'selected' : ''; ?>>Name: Z to A</option>
            </select>
        </div>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search shoes..." value="<?php echo escapeHtml($search); ?>" onkeypress="if(event.key === 'Enter') applyFilters()">
            <button onclick="applyFilters()"><i class="fas fa-search"></i> Search</button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- PRODUCT GRID -->
    <!-- ============================================ -->
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 10px;">
            <span class="results-count">
                <i class="fas fa-shoe-prints"></i> 
                Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
            </span>
            <?php if ($search || $category !== 'all'): ?>
                <a href="shop.php" style="background: #2a2a2a; color: #e5e5e5; padding: 6px 18px; border-radius: 60px; font-size: 0.8rem; text-decoration: none; transition: all 0.3s;">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>

        <div class="product-grid" id="productGrid">
            <?php if (empty($products)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>We couldn't find any products matching your filters.</p>
                    <a href="shop.php" class="btn-clear">Clear All Filters</a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card" data-product-id="<?php echo $product['id']; ?>" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
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
                            <div class="category">
                                <i class="fas fa-tag" style="font-size: 0.6rem;"></i> 
                                <?php echo escapeHtml($product['category_name'] ?? 'Footwear'); ?>
                            </div>
                            <div class="price-row">
                                <div class="price">Rs. <?php echo number_format($product['price'] ?? 0, 0); ?></div>
                                <div class="stock <?php echo (isset($product['stock']) && $product['stock'] > 0) ? 'in-stock' : ''; ?>">
                                    <?php if (isset($product['stock']) && $product['stock'] > 0): ?>
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i> In Stock
                                    <?php else: ?>
                                        <i class="fas fa-circle" style="font-size: 0.5rem; color: #ef4444;"></i> Out of Stock
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button class="quick-add" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>, this);">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&category=<?php echo $category; ?>&search=<?php echo $search; ?>&sort=<?php echo $sort; ?>">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Prev</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1&category=' . $category . '&search=' . $search . '&sort=' . $sort . '">1</a>';
                    if ($start_page > 2) echo '<span>...</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&category=<?php echo $category; ?>&search=<?php echo $search; ?>&sort=<?php echo $sort; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>...</span>';
                    echo '<a href="?page=' . $total_pages . '&category=' . $category . '&search=' . $search . '&sort=' . $sort . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&category=<?php echo $category; ?>&search=<?php echo $search; ?>&sort=<?php echo $sort; ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
                
                <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            </div>
        <?php endif; ?>
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
        // APPLY FILTERS
        // =============================================
        function applyFilters() {
            const category = document.getElementById('categoryFilter').value;
            const sort = document.getElementById('sortFilter').value;
            const search = document.getElementById('searchInput').value;
            
            let url = 'shop.php?';
            if (category !== 'all') url += 'category=' + encodeURIComponent(category) + '&';
            if (sort !== 'default') url += 'sort=' + encodeURIComponent(sort) + '&';
            if (search.trim() !== '') url += 'search=' + encodeURIComponent(search.trim()) + '&';
            
            url = url.replace(/[&?]$/, '');
            window.location.href = url;
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
        function addToCart(productId, button) {
            // Find the button if not passed
            if (!button) {
                button = event?.target?.closest('.quick-add') || document.querySelector('.quick-add[data-product-id="' + productId + '"]');
            }
            
            if (!button) return;
            
            // Save original content
            const originalHtml = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.classList.add('loading');
            button.disabled = true;
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
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
                    
                    // Show success
                    button.innerHTML = '<i class="fas fa-check"></i>';
                    button.classList.remove('loading');
                    button.classList.add('added');
                    button.disabled = false;
                    
                    showNotification('Added to cart!', 'Item has been added successfully');
                    
                    // Reset after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalHtml;
                        button.classList.remove('added');
                    }, 2000);
                } else {
                    // Show error
                    button.innerHTML = '<i class="fas fa-times"></i>';
                    button.classList.remove('loading');
                    button.disabled = false;
                    
                    alert(data.message || 'Error adding to cart');
                    
                    setTimeout(() => {
                        button.innerHTML = originalHtml;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = '<i class="fas fa-times"></i>';
                button.classList.remove('loading');
                button.disabled = false;
                alert('Error adding to cart. Please try again.');
                
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                }, 2000);
            });
        }

        // =============================================
        // KEYBOARD SHORTCUTS
        // =============================================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'F') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        console.log('🛍️ SOLEMATCH - Shop Page');
        console.log('👟 Browse our premium footwear collection!');
        console.log('💡 No login required to add items to cart!');
        console.log('💡 Tip: Press Ctrl+Shift+F to focus search');
    </script>

</body>
</html>