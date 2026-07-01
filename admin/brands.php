<?php
// ============================================
// SOLEMATCH - Admin Brand Management
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
// HANDLE CRUD OPERATIONS
// ============================================
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Handle Add/Edit Brand
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = 'Brand name is required.';
    } else {
        try {
            // Check for duplicate brand name
            $check_stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ? AND id != ?");
            $check_stmt->execute([$name, $id]);
            if ($check_stmt->rowCount() > 0) {
                $error = 'A brand with this name already exists.';
            } else {
                if ($id > 0) {
                    // Update existing brand
                    $stmt = $pdo->prepare("
                        UPDATE brands SET 
                            name = ?, 
                            description = ?, 
                            logo_url = ?, 
                            status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $logo_url, $status, $id]);
                    $message = 'Brand updated successfully!';
                } else {
                    // Insert new brand
                    $stmt = $pdo->prepare("
                        INSERT INTO brands (name, description, logo_url, status) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $logo_url, $status]);
                    $message = 'Brand added successfully!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        // Check if brand has products
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
        $check_stmt->execute([$id]);
        $result = $check_stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = 'Cannot delete this brand. It has ' . $result['count'] . ' products associated with it.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Brand deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Cannot delete brand: ' . $e->getMessage();
    }
}

// Get edit data
$edit_brand = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $edit_brand = $stmt->fetch();
    if (!$edit_brand) {
        header('Location: brands.php');
        exit;
    }
}

// Get all brands
$stmt = $pdo->query("SELECT * FROM brands ORDER BY name");
$brands = $stmt->fetchAll();

// Get brand stats
$total_brands = count($brands);
$active_brands = 0;
$inactive_brands = 0;

foreach ($brands as $brand) {
    if ($brand['status'] === 'active') {
        $active_brands++;
    } else {
        $inactive_brands++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands | SOLE ADMIN</title>
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
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
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
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #ff6b6b; }
        .btn-secondary { background: #2a2a2a; color: #e5e5e5; }
        .btn-secondary:hover { background: #3a3a3a; }
        
        /* Messages */
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
            min-width: 500px;
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
        
        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 8px;
            background: #1a1a1a;
        }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active { background: #1e3a2f; color: #4ade80; border: 1px solid #2e7d5e; }
        .status-inactive { background: #3a1e1e; color: #f87171; border: 1px solid #b91c1c; }
        
        .actions a {
            margin: 0 4px;
            color: #aaa;
            transition: 0.2s;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-block;
        }
        .actions a:hover { color: #d98c5f; background: #2a2a2a; }
        .actions a.delete:hover { color: #ef4444; }
        
        /* Form */
        .form-container {
            background: #121212;
            padding: 32px;
            border-radius: 24px;
            border: 1px solid #2a2a2a;
            max-width: 500px;
            margin: 0 auto;
        }
        .form-container h2 {
            margin-bottom: 24px;
            color: #ffffff;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #d98c5f;
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 0.85rem;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 28px;
            border: 1px solid #2a2a2a;
            background: #1a1a1a;
            color: #e5e5e5;
            font-family: inherit;
            font-size: 0.95rem;
            transition: 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #d98c5f;
            box-shadow: 0 0 0 2px rgba(217, 140, 95, 0.2);
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
            border-radius: 16px;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .back-link {
            color: #d98c5f;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        .back-link:hover { text-decoration: underline; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8a8a8a;
        }
        .empty-state i {
            font-size: 4rem;
            color: #2a2a2a;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: #ffffff;
            margin-bottom: 8px;
        }
        
        .product-count {
            font-size: 0.75rem;
            color: #6a6a6a;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    
    <!-- ============================================ -->
    <!-- HEADER -->
    <!-- ============================================ -->
    <div class="header">
        <h1><i class="fas fa-trademark"></i> Manage Brands</h1>
        <div>
            <a href="index.php" class="btn btn-secondary" style="margin-right: 10px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="?action=add" class="btn">
                <i class="fas fa-plus"></i> Add Brand
            </a>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MESSAGES -->
    <!-- ============================================ -->
    <?php if ($message): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- STATS -->
    <!-- ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number"><?php echo $total_brands; ?></div>
            <div class="label">Total Brands</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $active_brands; ?></div>
            <div class="label">Active</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $inactive_brands; ?></div>
            <div class="label">Inactive</div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ADD/EDIT FORM -->
    <!-- ============================================ -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <a href="brands.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to brand list</a>
        <div class="form-container">
            <h2><?php echo $action === 'edit' ? 'Edit' : 'Add'; ?> Brand</h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_brand['id'] ?? 0; ?>">
                
                <div class="form-group">
                    <label>Brand Name *</label>
                    <input type="text" name="name" value="<?php echo escapeHtml($edit_brand['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of the brand..."><?php echo escapeHtml($edit_brand['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Logo URL</label>
                    <input type="url" name="logo_url" placeholder="https://example.com/logo.png" value="<?php echo escapeHtml($edit_brand['logo_url'] ?? ''); ?>">
                    <small style="color: #6a6a6a; display: block; margin-top: 4px;">Leave empty to use default icon</small>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?php echo (isset($edit_brand) && $edit_brand['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($edit_brand) && $edit_brand['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Save Brand</button>
                    <a href="brands.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    
    <!-- ============================================ -->
    <!-- BRAND LIST -->
    <!-- ============================================ -->
    <?php else: ?>
        <div class="table-wrapper">
            <?php if (empty($brands)): ?>
                <div class="empty-state">
                    <i class="fas fa-trademark"></i>
                    <h3>No Brands Yet</h3>
                    <p>Start by adding your first brand to the store.</p>
                    <a href="?action=add" class="btn" style="margin-top: 16px;">
                        <i class="fas fa-plus"></i> Add Brand
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Brand Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $brand): 
                            // Count products for this brand
                            $prod_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
                            $prod_stmt->execute([$brand['id']]);
                            $prod_count = $prod_stmt->fetch()['count'] ?? 0;
                        ?>
                            <tr>
                                <td>
                                    <?php if ($brand['logo_url']): ?>
                                        <img class="brand-logo" src="<?php echo $brand['logo_url']; ?>" 
                                             alt="<?php echo escapeHtml($brand['name']); ?>"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #1a1a1a; border-radius: 8px; color: #d98c5f; font-size: 1.5rem;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo escapeHtml($brand['name']); ?></strong></td>
                                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo escapeHtml($brand['description'] ?? 'No description'); ?>
                                </td>
                                <td>
                                    <?php echo $prod_count; ?>
                                    <?php if ($prod_count > 0): ?>
                                        <span class="product-count">(<?php echo $prod_count; ?> products)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $brand['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($brand['status']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="?action=edit&id=<?php echo $brand['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($prod_count == 0): ?>
                                        <a href="?action=delete&id=<?php echo $brand['id']; ?>" class="delete" 
                                           onclick="return confirm('Delete this brand? This action cannot be undone.')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #6a6a6a; cursor: not-allowed;" title="Cannot delete - has <?php echo $prod_count; ?> products">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 16px; color: #6a6a6a; font-size: 0.85rem;">
            <i class="fas fa-info-circle"></i> Total brands: <?php echo count($brands); ?>
        </div>
    <?php endif; ?>
    
</div>

<script>
    // Preview logo URL on change
    document.querySelector('input[name="logo_url"]')?.addEventListener('input', function() {
        const url = this.value;
        const preview = document.querySelector('.brand-logo');
        if (preview && url) {
            preview.src = url;
        }
    });
</script>
</body>
</html>