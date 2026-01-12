<?php
session_start();
require_once '../database/db_connect.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID  = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

/* =====================
   CHECK IF STUDENT FAILED THE QUIZ - BLOCK ACCESS TO LESSONS
===================== */
$stmt = $conn->prepare("
    SELECT qr.passed, qr.status 
    FROM quiz_results qr
    JOIN quizzes q ON qr.quizID = q.quizID
    WHERE q.courseID = ? AND qr.userID = ?
    ORDER BY qr.takenAt DESC
    LIMIT 1
");
$stmt->execute([$courseID, $userID]);
$quizResult = $stmt->fetch();

// If student failed the quiz, block access to course content
if ($quizResult && $quizResult['status'] == 'failed' && $quizResult['passed'] == 0) {
    $_SESSION['error'] = "Access denied. You must pay to retake this course after failing the quiz.";
    header('Location: retake_course.php?id=' . $courseID);
    exit();
}

/* =====================
   COURSE + ENROLLMENT
===================== */
$stmt = $conn->prepare("
    SELECT 
        c.*,
        e.enrollmentID,
        e.progressPercentage,
        CONCAT(u.firstName, ' ', u.lastName) AS instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    JOIN enrollments e ON c.courseID = e.courseID
    WHERE c.courseID = ? 
      AND e.userID = ? 
      AND e.status = 'active'
");
$stmt->execute([$courseID, $userID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: my_courses.php');
    exit();
}

/* =====================
   LESSONS
===================== */
$stmt = $conn->prepare("
    SELECT * FROM lessons 
    WHERE courseID = ?
    ORDER BY uploadedAt ASC
");
$stmt->execute([$courseID]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   COMPLETED LESSONS
===================== */
$stmt = $conn->prepare("
    SELECT lessonID 
    FROM lesson_completions
    WHERE userID = ?
      AND lessonID IN (
        SELECT lessonID FROM lessons WHERE courseID = ?
      )
");
$stmt->execute([$userID, $courseID]);
$completedLessons = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =====================
   CHECK ALL LESSONS
===================== */
$allLessonsCompleted = count($lessons) > 0 
    && count($lessons) === count($completedLessons);

/* =====================
   QUIZ + RESULT
===================== */
$stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quizID = $stmt->fetchColumn();

$quizPassed = false;
if ($quizID) {
    $stmt = $conn->prepare("
        SELECT status 
        FROM quiz_results 
        WHERE userID = ? AND quizID = ?
        ORDER BY takenAt DESC
        LIMIT 1
    ");
    $stmt->execute([$userID, $quizID]);
    $quizPassed = $stmt->fetchColumn() === 'passed';
}

/* =====================
   CURRENT LESSON
===================== */
$currentLessonIndex = $_GET['lesson'] ?? 0;
$currentLesson = $lessons[$currentLessonIndex] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
<link rel="icon" href="../images/Learnexus.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f8f9fa; }
.learning-container { display:flex; height:100vh; }
.sidebar { width:350px; background:#fff; border-right:1px solid #ddd; display:flex; flex-direction:column; }
.lesson-item { padding:15px 20px; display:flex; gap:10px; border-bottom:1px solid #eee; text-decoration:none; color:#333; transition: all 0.2s; }
.lesson-item:hover { background:#f8f9fa; }
.lesson-item.active { background:#e3f2fd; }
.lesson-icon { width:36px; height:36px; display:flex; align-items:center; justify-content:center; background:#e3f2fd; border-radius:8px; }
.lesson-item.active .lesson-icon { background:#1e88e5; color:#fff; }
.content-area { flex:1; display:flex; flex-direction:column; }
.lesson-viewer { padding:30px; flex:1; overflow:auto; }
.btn-nav { padding:12px 20px; border:1px solid #ccc; border-radius:8px; background:#fff; text-decoration:none; display:block; text-align:center; transition:all 0.2s; }
.btn-nav:hover { background:#f8f9fa; }
.btn-nav.primary { background:#1e88e5; color:#fff; border-color:#1e88e5; }
.btn-nav.primary:hover { background:#1565c0; color:#fff; }
.btn-nav:disabled { opacity:.5; cursor:not-allowed; }
.disabled-link {
    pointer-events: none;
    opacity: 0.5;
    cursor: not-allowed;
}
.progress-text { display:block; margin-top:5px; font-size:12px; }
.course-header { padding:20px; border-bottom:1px solid #eee; background:#fff; }
</style>
</head>

<body>
<div class="learning-container">

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">
    <div class="p-3 border-bottom">
        <h6 class="mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>
        <small class="text-muted">
            <i class="bi bi-person"></i> <?php echo htmlspecialchars($course['instructorName']); ?>
        </small>

        <div class="mt-3">
            <div class="progress" style="height:8px;">
                <div class="progress-bar bg-success" style="width:<?php echo $course['progressPercentage']; ?>%"></div>
            </div>
            <small class="text-muted progress-text">
                <?php echo number_format($course['progressPercentage'],0); ?>% Complete
            </small>
        </div>
    </div>

    <div class="flex-grow-1 overflow-auto">
        <?php foreach ($lessons as $i => $lesson): 
            $done = in_array($lesson['lessonID'], $completedLessons);
        ?>
        <a href="?id=<?php echo $courseID ?>&lesson=<?php echo $i ?>"
           class="lesson-item <?php echo $i==$currentLessonIndex?'active':'' ?>">
            <div class="lesson-icon">
                <i class="bi <?php echo $done?'bi-check-circle-fill':'bi-file-earmark-pdf'; ?>"></i>
            </div>
            <div>
                <div><strong><?php echo htmlspecialchars($lesson['title']); ?></strong></div>
                <small class="text-muted">
                    <input type="checkbox"
                        class="lesson-complete-checkbox me-1"
                        data-lesson-id="<?php echo $lesson['lessonID']; ?>"
                        <?php echo $done?'checked':'' ?>>
                    Lesson <?php echo $i+1; ?>
                </small>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ================= BUTTONS ================= -->
    <div class="p-3 border-top">

        <!-- TAKE QUIZ -->
        <a href="<?php echo $allLessonsCompleted ? 'course_quiz.php?id='.$courseID : 'javascript:void(0)'; ?>"
           id="take-quiz-btn"
           class="btn-nav primary w-100 mb-2 <?php echo !$allLessonsCompleted ? 'disabled-link' : ''; ?>"
           title="<?php echo !$allLessonsCompleted ? 'Complete all lessons first' : ''; ?>">
            <i class="bi bi-question-circle"></i> Take Quiz
        </a>

        <!-- CERTIFICATE -->
        <a href="certificate.php?course=<?php echo $courseID; ?>"
           id="unlock-certificate-btn"
           class="btn-nav primary w-100"
           <?php
           if (!$allLessonsCompleted) {
               echo 'style="pointer-events:none;opacity:0.5;" title="Complete all lessons first"';
           } elseif (!$quizPassed) {
               echo 'style="pointer-events:none;opacity:0.5;" title="Pass the quiz to unlock"';
           }
           ?>>
            <i class="bi bi-award"></i> Unlock E-Certificate
        </a>

    </div>
</div>

<!-- ================= CONTENT ================= -->
<div class="content-area">
    <div class="course-header">
        <a href="my_courses.php" class="text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to My Courses
        </a>
    </div>

    <div class="lesson-viewer">
        <?php if ($currentLesson): ?>
            <h4 class="mb-3"><?php echo htmlspecialchars($currentLesson['title']); ?></h4>
            <iframe src="../<?php echo htmlspecialchars($currentLesson['filename']); ?>"
                width="100%" height="600" style="border:1px solid #ddd; border-radius:8px;"></iframe>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Select a lesson from the sidebar to start learning.
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<script>
function updateButtons(){
    const checkboxes = document.querySelectorAll('.lesson-complete-checkbox');
    const allChecked = [...checkboxes].every(cb => cb.checked);

    const quizBtn = document.getElementById('take-quiz-btn');
    const certBtn = document.getElementById('unlock-certificate-btn');

    if(allChecked){
        quizBtn.classList.remove('disabled-link');
        quizBtn.removeAttribute('title');
        quizBtn.style.pointerEvents = 'auto';
        quizBtn.style.opacity = '1';
    } else {
        quizBtn.classList.add('disabled-link');
        quizBtn.title = 'Complete all lessons first';
        certBtn.style.pointerEvents = 'none';
        certBtn.style.opacity = '0.5';
        certBtn.title = 'Complete all lessons first';
    }
}

document.querySelectorAll('.lesson-complete-checkbox').forEach(cb=>{
    cb.addEventListener('change',()=>{
        fetch('mark_lesson_complete.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                lessonID:cb.dataset.lessonId,
                completed:cb.checked ? 1 : 0
            })
        }).then(res => res.json())
          .then(data => {
              if(data.success) {
                  updateButtons();
                  // Update progress bar
                  location.reload();
              }
          })
          .catch(err => console.error('Error:', err));
    });
});

updateButtons();
</script>

</body>
</html>