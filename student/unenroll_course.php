<?php
// unenroll_course.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$enrollmentID = $_POST['enrollment_id'] ?? 0;
$userID = $_SESSION['user_id'];

if (empty($enrollmentID)) {
    echo json_encode(['success' => false, 'message' => 'Missing enrollment ID']);
    exit();
}

try {
    // Verify that this enrollment belongs to the current user
    $stmt = $conn->prepare("
        SELECT e.enrollmentID, e.courseID, c.title, p.paymentID, p.amount
        FROM enrollments e
        JOIN courses c ON e.courseID = c.courseID
        LEFT JOIN payments p ON e.paymentID = p.paymentID
        WHERE e.enrollmentID = ? AND e.userID = ?
    ");
    $stmt->execute([$enrollmentID, $userID]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
        exit();
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // Delete lesson completions for this enrollment
        $stmt = $conn->prepare("
            DELETE lc FROM lesson_completions lc
            JOIN lessons l ON lc.lessonID = l.lessonID
            WHERE l.courseID = ? AND lc.userID = ?
        ");
        $stmt->execute([$enrollment['courseID'], $userID]);

        // Delete quiz results for this enrollment
        $stmt = $conn->prepare("
            DELETE qr FROM quiz_results qr
            JOIN quizzes q ON qr.quizID = q.quizID
            WHERE q.courseID = ? AND qr.userID = ?
        ");
        $stmt->execute([$enrollment['courseID'], $userID]);

        // Delete certificates if any
        $stmt = $conn->prepare("
            DELETE FROM certificates 
            WHERE enrollmentID = ? AND userID = ?
        ");
        $stmt->execute([$enrollmentID, $userID]);

        // Delete the enrollment
        $stmt = $conn->prepare("
            DELETE FROM enrollments 
            WHERE enrollmentID = ? AND userID = ?
        ");
        $stmt->execute([$enrollmentID, $userID]);

        // Note: We're NOT deleting the payment record for financial tracking purposes
        // But we could update it to mark as "refunded" if needed
        if (!empty($enrollment['paymentID'])) {
            // Optional: Update payment status
            // $stmt = $conn->prepare("UPDATE payments SET status = 'refunded' WHERE paymentID = ?");
            // $stmt->execute([$enrollment['paymentID']]);
        }

        // Commit transaction
        $conn->commit();

        // Log the unenrollment
        error_log("User {$userID} unenrolled from course {$enrollment['courseID']} (Enrollment ID: {$enrollmentID})");

        echo json_encode([
            'success' => true,
            'message' => 'Successfully unenrolled from course',
            'courseTitle' => $enrollment['title']
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Unenroll Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while processing unenrollment'
    ]);
}
?>