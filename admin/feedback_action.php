<?php
session_start();
require_once '../database/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_status':
            $feedbackID = (int)($_POST['feedback_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            // Validate status
            if (!in_array($status, ['unread', 'read', 'archived'])) {
                throw new Exception('Invalid status');
            }
            
            if ($feedbackID <= 0) {
                throw new Exception('Invalid feedback ID');
            }
            
            // Update status
            if ($status === 'read') {
                $stmt = $conn->prepare("
                    UPDATE sms_feedback 
                    SET status = ?, readAt = NOW() 
                    WHERE feedbackID = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    UPDATE sms_feedback 
                    SET status = ? 
                    WHERE feedbackID = ?
                ");
            }
            
            $stmt->execute([$status, $feedbackID]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback status updated successfully'
                ]);
            } else {
                throw new Exception('Feedback not found or no changes made');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Feedback Action Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
