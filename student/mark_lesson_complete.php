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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$lessonID = intval($data['lessonID'] ?? 0);
$completed = intval($data['completed'] ?? 0);

if (!$lessonID) {
    echo json_encode(['success' => false, 'message' => 'Invalid lesson ID']);
    exit();
}

// Get the courseID for this lesson
$stmt = $conn->prepare("SELECT courseID FROM lessons WHERE lessonID = ?");
$stmt->execute([$lessonID]);
$courseID = $stmt->fetchColumn();

if (!$courseID) {
    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    exit();
}

// Add or remove completion
if ($completed) {
    $stmt = $conn->prepare("INSERT IGNORE INTO lesson_completions (userID, lessonID) VALUES (?, ?)");
    $stmt->execute([$userID, $lessonID]);
} else {
    $stmt = $conn->prepare("DELETE FROM lesson_completions WHERE userID = ? AND lessonID = ?");
    $stmt->execute([$userID, $lessonID]);
}

// Calculate new progress
$stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM lesson_completions WHERE userID = ? AND lessonID IN (SELECT lessonID FROM lessons WHERE courseID = ?)");
$stmt->execute([$userID, $courseID]);
$completedLessons = $stmt->fetchColumn();

$progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

// Update enrollment table
$stmt = $conn->prepare("UPDATE enrollments SET progressPercentage = ? WHERE userID = ? AND courseID = ?");
$stmt->execute([$progress, $userID, $courseID]);

// Return JSON response
echo json_encode(['success' => true, 'progress' => $progress]);
