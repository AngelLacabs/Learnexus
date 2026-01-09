<?php
// helpers/otp_helper.php

require_once 'database/db_connect.php';

class OTPHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Generate a 6-digit OTP
    public function generateOTP() {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    // Create email OTP record
    public function createEmailOTP($email, $userID = null) {
        // Clean up old OTPs for this email
        $this->cleanupOldOTPs($email, 'email');
        
        $otpCode = $this->generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO email_otp (email, otpCode, userID, expiresAt) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$email, $otpCode, $userID, $expiresAt]);
            
            return $otpCode;
        } catch (PDOException $e) {
            error_log("Email OTP creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Verify email OTP
    public function verifyEmailOTP($email, $otpCode) {
        try {
            $stmt = $this->conn->prepare("
                SELECT emailOtpID, userID, expiresAt 
                FROM email_otp 
                WHERE email = ? 
                AND otpCode = ? 
                AND verified = 0 
                ORDER BY createdAt DESC 
                LIMIT 1
            ");
            $stmt->execute([$email, $otpCode]);
            $otpRecord = $stmt->fetch();
            
            if (!$otpRecord) {
                return ['success' => false, 'message' => 'Invalid OTP code'];
            }
            
            // Check if OTP is expired
            if (strtotime($otpRecord['expiresAt']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired'];
            }
            
            // Mark OTP as verified
            $updateStmt = $this->conn->prepare("
                UPDATE email_otp 
                SET verified = 1 
                WHERE emailOtpID = ?
            ");
            $updateStmt->execute([$otpRecord['emailOtpID']]);
            
            // Update user's email verification status
            if ($otpRecord['userID']) {
                $userStmt = $this->conn->prepare("
                    UPDATE users 
                    SET emailVerified = 1 
                    WHERE userID = ?
                ");
                $userStmt->execute([$otpRecord['userID']]);
            }
            
            return ['success' => true, 'userID' => $otpRecord['userID']];
            
        } catch (PDOException $e) {
            error_log("OTP verification failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }
    
    // Clean up old OTPs
    private function cleanupOldOTPs($email, $type = 'email') {
        try {
            if ($type === 'email') {
                $stmt = $this->conn->prepare("
                    DELETE FROM email_otp 
                    WHERE email = ? 
                    AND (expiresAt < NOW() OR createdAt < DATE_SUB(NOW(), INTERVAL 1 DAY))
                ");
                $stmt->execute([$email]);
            }
        } catch (PDOException $e) {
            error_log("OTP cleanup failed: " . $e->getMessage());
        }
    }
    
    // Resend OTP
    public function resendEmailOTP($email, $userID = null) {
        return $this->createEmailOTP($email, $userID);
    }
}
?>