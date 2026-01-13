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
<html>
<head>
    <title>Edit Quiz - <?= htmlspecialchars($quiz['title']) ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 900px;
        }
        .quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .question-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 25px;
        }
        .add-question-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        .correct-answer {
            font-weight: bold;
            color: #28a745;
        }
        .option-item {
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid transparent;
        }
        .option-item.correct {
            background-color: #d4edda;
            border-left-color: #28a745;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <a href="edit_course.php?id=<?= $quiz['courseID'] ?>" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back to Course
    </a>

    <div class="quiz-header">
        <h2><i class="bi bi-patch-question"></i> Edit Quiz: <?= htmlspecialchars($quiz['title']) ?></h2>
        <p class="mb-0">Course: <?= htmlspecialchars($quiz['courseTitle']) ?></p>
        <small class="text-white-50">Total Questions: <?= count($questions) ?></small>
    </div>

    <!-- Add Question Form -->
    <div class="add-question-form">
        <h4 class="mb-4"><i class="bi bi-plus-circle"></i> Add Multiple Choice Question</h4>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">Question Text</label>
                <textarea name="question_text" class="form-control" rows="3" required 
                          placeholder="Enter your question here..."></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Option A</label>
                    <input type="text" name="optionA" class="form-control" required 
                           placeholder="Enter option A">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Option B</label>
                    <input type="text" name="optionB" class="form-control" required 
                           placeholder="Enter option B">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Option C</label>
                    <input type="text" name="optionC" class="form-control" required 
                           placeholder="Enter option C">
                </div>
                <div class="col-md-6 mb-3">
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

            <button type="submit" name="add_question" class="btn btn-primary btn-lg">
                <i class="bi bi-plus-circle"></i> Add Question
            </button>
        </form>
    </div>

    <!-- Existing Questions -->
    <h4 class="mb-3"><i class="bi bi-list-ul"></i> Existing Questions (<?= count($questions) ?>)</h4>

    <?php if ($questions): ?>
        <?php foreach ($questions as $index => $q): ?>
            <div class="question-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">
                        <span class="badge bg-primary me-2">Q<?= $index + 1 ?></span>
                        <?= htmlspecialchars($q['question']) ?>
                    </h5>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this question?');" style="display: inline;">
                        <input type="hidden" name="question_id" value="<?= $q['questionID'] ?>">
                        <button name="delete_question" class="btn btn-danger btn-sm">
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
                            <strong><?= numberToLetter($optIndex) ?>.</strong> <?= htmlspecialchars($optText) ?>
                            <?php if ($optIndex === $correctNum): ?>
                                <i class="bi bi-check-circle-fill text-success float-end"></i>
                                <span class="badge bg-success float-end me-2">Correct Answer</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No questions added yet. Add your first question above!
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>