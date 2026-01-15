<?php
session_start();
header('Content-Type: application/json');
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userID   = $_SESSION['user_id'];
$courseID = $_POST['courseID'] ?? null;

if (!$courseID) {
    echo json_encode(['success' => false, 'message' => 'Invalid course']);
    exit;
}

try {
    // Check if already enrolled first
    $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
    $stmt->execute([$userID, $courseID]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
        exit;
    }

    // Insert new enrollment
    $stmt = $conn->prepare("
        INSERT INTO enrollments (userID, courseID, status, enrolledAt)
        VALUES (?, ?, 'active', NOW())
    ");
    $stmt->execute([$userID, $courseID]);

    echo json_encode(['success' => true, 'message' => 'Enrollment successful']);

} catch (PDOException $e) {
    error_log("Enrollment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Enrollment failed: ' . $e->getMessage()]);
}
