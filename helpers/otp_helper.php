<?php
// helpers/otp_helper.php

require_once 'database/db_connect.php';

class OTPHelper
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function generateOTP()
    {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function createEmailOTP($email, $userID = null)
    {
        $this->cleanupOldOTPs($email);

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

    public function verifyEmailOTP($email, $otpCode)
    {
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

            if (strtotime($otpRecord['expiresAt']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired'];
            }

            $this->markOTPVerified($otpRecord['emailOtpID']);
            $this->updateUserVerification($otpRecord['userID']);

            return ['success' => true, 'userID' => $otpRecord['userID']];
        } catch (PDOException $e) {
            error_log("OTP verification failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }

    private function cleanupOldOTPs($email)
    {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM email_otp 
                WHERE email = ? 
                AND (expiresAt < NOW() OR createdAt < DATE_SUB(NOW(), INTERVAL 1 DAY))
            ");
            $stmt->execute([$email]);
        } catch (PDOException $e) {
            error_log("OTP cleanup failed: " . $e->getMessage());
        }
    }

    private function markOTPVerified($otpID)
    {
        $stmt = $this->conn->prepare("
            UPDATE email_otp 
            SET verified = 1 
            WHERE emailOtpID = ?
        ");
        $stmt->execute([$otpID]);
    }

    private function updateUserVerification($userID)
    {
        if ($userID) {
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET emailVerified = 1 
                WHERE userID = ?
            ");
            $stmt->execute([$userID]);
        }
    }

    public function resendEmailOTP($email, $userID = null)
    {
        return $this->createEmailOTP($email, $userID);
    }
}
