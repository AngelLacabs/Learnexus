<?php
// config/sms_config.php

class SMSSender
{
    private $gateway_url;
    private $username;
    private $password;

    public function __construct()
    {
        // Update these with your SMS gateway configuration
        $this->gateway_url = "http://192.168.0.251:8080"; // Your SMS gateway URL
        $this->username = "sms"; // Your SMS gateway username
        $this->password = "88888888"; // Your SMS gateway password
    }

    public function sendOTP($phoneNumber, $toName, $otpCode)
    {
        try {
            $message = "Hello $toName,\nYour Learnexus verification code is: $otpCode\nValid for 10 minutes.\n\nDo not share this code with anyone.";

            $url = rtrim($this->gateway_url, '/') . '/messages';
            $payload = [
                "phoneNumbers" => [$phoneNumber],
                "message" => $message
            ];

            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Authorization: Basic ' . base64_encode("$this->username:$this->password")
                    ],
                    'content' => json_encode($payload)
                ]
            ];

            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);

            if ($response !== false) {
                return ['success' => true, 'message' => 'SMS sent successfully'];
            } else {
                throw new Exception('Failed to send SMS');
            }
        } catch (Exception $e) {
            error_log("SMS sending failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send SMS. Please try email verification.'];
        }
    }
}

function sendSMSOTP($phoneNumber, $toName, $otpCode)
{
    $smsSender = new SMSSender();
    return $smsSender->sendOTP($phoneNumber, $toName, $otpCode);
}
