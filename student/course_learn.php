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
   CHECK IF STUDENT FAILED THE QUIZ - BLOCK ACCESS ONLY IF NO RETAKE PAYMENT
===================== */
$stmt = $conn->prepare("
    SELECT qr.passed, qr.status, qr.takenAt
    FROM quizresults qr
    JOIN quizzes q ON qr.quizID = q.quizID
    WHERE q.courseID = ? AND qr.userID = ?
    ORDER BY qr.takenAt DESC
    LIMIT 1
");
$stmt->execute([$courseID, $userID]);
$quizResult = $stmt->fetch();

// Check if student failed and hasn't paid for retake
if ($quizResult && $quizResult['status'] == 'failed' && $quizResult['passed'] == 0) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as retake_payment_count
        FROM payments p
        JOIN enrollments e ON p.enrollmentID = e.enrollmentID
        WHERE e.courseID = ? 
        AND e.userID = ?
        AND p.paymentDate > ?
        AND p.status = 'completed'
    ");
    $stmt->execute([$courseID, $userID, $quizResult['takenAt']]);
    $retakePayment = $stmt->fetch();
    
    if ($retakePayment['retake_payment_count'] == 0) {
        $_SESSION['error'] = "Access denied. You must pay to retake this course after failing the quiz.";
        header('Location: retake_course.php?id=' . $courseID);
        exit();
    }
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
    FROM lessoncompletion
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
$quizTaken = false;
if ($quizID) {
    $stmt = $conn->prepare("
        SELECT status 
        FROM quizresults 
        WHERE userID = ? AND quizID = ?
        ORDER BY takenAt DESC
        LIMIT 1
    ");
    $stmt->execute([$userID, $quizID]);
    $quizStatus = $stmt->fetchColumn();
    $quizTaken = !empty($quizStatus);
    $quizPassed = $quizStatus === 'passed';
}

/* =====================
   CALCULATE DYNAMIC PROGRESS
===================== */
$totalSteps = count($lessons) + ($quizID ? 1 : 0);
$completedSteps = count($completedLessons);
if ($quizPassed) {
    $completedSteps++;
}
$dynamicProgress = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;

/* =====================
   CERTIFICATE ELIGIBILITY
===================== */
$canGetCertificate = $allLessonsCompleted && $quizPassed;

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
<link rel="icon" href="../images/Learnexus.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root {
    --sidebar-width: 380px;
    --header-height: 70px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.learning-container {
    display: flex;
    height: 100vh;
    overflow: hidden;
}

.course-sidebar {
    width: var(--sidebar-width);
    background: white;
    border-right: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 12px rgba(0,0,0,0.08);
}

.course-header {
    padding: 24px;
    border-bottom: 2px solid #f0f0f0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.course-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
    line-height: 1.4;
}

.instructor-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    opacity: 0.95;
    margin-bottom: 16px;
}

.progress-section {
    background: rgba(255, 255, 255, 0.15);
    padding: 12px;
    border-radius: 12px;
}

.progress {
    height: 10px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.3);
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, #43a047 0%, #66bb6a 100%);
    transition: width 0.3s ease;
}

.progress-text {
    display: block;
    margin-top: 8px;
    font-size: 0.875rem;
    font-weight: 600;
}

.lessons-container {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.lessons-container::-webkit-scrollbar {
    width: 8px;
}

.lessons-container::-webkit-scrollbar-track {
    background: #f5f5f5;
}

.lessons-container::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 4px;
}

.lessons-container::-webkit-scrollbar-thumb:hover {
    background: #999;
}

.section-label {
    padding: 16px 24px 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #666;
    letter-spacing: 0.5px;
}

.lesson-item {
    padding: 16px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    border-bottom: 1px solid #f0f0f0;
    text-decoration: none;
    color: #333;
    transition: all 0.2s ease;
    cursor: pointer;
    background: white;
}

.lesson-item:hover {
    background: #f8f9fa;
    padding-left: 28px;
}

