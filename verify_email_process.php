<?php
session_start();

// Check sessions
if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_email'])) {
    $_SESSION['error'] = 'Session expired.';
    header('Location: register.php');
    exit();
}

// Get data
$otpCode = trim($_POST['otp'] ?? '');
$email = $_SESSION['otp_email'];
$pendingData = $_SESSION['pending_registration'];

// Validate
if (empty($otpCode) || !preg_match('/^\d{6}$/', $otpCode)) {
    $_SESSION['error'] = 'Please enter a valid 6-digit OTP code.';
    header('Location: verify_email.php');
    exit();
}

// Load database
try {
    require_once 'database/db_connect.php';
    
    error_log("=== OTP VERIFICATION ===");
    error_log("Email: $email, OTP: $otpCode");
    
    $isValid = false;
    
    // Try standard column names first
    try {
        $stmt = $conn->prepare("SELECT otpCode, createdAt FROM emailotp WHERE email = ? ORDER BY createdAt DESC LIMIT 1");
        $stmt->execute([$email]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record && isset($record['otpCode'])) {
            error_log("✅ Found OTP: " . $record['otpCode']);
            
            // Check OTP match
            if ((string)$otpCode === (string)$record['otpCode']) {
                error_log("✅ OTP MATCHES!");
                
                // Check expiration
                $createdTime = strtotime($record['createdAt']);
                $elapsed = time() - $createdTime;
                error_log("✅ OTP age: $elapsed seconds");
                
                if ($elapsed > 600) { // 10 minutes
                    $_SESSION['error'] = 'OTP has expired. Please request a new one.';
                    header('Location: verify_email.php');
                    exit();
                }
                
                $isValid = true;
            } else {
                error_log("❌ OTP mismatch: '$otpCode' vs '" . $record['otpCode'] . "'");
            }
        }
    } catch (Exception $e) {
        error_log("⚠️ Standard query failed: " . $e->getMessage());
    }
    
    // If not valid, check if there's any OTP in the table
    if (!$isValid) {
        try {
            $stmt = $conn->prepare("SELECT * FROM emailotp WHERE email = ? ORDER BY emailOtpID DESC LIMIT 1");
            $stmt->execute([$email]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                // Try to find a 6-digit OTP in any column
                foreach ($record as $column => $value) {
                    if (is_numeric($value) && strlen((string)$value) == 6) {
                        if ((string)$otpCode === (string)$value) {
                            error_log("✅ OTP found in column '$column' and matches!");
                            $isValid = true;
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("⚠️ Fallback query failed: " . $e->getMessage());
        }
    }
    
    if (!$isValid) {
        $_SESSION['error'] = 'Invalid OTP code. Please check and try again.';
        header('Location: verify_email.php');
        exit();
    }
    
    // ========== OTP VERIFIED - COMPLETE REGISTRATION ==========
    error_log("✅ OTP VERIFIED - Completing registration...");
    
    $passwordHash = password_hash($pendingData['password'], PASSWORD_DEFAULT);

    if ($pendingData['role'] == 'student') {
        $stmt = $conn->prepare("
            INSERT INTO users 
            (email, passwordHash, firstName, lastName, middleInitial, phone, role, studentNumber, emailVerified, phoneVerified, createdAt) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
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
            (email, passwordHash, firstName, lastName, middleInitial, phone, role, teacherNumber, emailVerified, phoneVerified, createdAt) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
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

    // Clear session
    unset($_SESSION['pending_registration']);
    unset($_SESSION['otp_email']);
    unset($_SESSION['smsotp']);
    unset($_SESSION['otp_phone']);

    $_SESSION['success'] = $successMessage;
    header('Location: index.php');
    exit();
    
} catch (Exception $e) {
    error_log("❌ System Error: " . $e->getMessage());
    $_SESSION['error'] = 'System error. Please try again.';
    header('Location: verify_email.php');
    exit();
}