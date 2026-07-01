<?php
// ============================================
// SUPER SIMPLE LOGIN - No includes, no functions
// ============================================

session_start();

// Database
$db_host = 'localhost';
$db_name = 'sole_match';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple query
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Check password
        if (password_verify($password, $user['password_hash'])) {
            // Login success!
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                $_SESSION['admin_logged_in'] = true;
                header('Location: ../admin/index.php');
                exit;
            } else {
                header('Location: ../pages/index.php');
                exit;
            }
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body { font-family: Arial; background: #0a0a0a; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .container { background: #121212; padding: 40px; border-radius: 20px; border: 1px solid #2a2a2a; width: 400px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 30px; border: 1px solid #2a2a2a; background: #1a1a1a; color: #fff; }
        button { width: 100%; padding: 14px; background: #d98c5f; border: none; border-radius: 30px; font-weight: bold; cursor: pointer; font-size: 16px; }
        .error { color: #f87171; background: #3a1e1e; padding: 12px; border-radius: 12px; margin-bottom: 16px; }
        .info { color: #6a6a6a; font-size: 12px; margin-top: 16px; padding: 12px; background: #1a1a1a; border-radius: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="text-align: center;">SOLEMATCH</h2>
        <p style="text-align: center; color: #8a8a8a;">Login to your account</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="Email" value="admin@solematch.com" required>
            <input type="password" name="password" placeholder="Password" value="admin123" required>
            <button type="submit">Login</button>
        </form>
        
        <div class="info">
            <strong>🔍 Debug Info:</strong><br>
            Try: admin@solematch.com / admin123<br>
            <?php
            $check = $pdo->query("SELECT COUNT(*) as count FROM users WHERE email = 'admin@solematch.com'");
            $count = $check->fetch();
            echo "Admin exists: " . ($count['count'] > 0 ? '✅ Yes' : '❌ No');
            ?>
        </div>
    </div>
</body>
</html>