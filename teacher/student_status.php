<?php
// ========================================
// FILE: teacher/student_status.php
// View individual student's quiz results and course progress
// ========================================
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$userID = $_GET['user_id'] ?? 0;

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

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

$activeTab = $_GET['tab'] ?? 'progress';
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

        /* Sidebar - Matching enrollees.php design */
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

        /* Navigation */
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

        /* Hamburger */
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

        /* Main Content Margin */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Student Header */
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            margin-right: 20px;
        }

        /* Status Tabs */
        .status-tab {
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #666;
        }

        .status-tab:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .status-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Cards */
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

        .course-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            height: 100%;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Course Image Placeholder */
        .course-img-placeholder {
            height: 140px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            border-radius: 16px 16px 0 0;
        }

        /* Quiz Results Table */
        .quiz-table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .quiz-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            color: #666;
            font-weight: 600;
            padding: 16px;
        }

        .quiz-table tbody tr {
            transition: background 0.2s;
        }

        .quiz-table tbody tr:hover {
            background: #f8f9fa;
        }

        .quiz-table tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #eaeaea;
        }

        /* Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.passed {
            background: rgba(76, 175, 80, 0.1);
            color: #43a047;
        }

        .status-badge.failed {
            background: rgba(244, 67, 54, 0.1);
            color: #ef5350;
        }

        .status-badge.completed {
            background: rgba(76, 175, 80, 0.1);
            color: #43a047;
        }

        .status-badge.ongoing {
            background: rgba(33, 150, 243, 0.1);
            color: #1e88e5;
        }

        /* Progress Bars */
        .progress-circle {
            width: 120px;
            height: 120px;
        }

        .progress-circle svg {
            width: 100%;
            height: 100%;
        }

        .progress-circle-bg {
            fill: none;
            stroke: #e0e0e0;
            stroke-width: 4;
        }

        .progress-circle-fill {
            fill: none;
            stroke-width: 4;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 0.5s ease;
        }

        /* Back Button */
        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            transition: background 0.2s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Search Input */
        .search-input {
            border: 1px solid #dee2e6;
            background: rgba(255, 255, 255, 0.9);
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .search-icon {
            color: #6c757d;
        }

        /* User Avatar */
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* Empty State */
        .empty-state-icon {
            font-size: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body>
    <!-- Hamburger Button (Mobile) -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar -->
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
        <div class="container-fluid">
            <!-- Header with Back Button, Search and Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <button class="btn btn-outline-secondary rounded-pill" onclick="window.history.back()">
                                <i class="bi bi-arrow-left me-2"></i>Back to Enrollees
                            </button>
                            
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <i class="bi bi-search search-icon position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                                <input type="text" id="searchInput" class="form-control search-input rounded-pill ps-5" 
                                       placeholder="Search in <?php echo htmlspecialchars($student['firstName']); ?>'s results..." autocomplete="off">
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold user-avatar">
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

            <!-- Student Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="student-header">
                        <div class="d-flex align-items-center">
                            <div class="student-avatar">
                                <?php echo strtoupper(substr($student['firstName'], 0, 1)); ?>
                            </div>
                            <div>
                                <h1 class="h3 fw-bold mb-1"><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></h1>
                                <p class="mb-2 opacity-75">Student Number: <?php echo htmlspecialchars($student['studentNumber']); ?></p>
                                <div class="d-flex gap-3">
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-book me-1"></i> <?php echo count($enrolledCourses); ?> courses
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-check-circle me-1"></i> <?php echo $passedQuizzes; ?> quizzes passed
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-clock me-1"></i> Enrolled on <?php echo date('M d, Y', strtotime($enrolledCourses[0]['enrolledAt'] ?? 'now')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="status-tab <?php echo $activeTab == 'progress' ? 'active' : ''; ?>" onclick="window.location.href='?user_id=<?php echo $userID; ?>&tab=progress'">
                                    <i class="bi bi-graph-up me-1"></i> Course Progress
                                </button>
                                <button class="status-tab <?php echo $activeTab == 'quizzes' ? 'active' : ''; ?>" onclick="window.location.href='?user_id=<?php echo $userID; ?>&tab=quizzes'">
                                    <i class="bi bi-patch-question me-1"></i> Quiz Results
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overall Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                                    <i class="bi bi-check-circle fs-4 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Passing Rate</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $passingPercentage; ?>%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);">
                                    <i class="bi bi-x-circle fs-4 text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Failing Rate</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $failingPercentage; ?>%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <i class="bi bi-clipboard-data fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Quizzes</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalQuizzes; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($activeTab == 'quizzes'): ?>
                <!-- Quiz Results Tab -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body p-0">
                                <div class="p-4 border-bottom">
                                    <h5 class="fw-bold mb-0"><i class="bi bi-patch-question me-2"></i> Quiz Results</h5>
                                    <p class="text-muted mb-0 small">Showing all quiz attempts</p>
                                </div>
                                
                                <?php if (count($quizResults) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover quiz-table mb-0">
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
                                                    // Safely handle missing fields
                                                    $score = $result['score'] ?? 0;
                                                    $totalQuestions = $result['totalQuestions'] ?? 0;
                                                    $percentage = ($totalQuestions > 0) ? round(($score / $totalQuestions) * 100) : 0;
                                                    $status = $result['status'] ?? 'unknown';
                                                    $submittedAt = $result['submittedAt'] ?? date('Y-m-d H:i:s');
                                                    $timeSpent = $result['timeSpent'] ?? 0;
                                                ?>
                                                    <tr class="quiz-row">
                                                        <td>
                                                            <div class="fw-medium"><?php echo htmlspecialchars($result['quizTitle'] ?? 'Untitled Quiz'); ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="text-muted"><?php echo htmlspecialchars($result['courseTitle'] ?? 'Unknown Course'); ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold"><?php echo $score; ?>/<?php echo $totalQuestions; ?></div>
                                                            <div class="small text-muted"><?php echo $percentage; ?>%</div>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge <?php echo $status; ?>">
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
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-patch-question empty-state-icon mb-3"></i>
                                        <h3 class="h5 fw-bold mb-3">No Quiz Results</h3>
                                        <p class="text-muted mb-4">This student hasn't taken any quizzes yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Course Progress Tab -->
                <div class="row" id="courseProgress">
                    <?php if (count($enrolledCourses) > 0): ?>
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
                            <div class="col-12 col-md-6 col-lg-4 mb-4">
                                <div class="card course-card">
                                    <div class="course-img-placeholder">
                                        <i class="bi bi-book"></i>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h5 class="fw-bold mb-2 text-truncate"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <span class="status-badge <?php echo $course['status']; ?>">
                                                    • <?php echo ucfirst($course['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="small">Course Progress</span>
                                                <span class="small fw-bold"><?php echo round($course['progressPercentage']); ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" style="width: <?php echo $course['progressPercentage']; ?>%; 
                                                    background: <?php echo $course['status'] == 'completed' ? '#43a047' : '#1e88e5'; ?>;">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="small">Quiz Passing Rate</span>
                                                <span class="small fw-bold"><?php echo $coursePassingRate; ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?php echo $coursePassingRate >= 70 ? 'bg-success' : 'bg-danger'; ?>" 
                                                     style="width: <?php echo $coursePassingRate; ?>%;">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <div class="text-center p-2 rounded bg-light">
                                                    <div class="small text-muted">Enrolled</div>
                                                    <div class="fw-bold"><?php echo date('M d, Y', strtotime($course['enrolledAt'] ?? 'now')); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-center p-2 rounded bg-light">
                                                    <div class="small text-muted">Quizzes Taken</div>
                                                    <div class="fw-bold"><?php echo $courseQuizTotal; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button class="btn btn-outline-primary w-100 rounded-pill fw-semibold" 
                                                onclick="window.location.href='view_quizresults.php?course_id=<?php echo $course['courseID']; ?>&user_id=<?php echo $userID; ?>'">
                                            <i class="bi bi-eye me-2"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card border-0 rounded-4 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-book empty-state-icon mb-3"></i>
                                    <h3 class="h5 fw-bold mb-3">Not Enrolled in Any Courses</h3>
                                    <p class="text-muted mb-4">This student is not enrolled in any of your courses.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Hamburger animation
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');

    if (hamburgerBtn && sidebar) {
        sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
        sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
    }

    // Active nav state
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    const currentPage = window.location.pathname.split('/').pop();

    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        }
        
        // Close sidebar on mobile after click
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                if (offcanvas) offcanvas.hide();
            }
        });
    });

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        
        <?php if ($activeTab == 'quizzes'): ?>
            // Search in quiz results table
            document.querySelectorAll('.quiz-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        <?php else: ?>
            // Search in course progress
            document.querySelectorAll('.course-card').forEach(card => {
                const title = card.querySelector('.fw-bold').textContent.toLowerCase();
                const container = card.closest('.col-12.col-md-6.col-lg-4');
                if (title.includes(term)) {
                    container.style.display = '';
                } else {
                    container.style.display = 'none';
                }
            });
        <?php endif; ?>
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>
</html>