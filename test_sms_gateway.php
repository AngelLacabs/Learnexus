<?php
require_once 'config/sms_config.php';

echo "<h2>SMS Gateway Test</h2>";

// Test 1: Check if gateway is reachable
echo "<h3>Test 1: Gateway Connection</h3>";
$ch = curl_init(SMS_GATEWAY_URL . '/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode > 0) {
    echo "✅ Gateway is reachable at " . SMS_GATEWAY_URL . "<br>";
    echo "HTTP Code: $httpCode<br>";
} else {
    echo "❌ Gateway is NOT reachable<br>";
    echo "Error: " . curl_error($ch) . "<br>";
}

// Test 2: Send actual SMS
echo "<h3>Test 2: Send Test SMS</h3>";
$testPhone = '09387450528'; // Your test number
$testOTP = rand(100000, 999999);

echo "Sending test SMS to: $testPhone<br>";
echo "Test OTP: $testOTP<br><br>";

$result = sendSMSOTP($testPhone, 'Test User', $testOTP);

echo "<strong>Result:</strong><br>";
echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "<br>";
echo "Message: " . $result['message'] . "<br>";

if (isset($result['http_code'])) {
    echo "HTTP Code: " . $result['http_code'] . "<br>";
}

if (isset($result['response'])) {
    echo "Response: <pre>" . htmlspecialchars($result['response']) . "</pre><br>";
}

// Test 3: Configuration Check
echo "<h3>Test 3: Configuration</h3>";
echo "Gateway URL: " . SMS_GATEWAY_URL . "<br>";
echo "Username: " . SMS_USERNAME . "<br>";
echo "Device ID: " . SMS_DEVICE_ID . "<br>";
echo "Sender Number: " . SMS_SENDER_NUMBER . "<br>";

// Test 4: Check if webhook is accessible
echo "<h3>Test 4: Webhook Check</h3>";
$webhookUrl = "http://192.168.18.34:8000/sms_webhook.php";
echo "Webhook URL: $webhookUrl<br>";
echo "Make sure your SMS Forwarder is configured to send to this URL<br>";

// Test 5: Recent SMS logs
echo "<h3>Test 5: Recent SMS Logs</h3>";
$logFile = __DIR__ . '/sms_log.txt';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $lastLogs = array_slice(explode("---\n", $logs), -5);
    echo "<pre>" . htmlspecialchars(implode("---\n", $lastLogs)) . "</pre>";
} else {
    echo "No SMS logs found yet.<br>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>If Test 1 fails, check if your phone's SMS Gateway app is running</li>";
echo "<li>If Test 2 fails, check the username/password in config/sms_config.php</li>";
echo "<li>Make sure your phone and laptop are on the same network</li>";
echo "<li>Check if SMS Forwarder webhook is pointing to: $webhookUrl</li>";
echo "</ol>";