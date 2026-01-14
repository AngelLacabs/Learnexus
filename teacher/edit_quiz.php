<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$quizID = $_GET['id'] ?? 0;

// Fetch quiz and course info
$stmt = $conn->prepare("
    SELECT q.*, c.title AS courseTitle, c.courseID 
    FROM quizzes q
    JOIN courses c ON q.courseID = c.courseID
    WHERE q.quizID = ? AND c.teacherID = ?
");
$stmt->execute([$quizID, $teacherID]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: courses.php');
    exit();
}

// Get course statistics for dashboard
$courseID = $quiz['courseID'];
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE courseID = ?");
$stmt->execute([$courseID]);
$enrolledStudents = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE courseID = ? AND (status = 'completed' OR completedAt IS NOT NULL)");
$stmt->execute([$courseID]);
$completedStudents = (int)$stmt->fetch()['count'];

$totalQuizQuestions = 0; // Will be calculated later

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

// Handle adding a question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $questionText = trim($_POST['question_text']);
    $options = [
        'A' => trim($_POST['optionA']),
        'B' => trim($_POST['optionB']),
        'C' => trim($_POST['optionC']),
        'D' => trim($_POST['optionD']),
    ];
    $correctOption = $_POST['correct_option']; // This will be 'A', 'B', 'C', or 'D'

    if (!in_array($correctOption, ['A','B','C','D'])) {
        die('Invalid correct option.');
    }

    // Convert letter to number (A=0, B=1, C=2, D=3) for storage
    $correctOptionNumber = ord($correctOption) - ord('A'); // A=0, B=1, C=2, D=3

    $stmt = $conn->prepare("
        INSERT INTO quizquestions 
        (quizID, question, option1, option2, option3, option4, correct_option)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $quizID,
        $questionText,
        $options['A'],
        $options['B'],
        $options['C'],
        $options['D'],
        $correctOptionNumber  // Store as number: 0, 1, 2, or 3
    ]);

    $_SESSION['success'] = "Question added successfully!";
    header("Location: edit_quiz.php?id=$quizID");
    exit();
}

// Handle updating quiz details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quiz'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $passingScore = intval($_POST['passingScore']);
    $timeLimitMinutes = intval($_POST['timeLimitMinutes']);
    
    $stmt = $conn->prepare("
        UPDATE quizzes 
        SET title = ?, description = ?, passingScore = ?, timeLimitMinutes = ?
        WHERE quizID = ?
    ");
    $stmt->execute([$title, $description, $passingScore, $timeLimitMinutes, $quizID]);
    
    $_SESSION['success'] = "Quiz updated successfully!";
    
    // Refresh quiz data
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE quizID = ?");
    $stmt->execute([$quizID]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header("Location: edit_quiz.php?id=$quizID");
    exit();
}

// Handle deleting a question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $questionID = intval($_POST['question_id']);
    $stmt = $conn->prepare("DELETE FROM quizquestions WHERE questionID = ? AND quizID = ?");
    $stmt->execute([$questionID, $quizID]);
    
    $_SESSION['success'] = "Question deleted successfully!";
    header("Location: edit_quiz.php?id=$quizID");
    exit();
}

// Handle delete quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    try {
        $conn->beginTransaction();
        
        // Get question IDs
        $stmt = $conn->prepare("SELECT questionID FROM quizquestions WHERE quizID = ?");
        $stmt->execute([$quizID]);
        $questionIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete quiz results
        $stmt = $conn->prepare("DELETE FROM quizresults WHERE quizID = ?");
        $stmt->execute([$quizID]);
        
        // Delete quiz questions
        $stmt = $conn->prepare("DELETE FROM quizquestions WHERE quizID = ?");
        $stmt->execute([$quizID]);
        
        // Delete quiz
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE quizID = ?");
        $stmt->execute([$quizID]);
        
        $conn->commit();
        
        // Redirect to manage course page
        $_SESSION['success'] = "Quiz deleted successfully!";
        header("Location: manage_course.php?id=$courseID&tab=quizzes");
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Failed to delete quiz: " . $e->getMessage();
    }
}

