<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

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

// Get course statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE courseID = ?");
$stmt->execute([$courseID]);
$enrolledStudents = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalQuizzes = $stmt->fetch()['count'];

// Get all lessons
$stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
$stmt->execute([$courseID]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all quizzes
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ? ORDER BY createdAt DESC");
$stmt->execute([$courseID]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get enrolled students
$stmt = $conn->prepare("
    SELECT u.*, e.enrolledAt, e.progressPercentage, e.status
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    WHERE e.courseID = ?
    ORDER BY e.enrolledAt DESC
");
$stmt->execute([$courseID]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

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
    
    // Refresh course data
    $stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    try {
        $conn->beginTransaction();
        
        // 1. Delete lesson files from server
        $stmt = $conn->prepare("SELECT filename FROM lessons WHERE courseID = ?");
        $stmt->execute([$courseID]);
        $lessonFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($lessonFiles as $file) {
            $filePath = '../' . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // 2. Delete lessons from database
        $stmt = $conn->prepare("DELETE FROM lessons WHERE courseID = ?");
        $stmt->execute([$courseID]);
        
        // 3. Get quiz IDs for this course
        $stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
        $stmt->execute([$courseID]);
        $quizIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 4. Delete quiz results
        if (!empty($quizIDs)) {
            $placeholders = implode(',', array_fill(0, count($quizIDs), '?'));
            $stmt = $conn->prepare("DELETE FROM quiz_results WHERE quizID IN ($placeholders)");
            $stmt->execute($quizIDs);
            
            // 5. Delete quiz questions
            $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quizID IN ($placeholders)");
            $stmt->execute($quizIDs);
            
            // 6. Delete quizzes
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE courseID = ?");
            $stmt->execute([$courseID]);
        }
        
        // 7. Delete enrollments
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE courseID = ?");
        $stmt->execute([$courseID]);
        
        // 8. Delete course
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
    }
}

// Handle lesson upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_lesson']) && isset($_FILES['lesson_file'])) {
    $lessonTitle = trim($_POST['lesson_title']);
    $file = $_FILES['lesson_file'];
    
    // Detailed error tracking
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
                    
                    // Refresh lessons
                    $stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
                    $stmt->execute([$courseID]);
                    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Database insert failed - delete uploaded file
                    unlink($uploadPath);
                    $error = "Database error: Failed to save lesson record";
                }
            } else {
                $error = "File uploaded but cannot be found on disk!";
            }
        } else {
            $error = "Failed to move uploaded file! Check directory permissions.";
        }
    } else {
        // Show first error
        $error = $uploadErrors[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Course - <?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Sidebar - Left side */
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

        /* Back Button */
        .btn-back {
            background: white;
            color: #666;
            border: 1px solid #e5e7eb;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .btn-back:hover {
            background: #f8f9fa;
            color: #374151;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Course Header */
        .course-header {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(125, 79, 171, 0.2);
        }

        .course-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .course-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
        }

        /* Stats Grid */
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

        /* Tabs */
        .tabs-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
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
            display: flex;
            align-items: center;
            gap: 8px;
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

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Section Cards */
        .section-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid #eaeaea;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: border-color 0.2s;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #7d4fab;
            box-shadow: 0 0 0 3px rgba(125, 79, 171, 0.1);
        }

        /* Buttons */
        .btn-save {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #0da271 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Lesson Items */
        .lesson-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
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

        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .btn-outline-primary {
            border-color: #7d4fab;
            color: #7d4fab;
        }

        .btn-outline-primary:hover {
            background: #7d4fab;
            color: white;
        }

        .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
        }

        .btn-outline-info {
            border-color: #17a2b8;
            color: #17a2b8;
        }

        .btn-outline-info:hover {
            background: #17a2b8;
            color: white;
        }

        /* Student Rows */
        .student-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
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
            background: linear-gradient(90deg, #7fb3cd 0%, #7d4fab 100%);
            border-radius: 4px;
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
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            border: 1px solid #eaeaea;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #636e72;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #9ca3af;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                padding: 20px;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-profile {
                align-self: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .student-row {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .progress-bar-custom {
                width: 100%;
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
                <a href="courses.php" class="menu-item active">
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
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <h1>Manage Course</h1>
                    <p>Manage your course content, students, and settings</p>
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

            <!-- Back Button -->
            <a href="courses.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Courses
            </a>

            <!-- Course Header -->
            <div class="course-header">
                <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                <p><?php echo htmlspecialchars($course['description']); ?></p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Enrolled Students</h3>
                        <div class="number"><?php echo $enrolledStudents; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Lessons</h3>
                        <div class="number"><?php echo $totalLessons; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-patch-question-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Quizzes</h3>
                        <div class="number"><?php echo $totalQuizzes; ?></div>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="tabs-section">
                <div class="tabs">
                    <div class="tab active" data-tab="details">
                        <i class="bi bi-info-circle"></i> Course Details
                    </div>
                    <div class="tab" data-tab="lessons">
                        <i class="bi bi-file-earmark-pdf"></i> Lessons
                    </div>
                    <div class="tab" data-tab="quizzes">
                        <i class="bi bi-patch-question"></i> Quizzes
                    </div>
                    <div class="tab" data-tab="students">
                        <i class="bi bi-people"></i> Students
                    </div>
                </div>

                <!-- Course Details Tab -->
                <div id="details-tab" class="tab-content active">
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
                                <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Price (PHP)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $course['price']; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control">
                                        <option value="Programming" <?php echo $course['category'] == 'Programming' ? 'selected' : ''; ?>>Programming</option>
                                        <option value="Design" <?php echo $course['category'] == 'Design' ? 'selected' : ''; ?>>Design</option>
                                        <option value="Business" <?php echo $course['category'] == 'Business' ? 'selected' : ''; ?>>Business</option>
                                        <option value="Marketing" <?php echo $course['category'] == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="draft" <?php echo $course['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $course['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                        <option value="archived" <?php echo $course['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Passing Score (%)</label>
                                <input type="number" name="passingScore" class="form-control" min="0" max="100" value="<?php echo $course['passingScore']; ?>">
                            </div>
                            
                            <button type="submit" class="btn-save">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </form>

                        <!-- Danger Zone - Delete Course -->
                        <div class="danger-zone">
                            <div class="danger-zone-title">
                                <i class="bi bi-exclamation-triangle"></i> Danger Zone
                            </div>
                            <p class="text-muted mb-3">This action cannot be undone! All course data, lessons, quizzes, and student enrollments will be permanently deleted.</p>
                            
                            <form method="POST" id="deleteCourseForm">
                                <input type="hidden" name="delete_course" value="1">
                                <button type="button" class="btn-danger" onclick="confirmDeleteCourse()">
                                    <i class="bi bi-trash"></i> Delete Course
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Lessons Tab -->
                <div id="lessons-tab" class="tab-content">
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
                                <i class="bi bi-cloud-upload"></i> Upload Lesson
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
                                    <div>
                                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                                        <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                        <small class="text-muted ms-2">Uploaded: <?php echo date('M d, Y', strtotime($lesson['uploadedAt'])); ?></small>
                                    </div>
                                    <div>
                                        <a href="../<?php echo $lesson['filename']; ?>" target="_blank" class="btn-action btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <button class="btn-action btn-outline-danger" onclick="deleteLesson(<?php echo $lesson['lessonID']; ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-file-earmark-pdf"></i>
                                <h3>No Lessons Yet</h3>
                                <p>No lessons uploaded yet. Upload your first lesson to get started!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quizzes Tab -->
                <div id="quizzes-tab" class="tab-content">
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-plus-circle"></i> Create New Quiz
                        </div>
                        
                        <a href="create_quiz.php?course_id=<?php echo $courseID; ?>" class="btn-success">
                            <i class="bi bi-plus-lg"></i> Create Quiz
                        </a>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-list"></i> All Quizzes (<?php echo count($quizzes); ?>)
                        </div>
                        
                        <?php if (count($quizzes) > 0): ?>
                            <?php foreach ($quizzes as $quiz): ?>
                                <div class="lesson-item">
                                    <div>
                                        <i class="bi bi-patch-question text-primary"></i>
                                        <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                                        <small class="text-muted ms-2">Passing: <?php echo $quiz['passingScore']; ?>%</small>
                                    </div>
                                    <div>
                                        <a href="edit_quiz.php?id=<?php echo $quiz['quizID']; ?>" class="btn-action btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="quiz_results.php?id=<?php echo $quiz['quizID']; ?>" class="btn-action btn-outline-info">
                                            <i class="bi bi-graph-up"></i> Results
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-patch-question"></i>
                                <h3>No Quizzes Yet</h3>
                                <p>No quizzes created yet. Create your first quiz to get started!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Students Tab -->
                <div id="students-tab" class="tab-content">
                    <div class="section-card">
                        <div class="section-title">
                            <i class="bi bi-people"></i> Enrolled Students (<?php echo count($students); ?>)
                        </div>
                        
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $student): ?>
                                <div class="student-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                    </div>
                                    <div class="text-center">
                                        <div class="progress-bar-custom">
                                            <div class="fill" style="width: <?php echo $student['progressPercentage']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($student['progressPercentage']); ?>% Complete</small>
                                    </div>
                                    <div>
                                        <span class="badge <?php echo $student['status'] == 'completed' ? 'bg-success' : 'bg-primary'; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <h3>No Students Yet</h3>
                                <p>No students have enrolled in this course yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab functionality
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding content
            const tabId = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });

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
                window.location.href = 'delete_lesson.php?id=' + lessonID + '&course_id=<?php echo $courseID; ?>';
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
                        <li>All quizzes (<?php echo $totalQuizzes; ?> quizzes)</li>
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