.lesson-item.active {
    background: linear-gradient(90deg, #e3f2fd 0%, #f3e5f5 100%);
    border-left: 4px solid #667eea;
    padding-left: 20px;
}

.lesson-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    border-radius: 12px;
    flex-shrink: 0;
    font-size: 1.25rem;
    transition: all 0.2s ease;
}

.lesson-item.active .lesson-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.lesson-item.completed .lesson-icon {
    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
    color: white;
}

.lesson-content {
    flex: 1;
    min-width: 0;
}

.lesson-title {
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.lesson-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    color: #666;
}

.lesson-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #667eea;
}

.quiz-item {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.quiz-item.unlocked {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
}

.quiz-item.passed {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
}

.quiz-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 12px;
    font-size: 1.25rem;
}

.action-buttons {
    padding: 20px 24px;
    border-top: 2px solid #f0f0f0;
    background: white;
}

.btn-action {
    width: 100%;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 600;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    margin-bottom: 12px;
}

.btn-primary-action {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary-action:hover:not(.disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-success-action {
    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
    color: white;
}

.btn-success-action:hover:not(.disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(67, 160, 71, 0.4);
}

.btn-action.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.content-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fafafa;
}

.top-bar {
    height: var(--header-height);
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 0 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.back-button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 10px;
    background: #f5f5f5;
    text-decoration: none;
    color: #333;
    font-weight: 600;
    transition: all 0.2s ease;
    border: none;
}

.back-button:hover {
    background: #e0e0e0;
    transform: translateX(-4px);
}

.view-controls {
    display: flex;
    gap: 12px;
}

.btn-control {
    padding: 10px 16px;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-control:hover {
    background: #f5f5f5;
    border-color: #667eea;
}

.lesson-viewer {
    flex: 1;
    overflow: auto;
    padding: 32px;
}

.lesson-viewer::-webkit-scrollbar {
    width: 10px;
}

.lesson-viewer::-webkit-scrollbar-track {
    background: #f5f5f5;
}

.lesson-viewer::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 5px;
}

.viewer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.lesson-header {
    background: white;
    padding: 24px 32px;
    border-radius: 16px;
    margin-bottom: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.lesson-header h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 12px;
}

.lesson-badges {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.badge-custom {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.pdf-viewer-container {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.pdf-frame {
    width: 100%;
    height: 700px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
}

.download-section {
    margin-top: 16px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-download {
    padding: 10px 24px;
    border-radius: 10px;
    background: #667eea;
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
    border: none;
}

.btn-download:hover {
    background: #5568d3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #ccc;
    margin-bottom: 20px;
}

.toast-notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 16px 24px;
    border-radius: 12px;
    color: white;
    z-index: 9999;
    min-width: 300px;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s;
    display: flex;
    align-items: center;
    gap: 12px;
}

.toast-success {
    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
}

.toast-error {
    background: linear-gradient(135deg, #dc3545 0%, #e57373 100%);
}

.toast-warning {
    background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
}

.toast-info {
    background: linear-gradient(135deg, #2196f3 0%, #64b5f6 100%);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

.mobile-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    z-index: 1000;
    font-size: 1.5rem;
}

@media (max-width: 992px) {
    .course-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        z-index: 999;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .course-sidebar.show {
        transform: translateX(0);
    }

    .content-area {
        width: 100%;
    }

    .mobile-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top-bar {
        padding: 0 16px;
    }

    .lesson-viewer {
        padding: 16px;
    }

    .pdf-frame {
        height: 500px;
    }
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 998;
}

.sidebar-overlay.show {
    display: block;
}
</style>
</head>

<body>
<div class="learning-container">

<div class="course-sidebar" id="courseSidebar">
    <div class="course-header">
        <h1 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h1>
        <div class="instructor-info">
            <i class="bi bi-person-circle"></i>
            <span><?php echo htmlspecialchars($course['instructorName']); ?></span>
        </div>

        <div class="progress-section">
            <div class="progress">
                <div class="progress-bar" id="progress-bar" style="width:<?php echo $dynamicProgress; ?>%"></div>
            </div>
            <span class="progress-text" id="progress-text">
                <?php echo $dynamicProgress; ?>% Complete - <?php echo count($completedLessons); ?>/<?php echo count($lessons); ?> Lessons
            </span>
        </div>
    </div>

    <div class="lessons-container">
        <div class="section-label">
            <i class="bi bi-journal-text me-1"></i> Course Content
        </div>

        <?php foreach ($lessons as $i => $lesson): 
            $done = in_array($lesson['lessonID'], $completedLessons);
            $isActive = $i == $currentLessonIndex;
        ?>
        <a href="?id=<?php echo $courseID ?>&lesson=<?php echo $i ?>"
           class="lesson-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $done ? 'completed' : ''; ?>">
            <div class="lesson-icon">
                <i class="bi <?php echo $done ? 'bi-check-circle-fill' : 'bi-file-earmark-text'; ?>"></i>
            </div>
            <div class="lesson-content">
                <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                <div class="lesson-meta">
                    <span><i class="bi bi-bookmark"></i> Lesson <?php echo $i + 1; ?></span>
                    <label onclick="event.stopPropagation();" style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox"
                            class="lesson-checkbox"
                            data-lesson-id="<?php echo $lesson['lessonID']; ?>"
                            <?php echo $done ? 'checked' : ''; ?>>
                        <span style="font-size: 0.75rem;"><?php echo $done ? 'Completed' : 'Mark complete'; ?></span>
                    </label>
                </div>
            </div>
        </a>
        <?php endforeach; ?>

        <?php if ($quizID): ?>
        <div class="section-label mt-3">
            <i class="bi bi-trophy me-1"></i> Assessment
        </div>
        
        <div class="quiz-item <?php 
            if ($quizPassed) echo 'passed';
            elseif ($allLessonsCompleted) echo 'unlocked';
        ?>">
            <div class="quiz-icon">
                <i class="bi <?php 
                    if ($quizPassed) echo 'bi-check-circle-fill text-success';
                    elseif ($allLessonsCompleted) echo 'bi-play-circle text-warning';
                    else echo 'bi-lock text-muted';
                ?>"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">Course Quiz</div>
                <small class="text-muted">
                    <?php if ($quizPassed): ?>
                        <i class="bi bi-check-circle-fill text-success"></i> Quiz Passed!
                    <?php elseif ($quizTaken && !$quizPassed): ?>
                        <i class="bi bi-x-circle text-danger"></i> Failed - Retake required
                    <?php elseif ($allLessonsCompleted): ?>
                        <i class="bi bi-exclamation-circle text-warning"></i> Ready to take
                    <?php else: ?>
                        <i class="bi bi-lock"></i> Complete all lessons first
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="action-buttons">
        <a href="<?php echo $allLessonsCompleted ? 'course_quiz.php?id='.$courseID : 'javascript:void(0)'; ?>"
           id="take-quiz-btn"
           class="btn-action btn-primary-action <?php echo !$allLessonsCompleted ? 'disabled' : ''; ?>"
           title="<?php echo !$allLessonsCompleted ? 'Complete all lessons first' : ''; ?>">
            <i class="bi <?php echo $quizTaken && !$quizPassed ? 'bi-arrow-repeat' : 'bi-play-circle'; ?>"></i>
            <?php echo $quizTaken && !$quizPassed ? 'Retake Quiz' : 'Take Quiz'; ?>
        </a>

        <a href="certificate.php?course=<?php echo $courseID; ?>"
           id="unlock-certificate-btn"
           class="btn-action btn-success-action <?php echo !$canGetCertificate ? 'disabled' : ''; ?>"
           title="<?php 
               if (!$allLessonsCompleted) echo 'Complete all lessons first';
               elseif (!$quizPassed) echo 'Pass the quiz to unlock';
               else echo 'Get your certificate';
           ?>">
            <i class="bi bi-award"></i>
            Get Certificate
        </a>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="content-area">
    <div class="top-bar">
        <a href="my_courses.php" class="back-button">
            <i class="bi bi-arrow-left"></i>
            <span class="d-none d-md-inline">Back to Courses</span>
        </a>

        <div class="view-controls">
            <button class="btn-control" title="Fullscreen" onclick="toggleFullscreen()">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
        </div>
    </div>

    <div class="lesson-viewer">
        <?php if ($currentLesson): ?>
            <div class="viewer-container">
                <div class="lesson-header">
                    <h2><?php echo htmlspecialchars($currentLesson['title']); ?></h2>
                    <div class="lesson-badges">
                        <span class="badge-custom bg-primary">Lesson <?php echo $currentLessonIndex + 1; ?> of <?php echo count($lessons); ?></span>
                        <?php if (in_array($currentLesson['lessonID'], $completedLessons)): ?>
                            <span class="badge-custom bg-success">
                                <i class="bi bi-check-circle-fill"></i> Completed
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pdf-viewer-container">
                    <?php
                    $pdfPath = '../' . $currentLesson['filename'];
                    if (!file_exists($pdfPath)):
                    ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            File not found: <?php echo htmlspecialchars($pdfPath); ?>
                        </div>
                    <?php else: ?>
                        <iframe 
                            src="<?php echo htmlspecialchars($pdfPath); ?>" 
                            class="pdf-frame"
                            type="application/pdf">
                            <p>Your browser does not support PDFs. 
                               <a href="<?php echo htmlspecialchars($pdfPath); ?>" target="_blank">Download the PDF</a>
                            </p>
                        </iframe>
                        
                        <div class="download-section">
                            <div>
                                <i class="bi bi-file-pdf text-danger fs-4"></i>
                                <span class="ms-2 fw-semibold"><?php echo htmlspecialchars($currentLesson['title']); ?>.pdf</span>
                            </div>
                            <a href="<?php echo htmlspecialchars($pdfPath); ?>" 
                               target="_blank" 
                               download
                               class="btn-download">
                                <i class="bi bi-download me-2"></i>Download
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-book"></i>
                <h3>Select a lesson to start learning</h3>
                <p class="text-muted">Choose any lesson from the sidebar to begin</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<button class="mobile-toggle" id="mobileToggle">
    <i class="bi bi-list"></i>
</button>

<script>
const totalLessons = <?php echo count($lessons); ?>;
const hasQuiz = <?php echo $quizID ? 'true' : 'false'; ?>;
const totalSteps = hasQuiz ? totalLessons + 1 : totalLessons;

const mobileToggle = document.getElementById('mobileToggle');
const courseSidebar = document.getElementById('courseSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

mobileToggle.addEventListener('click', () => {
    courseSidebar.classList.add('show');
    sidebarOverlay.classList.add('show');
});

sidebarOverlay.addEventListener('click', () => {
    courseSidebar.classList.remove('show');
    sidebarOverlay.classList.remove('show');
});

function updateProgress() {
    const checkboxes = document.querySelectorAll('.lesson-checkbox');
    const completedCount = [...checkboxes].filter(cb => cb.checked).length;
    
    const progressPercent = Math.round((completedCount / totalSteps) * 100);
    
    document.getElementById('progress-bar').style.width = progressPercent + '%';
    document.getElementById('progress-text').textContent = 
        `${progressPercent}% Complete - ${completedCount}/${totalLessons} Lessons`;
    
    return completedCount;
}

function updateButtons() {
    const checkboxes = document.querySelectorAll('.lesson-checkbox');
    const completedCount = [...checkboxes].filter(cb => cb.checked).length;
    const allChecked = completedCount === totalLessons;

    const quizBtn = document.getElementById('take-quiz-btn');
    const certBtn = document.getElementById('unlock-certificate-btn');

    if (allChecked) {
        quizBtn.classList.remove('disabled');
        quizBtn.removeAttribute('title');
        quizBtn.href = 'course_quiz.php?id=<?php echo $courseID; ?>';
    } else {
        quizBtn.classList.add('disabled');
        quizBtn.setAttribute('title', 'Complete all lessons first');
        quizBtn.href = 'javascript:void(0)';
    }

    if (!allChecked) {
        certBtn.classList.add('disabled');
        certBtn.setAttribute('title', 'Complete all lessons first');
    }
}

function toggleFullscreen() {
    const elem = document.documentElement;
    
    if (!document.fullscreenElement) {
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.mozRequestFullScreen) {
            elem.mozRequestFullScreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

document.querySelectorAll('.lesson-checkbox').forEach(cb => {
    cb.addEventListener('change', function(e) {
        e.stopPropagation(); 
        
        const lessonId = this.dataset.lessonId;
        const isCompleted = this.checked;

        const lessonItem = this.closest('.lesson-item');
        const icon = lessonItem.querySelector('.lesson-icon i');
        
        if (isCompleted) {
            icon.className = 'bi bi-check-circle-fill';
            lessonItem.classList.add('completed');
        } else {
            icon.className = 'bi bi-file-earmark-text';
            lessonItem.classList.remove('completed');
        }

        fetch('mark_lesson_complete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                lessonID: lessonId,
                completed: isCompleted ? 1 : 0
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateProgress();
                updateButtons();          
                showToast(isCompleted ? 'Lesson marked as complete!' : 'Lesson marked as incomplete', 'success');
            } else {
                this.checked = !isCompleted;
                if (isCompleted) {
                    icon.className = 'bi bi-file-earmark-text';
                    lessonItem.classList.remove('completed');
                } else {
                    icon.className = 'bi bi-check-circle-fill';
                    lessonItem.classList.add('completed');
                }
                showToast('Failed to update lesson status', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            this.checked = !isCompleted;
            if (isCompleted) {
                icon.className = 'bi bi-file-earmark-text';
                lessonItem.classList.remove('completed');
            } else {
                icon.className = 'bi bi-check-circle-fill';
                lessonItem.classList.add('completed');
            }
            showToast('Network error. Please try again.', 'error');
        });
    });
});

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 
                      type === 'error' ? 'bi-exclamation-circle-fill' : 
                      type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'} fs-5"></i>
        <div class="flex-grow-1">${message}</div>
        <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(toast);

    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 3000);
}

updateButtons();

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.fullscreenElement) {
        toggleFullscreen();
    }
});

document.addEventListener('click', function(e) {
    if (window.innerWidth <= 992 && 
        !courseSidebar.contains(e.target) && 
        e.target !== mobileToggle &&
        !mobileToggle.contains(e.target)) {
        courseSidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    const currentIndex = <?php echo $currentLessonIndex; ?>;
    
    if (e.key === 'ArrowRight' || e.key === 'd') {
        if (currentIndex < totalLessons - 1) {
            window.location.href = `?id=<?php echo $courseID; ?>&lesson=${currentIndex + 1}`;
        }
    } else if (e.key === 'ArrowLeft' || e.key === 'a') {
        if (currentIndex > 0) {
            window.location.href = `?id=<?php echo $courseID; ?>&lesson=${currentIndex - 1}`;
        }
    } else if (e.key === 'c') {
        const currentCheckbox = document.querySelector(`[data-lesson-id="<?php 
            echo isset($lessons[$currentLessonIndex]['lessonID']) ? $lessons[$currentLessonIndex]['lessonID'] : ''; 
        ?>"]`);
        if (currentCheckbox) {
            currentCheckbox.checked = !currentCheckbox.checked;
            currentCheckbox.dispatchEvent(new Event('change'));
        }
    }
});

document.querySelectorAll('.lesson-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (window.innerWidth <= 992) {
            courseSidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });
});

updateProgress();
</script>

</body>
</html>