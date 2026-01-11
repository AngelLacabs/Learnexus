<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// Get quiz for course
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("<h3>No quiz available for this course yet.</h3>");
}

$quizID = $quiz['quizID'];

// Fetch quiz questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quizID = ? ORDER BY questionID ASC");
$stmt->execute([$quizID]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if student already took the quiz
$stmt = $conn->prepare("
    SELECT * FROM quiz_results 
    WHERE quizID = ? AND userID = ?
");
$stmt->execute([$quizID, $userID]);
$existingResult = $stmt->fetch(PDO::FETCH_ASSOC);

$submitted = $existingResult ? true : false;
$results = [];
$score = 0;
$total = count($questions);
$passed = 0;

if ($submitted) {
    $score = $existingResult['score'];
    $passed = $existingResult['passed'];

    // Load student's answers from results table if you store them separately
    foreach ($questions as $q) {
        $qid = $q['questionID'];
        $correct = strtoupper($q['correct_option']);
        $studentAnswer = ''; // You may need to fetch from a saved column if storing answers
        $isCorrect = ($studentAnswer === $correct);

        $results[] = [
            'question' => $q['question'],
            'options' => [
                'A' => $q['option1'],
                'B' => $q['option2'],
                'C' => $q['option3'],
                'D' => $q['option4']
            ],
            'correct' => $correct,
            'student' => $studentAnswer,
            'isCorrect' => $isCorrect
        ];
    }
}

// Handle new submission if not already submitted
if (!$submitted && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];

    // Find enrollment
    $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
    $stmt->execute([$userID, $courseID]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) die("You are not enrolled in this course.");
    $enrollmentID = $enrollment['enrollmentID'];

    foreach ($questions as $q) {
        $qid = $q['questionID'];
        $correct = strtoupper($q['correct_option']);
        $studentAnswer = strtoupper($answers[$qid] ?? '');
        $isCorrect = ($studentAnswer === $correct);

        $results[] = [
            'question' => $q['question'],
            'options' => [
                'A' => $q['option1'],
                'B' => $q['option2'],
                'C' => $q['option3'],
                'D' => $q['option4']
            ],
            'correct' => $correct,
            'student' => $studentAnswer,
            'isCorrect' => $isCorrect
        ];

        if ($isCorrect) $score++;
    }

    $percentage = ($total > 0) ? ($score / $total * 100) : 0;
    $passed = ($percentage >= 70) ? 1 : 0;
    $submitted = true;

    // Save result
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (enrollmentID, userID, quizID, score, passed, takenAt)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE score=VALUES(score), passed=VALUES(passed), takenAt=NOW()
    ");
    $stmt->execute([$enrollmentID, $userID, $quizID, $percentage, $passed]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($quiz['title']) ?> - Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">

    <a href="course_learn.php?id=<?= $courseID ?>" class="btn btn-secondary mb-3">
        ← Back to Course
    </a>

    <h2><?= htmlspecialchars($quiz['title']) ?></h2>

    <?php if (!$submitted): ?>
        <form method="POST">
            <?php foreach ($questions as $index => $q): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <p class="fw-bold"><?= ($index + 1) . ". " . htmlspecialchars($q['question']) ?></p>
                        <?php 
                        $options = [
                            'A' => $q['option1'],
                            'B' => $q['option2'],
                            'C' => $q['option3'],
                            'D' => $q['option4']
                        ];
                        ?>
                        <?php foreach ($options as $key => $value): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= $q['questionID'] ?>]" value="<?= $key ?>" required>
                                <label class="form-check-label"><?= $key ?>. <?= htmlspecialchars($value) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Submit Quiz</button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            <h4>Quiz Results</h4>
            <p><strong>Score:</strong> <?= round($score / $total * 100, 2) ?>%</p>
            <p><strong>Status:</strong> <?= $passed ? 'Passed ✅' : 'Failed ❌' ?></p>
        </div>

        <?php foreach ($results as $index => $r): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <p class="fw-bold"><?= ($index + 1) . ". " . htmlspecialchars($r['question']) ?></p>
                    <ul class="list-group">
                        <?php foreach ($r['options'] as $key => $value): ?>
                            <li class="list-group-item
                                <?= $key === $r['correct'] ? 'list-group-item-success' : '' ?>
                                <?= $key === $r['student'] && !$r['isCorrect'] ? 'list-group-item-danger' : '' ?>
                            ">
                                <?= $key ?>. <?= htmlspecialchars($value) ?>
                                <?php if ($key === $r['correct']) echo ' ✅'; ?>
                                <?php if ($key === $r['student'] && !$r['isCorrect']) echo ' ❌ (Your Answer)'; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- E-Certificate Section -->
        <div class="mt-4">
            <?php if ($passed): ?>
                <a href="certificate.php?course=<?= $courseID ?>" class="btn btn-success">🎓 Unlock E-Certificate</a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>🎓 Unlock E-Certificate (Locked)</button>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
