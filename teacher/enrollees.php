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
            background: #f5f6fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 22px;
            font-weight: 700;
            color: white;
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .navbar-user:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: 700;
            overflow: hidden;
        }

        .navbar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            height: calc(100vh - 68px);
            background: white;
            position: fixed;
            left: 0;
            top: 68px;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            color: #374151;
        }
        
        .sidebar-menu {
            padding: 16px 0;
        }

        .menu-item {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 15px;
        }

        .menu-item:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .menu-item.active {
            background: linear-gradient(135deg,  #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
        }

        .menu-item i {
            font-size: 20px;
            width: 24px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #e5e7eb;
            margin-top: auto;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 68px;
            padding: 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 15px;
        }

        .nav-tabs {
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 10px 20px;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .nav-tabs .nav-link:hover {
            color: #1a73e8;
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: #1a73e8;
            border-bottom-color: #1a73e8;
            background: transparent;
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
            height: 120px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            margin-bottom: 15px;
        }

        .course-card-body {
            padding: 24px;
        }

        .enrollee-count {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .course-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .btn-view-enrollees {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-view-enrollees:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid #e0e0e0;
            color: #666;
            font-weight: 600;
            padding: 12px;
            background: #f8f9fa;
        }

        .table tbody tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-navbar">
        <a href="dashboard.php" class="navbar-brand">LEARNEXUS</a>
        <div class="navbar-user" onclick="window.location.href='settings.php'">
            <span style="font-weight: 600;">
                <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
            </span>
            <div class="navbar-avatar">
                <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Teacher Panel</div>
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
            <a href="../logout.php" class="menu-item">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>List of Enrollees</h1>
            <p>Manage and spread your knowledge</p>
        </div>

        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#per-courses">Per Courses</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#all-enrollees">All Enrollees</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="per-courses">
                <div class="row">
                    <?php foreach ($enrolleesByCourse as $data): ?>
                        <?php if (count($data['enrollees']) > 0): ?>
                            <div class="col-md-4 mb-4">
                                <div class="course-card">
                                    <div class="course-card-body">
                                        <div class="course-image">
                                            <i class="bi bi-book" style="font-size: 32px;"></i>
                                        </div>
                                        <p class="enrollee-count">Total of <?php echo count($data['enrollees']); ?> Enrollees</p>
                                        <h6 class="course-title"><?php echo htmlspecialchars($data['course']['title']); ?></h6>
                                        <button class="btn-view-enrollees" onclick="window.location.href='view_enrollees.php?course_id=<?php echo $data['course']['courseID']; ?>'">
                                            View Enrollees
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="all-enrollees">
                <div class="table-container">
                    <p class="text-muted mb-3">Total of <?php echo count($allEnrollees); ?> Enrollees</p>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Name of Enrollees</th>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>