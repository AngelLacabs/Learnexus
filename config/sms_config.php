<?php
// SMS Gateway Configuration
require_once __DIR__ . '/../helpers/env.php';
define('SMS_GATEWAY_URL', getenv('SMS_GATEWAY_URL') ?: 'http://192.168.8.131:8080');
define('SMS_USERNAME', getenv('SMS_USERNAME') ?: 'sms');
define('SMS_PASSWORD', getenv('SMS_PASSWORD') ?: 'OBRAuro1');
define('SMS_DEVICE_ID', getenv('SMS_DEVICE_ID') ?: '0000000055ecf0860000019ba379');
define('SMS_SENDER_NUMBER', getenv('SMS_SENDER_NUMBER') ?: '09954778940');

function sendSMSOTP($phoneNumber, $userName, $otpCode)
{
    $message = "Hello $userName! Your LEARNEXUS verification code is: $otpCode. The OTP will expire in 10 minutes.";
    
    $url = SMS_GATEWAY_URL . '/messages';
    
    $payload = [
        'phoneNumbers' => [$phoneNumber],
        'message' => $message
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(SMS_USERNAME . ':' . SMS_PASSWORD)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log the attempt
    error_log("SMS Send Attempt - Phone: $phoneNumber, HTTP Code: $httpCode, Response: $response");
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'response' => $response
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to send SMS: ' . ($error ?: 'Unknown error'),
            'http_code' => $httpCode,
            'response' => $response
        ];
    }
}