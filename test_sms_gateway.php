<?php
// Test if SMS Gateway is accessible
$phone_ip = "192.168.18.217";
$port = "8080";

// Test 1: Check if server is reachable
$socket = @fsockopen($phone_ip, $port, $errno, $errstr, 5);
if ($socket) {
    echo "✅ SMS Gateway server is reachable at $phone_ip:$port<br>";
    fclose($socket);
} else {
    echo "❌ SMS Gateway server NOT reachable: $errstr ($errno)<br>";
}

// Test 2: Try to send actual SMS
$gateway_url = "http://$phone_ip:$port";
$username = "sms";
$password = "OBRAuro1";
$recipient = "09940695628"; // Your test number
$test_otp = rand(100000, 999999);
$message = "Test SMS: $test_otp";

$url = rtrim($gateway_url, '/') . '/messages';
$payload = [
    'phoneNumbers' => [$recipient],
    'message' => $message
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$password")
        ],
        'content' => json_encode($payload),
        'timeout' => 10
    ]
];

$context = stream_context_create($options);

echo "<br>Trying to send SMS to: $recipient<br>";
echo "Message: $message<br><br>";

$response = @file_get_contents($url, false, $context);
$http_response_header = $http_response_header ?? [];

echo "Response:<br>";
if ($response === false) {
    echo "❌ Failed to send SMS<br>";
    echo "Error: " . error_get_last()['message'] ?? 'Unknown error';
} else {
    echo "✅ SMS sent (API Response): " . htmlspecialchars($response) . "<br>";
}

echo "<br>HTTP Headers:<br>";
print_r($http_response_header);
?>