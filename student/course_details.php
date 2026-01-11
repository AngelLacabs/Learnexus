<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;
$userID = $_SESSION['user_id'];

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ?
");
$stmt->execute([$courseID]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: course_catalog.php');
    exit();
}

// Check if already enrolled
$stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
$alreadyEnrolled = $stmt->fetch();

// Get modules count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM modules WHERE courseID = ?");
$stmt->execute([$courseID]);
$modulesCount = $stmt->fetch()['count'];

// Get contents count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM contents c
    JOIN modules m ON c.moduleID = m.moduleID
    WHERE m.courseID = ?
");
$stmt->execute([$courseID]);
$contentsCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .top-nav { background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%); padding: 15px 40px; }
        .container-main { max-width: 1200px; margin: 40px auto; padding: 0 40px; }
        .course-layout { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }
        .course-image { width: 100%; height: 300px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #999; margin-bottom: 20px; }
        .price-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: sticky; top: 20px; }
        .price { font-size: 32px; font-weight: 700; color: #1e88e5; margin-bottom: 20px; }
        .enroll-btn { background: #1e88e5; color: white; padding: 15px; border: none; border-radius: 8px; font-weight: 600; width: 100%; font-size: 16px; }
        .includes-list { list-style: none; padding: 0; margin: 20px 0 0 0; }
        .includes-list li { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <div class="top-nav">
        <div style="font-size: 20px; font-weight: 700; color: #1a73e8;">LEARNEXUS</div>
        <div>
            <a href="dashboard.php" class="me-3">Dashboard</a>
            <a href="course_catalog.php" class="me-3 fw-bold" style="color: #1a73e8;">Course Catalog</a>
            <a href="my_courses.php" class="me-3">My Courses</a>
            <a href="ai_tutor.php" class="me-3">AI Tutor</a>
        </div>
        <div class="d-flex align-items-center gap-3">
            <input type="text" placeholder="What do you want to learn today?" style="padding: 8px 15px; border: 1px solid #e0e0e0; border-radius: 6px; width: 300px;">
            <button style="padding: 8px 20px; background: #1e88e5; color: white; border: none; border-radius: 6px;">Search</button>
        </div>
    </div>

    <div class="container-main">
        <div class="course-layout">
            <div>
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                <p style="color: #666; font-size: 18px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                </p>
                
                <div style="margin: 30px 0;">
                    <p><strong>Created by</strong> <?php echo htmlspecialchars($course['instructorName']); ?></p>
                </div>
                
                <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h5>What you'll learn</h5>
                    <p style="color: #666;">
                        <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                    </p>
                </div>
            </div>
            
            <div>
                <div class="course-image">
                    // photo
                </div>
                
                <div class="price-card">
                    <div class="price">₱<?php echo number_format($course['price'], 2); ?></div>
                    
                    <?php if ($alreadyEnrolled): ?>
                        <button class="enroll-btn" style="background: #43a047;" onclick="window.location.href='my_courses.php'">
                            Already Enrolled - Go to Course
                        </button>
                    <?php else: ?>
                        <button class="enroll-btn" onclick="window.location.href='checkout.php?course_id=<?php echo $courseID; ?>'">
                            Pay & Enroll Now
                        </button>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px;">
                        <h6>This course includes:</h6>
                        <ul class="includes-list">
                            <li>
                                <i class="bi bi-download" style="color: #1e88e5;"></i>
                                <?php echo $contentsCount; ?> downloadable resources
                            </li>
                            <li>
                                <i class="bi bi-tv" style="color: #1e88e5;"></i>
                                Passing Score: <?php echo $course['passingScore']; ?>%
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>