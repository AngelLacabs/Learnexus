<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$quizID = $_GET['quiz_id'] ?? 0;
$userID = $_SESSION['user_id'];

// Get quiz details
$stmt = $conn->prepare("
    SELECT q.*, c.title as courseTitle, c.courseID
    FROM quizzes q
    JOIN courses c ON q.courseID = c.courseID
    WHERE q.quizID = ?
");
$stmt->execute([$quizID]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: my_courses.php');
    exit();
}

// Check enrollment
$stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $quiz['courseID']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    header('Location: my_courses.php');
    exit();
}

// Get questions with choices
$stmt = $conn->prepare("SELECT * FROM questions WHERE quizID = ? ORDER BY orderNumber");
$stmt->execute([$quizID]);
$questions = $stmt->fetchAll();

$questionChoices = [];
foreach ($questions as $q) {
    $stmt = $conn->prepare("SELECT * FROM choices WHERE questionID = ? ORDER BY choiceLetter");
    $stmt->execute([$q['questionID']]);
    $questionChoices[$q['questionID']] = $stmt->fetchAll();
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $totalPoints = 0;
    $earnedPoints = 0;
    
    foreach ($questions as $question) {
        $totalPoints += $question['points'];
        $studentAnswer = $answers[$question['questionID']] ?? '';
        
        // Check if correct
        foreach ($questionChoices[$question['questionID']] as $choice) {
            if ($choice['choiceLetter'] == $studentAnswer && $choice['isCorrect']) {
                $earnedPoints += $question['points'];
            }
        }
    }
    
    $percentage = ($totalPoints > 0) ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
    $status = ($percentage >= $quiz['passingScore']) ? 'passed' : 'failed';
    
    // Save result
    $stmt = $conn->prepare("
        INSERT INTO quizresults (enrollmentID, userID, quizID, score, totalPoints, percentage, status, submittedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$enrollment['enrollmentID'], $userID, $quizID, $earnedPoints, $totalPoints, $percentage, $status]);
    
    // Redirect to results
    header('Location: quiz_result.php?quiz_id=' . $quizID);
    exit();
}

$currentQuestion = $_GET['q'] ?? 1;
$currentQuestion = max(1, min($currentQuestion, count($questions)));
$totalQuestions = count($questions);
$progressPercentage = round(($currentQuestion / $totalQuestions) * 100);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quiz - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .quiz-header { background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%); padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .quiz-container { max-width: 800px; margin: 40px auto; }
        .question-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .choice-option { border: 2px solid #e0e0e0; padding: 15px; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
        .choice-option:hover { border-color: #1e88e5; background: #f8f9fa; }
        .choice-option.selected { border-color: #1e88e5; background: #e3f2fd; }
        .progress-bar-quiz { height: 8px; background: #e0e0e0; border-radius: 10px; overflow: hidden; }
        .progress-bar-quiz .progress { height: 100%; background: #1e88e5; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="quiz-header">
        <div>
            <strong>LEARNEXUS</strong>
            <div><small>CURRENT QUIZ</small></div>
            <div><?php echo htmlspecialchars($quiz['title']); ?></div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary">⏱ <?php echo $quiz['timeLimitMinutes'] ?? '00'; ?>:00</button>
            <button class="btn btn-danger">Quit Quiz</button>
        </div>
    </div>
    
    <div class="quiz-container">
        <h5>Question <?php echo $currentQuestion; ?> of <?php echo $totalQuestions; ?></h5>
        <p class="text-muted"><?php echo $progressPercentage; ?>% Completed</p>
        <div class="progress-bar-quiz mb-4">
            <div class="progress" style="width: <?php echo $progressPercentage; ?>%"></div>
        </div>
        
        <form method="POST" id="quizForm">
            <?php $question = $questions[$currentQuestion - 1]; ?>
            
            <div class="question-card">
                <h4 class="mb-4"><?php echo htmlspecialchars($question['questionText']); ?></h4>
                
                <?php foreach ($questionChoices[$question['questionID']] as $choice): ?>
                    <div class="choice-option" onclick="selectChoice(this, '<?php echo $choice['choiceLetter']; ?>', <?php echo $question['questionID']; ?>)">
                        <input type="radio" name="answers[<?php echo $question['questionID']; ?>]" 
                               value="<?php echo $choice['choiceLetter']; ?>" 
                               id="choice_<?php echo $choice['choiceID']; ?>" 
                               style="display: none;">
                        <label for="choice_<?php echo $choice['choiceID']; ?>" style="cursor: pointer; width: 100%;">
                            <strong><?php echo $choice['choiceLetter']; ?></strong> - <?php echo htmlspecialchars($choice['choiceText']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <?php if ($currentQuestion > 1): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='?quiz_id=<?php echo $quizID; ?>&q=<?php echo ($currentQuestion - 1); ?>'">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    
                    <?php if ($currentQuestion < $totalQuestions): ?>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='?quiz_id=<?php echo $quizID; ?>&q=<?php echo ($currentQuestion + 1); ?>'">
                            Next Question <i class="bi bi-arrow-right"></i>
                        </button>
                    <?php else: ?>
                        <button type="submit" name="submit_quiz" class="btn btn-success">
                            Submit <i class="bi bi-check-lg"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        function selectChoice(element, letter, questionID) {
            document.querySelectorAll('.choice-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.querySelector(`input[name="answers[${questionID}]"][value="${letter}"]`).checked = true;
        }
    </script>
</body>
</html>