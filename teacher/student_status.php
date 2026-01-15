<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$userID = $_GET['user_id'] ?? 0;
$activeTab = $_GET['tab'] ?? 'progress';

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ? AND role = 'student'");
$stmt->execute([$userID]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: enrollees.php');
    exit();
}

// Get student's enrollments in teacher's courses
$stmt = $conn->prepare("
    SELECT c.*, e.enrollmentID, e.progressPercentage, e.status, e.enrolledAt
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    WHERE e.userID = ? AND c.teacherID = ?
    ORDER BY e.enrolledAt DESC
");
$stmt->execute([$userID, $teacherID]);
$enrolledCourses = $stmt->fetchAll();

// Get quiz results
$stmt = $conn->prepare("
    SELECT qr.*, q.title as quizTitle, c.title as courseTitle, c.courseID
    FROM quizresults qr
    JOIN quizzes q ON qr.quizID = q.quizID
    JOIN courses c ON q.courseID = c.courseID
    WHERE qr.userID = ? AND c.teacherID = ?
    ORDER BY qr.submittedAt DESC
");
$stmt->execute([$userID, $teacherID]);
$quizResults = $stmt->fetchAll();

// Calculate overall passing/failing percentage
$totalQuizzes = count($quizResults);
$passedQuizzes = count(array_filter($quizResults, fn($q) => $q['status'] == 'passed'));
$passingPercentage = $totalQuizzes > 0 ? round(($passedQuizzes / $totalQuizzes) * 100) : 0;
$failingPercentage = 100 - $passingPercentage;

// Calculate quiz results by course
$quizResultsByCourse = [];
foreach ($quizResults as $result) {
    $courseID = $result['courseID'];
    if (!isset($quizResultsByCourse[$courseID])) {
        $quizResultsByCourse[$courseID] = [
            'courseTitle' => $result['courseTitle'],
            'results' => [],
            'passed' => 0,
            'total' => 0
        ];
    }
    $quizResultsByCourse[$courseID]['results'][] = $result;
    $quizResultsByCourse[$courseID]['total']++;
    if ($result['status'] == 'passed') {
        $quizResultsByCourse[$courseID]['passed']++;
    }
}

// Calculate course statistics
$totalCourses = count($enrolledCourses);
$completedCourses = count(array_filter($enrolledCourses, fn($c) => $c['status'] === 'completed'));
$activeCourses = count(array_filter($enrolledCourses, fn($c) => $c['status'] === 'active'));
$averageProgress = $totalCourses > 0 ? 
    round(array_sum(array_column($enrolledCourses, 'progressPercentage')) / $totalCourses) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?> - Student Status - Learnexus</title>
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

        /* Student Header */
        .student-header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        .student-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        /* Tab Navigation */
        .nav-tabs-custom {
            border: none;
            gap: 8px;
        }

        .nav-tabs-custom .nav-link {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 20px;
            color: #6b7280;
            font-weight: 500;
            margin: 0;
            transition: all 0.2s;
        }

        .nav-tabs-custom .nav-link:hover {
            background-color: #f9fafb;
            border-color: #d1d5db;
        }

        .nav-tabs-custom .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* Tab Content */
        .tab-content {
            background: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-top: 20px;
        }

        /* Course Cards */
        .course-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #eaeaea;
            transition: all 0.2s;
            cursor: pointer;
        }

        .course-card:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* Table Styles */
        .quiz-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .quiz-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #eaeaea;
            padding: 16px;
            font-weight: 600;
            color: #374151;
        }

        .quiz-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .quiz-table tbody tr {
            transition: all 0.2s;
        }

        .quiz-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .quiz-table tbody tr:last-child td {
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

        .bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .bg-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Course Stats */
        .course-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
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

        /* Course Progress Bars */
        .course-progress {
            margin-bottom: 12px;
        }

        .course-progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .course-progress-bar {
            height: 6px;
            border-radius: 3px;
            background: #e0e0e0;
            overflow: hidden;
        }

        .course-progress-fill {
            height: 100%;
            border-radius: 3px;
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
                                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></li>
                                </ol>
                            </nav>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar" 
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

            <!-- Student Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card student-header-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center me-4" 
                                         style="width: 80px; height: 80px; font-size: 2rem; font-weight: 600;">
                                        <?php echo strtoupper(substr($student['firstName'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h1 class="h2 fw-bold mb-1"><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></h1>
                                        <p class="mb-2 opacity-75">
                                            Student Number: <?php echo htmlspecialchars($student['studentNumber']); ?>
                                        </p>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge bg-white bg-opacity-20">
                                                <i class="bi bi-book me-1"></i> <?php echo $totalCourses; ?> Courses
                                            </span>
                                            <span class="badge bg-white bg-opacity-20">
                                                <i class="bi bi-check-circle me-1"></i> <?php echo $passedQuizzes; ?> Quizzes Passed
                                            </span>
                                            <span class="badge bg-white bg-opacity-20">
                                                <i class="bi bi-clock me-1"></i> Enrolled on <?php echo date('M d, Y', strtotime($enrolledCourses[0]['enrolledAt'] ?? 'now')); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <span class="student-badge">
                                    <?php echo $averageProgress; ?>% Average Progress
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overall Statistics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                                    <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Passing Rate</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $passingPercentage; ?>%</h3>
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
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                                    <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Failing Rate</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $failingPercentage; ?>%</h3>
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
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <i class="bi bi-clipboard-data-fill fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Quizzes</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalQuizzes; ?></h3>
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
                                    <i class="bi bi-book-fill fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Completed Courses</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $completedCourses; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="row mb-4">
                <div class="col-12">
                    <ul class="nav nav-tabs-custom d-flex flex-wrap" id="studentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'progress' ? 'active' : ''; ?>" 
                                    id="progress-tab" data-bs-toggle="tab" data-bs-target="#progress" type="button" role="tab">
                                <i class="bi bi-graph-up me-2"></i>Course Progress
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'quizzes' ? 'active' : ''; ?>" 
                                    id="quizzes-tab" data-bs-toggle="tab" data-bs-target="#quizzes" type="button" role="tab">
                                <i class="bi bi-patch-question me-2"></i>Quiz Results
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="studentTabsContent">
                <!-- Progress Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'progress' ? 'show active' : ''; ?>" id="progress" role="tabpanel">
                    <?php if (count($enrolledCourses) > 0): ?>
                        <div class="section-card">
                            <div class="section-title">
                                <i class="bi bi-book"></i> Enrolled Courses (<?php echo $totalCourses; ?>)
                            </div>
                            
                            <div class="row g-4">
                                <?php foreach ($enrolledCourses as $course): 
                                    $coursePassingRate = 0;
                                    $courseQuizTotal = 0;
                                    if (isset($quizResultsByCourse[$course['courseID']])) {
                                        $courseQuizData = $quizResultsByCourse[$course['courseID']];
                                        $courseQuizTotal = $courseQuizData['total'] ?? 0;
                                        $coursePassed = $courseQuizData['passed'] ?? 0;
                                        $coursePassingRate = $courseQuizTotal > 0 ? 
                                            round(($coursePassed / $courseQuizTotal) * 100) : 0;
                                    }
                                ?>
                                    <div class="col-12">
                                        <div class="course-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($course['description']); ?></p>
                                                </div>
                                                <span class="badge <?php 
                                                    echo $course['status'] == 'completed' ? 'bg-success' : 
                                                        ($course['status'] == 'active' ? 'bg-primary' : 'bg-warning');
                                                ?>">
                                                    <?php echo ucfirst($course['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="course-progress">
                                                <div class="course-progress-label">
                                                    <span class="small text-muted">Course Progress</span>
                                                    <span class="small fw-bold"><?php echo round($course['progressPercentage']); ?>%</span>
                                                </div>
                                                <div class="course-progress-bar">
                                                    <div class="course-progress-fill" style="width: <?php echo $course['progressPercentage']; ?>%; 
                                                        background: <?php echo $course['status'] == 'completed' ? '#10b981' : '#667eea'; ?>;">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="course-progress mb-3">
                                                <div class="course-progress-label">
                                                    <span class="small text-muted">Quiz Passing Rate</span>
                                                    <span class="small fw-bold"><?php echo $coursePassingRate; ?>%</span>
                                                </div>
                                                <div class="course-progress-bar">
                                                    <div class="course-progress-fill" style="width: <?php echo $coursePassingRate; ?>%; 
                                                        background: <?php echo $coursePassingRate >= 70 ? '#10b981' : '#dc3545'; ?>;">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <div class="text-center p-2 rounded bg-light">
                                                        <div class="small text-muted">Enrolled</div>
                                                        <div class="fw-bold"><?php echo date('M d, Y', strtotime($course['enrolledAt'] ?? 'now')); ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="text-center p-2 rounded bg-light">
                                                        <div class="small text-muted">Quizzes Taken</div>
                                                        <div class="fw-bold"><?php echo $courseQuizTotal; ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="text-center p-2 rounded bg-light">
                                                        <div class="small text-muted">Passing Rate</div>
                                                        <div class="fw-bold"><?php echo $coursePassingRate; ?>%</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-book empty-state-icon"></i>
                            <h3 class="h5 fw-bold mb-3">Not Enrolled in Any Courses</h3>
                            <p class="text-muted mb-4">This student is not enrolled in any of your courses.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quizzes Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'quizzes' ? 'show active' : ''; ?>" id="quizzes" role="tabpanel">
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-patch-question"></i> Quiz Results (<?php echo $totalQuizzes; ?>)
                        </div>
                        
                        <?php if (count($quizResults) > 0): ?>
                            <div class="table-responsive">
                                <table class="table quiz-table">
                                    <thead>
                                        <tr>
                                            <th>Quiz</th>
                                            <th>Course</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Date Taken</th>
                                            <th>Time Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody id="quizResultsBody">
                                        <?php foreach ($quizResults as $result): 
                                            $score = $result['score'] ?? 0;
                                            $totalQuestions = $result['totalQuestions'] ?? 0;
                                            $percentage = ($totalQuestions > 0) ? round(($score / $totalQuestions) * 100) : 0;
                                            $status = $result['status'] ?? 'unknown';
                                            $submittedAt = $result['submittedAt'] ?? date('Y-m-d H:i:s');
                                            $timeSpent = $result['timeSpent'] ?? 0;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($result['quizTitle'] ?? 'Untitled Quiz'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-muted"><?php echo htmlspecialchars($result['courseTitle'] ?? 'Unknown Course'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo $score; ?>/<?php echo $totalQuestions; ?></div>
                                                    <div class="text-muted small"><?php echo $percentage; ?>%</div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $status == 'passed' ? 'bg-success' : 
                                                            ($status == 'failed' ? 'bg-danger' : 'bg-warning');
                                                    ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-muted"><?php echo date('M d, Y', strtotime($submittedAt)); ?></div>
                                                    <div class="small text-muted"><?php echo date('h:i A', strtotime($submittedAt)); ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-muted"><?php echo gmdate("H:i:s", $timeSpent); ?></div>
                                                </td>
                                                
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-patch-question empty-state-icon"></i>
                                <h3 class="h5 fw-bold mb-3">No Quiz Results</h3>
                                <p class="text-muted mb-4">This student hasn't taken any quizzes yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

        // Initialize Bootstrap tabs with URL support
        const studentTabs = document.querySelectorAll('#studentTabs button[data-bs-toggle="tab"]');
        studentTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', event => {
                const activeTab = event.target.getAttribute('id').replace('-tab', '');
                const url = new URL(window.location);
                url.searchParams.set('tab', activeTab);
                window.history.pushState({}, '', url);
            });
        });

        // Restore active tab from URL on page load
        const activeTabFromUrl = '<?php echo $activeTab; ?>';
        if (activeTabFromUrl) {
            const tabElement = document.getElementById(`${activeTabFromUrl}-tab`);
            if (tabElement) {
                new bootstrap.Tab(tabElement).show();
            }
        }

        // Search functionality for quiz results
        const searchInput = document.getElementById('searchInput');
        const quizResultsBody = document.getElementById('quizResultsBody');
        
        if (searchInput && quizResultsBody) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = quizResultsBody.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>