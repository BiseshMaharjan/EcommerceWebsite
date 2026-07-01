<?php
// ============================================
// SOLEMATCH - Admin Dashboard
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

// Helper function
function escapeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Get stats
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
$total_products = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$total_users = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM brands");
$total_brands = $stmt->fetch()['total'] ?? 0;

// Get recent orders
$stmt = $pdo->query("
    SELECT o.*, u.full_name 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll();

// Get total sales
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE order_status = 'confirmed' OR order_status = 'delivered'");
$total_sales = $stmt->fetch()['total'] ?? 0;

// Get pending orders count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'pending'");
$pending_orders = $stmt->fetch()['total'] ?? 0;

// Get low stock products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock < 10 AND stock > 0");
$low_stock = $stmt->fetch()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SOLEMATCH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #0a0a0a;
            color: #e5e5e5;
            padding: 24px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a2a;
        }

        .admin-header .logo h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #d98c5f 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .admin-header .logo h1 i {
            color: #d98c5f;
        }

        .admin-header .logo .subtitle {
            color: #8a8a8a;
            font-size: 0.8rem;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-user .user-info {
            text-align: right;
        }

        .admin-user .user-info .name {
            font-weight: 600;
            color: #ffffff;
        }

        .admin-user .user-info .role {
            font-size: 0.7rem;
            color: #d98c5f;
        }

        .admin-user .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #d98c5f;
            color: #0a0a0a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .logout-btn {
            background: transparent;
            border: 1px solid #2a2a2a;
            color: #b0b0b0;
            padding: 8px 20px;
            border-radius: 60px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .logout-btn:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #121212;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #2a2a2a;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #d98c5f;
            transform: translateY(-2px);
        }

        .stat-card .stat-icon {
            font-size: 2rem;
            color: #d98c5f;
            margin-bottom: 8px;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
        }

        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #8a8a8a;
            margin-top: 4px;
        }

        .stat-card .stat-change {
            font-size: 0.7rem;
            margin-top: 8px;
            display: inline-block;
            padding: 2px 12px;
            border-radius: 60px;
        }

        .stat-card .stat-change.up {
            background: #1e3a2f;
            color: #4ade80;
        }

        .stat-card .stat-change.down {
            background: #3a1e1e;
            color: #f87171;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        /* Card */
        .card {
            background: #121212;
            border-radius: 20px;
            border: 1px solid #2a2a2a;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
        }

        .card-header h3 i {
            color: #d98c5f;
            margin-right: 8px;
        }

        .card-header a {
            color: #d98c5f;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .card-header a:hover {
            color: #ffffff;
        }

        .card-body {
            padding: 20px 24px;
        }

        /* Recent Orders Table */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        table th {
            text-align: left;
            padding: 12px 8px;
            color: #d98c5f;
            font-weight: 600;
            border-bottom: 1px solid #2a2a2a;
        }

        table td {
            padding: 12px 8px;
            border-bottom: 1px solid #1a1a1a;
        }

        table tr:hover td {
            background: #1a1a1a;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-confirmed {
            background: #1e3a2f;
            color: #4ade80;
            border: 1px solid #2e7d5e;
        }

        .status-pending {
            background: #3a3a1e;
            color: #facc15;
            border: 1px solid #7d7e2e;
        }

        .status-delivered {
            background: #1e3a2f;
            color: #4ade80;
            border: 1px solid #2e7d5e;
        }

        .status-cancelled {
            background: #3a1e1e;
            color: #f87171;
            border: 1px solid #b91c1c;
        }

        .status-processing {
            background: #1e3a5f;
            color: #60a5fa;
            border: 1px solid #2e5d7e;
        }

        .status-shipped {
            background: #1e3a5f;
            color: #60a5fa;
            border: 1px solid #2e5d7e;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .quick-action-btn {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #e5e5e5;
            text-decoration: none;
        }

        .quick-action-btn:hover {
            border-color: #d98c5f;
            transform: translateY(-2px);
            background: #1e1e1e;
        }

        .quick-action-btn i {
            font-size: 1.8rem;
            color: #d98c5f;
            display: block;
            margin-bottom: 8px;
        }

        .quick-action-btn span {
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .admin-user {
                width: 100%;
                justify-content: space-between;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <div class="admin-header">
            <div class="logo">
                <h1><i class="fas fa-crown"></i> SOLE ADMIN</h1>
                <div class="subtitle">Manage your shoe empire</div>
            </div>
            <div class="admin-user">
                <div class="user-info">
                    <div class="name"><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></div>
                    <div class="role"><i class="fas fa-shield-alt"></i> <?php echo $_SESSION['admin_role'] ?? 'Administrator'; ?></div>
                </div>
                <div class="avatar">
                    <?php echo substr($_SESSION['admin_name'] ?? 'A', 0, 1); ?>
                </div>
                <a href="../auth/logout.php" class="logout-btn" onclick="return confirm('Logout from admin panel?')">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-box"></i></div>
                <div class="stat-number"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-number"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-tags"></i></div>
                <div class="stat-number"><?php echo $total_brands; ?></div>
                <div class="stat-label">Total Brands</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-number">Rs. <?php echo number_format($total_sales, 0); ?></div>
                <div class="stat-label">Total Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $pending_orders; ?></div>
                <div class="stat-label">Pending Orders</div>
                <?php if ($pending_orders > 0): ?>
                    <span class="stat-change up">Needs attention</span>
                <?php else: ?>
                    <span class="stat-change down">All good!</span>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo $low_stock; ?></div>
                <div class="stat-label">Low Stock Items</div>
                <?php if ($low_stock > 0): ?>
                    <span class="stat-change up">Restock needed</span>
                <?php else: ?>
                    <span class="stat-change down">Stock healthy</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    <a href="orders.php">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <?php if (empty($recent_orders)): ?>
                            <p style="color: #6a6a6a; text-align: center; padding: 20px;">No orders yet</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                                            <td><?php echo $order['full_name'] ?? 'Guest'; ?></td>
                                            <td>Rs. <?php echo number_format($order['total_amount'], 0); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="products.php" class="quick-action-btn">
                            <i class="fas fa-box"></i>
                            <span>Manage Products</span>
                        </a>
                        <a href="orders.php" class="quick-action-btn">
                            <i class="fas fa-shopping-bag"></i>
                            <span>View Orders</span>
                        </a>
                        <a href="brands.php" class="quick-action-btn">
                            <i class="fas fa-tags"></i>
                            <span>Manage Brands</span>
                        </a>
                        <a href="users.php" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>