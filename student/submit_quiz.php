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

    // 1️⃣ Get the student's enrollment
    $stmt = $conn->prepare("
        SELECT enrollmentID 
        FROM enrollments 
        WHERE userID = ? AND courseID = ?
    ");
    $stmt->execute([$userID, $courseID]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment) {
        die("You are not enrolled in this course.");
    }

    $enrollmentID = $enrollment['enrollmentID'];

    // 2️⃣ Fetch correct answers
    $stmt = $conn->prepare("SELECT questionID, correct_option FROM quiz_questions WHERE quizID = ?");
    $stmt->execute([$quizID]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $score = 0;
    $total = count($questions);

    foreach ($questions as $q) {
        $qid = $q['questionID'];
        $correct = trim(strtoupper($q['correct_option']));
        $studentAnswer = trim(strtoupper($answers[$qid] ?? ''));

        if ($studentAnswer === $correct) {
            $score++;
        }
    }

    $percentage = ($total > 0) ? ($score / $total * 100) : 0;
    $passed = ($percentage >= 70) ? 1 : 0;

    // 3️⃣ Save result
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (enrollmentID, userID, quizID, score, passed, takenAt)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            score = VALUES(score),
            passed = VALUES(passed),
            takenAt = NOW()
    ");
    $stmt->execute([$enrollmentID, $userID, $quizID, $percentage, $passed]);

    // 4️⃣ Redirect back to course
    header("Location: course_learn.php?id=$courseID");
    exit();
}
?>
