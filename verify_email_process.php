<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';

if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_email'])) {
    $_SESSION['error'] = 'Invalid verification request.';
    header('Location: register.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify_email.php');
    exit();
}

$otpCode = trim($_POST['otp'] ?? '');
$email = $_SESSION['otp_email'];
$pendingData = $_SESSION['pending_registration'];

// Validate OTP format
if (empty($otpCode) || !preg_match('/^\d{6}$/', $otpCode)) {
    $_SESSION['error'] = 'Please enter a valid 6-digit OTP code.';
    header('Location: verify_email.php');
    exit();
}

// Verify OTP
$otpHelper = new OTPHelper($conn);
$verificationResult = $otpHelper->verifyEmailOTP($email, $otpCode);

if (!$verificationResult['success']) {
    $_SESSION['error'] = $verificationResult['message'];
    header('Location: verify_email.php');
    exit();
}

// OTP verified successfully - Complete registration
try {
    $passwordHash = password_hash($pendingData['password'], PASSWORD_DEFAULT);
    
    if ($pendingData['role'] == 'student') {
        $stmt = $conn->prepare("
            INSERT INTO users 
            (email, passwordHash, firstName, lastName, middleInitial, phone, role, studentNumber, emailVerified, createdAt) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $pendingData['email'],
            $passwordHash,
            $pendingData['firstName'],
            $pendingData['lastName'],
            $pendingData['middleInitial'],
            $pendingData['phone'],
            $pendingData['role'],
            $pendingData['studentNumber']
        ]);
        $userId = $conn->lastInsertId();
        $successMessage = 'Your account has been successfully created!<br><br>Student Number: <strong>' . htmlspecialchars($pendingData['studentNumber']) . '</strong><br>You can now login to your account.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO users 
            (email, passwordHash, firstName, lastName, middleInitial, phone, role, teacherNumber, emailVerified, createdAt) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $pendingData['email'],
            $passwordHash,
            $pendingData['firstName'],
            $pendingData['lastName'],
            $pendingData['middleInitial'],
            $pendingData['phone'],
            $pendingData['role'],
            $pendingData['teacherNumber']
        ]);
        $userId = $conn->lastInsertId();
        $successMessage = 'Your account has been successfully created!<br><br>Teacher Number: <strong>' . htmlspecialchars($pendingData['teacherNumber']) . '</strong><br>You can now login to your account.';
    }
    
    // Update OTP record with userID
    $updateStmt = $conn->prepare("
        UPDATE email_otp 
        SET userID = ? 
        WHERE email = ? 
        AND otpCode = ?
    ");
    $updateStmt->execute([$userId, $email, $otpCode]);
    
    // Clear session data
    unset($_SESSION['pending_registration']);
    unset($_SESSION['otp_email']);
    
    // Set success message and redirect to login
    $_SESSION['success'] = $successMessage;
    header('Location: index.php');
    exit();
    
} catch(PDOException $e) {
    error_log("Registration completion error: " . $e->getMessage());
    $_SESSION['error'] = 'Registration failed. Please try again.';
    header('Location: verify_email.php');
    exit();
}
?>