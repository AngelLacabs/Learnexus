<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get teacher's courses
$stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE teacherID = ? ORDER BY title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseID = $_POST['courseID'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $passingScore = intval($_POST['passingScore'] ?? 70);
    $timeLimitMinutes = !empty($_POST['timeLimitMinutes']) ? intval($_POST['timeLimitMinutes']) : null;
    $allowRetake = isset($_POST['allowRetake']) ? 1 : 0;
    
    if ($courseID && $title) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO quizzes (courseID, title, description, passingScore, timeLimitMinutes, allowRetake, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$courseID, $title, $description, $passingScore, $timeLimitMinutes, $allowRetake]);
            $quizID = $conn->lastInsertId();
            
            header('Location: edit_quiz.php?id=' . $quizID);
            exit();
        } catch (PDOException $e) {
            $error = 'Failed to create quiz';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Quiz - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Create a Quiz</h2>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label">Select Course *</label>
                <select name="courseID" class="form-control" required>
                    <option value="">Choose a course...</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['courseID']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Quiz Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Passing Score (%)</label>
                    <input type="number" name="passingScore" class="form-control" value="70" min="0" max="100">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Time Limit (minutes, optional)</label>
                    <input type="number" name="timeLimitMinutes" class="form-control" min="1">
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="allowRetake" class="form-check-input" id="allowRetake" checked>
                <label class="form-check-label" for="allowRetake">Allow students to retake this quiz</label>
            </div>
            <button type="submit" class="btn btn-primary">Create Quiz</button>
            <a href="quizzes.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>