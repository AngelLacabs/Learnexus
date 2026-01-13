<?php
session_start();
require_once '../database/db_connect.php';

header('Content-Type: application/json');

// Only allow logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userID = $_SESSION['user_id'];

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);
$lessonID  = (int)($data['lessonID'] ?? 0);
$completed = (int)($data['completed'] ?? 1);

if (!$lessonID) {
    echo json_encode(['success' => false, 'message' => 'Invalid lesson ID']);
    exit();
}

/* -------------------------------------------
   1️⃣ Get courseID from lesson
-------------------------------------------- */
$stmt = $conn->prepare("SELECT courseID FROM lessons WHERE lessonID = ?");
$stmt->execute([$lessonID]);
$courseID = $stmt->fetchColumn();

if (!$courseID) {
    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    exit();
}

/* -------------------------------------------
   2️⃣ Get enrollment + 🔒 GUARD
-------------------------------------------- */
$stmt = $conn->prepare("SELECT enrollmentID, status FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
    exit();
}

// 🔒 CRITICAL GUARD: Do not update completed courses
if ($enrollment['status'] === 'completed') {
    echo json_encode([
        'success' => false,
        'message' => 'Course already completed'
    ]);
    exit();
}

/* -------------------------------------------
   3️⃣ Add or remove lesson completion
-------------------------------------------- */
if ($completed) {
    $stmt = $conn->prepare("INSERT IGNORE INTO lesson_completions (userID, lessonID, completedAt) VALUES (?, ?, NOW())");
    $stmt->execute([$userID, $lessonID]);
} else {
    $stmt = $conn->prepare("DELETE FROM lesson_completions WHERE userID = ? AND lessonID = ?");
    $stmt->execute([$userID, $lessonID]);
}

/* -------------------------------------------
   4️⃣ Recalculate progress
-------------------------------------------- */
$stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM lesson_completions lc
    JOIN lessons l ON lc.lessonID = l.lessonID
    WHERE lc.userID = ? AND l.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$completedLessons = (int)$stmt->fetchColumn();

$progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

/* -------------------------------------------
   5️⃣ Update enrollment (LOCK if completed)
-------------------------------------------- */
if ($progress >= 100) {
    // Mark course as completed
    $stmt = $conn->prepare("
        UPDATE enrollments
        SET progressPercentage = 100,
            status = 'completed',
            completedAt = NOW()
        WHERE enrollmentID = ?
    ");
    $stmt->execute([$enrollment['enrollmentID']]);
} else {
    // Update progress only
    $stmt = $conn->prepare("
        UPDATE enrollments
        SET progressPercentage = ?
        WHERE enrollmentID = ?
    ");
    $stmt->execute([$progress, $enrollment['enrollmentID']]);
}

/* -------------------------------------------
   6️⃣ Return JSON response
-------------------------------------------- */
echo json_encode([
    'success' => true,
    'progress' => $progress
]);
