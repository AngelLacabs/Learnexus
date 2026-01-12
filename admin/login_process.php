<?php
session_start();
require_once '../database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // Check if user exists and is an admin
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['passwordHash'])) {
            // Successful login
            $_SESSION['user_id'] = $admin['userID'];
            $_SESSION['email'] = $admin['email'];
            $_SESSION['first_name'] = $admin['firstName'];
            $_SESSION['last_name'] = $admin['lastName'];
            $_SESSION['role'] = $admin['role'];
            $_SESSION['admin_logged_in'] = true;

            // Redirect to admin dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $_SESSION['error'] = 'Invalid email or password. Admin access only.';
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Admin Login Error: " . $e->getMessage());
        $_SESSION['error'] = 'System error. Please try again later.';
        header('Location: login.php');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}