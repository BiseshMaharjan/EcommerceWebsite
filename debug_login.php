<?php
// ============================================
// DEBUG LOGIN - Shows exactly what's happening
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

echo "<h2>🔍 Login Debug Tool</h2>";

// Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@solematch.com'");
$stmt->execute();
$admin = $stmt->fetch();

echo "<h3>1. Check if admin user exists:</h3>";
if ($admin) {
    echo "<p style='color: green;'>✅ Admin user found!</p>";
    echo "<pre>";
    print_r($admin);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ Admin user NOT found!</p>";
    echo "<p>Let's create one...</p>";
    
    // Create admin
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
    $stmt->execute(['admin', 'admin@solematch.com', $hash, 'Admin User']);
    
    echo "<p style='color: green;'>✅ Admin user created!</p>";
    echo "<p>Email: admin@solematch.com</p>";
    echo "<p>Password: $password</p>";
}

echo "<hr>";

echo "<h3>2. Test Password Verification:</h3>";

// Get the admin user again
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@solematch.com'");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin) {
    $test_password = 'admin123';
    $stored_hash = $admin['password_hash'];
    
    echo "<p>Password to test: <strong>$test_password</strong></p>";
    echo "<p>Stored hash: <code>$stored_hash</code></p>";
    
    $verify = password_verify($test_password, $stored_hash);
    
    if ($verify) {
        echo "<p style='color: green; font-size: 1.2rem;'>✅ PASSWORD IS CORRECT!</p>";
        echo "<p>The password 'admin123' matches the stored hash.</p>";
    } else {
        echo "<p style='color: red; font-size: 1.2rem;'>❌ PASSWORD IS WRONG!</p>";
        echo "<p>The password 'admin123' does NOT match the stored hash.</p>";
        
        // Fix it
        echo "<p>🔧 Fixing password...</p>";
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@solematch.com'");
        $stmt->execute([$new_hash]);
        
        echo "<p style='color: green;'>✅ Password has been reset to 'admin123'</p>";
        echo "<p>New hash: <code>$new_hash</code></p>";
    }
}

echo "<hr>";

echo "<h3>3. All Users in Database:</h3>";
$stmt = $pdo->query("SELECT id, username, email, role, status FROM users");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
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

echo "<hr>";

echo "<h3>4. Test Login Form:</h3>";
echo "<form method='POST' action='auth/login.php'>";
echo "<p>Email: <input type='text' name='email' value='admin@solematch.com' style='width: 300px;'></p>";
echo "<p>Password: <input type='text' name='password' value='admin123' style='width: 300px;'></p>";
echo "<p><button type='submit'>Test Login</button></p>";
echo "</form>";

echo "<hr>";
echo "<h3>5. Direct Login Link:</h3>";
echo "<p><a href='auth/login.php' style='font-size: 1.2rem; color: #d98c5f;'>Go to Login Page</a></p>";
echo "<p>Use: admin@solematch.com / admin123</p>";
?>