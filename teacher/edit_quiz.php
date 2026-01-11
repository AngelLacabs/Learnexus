<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$quizID = $_GET['id'] ?? 0;
$teacherID = $_SESSION['user_id'];

// Get quiz details
$stmt = $conn->prepare("
    SELECT q.*, c.title as courseTitle
    FROM quizzes q
    JOIN courses c ON q.courseID = c.courseID
    WHERE q.quizID = ? AND c.teacherID = ?
");
$stmt->execute([$quizID, $teacherID]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quizzes.php');
    exit();
}

// Get questions
$stmt = $conn->prepare("SELECT * FROM questions WHERE quizID = ? ORDER BY orderNumber");
$stmt->execute([$quizID]);
$questions = $stmt->fetchAll();

// Get choices for each question
$questionChoices = [];
foreach ($questions as $q) {
    $stmt = $conn->prepare("SELECT * FROM choices WHERE questionID = ? ORDER BY choiceLetter");
    $stmt->execute([$q['questionID']]);
    $questionChoices[$q['questionID']] = $stmt->fetchAll();
}

// Handle add question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $questionText = trim($_POST['questionText']);
    $points = intval($_POST['points'] ?? 1);
    $orderNumber = count($questions) + 1;
    
    $stmt = $conn->prepare("INSERT INTO questions (quizID, questionText, points, orderNumber) VALUES (?, ?, ?, ?)");
    $stmt->execute([$quizID, $questionText, $points, $orderNumber]);
    $questionID = $conn->lastInsertId();
    
    // Add choices
    $choices = ['A', 'B', 'C', 'D'];
    foreach ($choices as $letter) {
        $choiceText = trim($_POST['choice_' . $letter] ?? '');
        $isCorrect = ($_POST['correct_answer'] == $letter) ? 1 : 0;
        
        if ($choiceText) {
            $stmt = $conn->prepare("INSERT INTO choices (questionID, choiceLetter, choiceText, isCorrect) VALUES (?, ?, ?, ?)");
            $stmt->execute([$questionID, $letter, $choiceText, $isCorrect]);
        }
    }
    
    header('Location: edit_quiz.php?id=' . $quizID);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Quiz - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .question-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .correct-answer {
            background: #e8f5e9;
            border-left: 4px solid #43a047;
        }
    </style>
</head>
<body style="background: #f8f9fa;">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($quiz['courseTitle']); ?></p>
            </div>
            <div>
                <button class="btn btn-outline-secondary"><i class="bi bi-link"></i></button>
                <button class="btn btn-outline-secondary"><i class="bi bi-share"></i></button>
                <button class="btn btn-outline-secondary"><i class="bi bi-printer"></i></button>
                <button class="btn btn-outline-secondary"><i class="bi bi-files"></i></button>
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
            </div>
        </div>

        <!-- Display existing questions -->
        <?php foreach ($questions as $index => $q): ?>
            <div class="question-card">
                <h5><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($q['questionText']); ?></h5>
                
                <?php if (isset($questionChoices[$q['questionID']])): ?>
                    <?php foreach ($questionChoices[$q['questionID']] as $choice): ?>
                        <div class="p-2 mb-2 <?php echo $choice['isCorrect'] ? 'correct-answer' : 'border rounded'; ?>">
                            <?php echo $choice['choiceLetter']; ?>. <?php echo htmlspecialchars($choice['choiceText']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php 
                $correctChoice = array_filter($questionChoices[$q['questionID']], fn($c) => $c['isCorrect']);
                $correctChoice = reset($correctChoice);
                ?>
                <div class="mt-3">
                    <strong>Correct: <?php echo $correctChoice['choiceLetter']; ?>. <?php echo htmlspecialchars($correctChoice['choiceText']); ?></strong>
                </div>
                
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add question form -->
        <div class="card mt-4">
            <div class="card-body">
                <h5>Add Another Question</h5>
                <form method="POST">
                    <input type="hidden" name="add_question" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Question *</label>
                        <textarea name="questionText" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Choice A *</label>
                        <input type="text" name="choice_A" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Choice B *</label>
                        <input type="text" name="choice_B" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Choice C *</label>
                        <input type="text" name="choice_C" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Choice D *</label>
                        <input type="text" name="choice_D" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Correct Answer *</label>
                        <select name="correct_answer" class="form-control" required>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Points</label>
                        <input type="number" name="points" class="form-control" value="1" min="1">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Another Question</button>
                    <a href="quizzes.php" class="btn btn-secondary">Done</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>