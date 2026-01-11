<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';
require_once 'config/sms_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit();
}

$pendingData = $_SESSION['pending_registration'];
$phone = $pendingData['phone'];

try {
    $otpHelper = new OTPHelper($conn);
    $otpCode = $otpHelper->createSMSOTP($phone);

    if ($otpCode) {
        $toName = $pendingData['firstName'] . ' ' . $pendingData['lastName'];
        $smsSent = sendSMSOTP($phone, $toName, $otpCode);

        if ($smsSent['success']) {
            $_SESSION['otp_phone'] = $phone;
            unset($_SESSION['otp_email']);
            
            echo json_encode([
                'success' => true,
                'message' => 'SMS sent successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $smsSent['message']
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate OTP'
        ]);
    }
} catch (Exception $e) {
    error_log("Switch to SMS Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}