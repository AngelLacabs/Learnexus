<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get teacher avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

// Get total courses created by teacher
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE teacherID = ?");
$stmt->execute([$teacherID]);
$totalCourses = $stmt->fetch()['count'];

// Get total students enrolled in teacher's courses
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.userID) as count
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    WHERE c.teacherID = ?
");
$stmt->execute([$teacherID]);
$totalStudents = $stmt->fetch()['count'];

// Get total revenue from teacher's courses
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.amount), 0) as total
    FROM payments p
    JOIN courses c ON p.courseID = c.courseID
    WHERE c.teacherID = ? AND p.status = 'completed'
");
$stmt->execute([$teacherID]);
$totalRevenue = $stmt->fetch()['total'];

// Get recent courses (last 3)
$stmt = $conn->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as enrollmentCount,
           (SELECT COUNT(*) FROM modules WHERE courseID = c.courseID) as moduleCount,
           (SELECT COUNT(*) FROM quizzes WHERE courseID = c.courseID) as quizCount
    FROM courses c
    WHERE c.teacherID = ?
    ORDER BY c.createdAt DESC
    LIMIT 3
");
$stmt->execute([$teacherID]);
$recentCourses = $stmt->fetchAll();

// Motivational phrases for teachers
$teacherMotivations = [
    "Inspire minds, shape futures—your impact is immeasurable!",
    "Great teachers empower students to discover their potential.",
    "Every lesson you create changes lives. Keep inspiring!",
    "Your dedication to education makes the world brighter.",
    "Teaching is the art of awakening curiosity and joy in learning.",
    "You're not just teaching—you're building tomorrow's leaders.",
    "Knowledge shared is knowledge multiplied. Keep sharing!",
    "Your passion for teaching ignites the spark in your students.",
    "Excellence in education starts with educators like you.",
    "Thank you for shaping minds and transforming lives!"
];

$dayOfYear = date('z');
$dailyMotivationTeacher = $teacherMotivations[$dayOfYear % count($teacherMotivations)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Learnexus</title>
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            position: relative;
        }
        
        .status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.published {
            background: #e8f5e9;
            color: #43a047;
        }
        
        .status-badge.draft {
            background: #fff3e0;
            color: #fb8c00;
        }
        
        .course-body {
            padding: 20px;
        }
        
        .course-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .course-stat {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .course-stat .number {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .course-stat .label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .btn-manage {
            background: #1e88e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: background 0.2s;
        }
        
        .btn-manage:hover {
            background: #1565c0;
            color: white;
        }
        
        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
            <a class="nav-link" href="courses.php">
                <i class="bi bi-book"></i> My Courses
            </a>
            <a class="nav-link" href="create_course.php">
                <i class="bi bi-plus-circle"></i> Create Course
            </a>
            <a class="nav-link" href="students.php">
                <i class="bi bi-people"></i> Students
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="bi bi-graph-up"></i> Analytics
            </a>
            <a class="nav-link" href="settings.php">
                <i class="bi bi-gear"></i> Settings
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
                <input type="text" placeholder="Search courses, students...">
            </div>
            
            <div class="user-section">
                <div class="notification-icon">
                    <i class="bi bi-bell"></i>
                </div>
                
                <div class="user-info" onclick="window.location.href='settings.php'">
                    <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                    <div class="user-avatar">
                        <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                            <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2><?php echo $dailyMotivationTeacher; ?></h2>
            <p>Manage your courses and track student progress</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card blue">
                <div class="icon">
                    <i class="bi bi-book"></i>
                </div>
                <h3>Total Courses</h3>
                <div class="number"><?php echo $totalCourses; ?></div>
            </div>
            
            <div class="stat-card green">
                <div class="icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3>Total Students</h3>
                <div class="number"><?php echo $totalStudents; ?></div>
            </div>
            
            <div class="stat-card orange">
                <div class="icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <h3>Total Revenue</h3>
                <div class="number">₱<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
        </div>

        <!-- Recent Courses Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title mb-0">Recent Courses</h2>
            <button class="btn-create" onclick="window.location.href='create_course.php'">
                <i class="bi bi-plus-circle"></i> Create New Course
            </button>
        </div>

        <div class="course-grid">
            <?php if (count($recentCourses) > 0): ?>
                <?php foreach ($recentCourses as $course): ?>
                    <div class="course-card">
                        <div class="course-image">
                            <span class="status-badge <?php echo strtolower($course['status']); ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                            <span>Course Image</span>
                        </div>
                        <div class="course-body">
                            <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                            
                            <div class="course-stats">
                                <div class="course-stat">
                                    <div class="number"><?php echo $course['enrollmentCount']; ?></div>
                                    <div class="label">Students</div>
                                </div>
                                <div class="course-stat">
                                    <div class="number"><?php echo $course['moduleCount']; ?></div>
                                    <div class="label">Modules</div>
                                </div>
                                <div class="course-stat">
                                    <div class="number"><?php echo $course['quizCount']; ?></div>
                                    <div class="label">Quizzes</div>
                                </div>
                            </div>
                            
                            <button class="btn-manage" onclick="window.location.href='manage_course.php?id=<?php echo $course['courseID']; ?>'">
                                <i class="bi bi-gear"></i> Manage Course
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i>
                        You haven't created any courses yet. Click "Create New Course" to get started!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>