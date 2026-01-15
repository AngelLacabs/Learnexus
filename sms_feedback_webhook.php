<?php
/**
 * SMS Feedback Webhook Handler
 * Receives SMS messages from users via SMS Forwarder
 * 
 * Setup:
 * 1. Configure SMS Forwarder with routing parameter: 09954778940
 * 2. Set webhook URL to: http://localhost/Learnexus/sms_feedback_webhook.php
 *    (For external access, use ngrok: ngrok http 80, then use the ngrok URL)
 * 3. JSON payload template: {"from":"%from%","text":"%text%","sim":"%sim%"}
 * 4. Headers: User-Agent: "SMS Forwarder App", Content-Type: "application/json"
 */

require_once __DIR__ . '/database/db_connect.php';

// Set response to JSON
header('Content-Type: application/json');

// Log file for debugging
$logFile = __DIR__ . '/logs/sms_feedback_webhook.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}

function log_webhook($message, $data = null) {
    global $logFile;
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data) {
        $entry .= ' | ' . json_encode($data, JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Read raw POST body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

log_webhook('SMS Feedback webhook request received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'payload_size' => strlen($rawInput),
    'raw_payload' => $rawInput
]);

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method-not-allowed']);
    log_webhook('ERROR: Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
    exit;
}

// Validate payload structure
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid-payload', 'message' => 'Payload must be valid JSON']);
    log_webhook('ERROR: Invalid JSON payload', ['raw' => substr($rawInput, 0, 500)]);
    exit;
}

// Validate required fields
if (empty($data['from']) || empty($data['text'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing-required-fields', 'message' => 'Missing required fields: from or text']);
    log_webhook('ERROR: Missing required fields', ['data' => $data]);
    exit;
}

// Extract webhook data
$fromNumber = trim($data['from']);
$message = trim($data['text']);
$simSlot = isset($data['sim']) ? trim($data['sim']) : null;

// Sanitize phone number (remove any non-digit characters except +)
$fromNumber = preg_replace('/[^0-9+]/', '', $fromNumber);

log_webhook('Processing SMS feedback', [
    'from' => $fromNumber,
    'message_length' => strlen($message),
    'sim' => $simSlot
]);

try {
    // Insert feedback into database
    $stmt = $conn->prepare("
        INSERT INTO sms_feedback (from_number, message, sim_slot, status, createdAt) 
        VALUES (?, ?, ?, 'unread', NOW())
    ");
    
    $stmt->execute([$fromNumber, $message, $simSlot]);
    $feedbackID = $conn->lastInsertId();

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'feedback_id' => $feedbackID,
        'message' => 'Feedback received successfully'
    ]);
    
    log_webhook('SUCCESS: SMS feedback saved', [
        'feedback_id' => $feedbackID,
        'from' => $fromNumber,
        'sim' => $simSlot
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'error' => 'database-error', 
        'message' => 'Failed to save feedback'
    ]);
    log_webhook('ERROR: Database exception', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
