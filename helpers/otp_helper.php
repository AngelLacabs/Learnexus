<?php
class OTPHelper
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // ========== EMAIL OTP FUNCTIONS ==========
    public function createEmailOTP($email, $userID = null)
    {
        try {
            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $this->conn->prepare("
                INSERT INTO email_otp (email, otpCode, userID, expiresAt, verified, createdAt) 
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$email, $otpCode, $userID, $expiresAt]);

            error_log("Email OTP Created - Email: $email, Code: $otpCode, Expires: $expiresAt");
            return $otpCode;
        } catch (PDOException $e) {
            error_log("Email OTP Creation Error: " . $e->getMessage());
            return false;
        }
    }

    public function verifyEmailOTP($email, $otpCode)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM email_otp 
                WHERE email = ? AND otpCode = ? AND verified = 0 AND expiresAt > NOW() 
                ORDER BY createdAt DESC LIMIT 1
            ");
            $stmt->execute([$email, $otpCode]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                $updateStmt = $this->conn->prepare("UPDATE email_otp SET verified = 1 WHERE emailOtpID = ?");
                $updateStmt->execute([$record['emailOtpID']]);

                return ['success' => true, 'message' => 'Email verified successfully'];
            }

            return ['success' => false, 'message' => 'Invalid or expired OTP code'];
        } catch (PDOException $e) {
            error_log("Email OTP Verification Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }

    public function resendEmailOTP($email)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE email_otp SET verified = 1 WHERE email = ? AND verified = 0");
            $stmt->execute([$email]);

            return $this->createEmailOTP($email);
        } catch (PDOException $e) {
            error_log("Email OTP Resend Error: " . $e->getMessage());
            return false;
        }
    }

    // ========== SMS OTP FUNCTIONS ==========
    public function createSMSOTP($phone)
    {
        try {
            // Invalidate any existing OTPs for this phone
            $stmt = $this->conn->prepare("UPDATE sms_otp SET verified = 1 WHERE phone = ? AND verified = 0");
            $stmt->execute([$phone]);

            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $this->conn->prepare("
                INSERT INTO sms_otp (phone, otpCode, expiresAt, verified, createdAt) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$phone, $otpCode, $expiresAt]);

            error_log("SMS OTP Created - Phone: $phone, Code: $otpCode, Expires: $expiresAt");
            return $otpCode;
        } catch (PDOException $e) {
            error_log("SMS OTP Creation Error: " . $e->getMessage());
            return false;
        }
    }

    public function verifySMSOTP($phone, $otpCode)
    {
        try {
            $now = date('Y-m-d H:i:s');
            error_log("=== SMS OTP VERIFICATION ===");
            error_log("Phone: $phone");
            error_log("Code Entered: $otpCode");
            error_log("Current Time: $now");
            
            // Get the most recent unverified OTP
            $stmt = $this->conn->prepare("
                SELECT *, 
                       CASE WHEN expiresAt > ? THEN 'valid' ELSE 'expired' END as timeStatus
                FROM sms_otp 
                WHERE phone = ? AND verified = 0
                ORDER BY createdAt DESC 
                LIMIT 1
            ");
            $stmt->execute([$now, $phone]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                error_log("Found OTP Record:");
                error_log("  - DB Code: " . $record['otpCode']);
                error_log("  - Expires At: " . $record['expiresAt']);
                error_log("  - Time Status: " . $record['timeStatus']);
                error_log("  - Verified: " . $record['verified']);

                // Check if OTP matches
                if ($record['otpCode'] === $otpCode) {
                    error_log("✅ OTP Code Matches!");
                    
                    // Check if expired
                    if ($record['timeStatus'] === 'expired') {
                        error_log("❌ OTP is EXPIRED");
                        return ['success' => false, 'message' => 'OTP has expired. Please request a new one.'];
                    }
                    
                    // Mark as verified
                    $updateStmt = $this->conn->prepare("UPDATE sms_otp SET verified = 1 WHERE otpID = ?");
                    $updateStmt->execute([$record['otpID']]);

                    error_log("✅ SMS OTP Verified Successfully!");
                    return ['success' => true, 'message' => 'Phone verified successfully'];
                } else {
                    error_log("❌ OTP Code Does Not Match");
                    error_log("  Expected: " . $record['otpCode']);
                    error_log("  Got: $otpCode");
                }
            } else {
                error_log("❌ No OTP Record Found for phone: $phone");
            }

            return ['success' => false, 'message' => 'Invalid or expired OTP code'];
        } catch (PDOException $e) {
            error_log("SMS OTP Verification Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }

    public function resendSMSOTP($phone)
    {
        try {
            // This will invalidate old OTPs and create a new one
            return $this->createSMSOTP($phone);
        } catch (Exception $e) {
            error_log("SMS OTP Resend Error: " . $e->getMessage());
            return false;
        }
    }
}