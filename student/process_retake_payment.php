<?php
session_start();
require_once '../database/db_connect.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$orderID = $data['orderID'] ?? null;
$courseID = (int)($data['courseID'] ?? 0);
$amountPHP = $data['amount'] ?? 0;

// Validate input
if (!$orderID || !$courseID || !$amountPHP) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$userID = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->beginTransaction();
    
    // 1. Get the enrollment
    $stmt = $conn->prepare("
        SELECT enrollmentID 
        FROM enrollments 
        WHERE userID = ? AND courseID = ?
    ");
    $stmt->execute([$userID, $courseID]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enrollment) {
        throw new Exception('Enrollment not found');
    }
    
    $enrollmentID = $enrollment['enrollmentID'];
    
    // 2. Create payment record for retake
    $stmt = $conn->prepare("
        INSERT INTO payments (enrollmentID, userID, courseID, amount, transactionReference, status, paymentDate, createdAt)
        VALUES (?, ?, ?, ?, ?, 'completed', NOW(), NOW())
    ");
    $stmt->execute([$enrollmentID, $userID, $courseID, $amountPHP, $orderID]);
    
    // 3. Reset progress to 0% and set status back to 'active'
    $stmt = $conn->prepare("
        UPDATE enrollments 
        SET progressPercentage = 0,
            status = 'active',
            completedAt = NULL
        WHERE enrollmentID = ?
    ");
    $stmt->execute([$enrollmentID]);
    
    // 4. Delete all lesson completions for this course
    $stmt = $conn->prepare("
        DELETE FROM lesson_completions 
        WHERE userID = ? 
        AND lessonID IN (
            SELECT lessonID FROM lessons WHERE courseID = ?
        )
    ");
    $stmt->execute([$userID, $courseID]);
    
    // 5. Delete previous quiz results (so they can retake the quiz)
    $stmt = $conn->prepare("
        DELETE FROM quiz_results 
        WHERE userID = ? 
        AND quizID IN (
            SELECT quizID FROM quizzes WHERE courseID = ?
        )
    ");
    $stmt->execute([$userID, $courseID]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment successful. You can now restart the course.',
        'orderID' => $orderID
    ]);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Retake Payment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Retake Payment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>