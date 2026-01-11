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
    $correctOption = $_POST['correct_option'];

    if (!in_array($correctOption, ['A','B','C','D'])) {
        die('Invalid correct option.');
    }

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
        $correctOption
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Quiz - <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h2>Edit Quiz: <?= htmlspecialchars($quiz['title']) ?></h2>
    <p>Course: <?= htmlspecialchars($quiz['courseTitle']) ?></p>

    <hr>

    <!-- Add Question Form -->
    <h4>Add Multiple Choice Question (A–D)</h4>
    <form method="POST">
        <div class="mb-3">
            <label>Question</label>
            <textarea name="question_text" class="form-control" required></textarea>
        </div>

        <div class="mb-2">
            <label>A</label>
            <input type="text" name="optionA" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>B</label>
            <input type="text" name="optionB" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>C</label>
            <input type="text" name="optionC" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>D</label>
            <input type="text" name="optionD" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Correct Answer</label>
            <select name="correct_option" class="form-control" required>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="add_question" class="btn btn-success">
                💾 Save Question
            </button>
            <a href="edit_course.php?id=<?= $quiz['courseID'] ?>" class="btn btn-secondary">
                ← Back to Course
            </a>
        </div>
    </form>

    <hr>

    <!-- Existing Questions -->
    <h4>Existing Questions</h4>

    <?php if ($questions): ?>
        <?php foreach ($questions as $q): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <strong><?= htmlspecialchars($q['question']) ?></strong>
                    <ul class="mt-2">
                        <li class="<?= $q['correct_option']=='A'?'fw-bold text-success':'' ?>">A. <?= htmlspecialchars($q['option1']) ?></li>
                        <li class="<?= $q['correct_option']=='B'?'fw-bold text-success':'' ?>">B. <?= htmlspecialchars($q['option2']) ?></li>
                        <li class="<?= $q['correct_option']=='C'?'fw-bold text-success':'' ?>">C. <?= htmlspecialchars($q['option3']) ?></li>
                        <li class="<?= $q['correct_option']=='D'?'fw-bold text-success':'' ?>">D. <?= htmlspecialchars($q['option4']) ?></li>
                    </ul>

                    <form method="POST" onsubmit="return confirm('Delete this question?');">
                        <input type="hidden" name="question_id" value="<?= $q['questionID'] ?>">
                        <button name="delete_question" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No questions added yet.</p>
    <?php endif; ?>
</div>

</body>
</html>
