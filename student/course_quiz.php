<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// CHECK IF STUDENT ALREADY FAILED - Allow retake if they've paid
$stmt = $conn->prepare("
    SELECT qr.passed, qr.status, qr.takenAt
    FROM quiz_results qr
    JOIN quizzes q ON qr.quizID = q.quizID
    WHERE q.courseID = ? AND qr.userID = ?
    ORDER BY qr.takenAt DESC
    LIMIT 1
");
$stmt->execute([$courseID, $userID]);
$previousResult = $stmt->fetch();

// If student failed, check if they paid for retake
if ($previousResult && $previousResult['passed'] == 0) {
    // Check if they've paid for retake AFTER failing
    $stmt = $conn->prepare("
        SELECT COUNT(*) as retake_payment_count
        FROM payments p
        JOIN enrollments e ON p.enrollmentID = e.enrollmentID
        WHERE e.courseID = ? 
        AND e.userID = ?
        AND p.paymentDate > ?
        AND p.status = 'completed'
    ");
    $stmt->execute([$courseID, $userID, $previousResult['takenAt']]);
    $retakePayment = $stmt->fetch();
    
    // If no retake payment found, redirect to retake payment page
    if ($retakePayment['retake_payment_count'] == 0) {
        $_SESSION['error'] = "You must pay to retake this course after failing the quiz.";
        header('Location: retake_course.php?id=' . $courseID);
        exit();
    }
}

// Get course info
$stmt = $conn->prepare("SELECT title, passingScore FROM courses WHERE courseID = ?");
$stmt->execute([$courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found");
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

if (empty($questions)) {
    die("<h3>No questions available for this quiz yet.</h3>");
}

// Check if student already took the quiz (and hasn't paid for retake)
$stmt = $conn->prepare("
    SELECT * FROM quiz_results 
    WHERE quizID = ? AND userID = ?
    ORDER BY takenAt DESC
    LIMIT 1
");
$stmt->execute([$quizID, $userID]);
$existingResult = $stmt->fetch(PDO::FETCH_ASSOC);

$submitted = false;
$results = [];
$score = 0;
$total = count($questions);
$passed = 0;

// Only show existing result if they haven't paid for retake
if ($existingResult) {
    // Check if there's a retake payment AFTER this result
    $stmt = $conn->prepare("
        SELECT COUNT(*) as retake_count
        FROM payments p
        JOIN enrollments e ON p.enrollmentID = e.enrollmentID
        WHERE e.courseID = ? 
        AND e.userID = ?
        AND p.paymentDate > ?
        AND p.status = 'completed'
    ");
    $stmt->execute([$courseID, $userID, $existingResult['takenAt']]);
    $retakeCheck = $stmt->fetch();
    
    // If no retake payment, show existing result
    if ($retakeCheck['retake_count'] == 0) {
        $submitted = true;
        $score = $existingResult['score'];
        $passed = $existingResult['passed'];
        
        // Get the stored answers if available
        $stmt = $conn->prepare("
            SELECT qa.questionID, qa.selectedOption, qq.correct_option
            FROM quiz_answers qa
            JOIN quiz_questions qq ON qa.questionID = qq.questionID
            WHERE qa.quizResultID = ?
        ");
        $stmt->execute([$existingResult['resultID']]);
        $storedAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reconstruct results for review
        foreach ($questions as $q) {
            $studentAnswer = -1;
            foreach ($storedAnswers as $sa) {
                if ($sa['questionID'] == $q['questionID']) {
                    $studentAnswer = (int)$sa['selectedOption'];
                    break;
                }
            }
            
            $results[] = [
                'question' => $q['question'],
                'options' => [
                    $q['option1'],
                    $q['option2'],
                    $q['option3'],
                    $q['option4']
                ],
                'correct' => (int)$q['correct_option'],
                'student' => $studentAnswer
            ];
        }
    }
}

// Handle new submission
if (!$submitted && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];

    // Find enrollment
    $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
    $stmt->execute([$userID, $courseID]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) die("You are not enrolled in this course.");
    $enrollmentID = $enrollment['enrollmentID'];

    // Calculate score
    foreach ($questions as $q) {
        $qid = $q['questionID'];
        $correct = (int)$q['correct_option']; // 0, 1, 2, or 3
        $studentAnswer = isset($answers[$qid]) ? (int)$answers[$qid] : -1;
        
        if ($studentAnswer === $correct) {
            $score++;
        }
        
        $results[] = [
            'question' => $q['question'],
            'options' => [
                $q['option1'],
                $q['option2'],
                $q['option3'],
                $q['option4']
            ],
            'correct' => $correct,
            'student' => $studentAnswer
        ];
    }

    $percentage = ($total > 0) ? ($score / $total * 100) : 0;
    $passed = ($percentage >= ($course['passingScore'] ?? 70)) ? 1 : 0;
    $quizStatus = $passed ? 'passed' : 'failed';
    $submitted = true;

    // Save result with status
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (enrollmentID, userID, quizID, score, percentage, passed, status, takenAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$enrollmentID, $userID, $quizID, $score, $percentage, $passed, $quizStatus]);
    $resultID = $conn->lastInsertId();
    
    // Save individual answers for review
    foreach ($answers as $questionID => $selectedOption) {
        $stmt = $conn->prepare("
            INSERT INTO quiz_answers (quizResultID, questionID, selectedOption)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$resultID, (int)$questionID, (int)$selectedOption]);
    }
    
    // Update enrollment status if passed
    if ($passed) {
        $stmt = $conn->prepare("UPDATE enrollments SET status = 'completed', completedAt = NOW() WHERE enrollmentID = ?");
        $stmt->execute([$enrollmentID]);
    }
}

// Helper function to convert option index to letter
function getOptionLetter($index) {
    return chr(65 + $index); // 0=A, 1=B, 2=C, 3=D
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> - Quiz</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .quiz-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
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
        .option-label {
            display: block;
            padding: 15px 20px;
            margin-bottom: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option-label:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .option-label input[type="radio"] {
            margin-right: 10px;
        }
        .option-label input[type="radio"]:checked ~ span {
            font-weight: 600;
        }
        .option-correct {
            background: #d4edda !important;
            border-color: #c3e6cb !important;
        }
        .option-wrong {
            background: #f8d7da !important;
            border-color: #f5c6cb !important;
        }
        .question-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            text-align: center;
            line-height: 32px;
            font-weight: 600;
            margin-right: 10px;
        }
    </style>
</head>
<body>

<div class="quiz-container">

    <a href="course_learn.php?id=<?= $courseID ?>" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Back to Course
    </a>

    <div class="quiz-header">
        <h2><i class="bi bi-patch-question"></i> <?= htmlspecialchars($quiz['title']) ?></h2>
        <p class="mb-0"><?= htmlspecialchars($course['title']) ?></p>
        <?php if (!$submitted): ?>
            <p class="mb-0 mt-2">
                <i class="bi bi-info-circle"></i> Passing score: <?= $course['passingScore'] ?? 70 ?>% | 
                Total questions: <?= $total ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!$submitted): ?>
        <!-- QUIZ FORM -->
        <form method="POST">
            <?php foreach ($questions as $index => $q): ?>
                <div class="question-card">
                    <p class="fw-bold mb-3">
                        <span class="question-number"><?= ($index + 1) ?></span>
                        <?= htmlspecialchars($q['question']) ?>
                    </p>
                    
                    <?php 
                    $options = [
                        $q['option1'],
                        $q['option2'],
                        $q['option3'],
                        $q['option4']
                    ];
                    ?>
                    
                    <?php foreach ($options as $optIndex => $optText): ?>
                        <label class="option-label">
                            <input type="radio" 
                                   name="answers[<?= $q['questionID'] ?>]" 
                                   value="<?= $optIndex ?>" 
                                   required>
                            <span><strong><?= getOptionLetter($optIndex) ?>.</strong> <?= htmlspecialchars($optText) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-check-circle"></i> Submit Quiz
            </button>
        </form>
        
    <?php else: ?>
        <!-- RESULTS VIEW -->
        
        <?php if ($passed): ?>
            <div class="result-badge passed">
                <i class="bi bi-check-circle-fill"></i> Congratulations! You Passed!
                <div class="mt-2">Score: <?= $score ?>/<?= $total ?> (<?= round(($score/$total)*100, 2) ?>%)</div>
            </div>
        <?php else: ?>
            <div class="result-badge failed">
                <i class="bi bi-x-circle-fill"></i> Quiz Failed
                <div class="mt-2">Score: <?= $score ?>/<?= $total ?> (<?= round(($score/$total)*100, 2) ?>%)</div>
                <div class="mt-2" style="font-size: 14px;">Required: <?= $course['passingScore'] ?? 70 ?>% to pass</div>
            </div>
        <?php endif; ?>

        <h4 class="mt-4 mb-3">Review Your Answers</h4>

        <?php if (!empty($results)): ?>
            <?php foreach ($results as $index => $r): ?>
                <div class="question-card">
                    <p class="fw-bold mb-3">
                        <span class="question-number"><?= ($index + 1) ?></span>
                        <?= htmlspecialchars($r['question']) ?>
                    </p>
                    
                    <?php foreach ($r['options'] as $optIndex => $optText): ?>
                        <div class="option-label
                            <?= $optIndex === $r['correct'] ? 'option-correct' : '' ?>
                            <?= $optIndex === $r['student'] && $r['student'] !== $r['correct'] ? 'option-wrong' : '' ?>
                        ">
                            <strong><?= getOptionLetter($optIndex) ?>.</strong> <?= htmlspecialchars($optText) ?>
                            
                            <?php if ($optIndex === $r['correct']): ?>
                                <i class="bi bi-check-circle-fill text-success float-end"></i>
                            <?php endif; ?>
                            
                            <?php if ($optIndex === $r['student'] && $r['student'] !== $r['correct']): ?>
                                <i class="bi bi-x-circle-fill text-danger float-end"></i>
                                <span class="badge bg-danger float-end me-2">Your Answer</span>
                            <?php elseif ($optIndex === $r['student'] && $r['student'] === $r['correct']): ?>
                                <span class="badge bg-success float-end me-2">Your Answer</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

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