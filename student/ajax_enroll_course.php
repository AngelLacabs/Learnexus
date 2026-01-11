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
    $stmt = $conn->prepare("
        INSERT INTO enrollments (userID, courseID)
        VALUES (?, ?)
    ");
    $stmt->execute([$userID, $courseID]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Already enrolled']);
}
