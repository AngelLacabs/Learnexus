<?php
// mark_lesson_complete.php
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

$lessonID = $_POST['lesson_id'] ?? 0;
$courseID = $_POST['course_id'] ?? 0;
$userID = $_SESSION['user_id'];

if (empty($lessonID) || empty($courseID)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Verify enrollment
    $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
    $stmt->execute([$userID, $courseID]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit();
    }

    // Check if already completed
    $stmt = $conn->prepare("SELECT id FROM lesson_completions WHERE userID = ? AND lessonID = ?");
    $stmt->execute([$userID, $lessonID]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Lesson already completed']);
        exit();
    }

    // Mark as complete
    $stmt = $conn->prepare("INSERT INTO lesson_completions (userID, lessonID, completedAt) VALUES (?, ?, NOW())");
    $stmt->execute([$userID, $lessonID]);

    // Update enrollment progress
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM lessons WHERE courseID = ?) as totalLessons,
            (SELECT COUNT(*) FROM lesson_completions lc 
             JOIN lessons l ON lc.lessonID = l.lessonID 
             WHERE l.courseID = ? AND lc.userID = ?) as completedLessons,
            (SELECT COUNT(*) FROM quizzes WHERE courseID = ?) as totalQuizzes,
            (SELECT COUNT(DISTINCT qr.quizID) FROM quizresults qr
             JOIN quizzes q ON qr.quizID = q.quizID
             WHERE q.courseID = ? AND qr.userID = ? AND qr.passed = 1) as passedQuizzes
    ");
    $stmt->execute([$courseID, $courseID, $userID, $courseID, $courseID, $userID]);
    $progress = $stmt->fetch();

    $totalItems = $progress['totalLessons'] + $progress['totalQuizzes'];
    $completedItems = $progress['completedLessons'] + $progress['passedQuizzes'];
    $progressPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;

    $stmt = $conn->prepare("UPDATE enrollments SET progressPercentage = ? WHERE userID = ? AND courseID = ?");
    $stmt->execute([$progressPercentage, $userID, $courseID]);

    echo json_encode([
        'success' => true, 
        'message' => 'Lesson marked as complete',
        'progress' => $progressPercentage
    ]);

} catch (PDOException $e) {
    error_log("Mark Complete Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>