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

// Get lessons count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$lessonsCount = $stmt->fetch()['count'];

// Get quizzes count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quizzesCount = $stmt->fetch()['count'];

$totalContent = $lessonsCount + $quizzesCount;
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
        body { 
            background: #f8f9fa; 
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .top-nav { 
            background: white; 
            padding: 20px 40px; 
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #1e88e5;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover {
            color: #1e88e5;
        }
        .nav-links a.active {
            color: #1e88e5;
        }
        .search-bar {
            display: flex;
            gap: 10px;
        }
        .search-bar input {
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            width: 300px;
            font-size: 14px;
        }
        .search-bar button {
            padding: 10px 20px;
            background: #1e88e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-bar button:hover {
            background: #1565c0;
        }
        .container-main { 
            max-width: 1200px; 
            margin: 40px auto; 
            padding: 0 40px; 
        }
        .breadcrumb {
            margin-bottom: 20px;
            display: flex;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #1e88e5;
            text-decoration: none;
        }
        .course-layout { 
            display: grid; 
            grid-template-columns: 1fr 400px; 
            gap: 40px; 
        }
        .course-header {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .course-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #212121;
        }
        .course-header .description {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .instructor-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 0;
            border-top: 1px solid #f0f0f0;
            margin-top: 20px;
        }
        .instructor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e88e5;
            font-weight: 600;
            font-size: 18px;
        }
        .course-content-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .course-content-section h5 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .course-image { 
            width: 100%; 
            height: 250px; 
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #999;
            font-size: 48px;
            margin-bottom: 20px; 
        }
        .price-card { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
            position: sticky; 
            top: 20px; 
        }
        .price { 
            font-size: 36px; 
            font-weight: 700; 
            color: #1e88e5; 
            margin-bottom: 20px; 
        }
        .enroll-btn { 
            background: #1e88e5; 
            color: white; 
            padding: 16px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            width: 100%; 
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .enroll-btn:hover {
            background: #1565c0;
        }
        .enroll-btn.enrolled {
            background: #43a047;
        }
        .enroll-btn.enrolled:hover {
            background: #388e3c;
        }
        .includes-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .includes-section h6 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .includes-list { 
            list-style: none; 
            padding: 0; 
            margin: 0;
        }
        .includes-list li { 
            padding: 12px 0; 
            border-bottom: 1px solid #f0f0f0; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            color: #666;
        }
        .includes-list li:last-child {
            border-bottom: none;
        }
        .includes-list i {
            color: #1e88e5;
            font-size: 18px;
        }
        .course-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        .stat-item i {
            color: #1e88e5;
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="dashboard.php" class="logo">LEARNEXUS</a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="course_catalog.php" class="active">Course Catalog</a>
            <a href="my_courses.php">My Courses</a>
            <a href="ai_tutor.php">AI Tutor</a>
        </div>
        <div class="search-bar">
            <input type="text" placeholder="What do you want to learn today?">
            <button>Search</button>
        </div>
    </div>

    <div class="container-main">
        <div class="breadcrumb">
            <a href="course_catalog.php">Course Catalog</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($course['title']); ?></span>
        </div>

        <div class="course-layout">
            <div>
                <div class="course-header">
                    <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="description">
                        <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                    </p>
                    
                    <div class="course-stats">
                        <div class="stat-item">
                            <i class="bi bi-book"></i>
                            <span><?php echo $lessonsCount; ?> Lessons</span>
                        </div>
                        <div class="stat-item">
                            <i class="bi bi-clipboard-check"></i>
                            <span><?php echo $quizzesCount; ?> Quizzes</span>
                        </div>
                        <div class="stat-item">
                            <i class="bi bi-award"></i>
                            <span><?php echo $course['passingScore']; ?>% Passing Score</span>
                        </div>
                    </div>
                    
                    <div class="instructor-info">
                        <div class="instructor-avatar">
                            <?php echo strtoupper(substr($course['instructorName'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #999;">Instructor</div>
                            <div style="font-weight: 600; color: #212121;">
                                <?php echo htmlspecialchars($course['instructorName']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="course-content-section">
                    <h5>What you'll learn</h5>
                    <p style="color: #666; line-height: 1.6;">
                        This course includes <?php echo $lessonsCount; ?> comprehensive lessons and <?php echo $quizzesCount; ?> assessments to test your knowledge. 
                        You'll need to achieve a passing score of <?php echo $course['passingScore']; ?>% to complete the course and earn your certificate.
                    </p>
                    
                    <?php if ($totalContent > 0): ?>
                    <div style="margin-top: 20px;">
                        <h6 style="font-weight: 600; margin-bottom: 12px;">Course Content</h6>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php if ($lessonsCount > 0): ?>
                            <li style="padding: 10px 0; display: flex; align-items: center; gap: 10px; color: #666;">
                                <i class="bi bi-file-text" style="color: #1e88e5;"></i>
                                <span><?php echo $lessonsCount; ?> Reading Materials (PDF Documents)</span>
                            </li>
                            <?php endif; ?>
                            <?php if ($quizzesCount > 0): ?>
                            <li style="padding: 10px 0; display: flex; align-items: center; gap: 10px; color: #666;">
                                <i class="bi bi-clipboard-check" style="color: #1e88e5;"></i>
                                <span><?php echo $quizzesCount; ?> Assessments to Test Your Knowledge</span>
                            </li>
                            <?php endif; ?>
                            <li style="padding: 10px 0; display: flex; align-items: center; gap: 10px; color: #666;">
                                <i class="bi bi-trophy" style="color: #1e88e5;"></i>
                                <span>Certificate of Completion</span>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="course-image">
                    <i class="bi bi-book"></i>
                </div>
                
                <div class="price-card">
                    <div class="price">₱<?php echo number_format($course['price'], 2); ?></div>
                    
                    <?php if ($alreadyEnrolled): ?>
                        <button class="enroll-btn enrolled" onclick="window.location.href='view_course.php?id=<?php echo $courseID; ?>'">
                            <i class="bi bi-check-circle"></i> Already Enrolled - Go to Course
                        </button>
                    <?php else: ?>
                        <button class="enroll-btn" onclick="window.location.href='checkout.php?course_id=<?php echo $courseID; ?>'">
                            <i class="bi bi-cart"></i> Enroll Now
                        </button>
                    <?php endif; ?>
                    
                    <div class="includes-section">
                        <h6>This course includes:</h6>
                        <ul class="includes-list">
                            <li>
                                <i class="bi bi-book"></i>
                                <span><?php echo $lessonsCount; ?> Lessons</span>
                            </li>
                            <li>
                                <i class="bi bi-clipboard-check"></i>
                                <span><?php echo $quizzesCount; ?> Quizzes</span>
                            </li>
                            
                            <li>
                                <i class="bi bi-award"></i>
                                <span>Certificate of Completion</span>
                            </li>
                            
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>