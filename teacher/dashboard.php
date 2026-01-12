<?php
session_start();
require_once '../database/db_connect.php';

// Ensure student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$studentID = $_SESSION['user_id'];

// Get student avatar
$stmt = $conn->prepare("SELECT avatar, firstName, lastName FROM users WHERE userID = ?");
$stmt->execute([$studentID]);
$user = $stmt->fetch();
$userAvatar = $user['avatar'];

// Get courses the student is enrolled in
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM modules WHERE courseID = c.courseID) as moduleCount,
           (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as studentCount
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    WHERE e.userID = ?
    ORDER BY c.createdAt DESC
");
$stmt->execute([$studentID]);
$enrolledCourses = $stmt->fetchAll();

// Calculate course completion based on modules
function calculateCourseCompletion($moduleCount) {
    if ($moduleCount == 0) return 0;
    if ($moduleCount >= 5) return 100;
    return ($moduleCount / 5) * 100;
}

// Get course quiz status
function getCourseStatus($courseID, $studentID, $conn) {
    $stmt = $conn->prepare("
        SELECT q.quizID, r.score, q.passMark
        FROM quizzes q
        LEFT JOIN quiz_results r ON q.quizID = r.quizID AND r.userID = ?
        WHERE q.courseID = ?
        ORDER BY q.createdAt DESC
        LIMIT 1
    ");
    $stmt->execute([$studentID, $courseID]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return "Not Attempted";
    } elseif ($result['score'] >= $result['passMark']) {
        return "Passed";
    } else {
        return "Failed – Retake requires payment";
    }
}

// Array of motivational phrases
$motivationalPhrases = [
    "Keep pushing forward—every step counts!",
    "Believe in yourself and all that you are!",
    "Success is the sum of small efforts repeated daily.",
    "Your dedication today shapes tomorrow's success.",
    "Stay focused and never give up!",
    "Every day is a new opportunity to learn and grow.",
    "Strive for progress, not perfection.",
    "Great things take time. Keep going!",
    "Consistency is the key to mastery.",
    "You have the power to create amazing results!"
];

$dayOfYear = date('z'); // 0-365
$dailyMotivation = $motivationalPhrases[$dayOfYear % count($motivationalPhrases)];

$page_title = "Continue Learning - Learnexus";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;}
        .main-content {padding: 20px 40px;}
        .welcome-banner {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 16px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(102,126,234,0.3);}
        .welcome-banner h2 {font-size: 32px; font-weight: 700; margin-bottom: 10px;}
        .course-grid {display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;}
        .course-card {background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s;}
        .course-card:hover {transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1);}
        .course-image {width: 100%; height: 180px; background: #e0e0e0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 14px; position: relative;}
        .course-progress-badge {position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.95); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #333;}
        .course-body {padding: 20px;}
        .course-title {font-size: 16px; font-weight: 600; color: #333; margin-bottom: 8px;}
        .course-meta {font-size: 13px; color: #666; margin-bottom: 15px;}
        .progress {height: 8px; border-radius: 10px; background: #f0f0f0; margin-bottom: 15px;}
        .progress-bar {background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 10px;}
        .status-badge {position: absolute; top: 12px; left: 12px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white;}
    </style>
</head>
<body>
<div class="main-content">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <h2><?php echo $dailyMotivation; ?></h2>
        <p>Continue Learning and track your progress!</p>
    </div>

    <!-- Enrolled Courses -->
    <h2 class="section-title">Continue Learning</h2>
    <div class="course-grid">
        <?php if (count($enrolledCourses) > 0): ?>
            <?php foreach ($enrolledCourses as $course): ?>
                <?php 
                    $completion = calculateCourseCompletion($course['moduleCount']);
                    $courseStatus = getCourseStatus($course['courseID'], $studentID, $conn);
                    $statusColor = ($courseStatus == 'Passed') ? 'green' : (($courseStatus == 'Not Attempted') ? 'gray' : 'red');
                ?>
                <div class="course-card">
                    <div class="course-image">
                        <span class="course-progress-badge"><?php echo round($completion); ?>%</span>
                        <span class="status-badge" style="background-color: <?php echo $statusColor; ?>;"><?php echo $courseStatus; ?></span>
                    </div>
                    <div class="course-body">
                        <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                        <div class="course-meta">
                            <i class="bi bi-people"></i> <?php echo $course['studentCount']; ?> students enrolled
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $completion; ?>%"></div>
                        </div>
                        <button class="btn btn-primary" onclick="window.location.href='course_learn.php?id=<?php echo $course['courseID']; ?>'">
                            Continue Course
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    You haven't enrolled in any courses yet. <a href="courses.php">Browse courses</a> to get started!
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
