<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';
require_once 'config/sms_config.php';

if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_phone'])) {
    header('Location: register.php');
    exit();
}

$phone = $_SESSION['otp_phone'];
$pendingData = $_SESSION['pending_registration'];

$otpHelper = new OTPHelper($conn);
$otpCode = $otpHelper->resendSMSOTP($phone);

if ($otpCode) {
    $toName = $pendingData['firstName'] . ' ' . $pendingData['lastName'];
    $smsSent = sendSMSOTP($phone, $toName, $otpCode);

    if ($smsSent['success']) {
        $_SESSION['success'] = 'New OTP has been sent to your phone!';
    } else {
        $_SESSION['error'] = 'Failed to resend OTP. Please try again or use email verification.';
    }
} else {
    $_SESSION['error'] = 'Failed to generate new OTP. Please try again.';
}

header('Location: verify_sms.php');
exit();
