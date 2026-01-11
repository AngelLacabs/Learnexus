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
    $correctOption = intval($_POST['correct_option']); // 1-4
    $options = [
        trim($_POST['option1']),
        trim($_POST['option2']),
        trim($_POST['option3']),
        trim($_POST['option4'])
    ];

    $stmt = $conn->prepare("INSERT INTO quiz_questions (quizID, question, option1, option2, option3, option4, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$quizID, $questionText, $options[0], $options[1], $options[2], $options[3], $correctOption]);

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
    <title>Edit Quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .top-nav { background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%); padding: 15px 40px; }
        .brand { font-size: 20px; font-weight: 700; color: #1a73e8; }
    </style>
</head>
<body>
    <div class="top-nav d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="brand text-decoration-none">LEARNEXUS</a>
        <div>
            <a href="courses.php" class="me-3 text-decoration-none">Courses</a>
            <a href="edit_course.php?id=<?php echo $quiz['courseID']; ?>" class="me-3 text-decoration-none">Back to Course</a>
        </div>
    </div>

    <div class="container mt-5">
        <h2>Edit Quiz: <?php echo htmlspecialchars($quiz['title']); ?></h2>
        <p>Course: <?php echo htmlspecialchars($quiz['courseTitle']); ?></p>

        <hr>
        <h4>Add New Question</h4>
        <form method="POST" class="mb-4">
            <div class="mb-3">
                <label class="form-label">Question Text</label>
                <textarea name="question_text" class="form-control" required></textarea>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label>Option 1</label>
                    <input type="text" name="option1" class="form-control" required>
                </div>
                <div class="col">
                    <label>Option 2</label>
                    <input type="text" name="option2" class="form-control" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label>Option 3</label>
                    <input type="text" name="option3" class="form-control" required>
                </div>
                <div class="col">
                    <label>Option 4</label>
                    <input type="text" name="option4" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Correct Option</label>
                <select name="correct_option" class="form-control" required>
                    <option value="1">Option 1</option>
                    <option value="2">Option 2</option>
                    <option value="3">Option 3</option>
                    <option value="4">Option 4</option>
                </select>
            </div>
            <button type="submit" name="add_question" class="btn btn-success">Add Question</button>
        </form>

        <hr>
        <h4>Existing Questions</h4>
        <?php if(count($questions) > 0): ?>
            <ul class="list-group">
                <?php foreach($questions as $q): ?>
                    <li class="list-group-item">
                        <strong><?php echo htmlspecialchars($q['question']); ?></strong>
                        <ul>
                            <li <?php if($q['correct_option']==1) echo 'class="text-success fw-bold"'; ?>>1. <?php echo htmlspecialchars($q['option1']); ?></li>
                            <li <?php if($q['correct_option']==2) echo 'class="text-success fw-bold"'; ?>>2. <?php echo htmlspecialchars($q['option2']); ?></li>
                            <li <?php if($q['correct_option']==3) echo 'class="text-success fw-bold"'; ?>>3. <?php echo htmlspecialchars($q['option3']); ?></li>
                            <li <?php if($q['correct_option']==4) echo 'class="text-success fw-bold"'; ?>>4. <?php echo htmlspecialchars($q['option4']); ?></li>
                        </ul>
                        <form method="POST" onsubmit="return confirm('Delete this question?');">
                            <input type="hidden" name="question_id" value="<?php echo $q['questionID']; ?>">
                            <button type="submit" name="delete_question" class="btn btn-sm btn-danger">Delete Question</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No questions added yet.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
