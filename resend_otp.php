<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';
require_once 'config/email_config.php';

if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_email'])) {
    header('Location: register.php');
    exit();
}

$email = $_SESSION['otp_email'];
$pendingData = $_SESSION['pending_registration'];

$otpHelper = new OTPHelper($conn);
$otpCode = $otpHelper->resendEmailOTP($email);

if ($otpCode) {
    $toName = $pendingData['firstName'] . ' ' . $pendingData['lastName'];
    $emailSent = sendEmailOTP($email, $toName, $otpCode);

    if ($emailSent) {
        $_SESSION['success'] = 'New OTP has been sent to your email!';
    } else {
        $_SESSION['error'] = 'Failed to resend OTP. Please try again.';
    }
} else {
    $_SESSION['error'] = 'Failed to generate new OTP. Please try again.';
}

header('Location: verify_email.php');
exit();
