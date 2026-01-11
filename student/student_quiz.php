<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// 1️⃣ Get the quiz for this course
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("<h3>No quiz available for this course yet.</h3>");
}

$quizID = $quiz['quizID'];

// 2️⃣ Get student's enrollment
$stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    die("You are not enrolled in this course.");
}

$enrollmentID = $enrollment['enrollmentID'];

// 3️⃣ Check if student already submitted this quiz
$stmt = $conn->prepare("SELECT score, passed FROM quiz_results WHERE enrollmentID = ? AND quizID = ?");
$stmt->execute([$enrollmentID, $quizID]);
$existingResult = $stmt->fetch(PDO::FETCH_ASSOC);

// 4️⃣ Fetch quiz questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quizID = ? ORDER BY questionID ASC");
$stmt->execute([$quizID]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5️⃣ Handle submission
$submitted = false;
$score = 0;
$passed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingResult) {
    $answers = $_POST['answers'] ?? [];

    $total = count($questions);
    foreach ($questions as $q) {
        $qid = $q['questionID'];
        $correct = strtoupper($q['correct_option']);
        $studentAnswer = strtoupper($answers[$qid] ?? '');
        if ($studentAnswer === $correct) {
            $score++;
        }
    }

    $percentage = ($total > 0) ? ($score / $total * 100) : 0;
    $passed = ($percentage >= 70) ? 1 : 0;

    // Save result
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (enrollmentID, userID, quizID, score, passed, takenAt)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            score = VALUES(score),
            passed = VALUES(passed),
            takenAt = NOW()
    ");
    $stmt->execute([$enrollmentID, $userID, $quizID, $percentage, $passed]);

    $submitted = true;
    $existingResult = ['score' => $percentage, 'passed' => $passed];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">

    <a href="course_learn.php?id=<?php echo $courseID; ?>" class="btn btn-secondary mb-3">
        &larr; Back to Course
    </a>

    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>

    <?php if ($existingResult && $submitted): ?>
        <div class="alert alert-success">
            <h4>Quiz submitted!</h4>
            <p>Your Score: <?php echo round($existingResult['score'], 2); ?>%</p>
            <p>Status: <?php echo $existingResult['passed'] ? 'Passed ✅' : 'Failed ❌'; ?></p>
        </div>
    <?php elseif ($existingResult): ?>
        <div class="alert alert-info">
            <h4>You already submitted this quiz.</h4>
            <p>Your Score: <?php echo round($existingResult['score'], 2); ?>%</p>
            <p>Status: <?php echo $existingResult['passed'] ? 'Passed ✅' : 'Failed ❌'; ?></p>
        </div>
    <?php else: ?>
        <form method="POST">
            <?php foreach ($questions as $index => $q): ?>
                <div class="mb-3">
                    <label class="form-label"><?php echo ($index + 1) . '. ' . htmlspecialchars($q['question']); ?></label>
                    <div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="A" required>
                            <label class="form-check-label">A. <?php echo htmlspecialchars($q['option1']); ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="B">
                            <label class="form-check-label">B. <?php echo htmlspecialchars($q['option2']); ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="C">
                            <label class="form-check-label">C. <?php echo htmlspecialchars($q['option3']); ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answers[<?php echo $q['questionID']; ?>]" value="D">
                            <label class="form-check-label">D. <?php echo htmlspecialchars($q['option4']); ?></label>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">Submit Quiz</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
