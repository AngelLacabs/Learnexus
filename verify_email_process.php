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
    
    // SMART OTP VERIFICATION - Works with any table structure
    error_log("=== SMART OTP VERIFICATION ===");
    error_log("Email: $email, OTP: $otpCode");
    
    // Method 1: Try to find OTP with known column names
    $possibleColumns = ['otpCode', 'otp', 'code', 'verification_code'];
    $possibleTimeColumns = ['created_at', 'created', 'timestamp', 'createdDate', 'date_created'];
    
    $foundOTP = false;
    $isValid = false;
    
    foreach ($possibleColumns as $otpColumn) {
        foreach ($possibleTimeColumns as $timeColumn) {
            try {
                $query = "SELECT $otpColumn, $timeColumn FROM email_otp WHERE email = ? ORDER BY $timeColumn DESC LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->execute([$email]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($record && isset($record[$otpColumn])) {
                    error_log("✅ Found OTP in column '$otpColumn': " . $record[$otpColumn]);
                    $foundOTP = true;
                    
                    // Check OTP match
                    if ((string)$otpCode === (string)$record[$otpColumn]) {
                        error_log("✅ OTP MATCHES!");
                        
                        // Check expiration if we have timestamp
                        if (isset($record[$timeColumn])) {
                            $createdTime = strtotime($record[$timeColumn]);
                            $elapsed = time() - $createdTime;
                            error_log("✅ OTP created at: " . $record[$timeColumn] . " ($elapsed seconds ago)");
                            
                            if ($elapsed > 900) { // 15 minutes
                                $_SESSION['error'] = 'OTP has expired (15 minutes).';
                                header('Location: verify_email.php');
                                exit();
                            }
                        }
                        
                        $isValid = true;
                        break 3; // Break out of all loops
                    } else {
                        error_log("❌ OTP mismatch: Submitted '$otpCode' vs Stored '" . $record[$otpColumn] . "'");
                    }
                }
            } catch (Exception $e) {
                // Column doesn't exist, try next
                continue;
            }
        }
    }
    
    // If not found with known columns, try generic approach
    if (!$foundOTP) {
        error_log("⚠️ OTP not found with known columns. Trying generic search...");
        
        try {
            // Get all columns
            $stmt = $conn->prepare("SELECT * FROM email_otp WHERE email = ?");
            $stmt->execute([$email]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($records) {
                foreach ($records as $record) {
                    foreach ($record as $key => $value) {
                        // Look for 6-digit numbers
                        if (is_numeric($value) && strlen((string)$value) == 6) {
                            error_log("🔍 Found possible OTP in column '$key': $value");
                            
                            if ((string)$otpCode === (string)$value) {
                                error_log("✅ OTP MATCHES!");
                                $isValid = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("❌ Generic search failed: " . $e->getMessage());
        }
    }
    
    // Check SMS OTP as fallback (since we see sms_otp in session)
    if (!$isValid && isset($_SESSION['sms_otp'])) {
        error_log("⚠️ Checking SMS OTP fallback...");
        if ((string)$otpCode === (string)$_SESSION['sms_otp']) {
            error_log("✅ OTP verified from SMS session!");
            $isValid = true;
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

    // Clear session
    unset($_SESSION['pending_registration']);
    unset($_SESSION['otp_email']);
    unset($_SESSION['sms_otp']);
    unset($_SESSION['sms_phone']);

    $_SESSION['success'] = $successMessage;
    header('Location: index.php');
    exit();
    
} catch (Exception $e) {
    error_log("❌ System Error: " . $e->getMessage());
    $_SESSION['error'] = 'System error: ' . $e->getMessage();
    header('Location: verify_email.php');
    exit();
}
?>