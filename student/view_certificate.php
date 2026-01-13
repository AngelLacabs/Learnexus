<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invalid certificate ID');
}

$uuid   = $_GET['id'];
$userID = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        cert.*,
        c.title AS courseTitle,
        CONCAT(u.firstName, ' ', u.lastName) AS instructorName,
        e.completedAt
    FROM certificates cert
    JOIN enrollments e ON cert.enrollmentID = e.enrollmentID
    JOIN courses c ON cert.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    WHERE cert.certificateUUID = ?
      AND cert.userID = ?
");
$stmt->execute([$uuid, $userID]);
$certificate = $stmt->fetch();

if (!$certificate) {
    die('Certificate not found or access denied');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Certificate</title>
</head>
<body>
    <h1>Certificate of Completion</h1>
    <p><strong>Course:</strong> <?= htmlspecialchars($certificate['courseTitle']) ?></p>
    <p><strong>Instructor:</strong> <?= htmlspecialchars($certificate['instructorName']) ?></p>
    <p><strong>Issued:</strong> <?= date('F d, Y', strtotime($certificate['issuedAt'])) ?></p>

    <hr>
    <p>Certificate ID: <?= htmlspecialchars($certificate['certificateUUID']) ?></p>
</body>
</html>
