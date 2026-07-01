<?php
// ============================================
// FIX ADMIN - Create/Reset Admin User
// ============================================

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

echo "<h2>🔧 Creating Admin User</h2>";

// Delete existing admin
$stmt = $pdo->prepare("DELETE FROM users WHERE email = 'admin@solematch.com'");
$stmt->execute();
echo "<p>✅ Removed existing admin</p>";

// Create new admin with password 'admin123'
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
$stmt->execute(['admin', 'admin@solematch.com', $hash, 'Admin User']);

echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
echo "<p><strong>Email:</strong> admin@solematch.com</p>";
echo "<p><strong>Password:</strong> $password</p>";

echo "<hr>";
echo "<h3>📋 All Users:</h3>";
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
echo "<h3>🚀 Now try login:</h3>";
echo "<p><a href='auth/login.php' style='font-size: 1.2rem; color: #d98c5f;'>Go to Login Page</a></p>";
echo "<p>Email: <strong>admin@solematch.com</strong></p>";
echo "<p>Password: <strong>$password</strong></p>";
?>