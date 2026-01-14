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

// Get all courses for search (if needed)
$stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE teacherID = ? ORDER BY title ASC");
$stmt->execute([$teacherID]);
$allCourses = $stmt->fetchAll();

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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }

        /* Main Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Left side like the image */
        .sidebar {
            width: 250px;
            background: white;
            padding: 30px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 25px 30px;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 30px;
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3436;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 0 20px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: #636e72;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
        }

        .menu-item:hover {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.2);
        }

        .menu-item i {
            font-size: 18px;
            width: 24px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            padding: 0 25px;
        }

        /* UPDATED: Sidebar Logout Button - Simple Red Hover */
        .menu-item.logout-item {
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
            margin: 10px 16px;
            border-radius: 20px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item.logout-item:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        /* Top Header - LIKE THE IMAGE */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        /* Search Bar - Like the image */
        .search-container {
            flex: 1;
            max-width: 400px;
        }

        .search-box {
            position: relative;
            width: 100%;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            font-size: 14px;
            color: #333;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #7d4fab;
            box-shadow: 0 0 0 3px rgba(125, 79, 171, 0.1);
        }

        .search-box input::placeholder {
            color: #999;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7d4fab;
            font-size: 18px;
            pointer-events: none;
        }

        /* User Profile - Like the image */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: transform 0.2s;
            border: 1px solid #f0f0f0;
        }

        .user-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 12px;
            color: #666;
        }

        /* Welcome Section */
        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 8px;
        }

        .welcome-section p {
            color: #636e72;
            font-size: 16px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #388e3c;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #f57c00;
        }

        .stat-content h3 {
            font-size: 12px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .stat-content .number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
        }

        /* Courses Section */
        .courses-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #2d3436;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .course-header {
            padding: 20px;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            position: relative;
        }

        .course-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .course-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .course-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            opacity: 0.9;
        }

        .course-body {
            padding: 20px;
        }

        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .course-stat {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }

        .course-stat .number {
            font-size: 18px;
            font-weight: 700;
            color: #2d3436;
            display: block;
            margin-bottom: 3px;
        }

        .course-stat .label {
            font-size: 11px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-manage {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-manage:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #636e72;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #b2bec3;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }
            
            .sidebar-title, .menu-item span, .user-info h4, .user-info p {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .search-container {
                max-width: 100%;
            }
            
            .user-profile {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">LEARNEXUS</div>
            </div>
            
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item active">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="courses.php" class="menu-item">
                    <i class="bi bi-book"></i>
                    <span>Courses</span>
                </a>
                <a href="quizzes.php" class="menu-item">
                    <i class="bi bi-patch-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="enrollees.php" class="menu-item">
                    <i class="bi bi-people"></i>
                    <span>Enrollees</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <!-- UPDATED: Simple Red Hover Logout Button -->
                <a href="../logout.php" class="menu-item logout-item">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header - LIKE THE IMAGE -->
            <div class="top-header">
                <!-- Search Bar on the left -->
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" placeholder="Search your courses..." id="courseSearch">
                        <div class="search-icon">
                            <i class="bi bi-search"></i>
                        </div>
                    </div>
                </div>
                
                <!-- User Profile on the right -->
                <div class="user-profile" onclick="window.location.href='settings.php'">
                    <div class="user-avatar">
                        <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                            <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h4>
                        <p>Teacher</p>
                    </div>
                </div>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
                <p><?php echo htmlspecialchars($dailyMotivationTeacher); ?></p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-book-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Courses</h3>
                        <div class="number"><?php echo $totalCourses; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Students</h3>
                        <div class="number"><?php echo $totalStudents; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Revenue</h3>
                        <div class="number">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Courses -->
            <div class="courses-section">
                <div class="section-header">
                    <h2>Recent Courses</h2>
                </div>
                
                <div class="courses-grid">
                    <?php if (count($recentCourses) > 0): ?>
                        <?php foreach ($recentCourses as $course): ?>
                            <div class="course-card">
                                <div class="course-header">
                                    <div class="course-status <?php echo strtolower($course['status']); ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </div>
                                    <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                    <div class="course-meta">
                                        <span><i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($course['createdAt'])); ?></span>
                                    </div>
                                </div>
                                <div class="course-body">
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
                            <p>You haven't created any courses yet. Go to "Courses" section to get started!</p>
                            <button class="btn-manage" onclick="window.location.href='courses.php'">
                                <i class="bi bi-plus-circle"></i>
                                Create First Course
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('courseSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const courseCards = document.querySelectorAll('.course-card');
            
            courseCards.forEach(card => {
                const courseTitle = card.querySelector('.course-title').textContent.toLowerCase();
                if (courseTitle.includes(searchTerm) || searchTerm === '') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Add animation to cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .course-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>