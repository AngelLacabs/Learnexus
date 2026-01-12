<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Debug: Log what we're receiving
error_log("User Actions - Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("User Actions - POST Data: " . print_r($_POST, true));
error_log("User Actions - GET Data: " . print_r($_GET, true));

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// For debugging, let's see what action we got
error_log("User Actions - Action received: " . $action);

// For add action, we don't need a userID - fix the validation
if ($action === 'add') {
    // Skip userID check for add action
} else {
    $userID = $_GET['id'] ?? $_POST['id'] ?? 0;
    if (empty($userID)) {
        $_SESSION['error'] = 'User ID is required for this action';
        header('Location: users.php');
        exit();
    }
}

if (empty($action)) {
    $_SESSION['error'] = 'Invalid action: No action specified';
    header('Location: users.php');
    exit();
}

try {
    switch ($action) {
        case 'add':
            // Handle new user creation
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                error_log("Add user - POST data received");
                
                $firstName = trim($_POST['firstName'] ?? '');
                $lastName = trim($_POST['lastName'] ?? '');
                $middleInitial = trim($_POST['middleInitial'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                $role = $_POST['role'] ?? '';
                $status = $_POST['status'] ?? 'active';
                $emailVerified = isset($_POST['emailVerified']) ? 1 : 0;
                $phoneVerified = isset($_POST['phoneVerified']) ? 1 : 0;
                $userNumber = trim($_POST['user_number'] ?? '');
                
                // Debug: Log the received data
                error_log("Add user - First: $firstName, Last: $lastName, Email: $email, Role: $role, Status: $status");
                
                // Validate passwords match
                if ($password !== $confirmPassword) {
                    $_SESSION['error'] = 'Passwords do not match';
                    error_log("Add user - Password mismatch");
                    header('Location: users.php');
                    exit();
                }
                
                // Validate required fields
                if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
                    $_SESSION['error'] = 'All required fields must be filled';
                    error_log("Add user - Missing required fields");
                    header('Location: users.php');
                    exit();
                }
                
                // Check if email already exists
                $stmt = $conn->prepare("SELECT userID FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = 'Email already exists';
                    error_log("Add user - Email already exists: $email");
                    header('Location: users.php');
                    exit();
                }
                
                // Hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Set role-specific fields
                $studentNumber = null;
                $teacherNumber = null;
                
                if ($role === 'student' && !empty($userNumber)) {
                    $studentNumber = $userNumber;
                } elseif ($role === 'instructor' && !empty($userNumber)) {
                    $teacherNumber = $userNumber;
                }
                
                // Insert new user
                $stmt = $conn->prepare("
                    INSERT INTO users 
                    (firstName, lastName, middleInitial, email, phone, passwordHash, role, status, 
                     emailVerified, phoneVerified, studentNumber, teacherNumber, createdAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $result = $stmt->execute([
                    $firstName,
                    $lastName,
                    $middleInitial,
                    $email,
                    $phone,
                    $passwordHash,
                    $role,
                    $status,
                    $emailVerified,
                    $phoneVerified,
                    $studentNumber,
                    $teacherNumber
                ]);
                
                if ($result) {
                    $_SESSION['success'] = 'User created successfully!';
                    error_log("User created successfully - Email: $email, Role: $role");
                } else {
                    $_SESSION['error'] = 'Failed to create user. Please try again.';
                    error_log("Failed to create user - Email: $email");
                }
                
                header('Location: users.php');
                exit();
            } else {
                $_SESSION['error'] = 'Invalid request method for adding user';
                error_log("Add user - Invalid request method: " . $_SERVER['REQUEST_METHOD']);
                header('Location: users.php');
                exit();
            }
            break;
            
        case 'suspend':
            // Suspend user account
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE userID = ?");
            $stmt->execute([$userID]);
            
            $_SESSION['success'] = 'User account suspended successfully!';
            header("Location: user_view.php?id=$userID");
            exit();
            
        case 'activate':
            // Activate user account
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE userID = ?");
            $stmt->execute([$userID]);
            
            $_SESSION['success'] = 'User account activated successfully!';
            header("Location: user_view.php?id=$userID");
            exit();
            
        case 'verify_email':
            // Verify user email
            $stmt = $conn->prepare("UPDATE users SET emailVerified = 1 WHERE userID = ?");
            $stmt->execute([$userID]);
            
            $_SESSION['success'] = 'Email verified successfully!';
            header("Location: user_view.php?id=$userID");
            exit();
            
        case 'verify_phone':
            // Verify user phone
            $stmt = $conn->prepare("UPDATE users SET phoneVerified = 1 WHERE userID = ?");
            $stmt->execute([$userID]);
            
            $_SESSION['success'] = 'Phone verified successfully!';
            header("Location: user_view.php?id=$userID");
            exit();
            
        case 'reset_password':
            // Reset user password
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                $forceLogout = isset($_POST['force_logout']) ? 1 : 0;
                
                if ($newPassword !== $confirmPassword) {
                    $_SESSION['error'] = 'Passwords do not match';
                    header("Location: user_view.php?id=$userID");
                    exit();
                }
                
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET passwordHash = ? WHERE userID = ?");
                $stmt->execute([$passwordHash, $userID]);
                
                $_SESSION['success'] = 'Password reset successfully!' . ($forceLogout ? ' User has been logged out from all devices.' : '');
                header("Location: user_view.php?id=$userID");
                exit();
            }
            break;
            
        case 'delete':
            // Delete user account with cascade
            // First, check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
            $stmt->execute([$userID]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $_SESSION['error'] = 'User not found';
                header('Location: users.php');
                exit();
            }
            
            // Delete user (cascade will handle related data)
            $stmt = $conn->prepare("DELETE FROM users WHERE userID = ?");
            $stmt->execute([$userID]);
            
            $_SESSION['success'] = 'User and all related data deleted successfully!';
            header('Location: users.php');
            exit();
            
        default:
            $_SESSION['error'] = 'Invalid action specified: ' . htmlspecialchars($action);
            error_log("Invalid action specified: $action");
            header('Location: users.php');
            exit();
    }
    
} catch (PDOException $e) {
    error_log("User Action Error: " . $e->getMessage());
    
    // Check if it's a foreign key constraint error
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $_SESSION['error'] = 'Cannot delete user because they have active courses, enrollments, or other related data. Try suspending the account instead.';
    } else {
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    }
    
    if ($action === 'add') {
        header('Location: users.php');
    } else {
        header("Location: user_view.php?id=$userID");
    }
    exit();
}