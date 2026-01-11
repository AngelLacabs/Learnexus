<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quizID = $_POST['quizID'] ?? 0;
    $courseID = $_POST['courseID'] ?? 0;
    $answers = $_POST['answers'] ?? [];

    if (!$quizID || empty($answers)) {
        die("Invalid submission.");
    }

    // Fetch correct answers
    $stmt = $conn->prepare("SELECT questionID, correctAnswer FROM quiz_questions WHERE quizID = ?");
    $stmt->execute([$quizID]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $score = 0;
    $total = count($questions);

    foreach ($questions as $q) {
        $qid = $q['questionID'];
        $correct = trim(strtolower($q['correctAnswer']));
        $studentAnswer = trim(strtolower($answers[$qid] ?? ''));

        if ($studentAnswer === $correct) {
            $score++;
        }
    }

    $percentage = ($total > 0) ? ($score / $total * 100) : 0;
    $passed = ($percentage >= 70) ? 1 : 0; // Pass threshold 70%

    // Save result
    $stmt = $conn->prepare("INSERT INTO quiz_results (userID, quizID, score, passed) VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE score = VALUES(score), passed = VALUES(passed), takenAt = NOW()");
    $stmt->execute([$userID, $quizID, $percentage, $passed]);

    // Redirect back to course page
    header("Location: course_learn.php?id=$courseID");
    exit();
}
?>
