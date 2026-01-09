<?php
session_start();

// Check sessions first
if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_email'])) {
    $_SESSION['error'] = 'Session expired. Please start registration again.';
    header('Location: register.php');
    exit();
}

// Get data from session
$otpCode = trim($_POST['otp'] ?? '');
$email = $_SESSION['otp_email'];
$pendingData = $_SESSION['pending_registration'];

// Validate OTP format
if (empty($otpCode) || !preg_match('/^\d{6}$/', $otpCode)) {
    $_SESSION['error'] = 'Please enter a valid 6-digit OTP code.';
    header('Location: verify_email.php');
    exit();
}

// SIMPLE DEBUG: Show what we're working with
error_log("=== OTP VERIFICATION DEBUG ===");
error_log("Email: $email");
error_log("Submitted OTP: $otpCode");
error_log("Session ID: " . session_id());

// Try to include database files
$dbLoaded = false;
try {
    // Try to load database connection
    if (file_exists('database/db_connect.php')) {
        require_once 'database/db_connect.php';
        $dbLoaded = true;
        
        // Check if table exists
        $tables = $conn->query("SHOW TABLES LIKE 'email_otp'")->rowCount();
        if ($tables > 0) {
            error_log("✅ email_otp table exists");
            
            // Check for OTP in database
            $stmt = $conn->prepare("SELECT otpCode, created_at FROM email_otp WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otpRecord) {
                $storedOTP = $otpRecord['otpCode'];
                $createdAt = strtotime($otpRecord['created_at']);
                $currentTime = time();
                $elapsedTime = $currentTime - $createdAt;
                
                error_log("✅ Found OTP in database: $storedOTP");
                error_log("✅ Created: " . date('Y-m-d H:i:s', $createdAt));
                error_log("✅ Elapsed time: $elapsedTime seconds");
                
                // Check expiration (15 minutes)
                if ($elapsedTime > 900) {
                    $_SESSION['error'] = 'OTP has expired (15 minutes). Please request a new one.';
                    header('Location: verify_email.php');
                    exit();
                }
                
                // Compare OTPs
                if ((string)$otpCode === (string)$storedOTP) {
                    error_log("✅ OTP VERIFIED SUCCESSFULLY!");
                    
                    // Proceed with registration
                    require_once 'complete_registration.php';
                    exit();
                } else {
                    error_log("❌ OTP MISMATCH: Submitted '$otpCode' vs Stored '$storedOTP'");
                    $_SESSION['error'] = 'Invalid OTP code. Please check and try again.';
                    header('Location: verify_email.php');
                    exit();
                }
            } else {
                error_log("❌ No OTP found in database for $email");
                
                // Fallback: Check if OTP was sent via email (check session)
                if (isset($_SESSION['email_otp_sent'])) {
                    $emailOtp = $_SESSION['email_otp_sent'];
                    if ((string)$otpCode === (string)$emailOtp) {
                        error_log("✅ OTP verified from session cache");
                        require_once 'complete_registration.php';
                        exit();
                    }
                }
                
                $_SESSION['error'] = 'OTP not found. Please request a new verification code.';
                header('Location: verify_email.php');
                exit();
            }
        } else {
            error_log("❌ email_otp table does not exist");
            $_SESSION['error'] = 'System configuration error. Please contact support.';
            header('Location: verify_email.php');
            exit();
        }
    } else {
        error_log("❌ Database file not found");
        $_SESSION['error'] = 'System error. Please try again later.';
        header('Location: verify_email.php');
        exit();
    }
} catch (Exception $e) {
    error_log("❌ Database Error: " . $e->getMessage());
    $_SESSION['error'] = 'Temporary system issue. Please try again in a moment.';
    header('Location: verify_email.php');
    exit();
}
?>