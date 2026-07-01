<?php
// ============================================
// SOLEMATCH - Admin Product Management
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

// Check if user is admin - Redirect if not
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

// Get all brands and categories for dropdowns
$brands = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $brand_id = intval($_POST['brand_id'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $image_url = trim($_POST['image_url'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name) || $brand_id <= 0 || $category_id <= 0 || $price <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            if ($id > 0) {
                // Update existing product
                $stmt = $pdo->prepare("
                    UPDATE products SET 
                        name = ?, brand_id = ?, category_id = ?, description = ?, 
                        price = ?, stock = ?, image_url = ?, status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $brand_id, $category_id, $description, $price, $stock, $image_url, $status, $id]);
                $message = 'Product updated successfully!';
            } else {
                // Insert new product
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, brand_id, category_id, description, price, stock, image_url, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $brand_id, $category_id, $description, $price, $stock, $image_url, $status]);
                $message = 'Product added successfully!';
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
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Product deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Cannot delete product: ' . $e->getMessage();
    }
}

// Get edit data
$edit_product = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $edit_product = $stmt->fetch();
    if (!$edit_product) {
        header('Location: products.php');
        exit;
    }
}

// Get all products for listing
$stmt = $pdo->query("
    SELECT p.*, b.name as brand_name, c.name as category_name 
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.id DESC
");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | SOLE ADMIN</title>
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
        .header h1 i { color: #d98c5f; background: none; -webkit-background-clip: unset; }
        
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
            min-width: 700px;
        }
        th {
            background: #1a1a1a;
            padding: 14px 16px;
            text-align: left;
            color: #d98c5f;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #2a2a2a;
            vertical-align: middle;
        }
        tr:hover { background: #1a1a1a; }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 12px;
            background: #1a1a1a;
        }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
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
            max-width: 700px;
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
            min-height: 100px;
            resize: vertical;
            border-radius: 16px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .header h1 {
                font-size: 1.5rem;
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
        <h1><i class="fas fa-box"></i> Manage Products</h1>
        <div>
            <a href="index.php" class="btn btn-secondary" style="margin-right: 10px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="?action=add" class="btn">
                <i class="fas fa-plus"></i> Add Product
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
    <!-- ADD/EDIT FORM -->
    <!-- ============================================ -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <a href="products.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to product list</a>
        <div class="form-container">
            <h2><?php echo $action === 'edit' ? 'Edit' : 'Add'; ?> Product</h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_product['id'] ?? 0; ?>">
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" value="<?php echo escapeHtml($edit_product['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Brand *</label>
                        <select name="brand_id" required>
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>" <?php echo (isset($edit_product) && $edit_product['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                                    <?php echo escapeHtml($brand['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($edit_product) && $edit_product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo escapeHtml($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"><?php echo escapeHtml($edit_product['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price (Rs.) *</label>
                        <input type="number" step="0.01" name="price" value="<?php echo $edit_product['price'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Stock *</label>
                        <input type="number" name="stock" value="<?php echo $edit_product['stock'] ?? 0; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://example.com/image.jpg" value="<?php echo escapeHtml($edit_product['image_url'] ?? ''); ?>">
                    <small style="color: #6a6a6a; display: block; margin-top: 4px;">Leave empty to use placeholder image</small>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?php echo (isset($edit_product) && $edit_product['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($edit_product) && $edit_product['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Save Product</button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    
    <!-- ============================================ -->
    <!-- PRODUCT LIST -->
    <!-- ============================================ -->
    <?php else: ?>
        <div class="table-wrapper">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Yet</h3>
                    <p>Start by adding your first product to the store.</p>
                    <a href="?action=add" class="btn" style="margin-top: 16px;">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <img class="product-image" src="<?php echo $product['image_url'] ?? 'https://placehold.co/60x60/1a1a1a/d98c5f?text=Shoe'; ?>" 
                                         alt="<?php echo escapeHtml($product['name']); ?>"
                                         onerror="this.src='https://placehold.co/60x60/1a1a1a/d98c5f?text=Shoe'">
                                </td>
                                <td>
                                    <strong><?php echo escapeHtml($product['name']); ?></strong>
                                </td>
                                <td><?php echo escapeHtml($product['brand_name'] ?? 'N/A'); ?></td>
                                <td><?php echo escapeHtml($product['category_name'] ?? 'N/A'); ?></td>
                                <td>Rs. <?php echo number_format($product['price'], 0); ?></td>
                                <td>
                                    <?php echo $product['stock']; ?>
                                    <?php if ($product['stock'] < 10 && $product['stock'] > 0): ?>
                                        <span style="color: #facc15; font-size: 0.7rem;">(Low)</span>
                                    <?php elseif ($product['stock'] <= 0): ?>
                                        <span style="color: #ef4444; font-size: 0.7rem;">(Out)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $product['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="?action=edit&id=<?php echo $product['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $product['id']; ?>" class="delete" 
                                       onclick="return confirm('Delete this product? This action cannot be undone.')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 16px; color: #6a6a6a; font-size: 0.85rem;">
            <i class="fas fa-info-circle"></i> Total products: <?php echo count($products); ?>
        </div>
    <?php endif; ?>
    
</div>

<script>
    // Preview image URL on change
    document.querySelector('input[name="image_url"]')?.addEventListener('input', function() {
        const url = this.value;
        const preview = document.querySelector('.product-image');
        if (preview && url) {
            preview.src = url;
        }
    });
</script>
</body>
</html>