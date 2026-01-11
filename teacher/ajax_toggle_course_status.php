<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$courseID = $data['courseID'] ?? 0;
$action = $data['action'] ?? '';

$teacherID = $_SESSION['user_id'];

if (!$courseID || !in_array($action, ['publish', 'unpublish'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Check if course belongs to this instructor
$stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ? AND teacherID = ?");
$stmt->execute([$courseID, $teacherID]);
$course = $stmt->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'message' => 'Course not found']);
    exit();
}

// Update course status
$newStatus = $action === 'publish' ? 'published' : 'draft';
$updateStmt = $conn->prepare("UPDATE courses SET status = ? WHERE courseID = ?");
$updateStmt->execute([$newStatus, $courseID]);

echo json_encode([
    'success' => true,
    'message' => $action === 'publish' ? 'Course published successfully' : 'Course unpublished successfully'
]);
