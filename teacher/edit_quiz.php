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
        INSERT INTO quiz_questions 
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

    header('Location: edit_quiz.php?id=' . $quizID);
    exit();
}

// Handle deleting a question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $questionID = intval($_POST['question_id']);
    $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE questionID = ? AND quizID = ?");
    $stmt->execute([$questionID, $quizID]);
    header('Location: edit_quiz.php?id=' . $quizID);
    exit();
}

// Fetch all questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quizID = ? ORDER BY questionID ASC");
$stmt->execute([$quizID]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to convert number to letter
function numberToLetter($num) {
    return chr(65 + intval($num)); // 0=A, 1=B, 2=C, 3=D
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

        /* Quiz Header */
        .quiz-header {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(125, 79, 171, 0.2);
        }

        .quiz-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .quiz-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
        }

        /* Quiz Stats */
        .quiz-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-badge {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-badge .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }

        .stat-badge .content h4 {
            font-size: 12px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .stat-badge .content .number {
            font-size: 24px;
            font-weight: 700;
            color: #2d3436;
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
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

        .form-control, .form-select {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: border-color 0.2s;
            width: 100%;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #7d4fab;
            box-shadow: 0 0 0 3px rgba(125, 79, 171, 0.1);
        }

        /* Buttons */
        .btn-primary {
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

        .btn-primary:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Question Cards */
        .question-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            padding: 25px;
            border: 1px solid #eaeaea;
            transition: all 0.2s;
        }

        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .question-number {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .question-number .badge {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            padding: 8px 15px;
            font-size: 14px;
            font-weight: 600;
        }

        .question-number h5 {
            margin: 0;
            color: #2d3436;
            font-weight: 600;
        }

        .options-list {
            margin-top: 15px;
        }

        .option-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-left: 4px solid #e0e0e0;
            border-radius: 6px;
            background: #f8f9fa;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .option-item:hover {
            background: #e9ecef;
        }

        .option-item.correct {
            background: #d4edda;
            border-left-color: #28a745;
        }

        .option-text {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .option-letter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            font-weight: 600;
            font-size: 14px;
        }

        .option-item.correct .option-letter {
            background: #28a745;
            color: white;
        }

        /* Badge for Correct Answer */
        .correct-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
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
            
            .quiz-stats {
                flex-direction: column;
            }
            
            .question-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .question-number {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
                    <h1>Edit Quiz</h1>
                    <p>Add and manage quiz questions</p>
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
            <a href="quizzes.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Quizzes
            </a>

            <!-- Quiz Header -->
            <div class="quiz-header">
                <h1><i class="bi bi-patch-question"></i> <?= htmlspecialchars($quiz['title']) ?></h1>
                <p>Course: <?= htmlspecialchars($quiz['courseTitle']) ?></p>
            </div>

            <!-- Quiz Stats -->
            <div class="quiz-stats">
                <div class="stat-badge">
                    <div class="icon">
                        <i class="bi bi-list-ul"></i>
                    </div>
                    <div class="content">
                        <h4>Total Questions</h4>
                        <div class="number"><?= count($questions) ?></div>
                    </div>
                </div>
                <div class="stat-badge">
                    <div class="icon">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div class="content">
                        <h4>Time Limit</h4>
                        <div class="number"><?= isset($quiz['timeLimitMinutes']) && $quiz['timeLimitMinutes'] > 0 ? $quiz['timeLimitMinutes'] . 'm' : 'Unlimited' ?></div>
                    </div>
                </div>
                <div class="stat-badge">
                    <div class="icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="content">
                        <h4>Passing Score</h4>
                        <div class="number"><?= isset($quiz['passingScore']) && $quiz['passingScore'] > 0 ? $quiz['passingScore'] . '%' : 'Not Set' ?></div>
                    </div>
                </div>
            </div>

            <!-- Add Question Form -->
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-plus-circle"></i> Add Multiple Choice Question
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Question Text</label>
                        <textarea name="question_text" class="form-control" rows="3" required 
                                  placeholder="Enter your question here..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Option A</label>
                            <input type="text" name="optionA" class="form-control" required 
                                   placeholder="Enter option A">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Option B</label>
                            <input type="text" name="optionB" class="form-control" required 
                                   placeholder="Enter option B">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Option C</label>
                            <input type="text" name="optionC" class="form-control" required 
                                   placeholder="Enter option C">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Option D</label>
                            <input type="text" name="optionD" class="form-control" required 
                                   placeholder="Enter option D">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-success">
                            <i class="bi bi-check-circle"></i> Correct Answer
                        </label>
                        <select name="correct_option" class="form-select" required>
                            <option value="">-- Select Correct Answer --</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>

                    <button type="submit" name="add_question" class="btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Question
                    </button>
                </form>
            </div>

            <!-- Existing Questions -->
            <div class="section-card">
                <div class="section-title">
                    <i class="bi bi-list-ul"></i> Existing Questions (<?= count($questions) ?>)
                </div>

                <?php if ($questions): ?>
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <div class="question-number">
                                    <span class="badge">Q<?= $index + 1 ?></span>
                                    <h5><?= htmlspecialchars($q['question']) ?></h5>
                                </div>
                                <form method="POST" onsubmit="return confirmDeleteQuestion();" style="display: inline;">
                                    <input type="hidden" name="question_id" value="<?= $q['questionID'] ?>">
                                    <button name="delete_question" class="btn-danger">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </div>

                            <div class="options-list">
                                <?php 
                                $correctNum = intval($q['correct_option']); // This is now 0, 1, 2, or 3
                                $options = [
                                    $q['option1'],
                                    $q['option2'],
                                    $q['option3'],
                                    $q['option4']
                                ];
                                ?>
                                
                                <?php foreach ($options as $optIndex => $optText): ?>
                                    <div class="option-item <?= $optIndex === $correctNum ? 'correct' : '' ?>">
                                        <div class="option-text">
                                            <span class="option-letter"><?= numberToLetter($optIndex) ?></span>
                                            <?= htmlspecialchars($optText) ?>
                                        </div>
                                        <?php if ($optIndex === $correctNum): ?>
                                            <span class="correct-badge">
                                                <i class="bi bi-check-circle"></i> Correct Answer
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-patch-question"></i>
                        <h3>No Questions Yet</h3>
                        <p>No questions added yet. Add your first question above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDeleteQuestion() {
        return confirm('Are you sure you want to delete this question? This action cannot be undone.');
    }
</script>
</body>
</html>