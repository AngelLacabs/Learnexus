<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;
$activeTab = $_GET['tab'] ?? 'details';

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

// Get course details and verify ownership
$stmt = $conn->prepare("
    SELECT c.*, 
        CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ? AND c.teacherID = ?
");
$stmt->execute([$courseID, $teacherID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found or you don't have permission to manage it.");
}

// Get course statistics for all tabs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE courseID = ?");
$stmt->execute([$courseID]);
$enrolledStudents = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalQuizCount = (int)$stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE courseID = ? AND (status = 'completed' OR completedAt IS NOT NULL)");
$stmt->execute([$courseID]);
$completedStudents = (int)$stmt->fetch()['count'];

// Get enrolled students data for dashboard display
$stmt = $conn->prepare("
    SELECT u.*, e.enrolledAt, e.progressPercentage, e.status
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    WHERE e.courseID = ?
    ORDER BY e.enrolledAt DESC
");
$stmt->execute([$courseID]);
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$studentsCount = count($allStudents);

// Get data for specific tabs only when needed
$lessons = [];
$quizzes = [];
$students = [];

if ($activeTab === 'lessons' || $activeTab === 'details') {
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
    $stmt->execute([$courseID]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($activeTab === 'quizzes' || $activeTab === 'details') {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ? ORDER BY createdAt DESC");
    $stmt->execute([$courseID]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($activeTab === 'students') {
    $students = $allStudents;
}

// Handle course update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $status = $_POST['status'];
    $passingScore = intval($_POST['passingScore']);
    
    $stmt = $conn->prepare("
        UPDATE courses 
        SET title = ?, description = ?, price = ?, category = ?, status = ?, passingScore = ?
        WHERE courseID = ? AND teacherID = ?
    ");
    $stmt->execute([$title, $description, $price, $category, $status, $passingScore, $courseID, $teacherID]);
    
    $success = "Course updated successfully!";
    $activeTab = 'details';
    
    // Refresh course data
    $stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    try {
        $conn->beginTransaction();
        
        // Delete lesson files from server
        $stmt = $conn->prepare("SELECT filename FROM lessons WHERE courseID = ?");
        $stmt->execute([$courseID]);
        $lessonFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($lessonFiles as $file) {
            $filePath = '../' . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete lessons from database
        $stmt = $conn->prepare("DELETE FROM lessons WHERE courseID = ?");
        $stmt->execute([$courseID]);
        
        // Get quiz IDs for this course
        $stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
        $stmt->execute([$courseID]);
        $quizIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete quiz results
        if (!empty($quizIDs)) {
            $placeholders = implode(',', array_fill(0, count($quizIDs), '?'));
            $stmt = $conn->prepare("DELETE FROM quizresults WHERE quizID IN ($placeholders)");
            $stmt->execute($quizIDs);
            
            // Delete quiz questions
            $stmt = $conn->prepare("DELETE FROM quizquestions WHERE quizID IN ($placeholders)");
            $stmt->execute($quizIDs);
            
            // Delete quizzes
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE courseID = ?");
            $stmt->execute([$courseID]);
        }
        
        // Delete enrollments
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE courseID = ?");
        $stmt->execute([$courseID]);
        
        // Delete course
        $stmt = $conn->prepare("DELETE FROM courses WHERE courseID = ? AND teacherID = ?");
        $stmt->execute([$courseID, $teacherID]);
        
        $conn->commit();
        
        // Redirect to courses page after successful deletion
        $_SESSION['success'] = "Course deleted successfully!";
        header('Location: courses.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Failed to delete course: " . $e->getMessage();
        $activeTab = 'details';
    }
}

// Handle lesson upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_lesson']) && isset($_FILES['lesson_file'])) {
    $lessonTitle = trim($_POST['lesson_title']);
    $file = $_FILES['lesson_file'];
    
    $uploadErrors = [];
    
    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/lessons/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            $error = "Failed to create upload directory!";
            $uploadErrors[] = "Directory creation failed";
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        $error = "Upload directory is not writable!";
        $uploadErrors[] = "Directory not writable";
    }
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $error = $errorMessages[$file['error']] ?? "Unknown upload error: " . $file['error'];
        $uploadErrors[] = $error;
    }
    
    // Validate file extension
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf'];
    
    if (!in_array($fileExt, $allowedExts)) {
        $error = "Only PDF files are allowed! You uploaded: .$fileExt";
        $uploadErrors[] = $error;
    }
    
    // Validate file size (10MB limit)
    if ($file['size'] > 10485760) {
        $error = "File size must be less than 10MB! Your file: " . round($file['size']/1048576, 2) . "MB";
        $uploadErrors[] = $error;
    }
    
    // If no errors, proceed with upload
    if (empty($uploadErrors)) {
        // Generate unique filename
        $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
        $uploadPath = $uploadDir . $newFileName;
        $dbPath = 'uploads/lessons/' . $newFileName;
        
        // Attempt to move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            
            // Verify file exists
            if (file_exists($uploadPath)) {
                
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO lessons (courseID, title, filename, uploadedAt) VALUES (?, ?, ?, NOW())");
                
                if ($stmt->execute([$courseID, $lessonTitle, $dbPath])) {
                    $success = "Lesson uploaded successfully! File: $newFileName";
                    $activeTab = 'lessons';
                    
                    // Refresh lessons
                    $stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
                    $stmt->execute([$courseID]);
                    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Database insert failed - delete uploaded file
                    unlink($uploadPath);
                    $error = "Database error: Failed to save lesson record";
                    $activeTab = 'lessons';
                }
            } else {
                $error = "File uploaded but cannot be found on disk!";
                $activeTab = 'lessons';
            }
        } else {
            $error = "Failed to move uploaded file! Check directory permissions.";
            $activeTab = 'lessons';
        }
    } else {
        // Show first error
        $error = $uploadErrors[0];
        $activeTab = 'lessons';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Manage Course - Learnexus</title>
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

        /* Stats Cards - UPDATED: All cards same size */
        .stat-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Ensure all stat cards have same height */
        .row.g-3.mb-4 > .col-md-3 {
            display: flex;
        }

        .row.g-3.mb-4 > .col-md-3 > .stat-card {
            width: 100%;
        }

        .stat-card .card-body {
            min-height: 140px;
            display: flex;
            align-items: center;
        }

        .stat-card .d-flex.align-items-center.gap-3 {
            width: 100%;
        }

        .stat-card .rounded-circle {
            flex-shrink: 0 !important;
        }

        .stat-card .d-flex.align-items-center.gap-3 > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .stat-card h6 {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Course Hero */
        .course-hero {
            height: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        /* Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #0da271 0%, #047857 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Lesson Items */
        .lesson-item {
            background: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            border: 1px solid #eaeaea;
        }

        .lesson-item:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* Student Rows */
        .student-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: white;
            border-radius: 12px;
            margin-bottom: 10px;
            border: 1px solid #eaeaea;
            transition: all 0.2s;
        }

        .student-row:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e0e0e0;
            width: 200px;
            overflow: hidden;
        }

        .progress-bar-custom .fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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

        /* Danger Zone */
        .danger-zone {
            border-top: 2px solid #fee;
            padding-top: 30px;
            margin-top: 40px;
        }

        .danger-zone-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        /* Course Status Badge */
        .course-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
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
        <div class="container-fluid" style="max-width: 1200px;">
            <!-- Breadcrumb & User -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="courses.php" class="text-decoration-none">My Courses</a></li>
                                    <li class="breadcrumb-item active">Manage: <?php echo htmlspecialchars($course['title']); ?></li>
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

            <!-- Course Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm card-hover">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h1 class="h2 fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h1>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($course['description']); ?></p>
                                </div>
                                <span class="course-badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person text-primary"></i>
                                    <span><?php echo htmlspecialchars($course['instructorName']); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-tag text-success"></i>
                                    <span><?php echo htmlspecialchars($course['category']); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-currency-exchange text-warning"></i>
                                    <span>₱<?php echo number_format($course['price'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics - UPDATED: All cards same size -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 d-flex">
                    <div class="card stat-card w-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                    style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); flex-shrink: 0;">
                                    <i class="bi bi-people-fill fs-4 text-primary"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h6 class="text-muted mb-1 text-truncate">Enrolled Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $enrolledStudents; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 d-flex">
                    <div class="card stat-card w-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                    style="width: 60px; height: 60px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); flex-shrink: 0;">
                                    <i class="bi bi-file-earmark-pdf-fill fs-4 text-success"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h6 class="text-muted mb-1 text-truncate">Total Lessons</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalLessons; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 d-flex">
                    <div class="card stat-card w-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                    style="width: 60px; height: 60px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); flex-shrink: 0;">
                                    <i class="bi bi-patch-question-fill fs-4 text-warning"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h6 class="text-muted mb-1 text-truncate">Quizzes</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalQuizCount; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 d-flex">
                    <div class="card stat-card w-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                    style="width: 60px; height: 60px; background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); flex-shrink: 0;">
                                    <i class="bi bi-check-circle-fill fs-4 text-danger"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h6 class="text-muted mb-1 text-truncate">Students Completed</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $completedStudents; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="row mb-4">
                <div class="col-12">
                    <ul class="nav nav-tabs-custom d-flex flex-wrap" id="courseTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'details' ? 'active' : ''; ?>" 
                                    id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                <i class="bi bi-info-circle me-2"></i>Course Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'lessons' ? 'active' : ''; ?>" 
                                    id="lessons-tab" data-bs-toggle="tab" data-bs-target="#lessons" type="button" role="tab">
                                <i class="bi bi-file-earmark-pdf me-2"></i>Lessons (<?php echo $totalLessons; ?>)
                            </button>
                        </li>
                        
                    </ul>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="courseTabsContent">
                <!-- Details Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'details' ? 'show active' : ''; ?>" id="details" role="tabpanel">
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-pencil"></i> Edit Course Information
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="update_course" value="1">
                            
                            <div class="mb-4">
                                <label class="form-label">Course Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Price (PHP)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $course['price']; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control" required>
                                        <option value="Programming" <?php echo $course['category'] == 'Programming' ? 'selected' : ''; ?>>Programming</option>
                                        <option value="Design" <?php echo $course['category'] == 'Design' ? 'selected' : ''; ?>>Design</option>
                                        <option value="Business" <?php echo $course['category'] == 'Business' ? 'selected' : ''; ?>>Business</option>
                                        <option value="Marketing" <?php echo $course['category'] == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control" required>
                                        <option value="draft" <?php echo $course['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $course['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                        <option value="archived" <?php echo $course['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Passing Score (%)</label>
                                <input type="number" name="passingScore" class="form-control" min="0" max="100" value="<?php echo $course['passingScore']; ?>" required>
                            </div>
                            
                            <button type="submit" class="btn-gradient">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </form>

                        <div class="danger-zone mt-5">
                            <div class="danger-zone-title">
                                <i class="bi bi-exclamation-triangle"></i> Danger Zone
                            </div>
                            <p class="text-muted mb-3">This action cannot be undone! All course data, lessons, quizzes, and student enrollments will be permanently deleted.</p>
                            
                            <form method="POST" id="deleteCourseForm">
                                <input type="hidden" name="delete_course" value="1">
                                <button type="button" class="btn-danger" onclick="confirmDeleteCourse()">
                                    <i class="bi bi-trash me-2"></i> Delete Course
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Lessons Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'lessons' ? 'show active' : ''; ?>" id="lessons" role="tabpanel">
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-upload"></i> Upload New Lesson
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_lesson" value="1">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Lesson Title</label>
                                    <input type="text" name="lesson_title" class="form-control" placeholder="e.g., Introduction to PHP" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Upload PDF</label>
                                    <input type="file" name="lesson_file" class="form-control" accept=".pdf" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-success">
                                <i class="bi bi-cloud-upload me-2"></i> Upload Lesson
                            </button>
                        </form>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-list"></i> All Lessons (<?php echo count($lessons); ?>)
                        </div>
                        
                        <?php if (count($lessons) > 0): ?>
                            <?php foreach ($lessons as $lesson): ?>
                                <div class="lesson-item">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-pdf text-danger fs-4 me-3"></i>
                                        <div>
                                            <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                            <div class="text-muted small">
                                                Uploaded: <?php echo date('M d, Y', strtotime($lesson['uploadedAt'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="../<?php echo $lesson['filename']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i> View
                                        </a>
                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteLesson(<?php echo $lesson['lessonID']; ?>)">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-file-earmark-pdf empty-state-icon"></i>
                                <h3 class="h5 fw-bold mb-3">No Lessons Yet</h3>
                                <p class="text-muted mb-4">Upload your first lesson to get started!</p>
                            </div>
                        <?php endif; ?>
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
            const courseTabs = document.querySelectorAll('#courseTabs button[data-bs-toggle="tab"]');
            courseTabs.forEach(tab => {
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

            <?php if (isset($success)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($success); ?>',
                timer: 3000,
                showConfirmButton: true
            });
            <?php endif; ?>

            <?php if (isset($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo addslashes($error); ?>',
                showConfirmButton: true
            });
            <?php endif; ?>

            function deleteLesson(lessonID) {
                Swal.fire({
                    title: 'Delete Lesson?',
                    text: "This action cannot be undone!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'delete_lesson.php?id=' + lessonID + '&course_id=<?php echo $courseID; ?>&tab=lessons';
                    }
                });
            }

            function confirmDeleteCourse() {
                Swal.fire({
                    title: 'Delete Course?',
                    html: `
                        <div style="text-align: left;">
                            <p>This action will permanently delete:</p>
                            <ul>
                                <li>The course: <strong><?php echo htmlspecialchars($course['title']); ?></strong></li>
                                <li>All lessons (<?php echo $totalLessons; ?> files)</li>
                                <li>Quiz (<?php echo $totalQuizCount; ?>)</li>
                                <li>All student enrollments (<?php echo $enrolledStudents; ?> students)</li>
                            </ul>
                            <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                            <p>Type <strong>"DELETE"</strong> to confirm:</p>
                            <input type="text" id="confirmDeleteInput" class="swal2-input" placeholder="Type DELETE here">
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete everything',
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const confirmValue = document.getElementById('confirmDeleteInput').value;
                        if (confirmValue !== 'DELETE') {
                            Swal.showValidationMessage('You must type "DELETE" to confirm');
                        }
                        return confirmValue === 'DELETE';
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteCourseForm').submit();
                    }
                });
            }
        </script>
    </body>
    </html>