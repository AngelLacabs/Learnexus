<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';
require_once 'config/sms_config.php';

if (!isset($_SESSION['pending_registration'])) {
    header('Location: register.php');
    exit();
}

$pendingData = $_SESSION['pending_registration'];
$phone = $pendingData['phone'];

$otpHelper = new OTPHelper($conn);
$otpCode = $otpHelper->createSMSOTP($phone);

if ($otpCode) {
    $toName = $pendingData['firstName'] . ' ' . $pendingData['lastName'];
    $smsSent = sendSMSOTP($phone, $toName, $otpCode);

    if ($smsSent['success']) {
        $_SESSION['otp_phone'] = $phone;
        unset($_SESSION['otp_email']); // Remove email OTP session
        $_SESSION['success'] = 'OTP has been sent to your phone! Please check your messages.';
        header('Location: verify_sms.php');
        exit();
    } else {
        // If SMS fails, stay with email
        $_SESSION['error'] = 'Failed to send SMS. Please try email verification again.';
        header('Location: verify_email.php');
        exit();
    }
} else {
    $_SESSION['error'] = 'Failed to generate SMS OTP. Please try again.';
    header('Location: verify_email.php');
    exit();
}
