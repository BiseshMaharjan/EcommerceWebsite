<?php
// ============================================
// SOLEMATCH - Admin Order Management
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

function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// ============================================
// HANDLE ORDER STATUS UPDATE
// ============================================
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = $_POST['order_status'] ?? '';
    $payment_status = $_POST['payment_status'] ?? '';
    
    if ($order_id && $new_status) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $payment_status, $order_id]);
            $message = 'Order status updated successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to update order: ' . $e->getMessage();
        }
    }
}

// ============================================
// GET ORDER DETAILS (for view modal)
// ============================================
$view_order = null;
$view_items = [];
$show_view = false;

if (isset($_GET['view']) && !empty($_GET['view'])) {
    $order_id = intval($_GET['view']);
    $show_view = true;
    
    // Get order details
    // Get order details - Updated to include phone
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email, u.phone 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$view_order = $stmt->fetch();
    
    if ($view_order) {
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
        $view_items = $stmt->fetchAll();
    }
}

// ============================================
// GET ALL ORDERS
// ============================================
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "
    SELECT o.*, u.full_name, u.email 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR u.full_name LIKE ? OR o.shipping_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ============================================
// GET STATS
// ============================================
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'pending'");
$pending = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'confirmed'");
$confirmed = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'delivered'");
$delivered = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $stmt->fetch()['total'] ?? 0;

// Status options
$status_options = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

