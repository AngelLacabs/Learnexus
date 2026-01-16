<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $status = trim($_POST['status']);
    $passingScore = intval($_POST['passingScore']);
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO courses (teacherID, title, description, price, category, status, passingScore, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$teacherID, $title, $description, $price, $category, $status, $passingScore]);
        
        $courseID = $conn->lastInsertId();
        $_SESSION['success'] = "Course created successfully!";
        
        // Redirect to the new course's management page
        header("Location: manage_course.php?id=$courseID");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to create course: " . $e->getMessage();
        header("Location: courses.php");
        exit();
    }
}

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

// Check for success/error messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get all courses by teacher
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(DISTINCT e.userID) as enrolledCount,
           COUNT(DISTINCT l.lessonID) as lessonCount
    FROM courses c
    LEFT JOIN enrollments e ON c.courseID = e.courseID
    LEFT JOIN lessons l ON c.courseID = l.courseID
    WHERE c.teacherID = ?
    GROUP BY c.courseID
    ORDER BY c.createdAt DESC
");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Calculate statistics
$totalCourses = count($courses);
$publishedCourses = count(array_filter($courses, fn($c) => $c['status'] === 'published'));
$draftCourses = count(array_filter($courses, fn($c) => $c['status'] === 'draft'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Sidebar - Matching student design */
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

        /* Navigation - Matching student design */
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

        /* Hamburger - Matching student design */
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

        /* Course Cards */
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

        /* Status Badges */
        .badge-published {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }

        .badge-draft {
            background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }

        /* Search Input - UPDATED: REMOVED visible border/stroke like dashboard.php */
        .search-input {
            padding-left: 2.5rem;
            border: 2px solid transparent; /* Transparent border like dashboard.php */
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .search-input:focus {
            border-color: #667eea; /* Only shows border on focus like dashboard.php */
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-input:focus ~ .search-icon {
            color: #667eea;
        }

        /* Add clear search button like dashboard.php */
        .clear-search {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            display: none;
        }

        .clear-search.show {
            display: block;
        }

        /* User Avatar */
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* Status Tabs */
        .status-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
        }

        .status-tab:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .status-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Course Image Placeholder */
        .course-img-placeholder {
            height: 160px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            border-radius: 16px 16px 0 0;
            position: relative;
        }

        /* Action Buttons - UPDATED to match create_quiz.php */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-outline-secondary {
            border-color: #e5e7eb;
            color: #6b7280;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #d1d5db;
            color: #374151;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state-icon {
            font-size: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Course Stats */
        .course-stat-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }

        .course-stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #2d3436;
            display: block;
        }

        .course-stat-label {
            font-size: 12px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Add Course Button */
        .add-course-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 1000;
        }

        .add-course-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 20px 30px;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            border-top: 1px solid #eaeaea;
            padding: 20px 30px;
            border-radius: 0 0 16px 16px;
        }

        /* Form Styles */
        .form-control, .form-select {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            transition: border-color 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Modal Footer Buttons Container - Added to match create_quiz.php */
        .modal-footer-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            width: 100%;
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
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="courses.php">
                    <i class="bi bi-book fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
                    <i class="bi bi-patch-question fs-5"></i><span>Quizzes</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="enrollees.php">
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
            <!-- Header with Search and Profile - UPDATED: Now matches dashboard.php exactly -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" id="courseSearch" class="form-control search-input rounded-pill ps-5" 
                                       placeholder="Search your courses..." autocomplete="off">
                                <!-- Add clear search button like dashboard.php -->
                                <button class="clear-search" id="clearSearch">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold user-avatar">
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

            <!-- Page Title -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 fw-bold"><i class="bi bi-book me-2"></i>My Courses</h1>
                    <p class="text-muted">Manage and organize your courses</p>
                </div>
            </div>

            <!-- Stats Cards - REMOVED Archived, now only 3 cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <i class="bi bi-layers fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Courses</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalCourses; ?></h3>
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
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                                    <i class="bi bi-check-circle fs-4 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Published</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $publishedCourses; ?></h3>
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
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                                    <i class="bi bi-clock-history fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Draft</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $draftCourses; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Tabs - REMOVED Archived -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="status-tab active" data-status="all">All Courses</button>
                                <button class="status-tab" data-status="published">Published (<?php echo $publishedCourses; ?>)</button>
                                <button class="status-tab" data-status="draft">Draft (<?php echo $draftCourses; ?>)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Grid -->
            <div class="row g-4" id="coursesContainer">
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="col-12 col-md-6 col-lg-4 course-column" data-course-status="<?php echo $course['status']; ?>">
                            <div class="card course-card">
                                <div class="course-img-placeholder">
                                    <span class="position-absolute top-0 start-0 m-3 badge badge-<?php echo $course['status']; ?> rounded-pill px-3 py-1">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                    <i class="bi bi-book"></i>
                                </div>
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-2 text-truncate"><?php echo htmlspecialchars($course['title']); ?></h5>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-tag"></i> <?php echo htmlspecialchars($course['category']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <p class="text-muted mb-3 small"><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?><?php echo strlen($course['description']) > 100 ? '...' : ''; ?></p>
                                    
                                    <!-- Course Stats -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-4">
                                            <div class="course-stat-card">
                                                <span class="course-stat-number"><?php echo $course['enrolledCount']; ?></span>
                                                <span class="course-stat-label">Students</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="course-stat-card">
                                                <span class="course-stat-number"><?php echo $course['lessonCount']; ?></span>
                                                <span class="course-stat-label">Lessons</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="course-stat-card">
                                                <span class="course-stat-number">₱<?php echo number_format($course['price'], 0); ?></span>
                                                <span class="course-stat-label">Price</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button class="btn btn-gradient w-100 rounded-pill fw-semibold" 
                                            onclick="window.location.href='manage_course.php?id=<?php echo $course['courseID']; ?>'">
                                        <i class="bi bi-gear me-2"></i>Manage Course
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
                                <h3 class="h5 fw-bold mb-3">No Courses Yet</h3>
                                <p class="text-muted mb-4">You haven't created any courses yet. Create your first course to get started!</p>
                                <button type="button" class="btn btn-gradient rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                                    <i class="bi bi-plus me-2"></i>Create Course
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Create Course Modal -->
    <div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createCourseModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Create New Course
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="createCourseForm">
                    <div class="modal-body">
                        <input type="hidden" name="create_course" value="1">
                        
                        <div class="mb-4">
                            <label class="form-label">Course Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Web Development Fundamentals" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Describe what students will learn in this course..." required></textarea>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Price (PHP) *</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="Programming">Programming</option>
                                    <option value="Design">Design</option>
                                    <option value="Business">Business</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-control" required>
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <!-- Archived option removed from modal as well -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Passing Score (%) *</label>
                                <input type="number" name="passingScore" class="form-control" min="0" max="100" value="70" required>
                                <small class="text-muted">Minimum score required to pass quizzes</small>
                            </div>
                        </div>
                    </div>
                    <!-- UPDATED: Modal footer with buttons matching create_quiz.php -->
                    <div class="modal-footer">
                        <div class="modal-footer-buttons">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-2"></i> Cancel
                            </button>
                            <button type="submit" class="btn-gradient">
                                <i class="bi bi-plus-circle me-2"></i> Create Course
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Course Button (Floating) -->
    <button class="add-course-btn" data-bs-toggle="modal" data-bs-target="#createCourseModal">
        <i class="bi bi-plus"></i>
    </button>

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

        // Search functionality - UPDATED: Added clear search button functionality like dashboard.php
        const searchInput = document.getElementById('courseSearch');
        const clearSearchBtn = document.getElementById('clearSearch');

        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            clearSearchBtn.classList.toggle('show', term.length > 0);
            
            document.querySelectorAll('.course-card').forEach(card => {
                const title = card.querySelector('.fw-bold').textContent.toLowerCase();
                const container = card.closest('.course-column');
                if (title.includes(term)) {
                    container.style.display = '';
                } else {
                    container.style.display = 'none';
                }
            });
        });

        // Clear search functionality like dashboard.php
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearSearchBtn.classList.remove('show');
            document.querySelectorAll('.course-column').forEach(column => {
                column.style.display = '';
            });
            searchInput.focus();
        });

        // Clear search on escape key like dashboard.php
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                clearSearchBtn.classList.remove('show');
                document.querySelectorAll('.course-column').forEach(column => {
                    column.style.display = '';
                });
            }
        });

        // Status tab filtering
        document.querySelectorAll('.status-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const statusFilter = this.dataset.status;
                const courseColumns = document.querySelectorAll('.course-column');
                
                courseColumns.forEach(column => {
                    const courseStatus = column.dataset.courseStatus;
                    
                    if (statusFilter === 'all') {
                        column.style.display = '';
                    } else {
                        column.style.display = courseStatus === statusFilter ? '' : 'none';
                    }
                });
                
                // Show empty state if no courses match the filter
                const visibleCourses = document.querySelectorAll('.course-column[style=""]').length;
                const noCoursesElement = document.querySelector('.col-12 .card.text-center');
                
                if (visibleCourses === 0 && noCoursesElement) {
                    noCoursesElement.closest('.col-12').style.display = '';
                } else if (noCoursesElement) {
                    noCoursesElement.closest('.col-12').style.display = 'none';
                }
            });
        });

        // Course deletion confirmation
        function confirmDeleteCourse(courseID, courseTitle) {
            Swal.fire({
                title: 'Delete Course?',
                html: `
                    <div style="text-align: left;">
                        <p>Are you sure you want to delete <strong>"${courseTitle}"</strong>?</p>
                        <p class="text-danger"><strong>Warning:</strong> This will permanently delete:</p>
                        <ul>
                            <li>All lessons and uploaded files</li>
                            <li>All quizzes and questions</li>
                            <li>All student enrollments and progress</li>
                            <li>All quiz results</li>
                        </ul>
                        <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                        <p>Type <strong>"DELETE"</strong> to confirm:</p>
                        <input type="text" id="confirmDeleteInput" class="swal2-input" placeholder="Type DELETE here">
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete everything',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                preConfirm: () => {
                    const confirmValue = document.getElementById('confirmDeleteInput').value;
                    if (confirmValue !== 'DELETE') {
                        Swal.showValidationMessage('You must type "DELETE" to confirm');
                    }
                    return confirmValue === 'DELETE';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit to delete_course.php
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete_course.php';
                    
                    const courseIDInput = document.createElement('input');
                    courseIDInput.type = 'hidden';
                    courseIDInput.name = 'course_id';
                    courseIDInput.value = courseID;
                    
                    form.appendChild(courseIDInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
            
            return false; // Prevent default link behavior
        }

        // Clear create course form when modal is closed
        const createCourseModal = document.getElementById('createCourseModal');
        if (createCourseModal) {
            createCourseModal.addEventListener('hidden.bs.modal', function() {
                document.getElementById('createCourseForm').reset();
            });
        }

        // Form validation for create course
        document.getElementById('createCourseForm').addEventListener('submit', function(e) {
            const title = this.querySelector('input[name="title"]').value.trim();
            const description = this.querySelector('textarea[name="description"]').value.trim();
            const price = parseFloat(this.querySelector('input[name="price"]').value);
            const category = this.querySelector('select[name="category"]').value;
            
            if (!title) {
                e.preventDefault();
                Swal.fire('Error', 'Please enter a course title.', 'error');
                return false;
            }
            
            if (!description) {
                e.preventDefault();
                Swal.fire('Error', 'Please enter a course description.', 'error');
                return false;
            }
            
            if (price < 0) {
                e.preventDefault();
                Swal.fire('Error', 'Price cannot be negative.', 'error');
                return false;
            }
            
            if (!category) {
                e.preventDefault();
                Swal.fire('Error', 'Please select a category.', 'error');
                return false;
            }
            
            return true;
        });

        <?php if (isset($success)): ?>
        // Show success toast
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>

        <?php if (isset($error)): ?>
        // Show error toast
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($error); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>
    </script>
</body>
</html>