// Fetch all questions
$stmt = $conn->prepare("SELECT * FROM quizquestions WHERE quizID = ? ORDER BY questionID ASC");
$stmt->execute([$quizID]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalQuizQuestions = count($questions);

// Helper function to convert number to letter
function numberToLetter($num) {
    return chr(65 + intval($num)); // 0=A, 1=B, 2=C, 3=D
}

// Check for success message from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - <?= htmlspecialchars($quiz['title']) ?> - Learnexus</title>
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

        /* Sidebar - Matching courses.php design */
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

        /* Navigation - Matching courses.php design */
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

        /* Hamburger - Matching courses.php design */
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

        /* Stats Cards - Matching courses.php design */
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

        /* Quiz Header - New design matching courses.php */
        .quiz-header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            margin-bottom: 30px;
        }

        .quiz-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        /* Form Cards - Matching courses.php design */
        .form-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .form-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Section Cards */
        .section-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #eaeaea;
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

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            transition: border-color 0.2s;
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Buttons - Matching courses.php design */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 10px 24px;
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
            padding: 10px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #0da271 0%, #047857 100%);
            color: white;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Question Items */
        .question-item {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid #eaeaea;
            transition: all 0.2s;
        }

        .question-item:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* Action Buttons */
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .btn-outline-primary {
            border-color: #667eea;
            color: #667eea;
        }

        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-info {
            border-color: #17a2b8;
            color: #17a2b8;
        }

        .btn-outline-info:hover {
            background: #17a2b8;
            color: white;
            transform: translateY(-1px);
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

        /* Search Input - Matching courses.php */
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

        /* User Avatar - Matching courses.php */
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
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

        /* Option Items */
        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eaeaea;
        }

        .option-item.correct {
            background: #e8f5e9;
            border-color: #c8e6c9;
        }

        .option-number {
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .option-text {
            flex: 1;
            margin: 0;
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
            <!-- Header with Search and Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <a href="quizzes.php?id=<?php echo $courseID; ?>&tab=quizzes" class="btn-back d-flex align-items-center gap-2 text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Back to Quizzes
                            </a>
                            
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

            <!-- Page Title -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 fw-bold"><i class="bi bi-pencil me-2"></i>Edit Quiz</h1>
                    <p class="text-muted">Edit quiz details and manage questions</p>
                </div>
            </div>

            <!-- Quiz Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card quiz-header-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h1 class="h2 fw-bold mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                                    <p class="mb-0 opacity-75"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                </div>
                                <span class="quiz-badge">
                                    <?php echo count($questions); ?> Questions
                                </span>
                            </div>
                            <div class="d-flex gap-4 text-white opacity-75">
                                <div>
                                    <i class="bi bi-book-fill me-1"></i>
                                    <?php echo htmlspecialchars($quiz['courseTitle']); ?>
                                </div>
                                <div>
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    Passing: <?php echo isset($quiz['passingScore']) ? $quiz['passingScore'] . '%' : 'Not Set'; ?>
                                </div>
                                <div>
                                    <i class="bi bi-clock-fill me-1"></i>
                                    Time: <?php echo isset($quiz['timeLimitMinutes']) && $quiz['timeLimitMinutes'] > 0 ? $quiz['timeLimitMinutes'] . 'm' : 'Unlimited'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add New Question Form -->
            <div class="form-card p-4 mb-4">
                <div class="section-title">
                    <i class="bi bi-plus-circle"></i> Add New Question
                </div>
                
                <form method="POST">
                    <input type="hidden" name="add_question" value="1">
                    
                    <div class="mb-4">
                        <label class="form-label">Question Text</label>
                        <textarea name="question_text" class="form-control" rows="3" placeholder="Enter your question here..." required></textarea>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Option A</label>
                            <input type="text" name="optionA" class="form-control" placeholder="Enter option A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option B</label>
                            <input type="text" name="optionB" class="form-control" placeholder="Enter option B" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option C</label>
                            <input type="text" name="optionC" class="form-control" placeholder="Enter option C" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option D</label>
                            <input type="text" name="optionD" class="form-control" placeholder="Enter option D" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Correct Answer</label>
                        <select name="correct_option" class="form-control" required>
                            <option value="">Select correct answer</option>
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-success">
                        <i class="bi bi-plus-lg me-2"></i> Add Question
                    </button>
                </form>
            </div>

            <!-- Questions Section -->
            <div class="form-card p-4 mb-4">
                <div class="section-title">
                    <i class="bi bi-list"></i> Quiz Questions (<?php echo count($questions); ?>)
                </div>
                
                <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-item">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-bold mb-1">Question <?php echo $index + 1; ?></h5>
                                    <p class="mb-2"><?php echo htmlspecialchars($question['question']); ?></p>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="delete_question" value="1">
                                        <input type="hidden" name="question_id" value="<?php echo $question['questionID']; ?>">
                                        <button type="button" class="btn-action btn-outline-danger" onclick="confirmDeleteQuestion(this)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="options-list">
                                <?php 
                                $correctNum = intval($question['correct_option']);
                                $options = [
                                    $question['option1'],
                                    $question['option2'],
                                    $question['option3'],
                                    $question['option4']
                                ];
                                ?>
                                
                                <?php foreach ($options as $optIndex => $optText): ?>
                                    <div class="option-item <?php echo $optIndex === $correctNum ? 'correct' : ''; ?>">
                                        <div class="option-number"><?php echo numberToLetter($optIndex); ?></div>
                                        <p class="option-text mb-0"><?php echo htmlspecialchars($optText); ?></p>
                                        <?php if ($optIndex === $correctNum): ?>
                                            <span class="badge bg-success ms-2">Correct</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-patch-question empty-state-icon"></i>
                        <h3 class="h5 fw-bold mb-3">No Questions Yet</h3>
                        <p class="text-muted mb-4">Add questions to your quiz to get started!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Danger Zone -->
            <div class="form-card p-4">
                <div class="danger-zone">
                    <div class="danger-zone-title">
                        <i class="bi bi-exclamation-triangle"></i> Danger Zone
                    </div>
                    <p class="text-muted mb-3">This action cannot be undone! All quiz data, questions, and student results will be permanently deleted.</p>
                    
                    <form method="POST" id="deleteQuizForm">
                        <input type="hidden" name="delete_quiz" value="1">
                        <button type="button" class="btn-danger" onclick="confirmDeleteQuiz()">
                            <i class="bi bi-trash me-2"></i> Delete Quiz
                        </button>
                    </form>
                </div>
            </div>
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

function confirmDeleteQuestion(button) {
    Swal.fire({
        title: 'Delete Question?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            button.closest('form').submit();
        }
    });
}

function confirmDeleteQuiz() {
    Swal.fire({
        title: 'Delete Quiz?',
        html: `
            <div style="text-align: left;">
                <p>This action will permanently delete:</p>
                <ul>
                    <li>The quiz: <strong><?php echo htmlspecialchars($quiz['title']); ?></strong></li>
                    <li>All questions (<?php echo count($questions); ?> questions)</li>
                    <li>All student results</li>
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
            document.getElementById('deleteQuizForm').submit();
        }
    });
}
</script>
</body>
</html>