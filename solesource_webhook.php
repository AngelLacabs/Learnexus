<?php
/**
 * SoleSource Webhook Handler
 * Receives redemption notifications when students use vouchers at SoleSource checkout
 * 
 * Setup:
 * 1. Set SOLESOURCE_WEBHOOK_SECRET in .env (shared secret for webhook auth)
 * 2. Provide this URL to SoleSource: https://your-domain.com/solesource_webhook.php
 * 3. SoleSource will set COLLAB_WEBHOOK_URL=https://your-domain.com/solesource_webhook.php
 */

require_once __DIR__ . '/database/db_connect.php';

// Set response to JSON
header('Content-Type: application/json');

// Webhook secret (must match COLLAB_WEBHOOK_SECRET in SoleSource .env)
define('WEBHOOK_SECRET', 'learnexus_webhook_secret_2026');

// Log file for debugging
$logFile = __DIR__ . '/logs/solesource_webhook.log';
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

log_webhook('Webhook request received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'payload_size' => strlen($rawInput)
]);


// Validate payload structure
if (!is_array($data) || empty($data['code']) || empty($data['order-number'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid-payload']);
    log_webhook('ERROR: Invalid payload', ['raw' => substr($rawInput, 0, 200)]);
    exit;
}

// Verify webhook signature
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

log_webhook('Auth check', ['header' => $authHeader ? substr($authHeader, 0, 20) . '...' : 'missing']);

if (strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'missing-auth-header']);
    log_webhook('ERROR: Missing Authorization header');
    exit;
}

$receivedSecret = trim(substr($authHeader, 7));
if ($receivedSecret !== WEBHOOK_SECRET) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid-secret']);
    log_webhook('ERROR: Invalid webhook secret', ['received' => substr($receivedSecret, 0, 10) . '...']);
    exit;
}


// Extract webhook data
$voucherCode = $data['code'];
$orderNumber = $data['order-number'];
$redeemedAt = $data['redeemed-at'] ?? date('Y-m-d H:i:s');
$discountApplied = $data['discount-applied'] ?? 0;
$studentId = $data['student-id'] ?? null;

log_webhook('Processing redemption', [
    'code' => $voucherCode,
    'order' => $orderNumber,
    'student' => $studentId
]);

try {
    // Look up voucher in database
    $stmt = $conn->prepare("SELECT voucherID, userID, isUsed FROM vouchers WHERE voucherCode = ?");
    $stmt->execute([$voucherCode]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'voucher-not-found']);
        log_webhook('ERROR: Voucher not found', ['code' => $voucherCode]);
        exit;
    }

    // Check if already redeemed (idempotency - allow duplicate webhook calls)
    if ($voucher['isUsed'] == 1) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'already-redeemed', 'voucher_id' => $voucher['voucherID']]);
        log_webhook('WARNING: Duplicate redemption', ['code' => $voucherCode, 'order' => $orderNumber]);
        exit;
    }

    // Mark voucher as redeemed
    $redeemedAtFormatted = date('Y-m-d H:i:s', strtotime($redeemedAt));
    
    $updateStmt = $conn->prepare("
        UPDATE vouchers 
        SET isUsed = 1, 
            redeemed_order = ?,
            redeemed_at = ?
        WHERE voucherID = ?
    ");
    
    $updateStmt->execute([$orderNumber, $redeemedAtFormatted, $voucher['voucherID']]);

    http_response_code(200);
    echo json_encode([
        'ok' => true, 
        'voucher_id' => $voucher['voucherID'],
        'order_number' => $orderNumber,
        'redeemed_at' => $redeemedAtFormatted
    ]);
    
    log_webhook('SUCCESS: Voucher redeemed', [
        'code' => $voucherCode,
        'order' => $orderNumber,
        'discount' => $discountApplied
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'database-error', 'details' => $e->getMessage()]);
    log_webhook('ERROR: Database exception', ['message' => $e->getMessage()]);
}
