<?php
require_once __DIR__ . '/../database/db_connect.php';

$email = 'learnexuspupstc@gmail.com';
$password = 'learnexus1829263061';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT userID FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing user
        $stmt = $conn->prepare("
            UPDATE users 
            SET passwordHash = ?, 
                role = 'admin', 
                status = 'active',
                emailVerified = 1,
                phoneVerified = 1
            WHERE email = ?
        ");
        $stmt->execute([$passwordHash, $email]);
        echo "✅ Admin user updated successfully!<br>";
    } else {
        // Create new admin user
        $stmt = $conn->prepare("
            INSERT INTO users 
            (email, passwordHash, firstName, lastName, middleInitial, phone, phoneVerified, emailVerified, role, status, createdAt) 
            VALUES (?, ?, 'Admin', 'Learnexus', 'L', '09123456789', 1, 1, 'admin', 'active', NOW())
        ");
        $stmt->execute([$email, $passwordHash]);
        echo "✅ Admin user created successfully!<br>";
    }
    
    echo "<br><strong>Login Credentials:</strong><br>";
    echo "Email: $email<br>";
    echo "Password: $password<br>";
    echo "<br>Password Hash: $passwordHash<br>";
    echo "<br><a href='login.php'>Go to Admin Login</a>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}