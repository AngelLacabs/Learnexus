<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
// Fetch instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

// Get courses by teacher
$stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE teacherID = ? ORDER BY title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Get enrollees per course
$enrolleesByCourse = [];
foreach ($courses as $course) {
    $stmt = $conn->prepare("
        SELECT u.userID, u.firstName, u.lastName, u.studentNumber, e.enrolledAt, e.progressPercentage
        FROM enrollments e
        JOIN users u ON e.userID = u.userID
        WHERE e.courseID = ?
        ORDER BY e.enrolledAt DESC
    ");
    $stmt->execute([$course['courseID']]);
    $enrolleesByCourse[$course['courseID']] = [
        'course' => $course,
        'enrollees' => $stmt->fetchAll()
    ];
}

// Get all enrollees
$stmt = $conn->prepare("
    SELECT u.userID, u.firstName, u.lastName, u.studentNumber, c.title as courseTitle
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    JOIN courses c ON e.courseID = c.courseID
    WHERE c.teacherID = ?
    ORDER BY u.lastName, u.firstName
");
$stmt->execute([$teacherID]);
$allEnrollees = $stmt->fetchAll();

// Calculate total students
$totalStudents = count($allEnrollees);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollees - Learnexus</title>
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

        /* Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 8px;
        }

        .header-left p {
            color: #636e72;
            font-size: 16px;
        }

        /* User Profile */
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
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

        /* Stats Card */
        .stats-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 30px;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }

        .stats-content h3 {
            font-size: 12px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .stats-content .number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
        }

        /* Tabs Section */
        .tabs-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            padding: 0 0 15px 0;
        }

        .tab {
            padding: 12px 24px;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            border-radius: 6px 6px 0 0;
        }

        .tab.active {
            color: #7d4fab;
            border-bottom-color: #7d4fab;
            background: rgba(125, 79, 171, 0.05);
        }

        .tab:hover:not(.active) {
            color: #7d4fab;
            background: rgba(125, 79, 171, 0.05);
        }

        /* Course Grid */
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #f0f0f0;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
        }

        .course-image {
            height: 120px;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }

        .course-body {
            padding: 24px;
        }

        .enrollee-count {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .course-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-view-enrollees {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-view-enrollees:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.3);
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2d3436;
            margin: 0;
        }

        .student-count {
            font-size: 14px;
            color: #666;
        }

        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            border-bottom: 2px solid #e0e0e0;
            color: #666;
            font-weight: 600;
            padding: 15px;
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }

        .table tbody tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eaeaea;
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
            color: #636e72;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #9ca3af;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .course-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-profile {
                align-self: flex-start;
            }
            
            .tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .table-container {
                padding: 15px;
                overflow-x: auto;
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
                <a href="dashboard.php" class="menu-item">
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
                <a href="enrollees.php" class="menu-item active">
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
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <h1>Enrollees</h1>
                    <p>Manage and spread your knowledge</p>
                </div>
                
                <!-- User Profile -->
                <div class="user-profile" onclick="window.location.href='settings.php'">
                    <div class="user-avatar">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
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

            <!-- Stats Card -->
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stats-content">
                    <h3>Total Students</h3>
                    <div class="number"><?php echo $totalStudents; ?></div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="tabs-section">
                <div class="tabs">
                    <div class="tab active" onclick="showTab('per-courses')">Per Courses</div>
                    <div class="tab" onclick="showTab('all-enrollees')">All Enrollees</div>
                </div>

                <!-- Per Courses Tab -->
                <div id="per-courses" class="tab-content active">
                    <div class="course-grid">
                        <?php if (count($enrolleesByCourse) > 0): ?>
                            <?php foreach ($enrolleesByCourse as $data): ?>
                                <?php if (count($data['enrollees']) > 0): ?>
                                    <div class="course-card">
                                        <div class="course-image">
                                            <i class="bi bi-book" style="font-size: 32px;"></i>
                                        </div>
                                        <div class="course-body">
                                            <div class="enrollee-count">
                                                <i class="bi bi-person"></i>
                                                <?php echo count($data['enrollees']); ?> Enrollees
                                            </div>
                                            <h6 class="course-title"><?php echo htmlspecialchars($data['course']['title']); ?></h6>
                                            <button class="btn-view-enrollees" onclick="window.location.href='view_enrollees.php?course_id=<?php echo $data['course']['courseID']; ?>'">
                                                <i class="bi bi-eye"></i>
                                                View Enrollees
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <h3>No Enrollees Yet</h3>
                                <p>No students have enrolled in your courses yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- All Enrollees Tab -->
                <div id="all-enrollees" class="tab-content">
                    <div class="table-container">
                        <div class="table-header">
                            <h3>All Enrollees</h3>
                            <div class="student-count">Total: <?php echo $totalStudents; ?> students</div>
                        </div>
                        
                        <?php if (count($allEnrollees) > 0): ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Name</th>
                                            <th>Student Number</th>
                                            <th>Course</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allEnrollees as $index => $student): ?>
                                            <tr onclick="window.location.href='student_status.php?user_id=<?php echo $student['userID']; ?>'">
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                                <td><?php echo htmlspecialchars($student['studentNumber']); ?></td>
                                                <td><?php echo htmlspecialchars($student['courseTitle']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <h3>No Enrollees Yet</h3>
                                <p>No students have enrolled in your courses yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Set active tab
            event.target.classList.add('active');
        }

        // Initialize with first tab active
        document.addEventListener('DOMContentLoaded', function() {
            showTab('per-courses');
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>