$payment_statuses = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'failed' => 'Failed'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | SOLE ADMIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Space Grotesk', sans-serif; 
            background: #0a0a0a; 
            color: #e5e5e5; 
            padding: 24px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a2a;
        }
        .header h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #ffffff 0%, #d98c5f 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }
        .header h1 i { color: #d98c5f; }
        
        .btn {
            background: #d98c5f;
            color: #0a0a0a;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        .btn:hover { background: #ffffff; transform: scale(1.02); }
        .btn-secondary { background: #2a2a2a; color: #e5e5e5; }
        .btn-secondary:hover { background: #3a3a3a; }
        
        .message {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success {
            background: #1e3a2f;
            color: #4ade80;
            border: 1px solid #2e7d5e;
        }
        .message.error {
            background: #3a1e1e;
            color: #f87171;
            border: 1px solid #b91c1c;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #121212;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #2a2a2a;
            text-align: center;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #d98c5f;
        }
        .stat-card .label {
            font-size: 0.8rem;
            color: #8a8a8a;
        }
        
        /* Filters */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
        }
        .filters a {
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            color: #8a8a8a;
            border: 1px solid #2a2a2a;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        .filters a:hover { border-color: #d98c5f; color: #ffffff; }
        .filters a.active {
            background: #d98c5f;
            color: #0a0a0a;
            border-color: #d98c5f;
        }
        .filters .search-box {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }
        .filters .search-box input {
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid #2a2a2a;
            background: #1a1a1a;
            color: #e5e5e5;
            font-family: inherit;
        }
        .filters .search-box input:focus {
            outline: none;
            border-color: #d98c5f;
        }
        .filters .search-box button {
            padding: 8px 20px;
            border-radius: 40px;
            border: none;
            background: #d98c5f;
            color: #0a0a0a;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        
        /* Table */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid #2a2a2a;
            background: #121212;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        th {
            background: #1a1a1a;
            padding: 14px 16px;
            text-align: left;
            color: #d98c5f;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #2a2a2a;
            vertical-align: middle;
        }
        tr:hover { background: #1a1a1a; }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #3a3a1e; color: #facc15; border: 1px solid #7d7e2e; }
        .status-confirmed { background: #1e3a2f; color: #4ade80; border: 1px solid #2e7d5e; }
        .status-processing { background: #1e3a5f; color: #60a5fa; border: 1px solid #2e5d7e; }
        .status-shipped { background: #1e3a5f; color: #60a5fa; border: 1px solid #2e5d7e; }
        .status-delivered { background: #1e3a2f; color: #4ade80; border: 1px solid #2e7d5e; }
        .status-cancelled { background: #3a1e1e; color: #f87171; border: 1px solid #b91c1c; }
        
        .payment-paid { background: #1e3a2f; color: #4ade80; border: 1px solid #2e7d5e; }
        .payment-pending { background: #3a3a1e; color: #facc15; border: 1px solid #7d7e2e; }
        .payment-failed { background: #3a1e1e; color: #f87171; border: 1px solid #b91c1c; }
        
        .actions a {
            margin: 0 4px;
            color: #aaa;
            transition: 0.2s;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-block;
        }
        .actions a:hover { color: #d98c5f; background: #2a2a2a; }
        
        .order-number {
            font-weight: 600;
            color: #d98c5f;
        }
        
        /* Modal */
        .modal {
            display: <?php echo $show_view ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: #121212;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid #2a2a2a;
        }
        .modal-content h2 {
            margin-bottom: 24px;
            color: #d98c5f;
        }
        .modal-content .close {
            float: right;
            color: #6a6a6a;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-content .close:hover { color: #ffffff; }
        
        .order-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #1a1a1a;
        }
        .order-detail-row .label { color: #8a8a8a; }
        .order-detail-row .value { font-weight: 500; }
        
        .order-items {
            margin-top: 16px;
        }
        .order-item {
            display: flex;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #1a1a1a;
            align-items: center;
        }
        .order-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        .order-item .item-info { flex: 1; }
        .order-item .item-info .name { font-weight: 600; }
        .order-item .item-info .variant { font-size: 0.8rem; color: #8a8a8a; }
        .order-item .item-total { color: #d98c5f; font-weight: 600; }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        @media (max-width: 768px) {
            .filters .search-box { margin-left: 0; width: 100%; }
            .filters .search-box input { flex: 1; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    
    <!-- HEADER -->
    <div class="header">
        <h1><i class="fas fa-shopping-bag"></i> Manage Orders</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>

    <!-- MESSAGES -->
    <?php if ($message): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_orders; ?></div>
            <div class="label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $pending; ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $confirmed; ?></div>
            <div class="label">Confirmed</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $delivered; ?></div>
            <div class="label">Delivered</div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
        <a href="?status=all" class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="?status=pending" class="<?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?status=confirmed" class="<?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
        <a href="?status=processing" class="<?php echo $status_filter === 'processing' ? 'active' : ''; ?>">Processing</a>
        <a href="?status=shipped" class="<?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">Shipped</a>
        <a href="?status=delivered" class="<?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
        <a href="?status=cancelled" class="<?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        
        <form class="search-box" method="GET">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="text" name="search" placeholder="Search orders..." value="<?php echo escapeHtml($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper">
        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #8a8a8a;">
                <i class="fas fa-shopping-bag" style="font-size: 3rem; color: #2a2a2a; margin-bottom: 16px; display: block;"></i>
                <h3 style="color: #ffffff; margin-bottom: 8px;">No Orders Found</h3>
                <p>Orders will appear here once customers make purchases.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><span class="order-number">#<?php echo $order['order_number']; ?></span></td>
                            <td><?php echo escapeHtml($order['full_name'] ?? 'Guest'); ?></td>
                            <td>Rs. <?php echo number_format($order['total_amount'], 0); ?></td>
                            <td>
                                <span class="status-badge payment-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                            <td class="actions">
                                <a href="?view=<?php echo $order['id']; ?>" title="View Order">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 16px; color: #6a6a6a; font-size: 0.85rem;">
        <i class="fas fa-info-circle"></i> Total orders: <?php echo count($orders); ?>
    </div>

    <!-- ============================================ -->
    <!-- VIEW ORDER MODAL -->
    <!-- ============================================ -->
    <?php if ($view_order): ?>
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <span class="close" onclick="window.location.href='orders.php'">&times;</span>
            
            <h2><i class="fas fa-receipt"></i> Order Details</h2>
            
            <div style="margin-bottom: 20px;">
                <div class="order-detail-row">
                    <span class="label">Order Number</span>
                    <span class="value">#<?php echo $view_order['order_number']; ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Date</span>
                    <span class="value"><?php echo date('d M Y, h:i A', strtotime($view_order['created_at'])); ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Customer</span>
                    <span class="value"><?php echo escapeHtml($view_order['full_name'] ?? 'Guest'); ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Email</span>
                    <span class="value"><?php echo escapeHtml($view_order['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo escapeHtml($view_order['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Shipping Address</span>
                    <span class="value"><?php echo escapeHtml($view_order['shipping_address']); ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Payment Method</span>
                    <span class="value"><?php echo ucfirst($view_order['payment_method']); ?></span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Payment Status</span>
                    <span class="value">
                        <span class="status-badge payment-<?php echo $view_order['payment_status']; ?>">
                            <?php echo ucfirst($view_order['payment_status']); ?>
                        </span>
                    </span>
                </div>
                <div class="order-detail-row">
                    <span class="label">Order Status</span>
                    <span class="value">
                        <span class="status-badge status-<?php echo $view_order['order_status']; ?>">
                            <?php echo ucfirst($view_order['order_status']); ?>
                        </span>
                    </span>
                </div>
                <?php if ($view_order['notes']): ?>
                <div class="order-detail-row">
                    <span class="label">Notes</span>
                    <span class="value"><?php echo escapeHtml($view_order['notes']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <h3 style="color: #d98c5f; margin-bottom: 12px;">Order Items</h3>
            <div class="order-items">
                <?php foreach ($view_items as $item): ?>
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
                            <div style="font-size: 0.8rem; color: #8a8a8a;">
                                <?php echo $item['quantity']; ?> × Rs. <?php echo number_format($item['price'], 0); ?>
                            </div>
                        </div>
                        <div class="item-total">Rs. <?php echo number_format($item['price'] * $item['quantity'], 0); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 16px; padding-top: 16px; border-top: 2px solid #2a2a2a; display: flex; justify-content: space-between; font-size: 1.2rem;">
                <span style="font-weight: 600;">Total</span>
                <span style="color: #d98c5f; font-weight: 700;">Rs. <?php echo number_format($view_order['total_amount'], 0); ?></span>
            </div>
            
            <!-- Update Status Form -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #2a2a2a;">
                <h4 style="color: #d98c5f; margin-bottom: 12px;">Update Order Status</h4>
                <form method="POST" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <input type="hidden" name="order_id" value="<?php echo $view_order['id']; ?>">
                    
                    <div>
                        <select name="order_status" style="padding: 10px 16px; border-radius: 40px; border: 1px solid #2a2a2a; background: #1a1a1a; color: #e5e5e5; font-family: inherit;">
                            <?php foreach ($status_options as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $view_order['order_status'] === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="payment_status" style="padding: 10px 16px; border-radius: 40px; border: 1px solid #2a2a2a; background: #1a1a1a; color: #e5e5e5; font-family: inherit;">
                            <?php foreach ($payment_statuses as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $view_order['payment_status'] === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_status" class="btn">Update Status</button>
                </form>
            </div>
            
            <div class="modal-actions">
                <a href="orders.php" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>
</body>
</html>