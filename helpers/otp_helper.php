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
        $this->cleanupOldOTPs($email, 'email');

        $otpCode = $this->generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

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

    // NEW METHOD FOR SMS OTP
    public function createSMSOTP($phone, $userID = null)
    {
        $this->cleanupOldOTPs($phone, 'sms');

        $otpCode = $this->generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO sms_otp (phone, otpCode, expiresAt) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$phone, $otpCode, $expiresAt]);

            return $otpCode;
        } catch (PDOException $e) {
            error_log("SMS OTP creation failed: " . $e->getMessage());
            return false;
        }
    }

    // UPDATE verification method
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

            $this->markOTPVerified($otpRecord['emailOtpID'], 'email');
            $this->updateUserVerification($otpRecord['userID']);

            return ['success' => true, 'userID' => $otpRecord['userID']];
        } catch (PDOException $e) {
            error_log("Email OTP verification failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }

    // NEW METHOD: Verify SMS OTP
    public function verifySMSOTP($phone, $otpCode)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT otpID, expiresAt 
                FROM sms_otp 
                WHERE phone = ? 
                AND otpCode = ? 
                AND verified = 0 
                ORDER BY createdAt DESC 
                LIMIT 1
            ");
            $stmt->execute([$phone, $otpCode]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                return ['success' => false, 'message' => 'Invalid OTP code'];
            }

            if (strtotime($otpRecord['expiresAt']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired'];
            }

            $this->markOTPVerified($otpRecord['otpID'], 'sms');

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("SMS OTP verification failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }

    private function cleanupOldOTPs($identifier, $type = 'email')
    {
        try {
            if ($type === 'email') {
                $stmt = $this->conn->prepare("
                    DELETE FROM email_otp 
                    WHERE email = ? 
                    AND (expiresAt < NOW() OR createdAt < DATE_SUB(NOW(), INTERVAL 1 DAY))
                ");
            } else {
                $stmt = $this->conn->prepare("
                    DELETE FROM sms_otp 
                    WHERE phone = ? 
                    AND (expiresAt < NOW() OR createdAt < DATE_SUB(NOW(), INTERVAL 1 DAY))
                ");
            }
            $stmt->execute([$identifier]);
        } catch (PDOException $e) {
            error_log("OTP cleanup failed: " . $e->getMessage());
        }
    }

    private function markOTPVerified($otpID, $type = 'email')
    {
        if ($type === 'email') {
            $stmt = $this->conn->prepare("
                UPDATE email_otp 
                SET verified = 1 
                WHERE emailOtpID = ?
            ");
        } else {
            $stmt = $this->conn->prepare("
                UPDATE sms_otp 
                SET verified = 1 
                WHERE otpID = ?
            ");
        }
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

    // NEW METHOD: Resend SMS OTP
    public function resendSMSOTP($phone)
    {
        return $this->createSMSOTP($phone);
    }
}
