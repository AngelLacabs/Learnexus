<?php
session_start();
require_once '../database/db_connect.php';

// Only allow logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userID = $_SESSION['user_id'];

// Read POST JSON
$data = json_decode(file_get_contents('php://input'), true);
$orderID  = $data['orderID'] ?? '';
$courseID = (int)($data['courseID'] ?? 0);
$amount   = (float)($data['amount'] ?? 0);

if (!$orderID || !$courseID || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// --- PAYPAL VERIFICATION ---
$paypalClientID     = 'YOUR_PAYPAL_CLIENT_ID';
$paypalSecret       = 'YOUR_PAYPAL_SECRET';
$paypalApiBase      = 'https://api-m.sandbox.paypal.com'; // sandbox; change for live

// Get access token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$paypalApiBase/v1/oauth2/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$paypalClientID:$paypalSecret");
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'PayPal auth failed']);
    exit();
}
$accessToken = $tokenData['access_token'];

// Get order details
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$paypalApiBase/v2/checkout/orders/$orderID");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
]);
$orderData = curl_exec($ch);
curl_close($ch);

$order = json_decode($orderData, true);

// Verify order status and amount
if (!isset($order['status']) || $order['status'] !== 'COMPLETED') {
    echo json_encode(['success' => false, 'message' => 'Payment not completed']);
    exit();
}

$paidAmount = $order['purchase_units'][0]['amount']['value'] ?? 0;
if (round((float)$paidAmount) != round($amount)) {
    echo json_encode(['success' => false, 'message' => 'Amount mismatch']);
    exit();
}

// --- RESET COURSE PROGRESS ---
try {
    // Reset progress
    $stmt = $conn->prepare("UPDATE course_progress SET progress=0, passed=0, quiz_score=NULL WHERE userID=? AND courseID=?");
    $stmt->execute([$userID, $courseID]);

    // Optional: store payment info
    $stmt = $conn->prepare("INSERT INTO retake_payments (userID, courseID, amount, paypal_order_id, createdAt) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userID, $courseID, $amount, $orderID]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
