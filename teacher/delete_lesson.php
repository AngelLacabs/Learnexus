<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$lessonID = $_GET['id'] ?? 0;
$courseID = $_GET['course_id'] ?? 0;
$teacherID = $_SESSION['user_id'];

// Verify ownership
$stmt = $conn->prepare("
    SELECT l.filename 
    FROM lessons l
    JOIN courses c ON l.courseID = c.courseID
    WHERE l.lessonID = ? AND c.teacherID = ?
");
$stmt->execute([$lessonID, $teacherID]);
$lesson = $stmt->fetch();

if ($lesson) {
    // Delete file
    if (file_exists('../' . $lesson['filename'])) {
        unlink('../' . $lesson['filename']);
    }
    
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM lessons WHERE lessonID = ?");
    $stmt->execute([$lessonID]);
}

header("Location: manage_course.php?id=$courseID");
exit();
?>