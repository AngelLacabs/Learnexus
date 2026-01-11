<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';
require_once 'config/email_config.php';

if (!isset($_SESSION['pending_registration'])) {
    header('Location: register.php');
    exit();
}

$pendingData = $_SESSION['pending_registration'];
$email = $pendingData['email'];

try {
    $otpHelper = new OTPHelper($conn);
    
    // Check if email OTP already exists
    if (!isset($_SESSION['otp_email'])) {
        $otpCode = $otpHelper->createEmailOTP($email);
    } else {
        $otpCode = $otpHelper->resendEmailOTP($email);
    }

    if ($otpCode) {
        $toName = $pendingData['firstName'] . ' ' . $pendingData['lastName'];
        $emailSent = sendEmailOTP($email, $toName, $otpCode);

        if ($emailSent) {
            $_SESSION['otp_email'] = $email;
            unset($_SESSION['otp_phone']);
            $_SESSION['success'] = 'OTP has been sent to your email!';
            header('Location: verify_email.php');
            exit();
        } else {
            $_SESSION['error'] = 'Failed to send email. Please try SMS verification.';
            header('Location: verify_sms.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'Failed to generate email OTP. Please try again.';
        header('Location: verify_sms.php');
        exit();
    }
} catch (Exception $e) {
    error_log("Switch to Email Error: " . $e->getMessage());
    $_SESSION['error'] = 'System error. Please try again.';
    header('Location: verify_sms.php');
    exit();
}