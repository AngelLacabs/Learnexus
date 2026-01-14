<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

// Get teacher's courses and whether each course already has a quiz (quizCount)
$stmt = $conn->prepare("SELECT c.courseID, c.title, (SELECT COUNT(*) FROM quizzes q WHERE q.courseID = c.courseID) as quizCount FROM courses c WHERE c.teacherID = ? ORDER BY c.title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Preselect from query string if provided
$preselectCourse = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$selectedCourseId = $preselectCourse;

// If preselectCourse already has a quiz, redirect to edit that quiz
if ($preselectCourse) {
    $stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ? LIMIT 1");
    $stmt->execute([$preselectCourse]);
    $existingQuiz = $stmt->fetchColumn();
    if ($existingQuiz) {
        header('Location: edit_quiz.php?id=' . $existingQuiz);
        exit();
    }
}

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseID = $_POST['courseID'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $passingScore = intval($_POST['passingScore'] ?? 70);
    $timeLimitMinutes = !empty($_POST['timeLimitMinutes']) ? intval($_POST['timeLimitMinutes']) : null;
    $allowRetake = isset($_POST['allowRetake']) ? 1 : 0;
    
    if ($courseID && $title) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO quizzes (courseID, title, description, passingScore, timeLimitMinutes, allowRetake, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$courseID, $title, $description, $passingScore, $timeLimitMinutes, $allowRetake]);
            $quizID = $conn->lastInsertId();
            
            header('Location: edit_quiz.php?id=' . $quizID);
            exit();
        } catch (PDOException $e) {
            $error = 'Failed to create quiz';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Learnexus</title>
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
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
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

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
        }

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: border-color 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #374151;
            border: 1px solid #e5e7eb;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            color: #374151;
            border-color: #dee2e6;
        }

        /* Back Button */
        .btn-back {
            background: #f8f9fa;
            color: #374151;
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
        }

        .btn-back:hover {
            background: #e9ecef;
            color: #374151;
            border-color: #dee2e6;
        }

        /* Section Title */
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 20px;
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
            <a href="quizzes.php" class="menu-item active">
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
            <a href="../logout.php" class="menu-item">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Back Button -->
        <a href="quizzes.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Quizzes
        </a>

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-plus-circle"></i> Create New Quiz</h1>
            <p>Design your quiz with multiple choice questions</p>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Select Course *</label>
                    <select name="courseID" class="form-select" required>
                        <option value="">Choose a course...</option>
                        <?php foreach ($courses as $course): ?>
                            <?php $disabled = $course['quizCount'] > 0 ? 'disabled' : ''; ?>
                            <?php $selected = $selectedCourseId && $selectedCourseId == $course['courseID'] ? 'selected' : ''; ?>
                            <option value="<?php echo $course['courseID']; ?>" <?php echo $disabled . ' ' . $selected; ?>><?php echo htmlspecialchars($course['title']); ?><?php echo $course['quizCount'] > 0 ? ' (Quiz exists)' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Quiz Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="Enter quiz title">
                </div>

                <div class="mb-4">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Optional description about the quiz"></textarea>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Passing Score (%)</label>
                        <input type="number" name="passingScore" class="form-control" value="70" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Time Limit (minutes, optional)</label>
                        <input type="number" name="timeLimitMinutes" class="form-control" min="1" placeholder="Optional">
                    </div>
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input type="checkbox" name="allowRetake" class="form-check-input" id="allowRetake" checked>
                        <label class="form-check-label" for="allowRetake">
                            <i class="bi bi-check-circle"></i> Allow students to retake this quiz
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Quiz
                    </button>
                    <a href="quizzes.php" class="btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>