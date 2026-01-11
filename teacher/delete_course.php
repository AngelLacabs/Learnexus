<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;
$teacherID = $_SESSION['user_id'];

// Verify ownership
$stmt = $conn->prepare("SELECT courseID FROM courses WHERE courseID = ? AND teacherID = ?");
$stmt->execute([$courseID, $teacherID]);

if ($stmt->fetch()) {
    // Delete course (cascades to modules, enrollments, etc.)
    $stmt = $conn->prepare("DELETE FROM courses WHERE courseID = ?");
    $stmt->execute([$courseID]);
    
    $_SESSION['success'] = 'Course deleted successfully';
} else {
    $_SESSION['error'] = 'Course not found or unauthorized';
}

header('Location: courses.php');
exit();