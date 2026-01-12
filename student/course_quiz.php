<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// CHECK IF STUDENT ALREADY FAILED - BLOCK RETAKE WITHOUT PAYMENT
$stmt = $conn->prepare("
    SELECT qr.passed, qr.status 
    FROM quiz_results qr
    JOIN quizzes q ON qr.quizID = q.quizID
    WHERE q.courseID = ? AND qr.userID = ?
    ORDER BY qr.takenAt DESC
    LIMIT 1
");
$stmt->execute([$courseID, $userID]);
$previousResult = $stmt->fetch();

// If student failed the last attempt, they must pay to retake
if ($previousResult && $previousResult['passed'] == 0) {
    $_SESSION['error'] = "You must pay to retake this course after failing the quiz.";
    header('Location: retake_course.php?id=' . $courseID);
    exit();
}

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
    ORDER BY takenAt DESC
    LIMIT 1
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
    $quizStatus = $passed ? 'passed' : 'failed';
    $submitted = true;

    // Save result with status
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (enrollmentID, userID, quizID, score, percentage, passed, status, takenAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$enrollmentID, $userID, $quizID, $score, $percentage, $passed, $quizStatus]);
    
    // Update enrollment status if passed
    if ($passed) {
        $stmt = $conn->prepare("UPDATE enrollments SET status = 'completed', completedAt = NOW() WHERE enrollmentID = ?");
        $stmt->execute([$enrollmentID]);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($quiz['title']) ?> - Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .quiz-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .question-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 25px;
        }
        .quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .result-badge {
            padding: 15px 25px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .result-badge.passed {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .result-badge.failed {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .option-correct {
            background: #d4edda !important;
            border-color: #c3e6cb !important;
        }
        .option-wrong {
            background: #f8d7da !important;
            border-color: #f5c6cb !important;
        }
    </style>
</head>
<body>

<div class="container mt-5 quiz-container">

    <a href="course_learn.php?id=<?= $courseID ?>" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back to Course
    </a>

    <div class="quiz-header">
        <h2><i class="bi bi-patch-question"></i> <?= htmlspecialchars($quiz['title']) ?></h2>
        <?php if (!$submitted): ?>
            <p class="mb-0">Answer all questions below. Passing score: 70%</p>
        <?php endif; ?>
    </div>

    <?php if (!$submitted): ?>
        <form method="POST">
            <?php foreach ($questions as $index => $q): ?>
                <div class="question-card">
                    <p class="fw-bold mb-3">
                        <span class="badge bg-primary me-2"><?= ($index + 1) ?></span>
                        <?= htmlspecialchars($q['question']) ?>
                    </p>
                    <?php 
                    $options = [
                        'A' => $q['option1'],
                        'B' => $q['option2'],
                        'C' => $q['option3'],
                        'D' => $q['option4']
                    ];
                    ?>
                    <?php foreach ($options as $key => $value): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="answers[<?= $q['questionID'] ?>]" 
                                   id="q<?= $q['questionID'] ?>_<?= $key ?>" value="<?= $key ?>" required>
                            <label class="form-check-label" for="q<?= $q['questionID'] ?>_<?= $key ?>">
                                <strong><?= $key ?>.</strong> <?= htmlspecialchars($value) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-check-circle"></i> Submit Quiz
            </button>
        </form>
    <?php else: ?>
        
        <?php if ($passed): ?>
            <div class="result-badge passed">
                <i class="bi bi-check-circle-fill"></i> Congratulations! You Passed!
                <div class="mt-2">Score: <?= round($score / $total * 100, 2) ?>%</div>
            </div>
        <?php else: ?>
            <div class="result-badge failed">
                <i class="bi bi-x-circle-fill"></i> Quiz Failed
                <div class="mt-2">Score: <?= round($score / $total * 100, 2) ?>%</div>
                <div class="mt-2" style="font-size: 14px;">You need to pay to retake this course.</div>
            </div>
        <?php endif; ?>

        <h4 class="mt-4 mb-3">Review Your Answers</h4>

        <?php foreach ($results as $index => $r): ?>
            <div class="question-card">
                <p class="fw-bold mb-3">
                    <span class="badge bg-secondary me-2"><?= ($index + 1) ?></span>
                    <?= htmlspecialchars($r['question']) ?>
                </p>
                <ul class="list-group">
                    <?php foreach ($r['options'] as $key => $value): ?>
                        <li class="list-group-item
                            <?= $key === $r['correct'] ? 'option-correct' : '' ?>
                            <?= $key === $r['student'] && !$r['isCorrect'] ? 'option-wrong' : '' ?>
                        ">
                            <strong><?= $key ?>.</strong> <?= htmlspecialchars($value) ?>
                            <?php if ($key === $r['correct']): ?>
                                <i class="bi bi-check-circle-fill text-success float-end"></i>
                            <?php endif; ?>
                            <?php if ($key === $r['student'] && !$r['isCorrect']): ?>
                                <i class="bi bi-x-circle-fill text-danger float-end"></i>
                                <span class="badge bg-danger float-end me-2">Your Answer</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <!-- Action Buttons -->
        <div class="mt-4 d-grid gap-2">
            <?php if ($passed): ?>
                <a href="certificate.php?course=<?= $courseID ?>" class="btn btn-success btn-lg">
                    <i class="bi bi-award"></i> View Your E-Certificate
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-house"></i> Back to Dashboard
                </a>
            <?php else: ?>
                <a href="retake_course.php?id=<?= $courseID ?>" class="btn btn-warning btn-lg">
                    <i class="bi bi-credit-card"></i> Pay to Retake Course
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house"></i> Back to Dashboard
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>