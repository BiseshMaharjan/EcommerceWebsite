<?php
// ============================================
// CREATE ADMIN USER - Run this once
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

// Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@solematch.com'");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin) {
    // Update admin password
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', status = 'active' WHERE email = 'admin@solematch.com'");
    $stmt->execute([$password_hash]);
    echo "✅ Admin password updated successfully!\n";
    echo "📧 Email: admin@solematch.com\n";
    echo "🔑 Password: admin123\n";
} else {
    // Create admin
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
    $stmt->execute(['admin', 'admin@solematch.com', $password_hash, 'Admin User']);
    echo "✅ Admin user created successfully!\n";
    echo "📧 Email: admin@solematch.com\n";
    echo "🔑 Password: admin123\n";
}

// Show all users
echo "\n📋 All Users:\n";
$stmt = $pdo->query("SELECT id, username, email, role, status FROM users");
$users = $stmt->fetchAll();
foreach ($users as $user) {
    echo "- ID: {$user['id']} | {$user['username']} | {$user['email']} | Role: {$user['role']} | Status: {$user['status']}\n";
}
?>