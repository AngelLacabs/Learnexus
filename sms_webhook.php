<?php
// Webhook to receive incoming SMS from SMS Forwarder
require_once 'database/db_connect.php';

// Get the raw POST data
$rawData = file_get_contents('php://input');

// Log everything for debugging
$logFile = __DIR__ . '/sms_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Received SMS\n";
$logEntry .= "Raw Data: " . $rawData . "\n";
$logEntry .= "Headers: " . json_encode(getallheaders()) . "\n";
$logEntry .= "---\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Parse JSON data
$data = json_decode($rawData, true);

if ($data && isset($data['from']) && isset($data['text'])) {
    $from = $data['from'];
    $message = $data['text'];
    $sentStamp = $data['sentStamp'] ?? time();
    $receivedStamp = $data['receivedStamp'] ?? time();
    $sim = $data['sim'] ?? 'unknown';

    try {
        // Store incoming SMS in database
        $stmt = $conn->prepare("
            INSERT INTO sms_feedback (userPhone, message, receivedAt, status) 
            VALUES (?, ?, NOW(), 'unread')
        ");
        $stmt->execute([$from, $message]);

        // Log success
        error_log("SMS stored successfully from: $from");
        
        // Send success response
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'SMS received']);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    error_log("Invalid SMS data received");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}