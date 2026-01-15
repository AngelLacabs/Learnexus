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
            
            // Update status for ALL messages (both inbound and outbound)
            // Set readAt only when marking as 'read', clear it when marking as 'unread'
            if ($status === 'read') {
                $stmt = $conn->prepare("
                    UPDATE sms_feedback 
                    SET status = ?, readAt = NOW() 
                    WHERE feedbackID = ?
                ");
                $stmt->execute([$status, $feedbackID]);
            } elseif ($status === 'unread') {
                $stmt = $conn->prepare("
                    UPDATE sms_feedback 
                    SET status = ?, readAt = NULL 
                    WHERE feedbackID = ?
                ");
                $stmt->execute([$status, $feedbackID]);
            } else { // archived
                $stmt = $conn->prepare("
                    UPDATE sms_feedback 
                    SET status = ? 
                    WHERE feedbackID = ?
                ");
                $stmt->execute([$status, $feedbackID]);
            }
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback status updated successfully'
                ]);
            } else {
                throw new Exception('Feedback not found or no changes made');
            }
            break;
            
        case 'delete':
            $feedbackID = (int)($_POST['feedback_id'] ?? 0);
            
            if ($feedbackID <= 0) {
                throw new Exception('Invalid feedback ID');
            }
            
            // Check if feedback exists
            $checkStmt = $conn->prepare("SELECT feedbackID FROM sms_feedback WHERE feedbackID = ?");
            $checkStmt->execute([$feedbackID]);
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Feedback not found');
            }
            
            // Delete the feedback
            $stmt = $conn->prepare("DELETE FROM sms_feedback WHERE feedbackID = ?");
            $stmt->execute([$feedbackID]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete feedback');
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
