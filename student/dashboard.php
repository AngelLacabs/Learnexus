<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get courses in progress
$stmt = $conn->prepare("
    SELECT c.*, e.progressPercentage, e.enrolledAt, e.status,
           CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    WHERE e.userID = ? AND e.status = 'active'
    ORDER BY e.enrolledAt DESC
    LIMIT 3
");
$stmt->execute([$userID]);
$inProgressCourses = $stmt->fetchAll();

// Get completed courses count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM enrollments 
    WHERE userID = ? AND status = 'completed'
");
$stmt->execute([$userID]);
$completedCount = $stmt->fetch()['count'];

// Get certificates count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM certificates 
    WHERE userID = ?
");
$stmt->execute([$userID]);
$certificatesCount = $stmt->fetch()['count'];

// Calculate weekly learning goal (80% hardcoded for now)
$weeklyGoalPercentage = 80;

$page_title = "Dashboard - Learnexus";
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
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%);
            padding-top: 20px;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .sidebar .brand {
            font-size: 20px;
            font-weight: 700;
            color: #1a73e8;
            padding: 0 20px;
            margin-bottom: 40px;
        }
        
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(26, 115, 232, 0.1);
            color: #1a73e8;
        }
        
        .sidebar .nav-link.active {
            background: #1a73e8;
            color: white;
        }
        
        .sidebar .nav-link i {
            font-size: 20px;
            width: 24px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px 40px;
        }
        
        .header-section {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .search-box {
            position: relative;
            width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-icon {
            position: relative;
            font-size: 22px;
            color: #666;
            cursor: pointer;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff5252;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-banner h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-banner p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }
        
        .stat-card.blue .icon {
            background: #e3f2fd;
            color: #1e88e5;
        }
        
        .stat-card.green .icon {
            background: #e8f5e9;
            color: #43a047;
        }
        
        .stat-card.orange .icon {
            background: #fff3e0;
            color: #fb8c00;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #333;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .course-image {
            width: 100%;
            height: 180px;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            position: relative;
        }
        
        .course-progress-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.95);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #333;
        }
        
        .course-body {
            padding: 20px;
        }
        
        .course-category {
            font-size: 12px;
            color: #1e88e5;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .course-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .course-instructor {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
            background: #f0f0f0;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }
        
        .btn-resume {
            background: #1e88e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: background 0.2s;
        }
        
        .btn-resume:hover {
            background: #1565c0;
            color: white;
        }
        
        .logout-btn {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px);
            background: white;
            border: 1px solid #e0e0e0;
            color: #666;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .logout-btn:hover {
            background: #f5f5f5;
            border-color: #ccc;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">LEARNEXUS</div>
        
        <nav class="nav flex-column">
            <a class="nav-link active" href="dashboard.php">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <a class="nav-link" href="course_catalog.php">
                <i class="bi bi-book"></i> Course Catalog
            </a>
            <a class="nav-link" href="my_courses.php">
                <i class="bi bi-journal-bookmark"></i> My Courses
            </a>
            <a class="nav-link" href="certificates.php">
                <i class="bi bi-award"></i> Certificates
            </a>
            <a class="nav-link" href="vouchers.php">
                <i class="bi bi-ticket-perforated"></i> Vouchers
            </a>
            <a class="nav-link" href="settings.php">
                <i class="bi bi-gear"></i> Settings
            </a>
            <a class="nav-link" href="ai_tutor.php">
                <i class="bi bi-chat-dots"></i> AI Tutor
            </a>
        </nav>
        
        <button class="logout-btn" onclick="window.location.href='../logout.php'">
            <i class="bi bi-box-arrow-left"></i> Logout
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-section">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search for courses, assignments...">
            </div>
            
           <div class="user-section">
    <div class="notification-icon">
        <i class="bi bi-bell"></i>
        <span class="notification-badge">3</span>
    </div>
    
    <div class="user-info" onclick="window.location.href='settings.php'" style="cursor: pointer;">
        <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
        </div>
    </div>
</div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Hello, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h2>
            <p>You've completed <?php echo $weeklyGoalPercentage; ?>% of your weekly learning goal.</p>
            <p>Keep up the great momentum!</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card blue">
                <div class="icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3>Courses in Progress</h3>
                <div class="number"><?php echo count($inProgressCourses); ?></div>
            </div>
            
            <div class="stat-card green">
                <div class="icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3>Completed Courses</h3>
                <div class="number"><?php echo $completedCount; ?></div>
            </div>
            
            <div class="stat-card orange">
                <div class="icon">
                    <i class="bi bi-award"></i>
                </div>
                <h3>Certificates Earned</h3>
                <div class="number"><?php echo $certificatesCount; ?></div>
            </div>
        </div>

        <!-- Continue Learning -->
        <h2 class="section-title">Continue Learning</h2>
        <div class="course-grid">
            <?php if (count($inProgressCourses) > 0): ?>
                <?php foreach ($inProgressCourses as $course): ?>
                    <div class="course-card">
                        <div class="course-image">
                            <span class="course-progress-badge"><?php echo round($course['progressPercentage']); ?>%</span>
                            // photo
                        </div>
                        <div class="course-body">
                            <div class="course-category"><?php echo htmlspecialchars($course['category'] ?? 'Design'); ?></div>
                            <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                            <div class="course-instructor">Dr. <?php echo htmlspecialchars($course['instructorName']); ?></div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $course['progressPercentage']; ?>%"></div>
                            </div>
                            <button class="btn btn-resume" onclick="window.location.href='course_view.php?id=<?php echo $course['courseID']; ?>'">
                                Resume
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        You haven't enrolled in any courses yet. Visit the <a href="course_catalog.php">Course Catalog</a> to get started!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>