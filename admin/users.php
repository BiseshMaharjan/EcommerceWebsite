<?php
// ============================================
// SOLEMATCH - Admin User Management
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
// HANDLE USER OPERATIONS
// ============================================
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Username, email, and full name are required.';
    } else {
        try {
            // Check for duplicate username or email
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check_stmt->execute([$username, $email, $id]);
            if ($check_stmt->rowCount() > 0) {
                $error = 'Username or email already exists.';
            } else {
                if ($id > 0) {
                    // Update existing user
                    if (!empty($password)) {
                        // Update with new password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                username = ?, 
                                email = ?, 
                                full_name = ?, 
                                phone = ?, 
                                role = ?, 
                                status = ?,
                                password_hash = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $full_name, $phone, $role, $status, $password_hash, $id]);
                    } else {
                        // Update without changing password
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                username = ?, 
                                email = ?, 
                                full_name = ?, 
                                phone = ?, 
                                role = ?, 
                                status = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $full_name, $phone, $role, $status, $id]);
                    }
                    $message = 'User updated successfully!';
                } else {
                    // Insert new user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, full_name, phone, role, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $email, $password_hash, $full_name, $phone, $role, $status]);
                    $message = 'User added successfully!';
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
    
    // Prevent admin from deleting themselves
    if ($id == $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            // Check if user has orders
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
            $check_stmt->execute([$id]);
            $result = $check_stmt->fetch();
            
            if ($result['count'] > 0) {
                // Instead of deleting, deactivate the user
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'User has been deactivated (has orders).';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'User deleted successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Cannot delete user: ' . $e->getMessage();
        }
    }
}

// Get edit data
$edit_user = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        header('Location: users.php');
        exit;
    }
}

// ============================================
// GET ALL USERS
// ============================================
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role_filter !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ============================================
// GET STATS
// ============================================
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$total_admins = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$total_customers = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$active_users = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'inactive'");
$inactive_users = $stmt->fetch()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | SOLE ADMIN</title>
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
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #ff6b6b; }
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #2a2a2a;
            vertical-align: middle;
        }
        tr:hover { background: #1a1a1a; }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #d98c5f;
            color: #0a0a0a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
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
        
        .role-badge {
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .role-admin { background: #1e3a5f; color: #60a5fa; border: 1px solid #2e5d7e; }
        .role-customer { background: #1e3a2f; color: #4ade80; border: 1px solid #2e7d5e; }
        .role-manager { background: #3a3a1e; color: #facc15; border: 1px solid #7d7e2e; }
        
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
        
        .form-container {
            background: #121212;
            padding: 32px;
            border-radius: 24px;
            border: 1px solid #2a2a2a;
            max-width: 600px;
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
        .form-group select {
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
        .form-group select:focus {
            outline: none;
            border-color: #d98c5f;
            box-shadow: 0 0 0 2px rgba(217, 140, 95, 0.2);
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
        
        .password-hint {
            font-size: 0.75rem;
            color: #6a6a6a;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .filters .search-box {
                margin-left: 0;
                width: 100%;
            }
            .filters .search-box input {
                flex: 1;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    
    <!-- HEADER -->
    <div class="header">
        <h1><i class="fas fa-users"></i> Manage Users</h1>
        <div>
            <a href="index.php" class="btn btn-secondary" style="margin-right: 10px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="?action=add" class="btn">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
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
            <div class="number"><?php echo $total_users; ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $active_users; ?></div>
            <div class="label">Active</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $inactive_users; ?></div>
            <div class="label">Inactive</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_admins; ?></div>
            <div class="label">Admins</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo $total_customers; ?></div>
            <div class="label">Customers</div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
        <a href="?role=all&status=all" class="<?php echo $role_filter === 'all' && $status_filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="?role=customer&status=all" class="<?php echo $role_filter === 'customer' && $status_filter === 'all' ? 'active' : ''; ?>">Customers</a>
        <a href="?role=admin&status=all" class="<?php echo $role_filter === 'admin' && $status_filter === 'all' ? 'active' : ''; ?>">Admins</a>
        <a href="?role=all&status=active" class="<?php echo $role_filter === 'all' && $status_filter === 'active' ? 'active' : ''; ?>">Active</a>
        <a href="?role=all&status=inactive" class="<?php echo $role_filter === 'all' && $status_filter === 'inactive' ? 'active' : ''; ?>">Inactive</a>
        
        <form class="search-box" method="GET">
            <input type="hidden" name="role" value="<?php echo $role_filter; ?>">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="text" name="search" placeholder="Search users..." value="<?php echo escapeHtml($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <!-- ADD/EDIT FORM -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <a href="users.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to user list</a>
        <div class="form-container">
            <h2><?php echo $action === 'edit' ? 'Edit' : 'Add'; ?> User</h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit_user['id'] ?? 0; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" value="<?php echo escapeHtml($edit_user['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo escapeHtml($edit_user['email'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo escapeHtml($edit_user['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo escapeHtml($edit_user['phone'] ?? ''); ?>" placeholder="98XXXXXXXX">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="customer" <?php echo (isset($edit_user) && $edit_user['role'] === 'customer') ? 'selected' : ''; ?>>Customer</option>
                            <option value="admin" <?php echo (isset($edit_user) && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                            <option value="manager" <?php echo (isset($edit_user) && $edit_user['role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo (isset($edit_user) && $edit_user['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($edit_user) && $edit_user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><?php echo $action === 'edit' ? 'New Password (leave blank to keep current)' : 'Password *'; ?></label>
                    <input type="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?> placeholder="Enter password">
                    <?php if ($action === 'edit'): ?>
                        <div class="password-hint">Leave blank to keep current password</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Save User</button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    
    <!-- USER LIST -->
    <?php else: ?>
        <div class="table-wrapper">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Users Found</h3>
                    <p>Users will appear here once they register.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-avatar">
                                        <?php echo substr($user['full_name'] ?? $user['username'], 0, 1); ?>
                                    </div>
                                </td>
                                <td><strong><?php echo escapeHtml($user['username']); ?></strong></td>
                                <td><?php echo escapeHtml($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="delete" 
                                           onclick="return confirm('Delete this user? This action cannot be undone.')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #6a6a6a; cursor: not-allowed;" title="Cannot delete your own account">
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
            <i class="fas fa-info-circle"></i> Total users: <?php echo count($users); ?>
        </div>
    <?php endif; ?>
    
</div>

<script>
    // Preview username for avatar
    document.querySelector('input[name="full_name"]')?.addEventListener('input', function() {
        const name = this.value;
        const avatar = document.querySelector('.user-avatar');
        if (avatar && name) {
            avatar.textContent = name.charAt(0).toUpperCase();
        }
    });
</script>
</body>
</html>