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
           (SELECT COUNT(*) FROM lessons WHERE courseID = c.courseID) as lessonCount,
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: white;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .brand {
            font-size: 22px;
            font-weight: 700;
            color: #1e88e5;
            text-decoration: none;
        }
        
        .sidebar-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background: #f8f9fa;
            color: #1e88e5;
        }

        .menu-item.active {
            background: #e3f2fd;
            color: #1e88e5;
            border-left-color: #1e88e5;
            font-weight: 600;
        }

        .menu-item i {
            font-size: 20px;
            width: 24px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 20px 40px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .user-info:hover {
            background: #f5f5f5;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Content Area */
        .content-area {
            padding: 40px;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }
        
        .welcome-banner h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-banner p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }
        
        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .stat-card .icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 28px;
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
            margin-bottom: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }
        
        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Course Grid */
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
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
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
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
            font-size: 48px;
        }
        
        .status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            backdrop-filter: blur(10px);
        }
        
        .status-badge.published {
            background: rgba(67, 160, 71, 0.9);
            color: white;
        }
        
        .status-badge.draft {
            background: rgba(251, 140, 0, 0.9);
            color: white;
        }
        
        .course-body {
            padding: 24px;
        }
        
        .course-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 48px;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .course-stat {
            text-align: center;
            padding: 12px 8px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .course-stat .number {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            display: block;
            margin-bottom: 4px;
        }
        
        .course-stat .label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-manage {
            background: #1e88e5;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: background 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-manage:hover {
            background: #1565c0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 12px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="brand">LEARNEXUS</a>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>
            <a href="courses.php" class="menu-item">
                <i class="bi bi-book"></i>
                <span>My Courses</span>
            </a>
            <a href="create_course.php" class="menu-item">
                <i class="bi bi-plus-circle"></i>
                <span>Create Course</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-item">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="user-section">
                <div class="user-info" onclick="window.location.href='settings.php'">
                    <span style="font-weight: 600; color: #333;">
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    </span>
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

        <!-- Content Area -->
        <div class="content-area">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2><?php echo htmlspecialchars($dailyMotivationTeacher); ?></h2>
                    <p>Manage your courses and track student progress</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card blue">
                    <div class="icon">
                        <i class="bi bi-book-fill"></i>
                    </div>
                    <h3>Total Courses</h3>
                    <div class="number"><?php echo $totalCourses; ?></div>
                </div>
                
                <div class="stat-card green">
                    <div class="icon">
                        <i class="bi bi-people-fill"></i>
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
            <div class="section-header">
                <h2 class="section-title">Recent Courses</h2>
                <button class="btn-create" onclick="window.location.href='create_course.php'">
                    <i class="bi bi-plus-circle"></i>
                    Create New Course
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
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="course-body">
                                <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                
                                <div class="course-stats">
                                    <div class="course-stat">
                                        <span class="number"><?php echo $course['enrollmentCount']; ?></span>
                                        <span class="label">Students</span>
                                    </div>
                                    <div class="course-stat">
                                        <span class="number"><?php echo $course['lessonCount']; ?></span>
                                        <span class="label">Lessons</span>
                                    </div>
                                    <div class="course-stat">
                                        <span class="number"><?php echo $course['quizCount']; ?></span>
                                        <span class="label">Quizzes</span>
                                    </div>
                                </div>
                                
                                <button class="btn-manage" onclick="window.location.href='manage_course.php?id=<?php echo $course['courseID']; ?>'">
                                    <i class="bi bi-gear-fill"></i>
                                    Manage Course
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-book"></i>
                        <h3>No Courses Yet</h3>
                        <p>You haven't created any courses yet. Click "Create New Course" to get started!</p>
                        <button class="btn-create" onclick="window.location.href='create_course.php'">
                            <i class="bi bi-plus-circle"></i>
                            Create Your First Course
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>