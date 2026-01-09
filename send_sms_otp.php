<?php
session_start();
require_once 'database/db_connect.php'; // Add this
header('Content-Type: application/json');

if (!isset($_SESSION['pending_registration'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$phoneNumber = $_POST['phone_number'] ?? '';
$pendingData = $_SESSION['pending_registration'];

// Generate OTP and store in database
require_once 'helpers/otp_helper.php';
$otpHelper = new OTPHelper($conn);
$otpCode = $otpHelper->createSMSOTP($phoneNumber); // Store in database

if (!$otpCode) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate OTP']);
    exit();
}

// Also store in session for fallback
$_SESSION['sms_otp'] = $otpCode;
$_SESSION['sms_phone'] = $phoneNumber;

// Send SMS using your gateway
$gateway_url = "http://192.168.18.217:8080";
$username = "sms";
$password = "OBRAuro1";

$message = "Your LEARNEXUS verification code is: $otpCode";

$url = rtrim($gateway_url, '/') . '/messages';
$payload = [
    'phoneNumbers' => [$phoneNumber],
    'message' => $message
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$password")
        ],
        'content' => json_encode($payload)
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response !== false) {
    echo json_encode(['success' => true, 'message' => 'SMS sent successfully', 'otp' => $otpCode]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send SMS']);
}
?>