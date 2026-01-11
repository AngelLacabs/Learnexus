<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;

// Fetch course info
$stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ?");
$stmt->execute([$courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    echo "Course not found.";
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1><?php echo htmlspecialchars($course['title']); ?></h1>
    <p><?php echo htmlspecialchars($course['description']); ?></p>
    <a href="course_learn.php?id=<?php echo $courseID; ?>" class="btn btn-primary">Start Learning</a>
</div>
</body>
</html>
