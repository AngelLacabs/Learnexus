<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// Get the quiz for this course
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "<h3>No quiz available for this course yet.</h3>";
    exit();
}

// Fetch quiz questions
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quizID = ?");
$stmt->execute([$quiz['quizID']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <!-- Back Button -->
    <a href="course_learn.php?id=<?php echo $courseID; ?>" class="btn btn-secondary mb-3">
        &larr; Back to Course
    </a>

    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>

    <?php if (count($questions) > 0): ?>
    <form method="POST" action="submit_quiz.php">
        <input type="hidden" name="quizID" value="<?php echo $quiz['quizID']; ?>">
        <input type="hidden" name="courseID" value="<?php echo $courseID; ?>">
        <?php foreach ($questions as $index => $q): ?>
        <div class="mb-3">
            <label class="form-label"><?php echo ($index + 1) . ". " . htmlspecialchars($q['question']); ?></label>
            <input type="text" name="answers[<?php echo $q['questionID']; ?>]" class="form-control" required>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Submit Quiz</button>
    </form>
    <?php else: ?>
        <p>No questions added yet for this quiz.</p>
    <?php endif; ?>
</div>
</body>
</html>
