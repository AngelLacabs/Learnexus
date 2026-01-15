<?php
session_start();
require_once '../database/db_connect.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userID = $_SESSION['user_id'];

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);
$lessonID = (int) ($data['lessonID'] ?? 0);
$completed = (int) ($data['completed'] ?? 1);

if (!$lessonID) {
    echo json_encode(['success' => false, 'message' => 'Invalid lesson ID']);
    exit();
}

// Get course ID from lesson
$stmt = $conn->prepare("SELECT courseID FROM lessons WHERE lessonID = ?");
$stmt->execute([$lessonID]);
$courseID = $stmt->fetchColumn();

if (!$courseID) {
    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    exit();
}

// Get enrollment
$stmt = $conn->prepare("SELECT enrollmentID, status FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
    exit();
}

// Don't allow changes if already completed (unless manually resetting)
if ($enrollment['status'] === 'completed' && $completed == 1) {
    echo json_encode([
        'success' => true,
        'message' => 'Course already completed',
        'progress' => 100,
        'alreadyCompleted' => true
    ]);
    exit();
}

// Mark or unmark lesson
if ($completed) {
    $stmt = $conn->prepare("INSERT IGNORE INTO lessoncompletion (userID, lessonID, completedAt) VALUES (?, ?, NOW())");
    $stmt->execute([$userID, $lessonID]);
} else {
    $stmt = $conn->prepare("DELETE FROM lessoncompletion WHERE userID = ? AND lessonID = ?");
    $stmt->execute([$userID, $lessonID]);
}

// === UNIFIED PROGRESS CALCULATION (Same as my_courses.php and course_learn.php) ===

// Get total lessons
$stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = (int) $stmt->fetchColumn();

// Get completed lessons
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM lessoncompletion lc
    JOIN lessons l ON lc.lessonID = l.lessonID
    WHERE lc.userID = ? AND l.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$completedLessons = (int) $stmt->fetchColumn();

// Get quiz info
$stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quizID = $stmt->fetchColumn();

// Check if quiz passed
$quizPassed = false;
if ($quizID) {
    $stmt = $conn->prepare("
        SELECT status 
        FROM quizresults 
        WHERE userID = ? AND quizID = ?
        ORDER BY takenAt DESC
        LIMIT 1
    ");
    $stmt->execute([$userID, $quizID]);
    $quizStatus = $stmt->fetchColumn();
    $quizPassed = ($quizStatus === 'passed');
}

// UNIFIED PROGRESS CALCULATION
$totalSteps = $totalLessons + ($quizID ? 1 : 0);
$completedSteps = $completedLessons;
if ($quizPassed) {
    $completedSteps++;
}
$progress = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;

// === CRITICAL FIX: Only mark as completed when BOTH conditions are met ===
$allLessonsCompleted = ($totalLessons > 0) && ($completedLessons === $totalLessons);
$quizRequirementMet = !$quizID || $quizPassed; // No quiz OR quiz passed

// IMPORTANT: Course is only complete when ALL lessons done AND quiz requirement met
$shouldMarkComplete = $allLessonsCompleted && $quizRequirementMet;

// Update enrollment status
if ($shouldMarkComplete) {
    // Mark as completed
    $stmt = $conn->prepare("
        UPDATE enrollments
        SET progressPercentage = ?,
            status = 'completed',
            completedAt = NOW()
        WHERE enrollmentID = ? AND status != 'completed'
    ");
    $stmt->execute([100, $enrollment['enrollmentID']]);
    
    $newStatus = 'completed';
} else {
    // Still active - just update progress
    $stmt = $conn->prepare("
        UPDATE enrollments
        SET progressPercentage = ?,
            status = 'active',
            completedAt = NULL
        WHERE enrollmentID = ?
    ");
    $stmt->execute([$progress, $enrollment['enrollmentID']]);
    
    $newStatus = 'active';
}

// Return success with detailed info
echo json_encode([
    'success' => true,
    'progress' => $progress,
    'completedLessons' => $completedLessons,
    'totalLessons' => $totalLessons,
    'allLessonsCompleted' => $allLessonsCompleted,
    'quizPassed' => $quizPassed,
    'hasQuiz' => (bool) $quizID,
    'enrollmentStatus' => $newStatus,
    'shouldMarkComplete' => $shouldMarkComplete,
    'debug' => [
        'totalSteps' => $totalSteps,
        'completedSteps' => $completedSteps,
        'quizRequirementMet' => $quizRequirementMet
    ]
]);
?>