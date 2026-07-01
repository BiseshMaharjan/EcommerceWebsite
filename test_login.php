<?php
// ============================================
// SIMPLE LOGIN TEST - No fancy stuff
// ============================================

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

echo "<h2>🔐 Login Test</h2>";

// Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@solematch.com'");
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    echo "<p style='color: red;'>❌ Admin user not found! Creating...</p>";
    
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
    $stmt->execute(['admin', 'admin@solematch.com', $hash, 'Admin User']);
    
    echo "<p style='color: green;'>✅ Admin created!</p>";
    
    // Get the new admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@solematch.com'");
    $stmt->execute();
    $admin = $stmt->fetch();
}

echo "<h3>Admin User:</h3>";
echo "<pre>";
print_r($admin);
echo "</pre>";

// Test password verification
$test_password = 'admin123';
$stored_hash = $admin['password_hash'];

echo "<h3>Password Test:</h3>";
echo "Testing password: <strong>$test_password</strong><br>";
echo "Stored hash: <code>$stored_hash</code><br>";

$verify = password_verify($test_password, $stored_hash);

if ($verify) {
    echo "<p style='color: green; font-size: 1.5rem;'>✅ SUCCESS! Password matches!</p>";
    echo "<p>You can now login with:</p>";
    echo "<p>Email: <strong>admin@solematch.com</strong></p>";
    echo "<p>Password: <strong>$test_password</strong></p>";
} else {
    echo "<p style='color: red; font-size: 1.5rem;'>❌ FAILED! Password doesn't match!</p>";
    echo "<p>Fixing password...</p>";
    
    // Fix it
    $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@solematch.com'");
    $stmt->execute([$new_hash]);
    
    echo "<p style='color: green;'>✅ Password reset! Try again.</p>";
    echo "<p><a href='test_login.php'>Click here to test again</a></p>";
}

echo "<hr>";
echo "<h3>Login Form:</h3>";
?>
<form method="POST" action="auth/login.php">
    <p>
        <label>Email:</label><br>
        <input type="email" name="email" value="admin@solematch.com" style="width: 300px; padding: 10px;">
    </p>
    <p>
        <label>Password:</label><br>
        <input type="text" name="password" value="admin123" style="width: 300px; padding: 10px;">
    </p>
    <button type="submit" style="padding: 10px 30px; background: #d98c5f; border: none; border-radius: 30px; font-weight: bold; cursor: pointer;">Login</button>
</form>
<?php

echo "<hr>";
echo "<h3>All Users:</h3>";
$stmt = $pdo->query("SELECT id, username, email, role, status FROM users");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>{$user['status']}</td>";
    echo "</tr>";
}
echo "</table>";
?>