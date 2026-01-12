<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;
$userID = $_SESSION['user_id'];

// CHECK IF STUDENT FAILED THE QUIZ - BLOCK ACCESS
$stmt = $conn->prepare("
    SELECT qr.passed, qr.status 
    FROM quiz_results qr
    JOIN quizzes q ON qr.quizID = q.quizID
    WHERE q.courseID = ? AND qr.userID = ?
    ORDER BY qr.takenAt DESC
    LIMIT 1
");
$stmt->execute([$courseID, $userID]);
$quizResult = $stmt->fetch();

// If student failed the quiz, redirect them to payment page
if ($quizResult && $quizResult['status'] == 'failed' && $quizResult['passed'] == 0) {
    $_SESSION['error'] = "You must pay to retake this course after failing the quiz.";
    header('Location: retake_course.php?id=' . $courseID);
    exit();
}

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
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .course-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .btn-start {
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="course-header">
    <div class="container">
        <a href="dashboard.php" class="btn btn-light mb-3">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="display-4"><?php echo htmlspecialchars($course['title']); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($course['category'] ?? 'General'); ?></p>
    </div>
</div>

<div class="container mb-5">
    <div class="course-content">
        <h3 class="mb-4">Course Overview</h3>
        <p class="lead"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
        
        <hr class="my-4">
        
        <div class="d-grid gap-2 col-md-6 mx-auto">
            <a href="course_learn.php?id=<?php echo $courseID; ?>" class="btn btn-primary btn-start">
                <i class="bi bi-play-circle"></i> Start Learning
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>