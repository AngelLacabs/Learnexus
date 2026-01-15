<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['course_id'] ?? 0;
$teacherID = $_SESSION['user_id'];

// Verify course ownership
$stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ? AND teacherID = ?");
$stmt->execute([$courseID, $teacherID]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: enrollees.php');
    exit();
}

// Get enrollees
$stmt = $conn->prepare("
    SELECT u.userID, u.firstName, u.lastName, u.studentNumber, 
           e.enrolledAt, e.progressPercentage, e.status
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    WHERE e.courseID = ?
    ORDER BY u.lastName, u.firstName
");
$stmt->execute([$courseID]);
$enrollees = $stmt->fetchAll();

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Enrollees - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Sidebar - EXACTLY matching dashboard */
        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation - EXACTLY matching dashboard */
        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: #1a73e8;
            border-radius: 0 4px 4px 0;
            transition: height 0.25s ease;
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active::before {
            display: none;
        }

        /* Hamburger - EXACTLY matching dashboard */
        .hamburger-btn {
            width: 50px;
            height: 50px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .hamburger-icon span {
            display: block;
            width: 24px;
            height: 3px;
            background: #1a73e8;
            border-radius: 3px;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        /* Main Content Margin - EXACTLY matching dashboard */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Card Hover Effects */
        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
        }

        /* Stats Cards */
        .stat-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        /* Table Styles */
        .enrollees-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .enrollees-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #eaeaea;
            padding: 16px;
            font-weight: 600;
            color: #374151;
        }

        .enrollees-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .enrollees-table tbody tr {
            transition: all 0.2s;
            cursor: pointer;
        }

        .enrollees-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .enrollees-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .bg-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        /* Student Info Card */
        .student-info-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #eaeaea;
            transition: all 0.2s;
            cursor: pointer;
        }

        .student-info-card:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* Progress Bar */
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e0e0e0;
            overflow: hidden;
        }

        .progress-bar-custom .fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .empty-state-icon {
            font-size: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }

        /* Course Badge */
        .course-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        /* Search Input */
        .search-input {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 16px;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Buttons */
        .btn-outline-primary {
            border-color: #667eea;
            color: #667eea;
        }

        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1a73e8;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #10b981;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #f59e0b;
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
            color: #9c27b0;
        }
    </style>
</head>
<body>
    <!-- Hamburger Button (Mobile) - EXACTLY matching dashboard -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar - EXACTLY matching dashboard -->
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100" style="width: var(--sidebar-width);" id="sidebar">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>
            
            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="courses.php">
                    <i class="bi bi-book fs-5"></i><span>My Courses</span>
                </a>

                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
                    <i class="bi bi-patch-question fs-5"></i><span>Quizzes</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="enrollees.php">
                    <i class="bi bi-people fs-5"></i><span>Enrollees</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
            </nav>
            
            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold" onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid" style="max-width: 1200px;">
            <!-- Breadcrumb & User -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="enrollees.php" class="text-decoration-none">Enrollees</a></li>
                                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($course['title']); ?></li>
                                </ol>
                            </nav>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" 
                                             class="w-100 h-100 rounded-circle object-fit-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm card-hover" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h1 class="h2 fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h1>
                                    <p class="mb-0 opacity-75"><?php echo htmlspecialchars($course['description']); ?></p>
                                </div>
                                <span class="course-badge">
                                    <?php echo count($enrollees); ?> Students
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <?php 
            // Calculate statistics
            $totalStudents = count($enrollees);
            $activeStudents = count(array_filter($enrollees, function($student) {
                return $student['status'] === 'active';
            }));
            $completedStudents = count(array_filter($enrollees, function($student) {
                return $student['status'] === 'completed';
            }));
            $averageProgress = $totalStudents > 0 ? 
                round(array_sum(array_column($enrollees, 'progressPercentage')) / $totalStudents) : 0;
            ?>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <i class="bi bi-people-fill fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalStudents; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                                    <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Completed</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $completedStudents; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                                    <i class="bi bi-clock-fill fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">In Progress</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $activeStudents; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                                    <i class="bi bi-graph-up-fill fs-4 text-purple"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Avg. Progress</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $averageProgress; ?>%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollees Section -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-0">Student List</h5>
                        <p class="text-muted mb-0"><?php echo count($enrollees); ?> enrollees found</p>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control search-input" placeholder="Search students..." style="width: 250px;" id="searchInput">
                    </div>
                </div>
                
                <?php if (count($enrollees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table enrollees-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Student</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Enrolled Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="enrolleesTableBody">
                                <?php foreach ($enrollees as $index => $student): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold me-3" 
                                                     style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <?php echo strtoupper(substr($student['firstName'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></strong>
                                                    <div class="text-muted small">
                                                        <?php echo $student['studentNumber'] ?? 'No ID'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress-bar-custom mb-2">
                                                <div class="fill" style="width: <?php echo $student['progressPercentage']; ?>%"></div>
                                            </div>
                                            <div class="text-muted small"><?php echo $student['progressPercentage']; ?>%</div>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $student['status'] == 'completed' ? 'bg-success' : 
                                                    ($student['status'] == 'active' ? 'bg-primary' : 'bg-warning');
                                            ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted">
                                            <?php echo date('M d, Y', strtotime($student['enrolledAt'])); ?>
                                        </td>
                                        <td>
                                            <a href="student_status.php?user_id=<?php echo $student['userID']; ?>&course_id=<?php echo $courseID; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-people empty-state-icon"></i>
                        <h3 class="h5 fw-bold mb-3">No Students Yet</h3>
                        <p class="text-muted mb-4">No students have enrolled in this course yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alternative Card View (Hidden by default) -->
            <div class="row g-4 d-none" id="cardView">
                <?php foreach ($enrollees as $student): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="student-info-card" onclick="window.location.href='student_status.php?user_id=<?php echo $student['userID']; ?>&course_id=<?php echo $courseID; ?>'">
                            <div class="d-flex align-items-start mb-3">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold me-3" 
                                     style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <?php echo strtoupper(substr($student['firstName'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></h6>
                                    <div class="text-muted small mb-2"><?php echo $student['studentNumber'] ?? 'No ID'; ?></div>
                                    <span class="badge <?php 
                                        echo $student['status'] == 'completed' ? 'bg-success' : 
                                            ($student['status'] == 'active' ? 'bg-primary' : 'bg-warning');
                                    ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">Progress</span>
                                    <span class="small fw-bold"><?php echo $student['progressPercentage']; ?>%</span>
                                </div>
                                <div class="progress-bar-custom">
                                    <div class="fill" style="width: <?php echo $student['progressPercentage']; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="text-muted small">
                                <i class="bi bi-calendar me-1"></i> Enrolled: <?php echo date('M d, Y', strtotime($student['enrolledAt'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hamburger animation - EXACTLY matching dashboard
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        // Active nav state - EXACTLY matching dashboard
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop();
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            }
            
            // Close sidebar
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                    if (offcanvas) offcanvas.hide();
                }
            });
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const enrolleesTableBody = document.getElementById('enrolleesTableBody');
        
        if (searchInput && enrolleesTableBody) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = enrolleesTableBody.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html> 