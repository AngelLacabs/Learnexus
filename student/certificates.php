<?php
session_start();
require_once '../database/db_connect.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID  = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

/* =====================
   COURSE
===================== */
$stmt = $conn->prepare("SELECT title FROM courses WHERE courseID = ?");
$stmt->execute([$courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found.");
}

/* =====================
   LESSON COMPLETION CHECK
===================== */
$stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM lesson_completions 
    WHERE userID = ?
      AND lessonID IN (
        SELECT lessonID FROM lessons WHERE courseID = ?
      )
");
$stmt->execute([$userID, $courseID]);
$completedLessons = (int)$stmt->fetchColumn();

$allLessonsCompleted = $totalLessons > 0 && $completedLessons === $totalLessons;

/* =====================
   QUIZ CHECK
===================== */
$stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quizID = $stmt->fetchColumn();

$quizPassed = false;
if ($quizID) {
    $stmt = $conn->prepare("
        SELECT status 
        FROM quiz_results 
        WHERE userID = ? AND quizID = ?
    ");
    $stmt->execute([$userID, $quizID]);
    $quizPassed = $stmt->fetchColumn() === 'passed';
}

/* =====================
   HARD BLOCK (SECURITY)
===================== */
if (!$allLessonsCompleted || !$quizPassed) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Certificate Locked</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-warning text-center">
                <h4>Certificate Locked</h4>
                <p>
                    <?php if (!$allLessonsCompleted): ?>
                        Complete all lessons first.
                    <?php else: ?>
                        Pass the quiz to unlock your certificate.
                    <?php endif; ?>
                </p>
                <a href="course_learn.php?id=<?php echo $courseID; ?>" class="btn btn-secondary mt-2">
                    Go Back to Course
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate - <?php echo htmlspecialchars($course['title']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.certificate {
    max-width: 800px;
    margin: 50px auto;
    padding: 50px;
    text-align: center;
    border: 10px solid #1e88e5;
    border-radius: 15px;
    background: #f8f9fa;
}
.certificate h1 { font-size: 36px; margin-bottom: 20px; }
.certificate p { font-size: 18px; }
.btn-download { margin-top: 30px; }
</style>
</head>

<body>
<div class="container">
    <div class="certificate">
        <h1>Certificate of Completion</h1>
        <p>This certifies that</p>

        <h2>
            <?php echo htmlspecialchars($_SESSION['firstName'] ?? 'Student'); ?>
            <?php echo htmlspecialchars($_SESSION['lastName'] ?? ''); ?>
        </h2>

        <p>has successfully completed the course</p>
        <h3><?php echo htmlspecialchars($course['title']); ?></h3>

        <p>on <?php echo date('F d, Y'); ?></p>

        <a href="download_certificate.php?courseID=<?php echo $courseID; ?>"
           class="btn btn-primary btn-download">
            Download Certificate
        </a>
    </div>
</div>
</body>
</html>
