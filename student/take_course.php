<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Helper function to construct correct PDF URL
function getPdfUrl($filename) {
    // Remove any leading slashes
    $cleanPath = ltrim($filename, '/');
    
    // If the path already starts with 'uploads/', use it as is
    // Otherwise, prepend 'uploads/lessons/'
    if (strpos($cleanPath, 'uploads/') === 0) {
        return '../' . $cleanPath;
    } else {
        return '../uploads/lessons/' . $cleanPath;
    }
}

$courseID = $_GET['id'] ?? 0;
$userID = $_SESSION['user_id'];

// Check enrollment
$stmt = $conn->prepare("
    SELECT e.enrollmentID, e.progressPercentage, e.status, e.completedAt,
           c.title as courseTitle, c.description, c.price, c.passingScore,
           CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    WHERE e.userID = ? AND e.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    $_SESSION['error'] = 'You are not enrolled in this course.';
    header('Location: my_courses.php');
    exit();
}

// 🔒 PROTECTION: Get lessons based on completion status
if ($enrollment['status'] === 'completed' && !empty($enrollment['completedAt'])) {
    // LOCKED VIEW: Show only lessons that existed at completion time
    $stmt = $conn->prepare("
        SELECT l.*, 
               EXISTS(SELECT 1 FROM lesson_completions WHERE lessonID = l.lessonID AND userID = ?) as isCompleted
        FROM lessons l
        WHERE l.courseID = ?
          AND l.uploadedAt <= ?
        ORDER BY l.lessonID ASC
    ");
    $stmt->execute([$userID, $courseID, $enrollment['completedAt']]);
} else {
    // ACTIVE VIEW: Show all current lessons
    $stmt = $conn->prepare("
        SELECT l.*, 
               EXISTS(SELECT 1 FROM lesson_completions WHERE lessonID = l.lessonID AND userID = ?) as isCompleted
        FROM lessons l
        WHERE l.courseID = ?
        ORDER BY l.lessonID ASC
    ");
    $stmt->execute([$userID, $courseID]);
}
$lessons = $stmt->fetchAll();

// 🔒 PROTECTION: Get quizzes based on completion status
if ($enrollment['status'] === 'completed' && !empty($enrollment['completedAt'])) {
    // LOCKED VIEW: Show only quizzes that existed at completion time
    $stmt = $conn->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM quiz_questions WHERE quizID = q.quizID) as questionCount,
               (SELECT MAX(takenAt) FROM quizresults WHERE quizID = q.quizID AND userID = ?) as lastAttempt,
               (SELECT passed FROM quizresults WHERE quizID = q.quizID AND userID = ? ORDER BY takenAt DESC LIMIT 1) as hasPassed,
               (SELECT score FROM quizresults WHERE quizID = q.quizID AND userID = ? ORDER BY takenAt DESC LIMIT 1) as lastScore
        FROM quizzes q
        WHERE q.courseID = ?
          AND q.createdAt <= ?
        ORDER BY q.quizID ASC
    ");
    $stmt->execute([$userID, $userID, $userID, $courseID, $enrollment['completedAt']]);
} else {
    // ACTIVE VIEW: Show all current quizzes
    $stmt = $conn->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM quiz_questions WHERE quizID = q.quizID) as questionCount,
               (SELECT MAX(takenAt) FROM quizresults WHERE quizID = q.quizID AND userID = ?) as lastAttempt,
               (SELECT passed FROM quizresults WHERE quizID = q.quizID AND userID = ? ORDER BY takenAt DESC LIMIT 1) as hasPassed,
               (SELECT score FROM quizresults WHERE quizID = q.quizID AND userID = ? ORDER BY takenAt DESC LIMIT 1) as lastScore
        FROM quizzes q
        WHERE q.courseID = ?
        ORDER BY q.quizID ASC
    ");
    $stmt->execute([$userID, $userID, $userID, $courseID]);
}
$quizzes = $stmt->fetchAll();

// Calculate progress
$totalItems = count($lessons) + count($quizzes);
$completedLessons = count(array_filter($lessons, fn($l) => $l['isCompleted']));
$passedQuizzes = count(array_filter($quizzes, fn($q) => $q['hasPassed']));
$completedItems = $completedLessons + $passedQuizzes;
$progressPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;

// Update progress and check completion
$isCompleted = ($progressPercentage >= 100);
$stmt = $conn->prepare("UPDATE enrollments SET progressPercentage = ?, completedAt = ? WHERE enrollmentID = ?");
$stmt->execute([$progressPercentage, $isCompleted ? date('Y-m-d H:i:s') : null, $enrollment['enrollmentID']]);

// Check if certificate exists
$certificateUUID = null;
if ($isCompleted) {
    $stmt = $conn->prepare("SELECT certificateUUID FROM certificates WHERE enrollmentID = ?");
    $stmt->execute([$enrollment['enrollmentID']]);
    $cert = $stmt->fetch();
    
    if (!$cert) {
        // Generate certificate
        $uuid = uniqid('cert_', true);
        $stmt = $conn->prepare("
            INSERT INTO certificates (certificateUUID, userID, courseID, enrollmentID, issuedAt) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uuid, $userID, $courseID, $enrollment['enrollmentID']]);
        $certificateUUID = $uuid;
    } else {
        $certificateUUID = $cert['certificateUUID'];
    }
}

// Get current view (lesson or quiz)
$currentLessonID = $_GET['lesson_id'] ?? null;
$currentQuizID = $_GET['quiz_id'] ?? null;
$currentLesson = null;
$currentQuiz = null;

if ($currentLessonID) {
    foreach ($lessons as $lesson) {
        if ($lesson['lessonID'] == $currentLessonID) {
            $currentLesson = $lesson;
            break;
        }
    }
} elseif ($currentQuizID) {
    foreach ($quizzes as $quiz) {
        if ($quiz['quizID'] == $currentQuizID) {
            $currentQuiz = $quiz;
            break;
        }
    }
} elseif (!empty($lessons)) {
    $currentLesson = $lessons[0];
    $currentLessonID = $currentLesson['lessonID'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($enrollment['courseTitle']); ?> - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            margin: 0; 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .course-layout { 
            display: grid; 
            grid-template-columns: 320px 1fr; 
            height: 100vh; 
        }
        .sidebar { 
            background: white; 
            border-right: 1px solid #e0e0e0; 
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header { 
            padding: 24px; 
            border-bottom: 1px solid #e0e0e0;
            flex-shrink: 0;
        }
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e88e5;
            text-decoration: none;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .back-btn:hover {
            color: #1565c0;
        }
        .course-info h6 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .progress-container {
            margin-top: 16px;
        }
        .progress-bar-custom {
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #43a047 0%, #66bb6a 100%);
            border-radius: 10px;
            transition: width 0.3s;
        }
        .progress-text {
            font-size: 13px;
            color: #666;
        }
        .locked-indicator {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 12px;
            text-align: center;
        }
        .locked-indicator i {
            font-size: 16px;
            margin-bottom: 4px;
        }
        .content-list {
            flex: 1;
            overflow-y: auto;
        }
        .section-header {
            padding: 16px 24px;
            font-weight: 600;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        .lesson-item, .quiz-item {
            padding: 16px 24px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .lesson-item:hover, .quiz-item:hover {
            background: #f8f9fa;
        }
        .lesson-item.active, .quiz-item.active {
            background: #e3f2fd;
            border-left: 4px solid #1e88e5;
        }
        .lesson-item.completed, .quiz-item.completed {
            background: #f1f8f4;
        }
        .item-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }
        .item-icon.lesson {
            background: #e3f2fd;
            color: #1e88e5;
        }
        .item-icon.quiz {
            background: #fff3e0;
            color: #fb8c00;
        }
        .item-icon.completed {
            background: #43a047;
            color: white;
        }
        .item-info {
            flex: 1;
            min-width: 0;
        }
        .item-title {
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-meta {
            font-size: 12px;
            color: #999;
        }
        .main-content { 
            overflow-y: auto; 
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        .top-bar {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 16px 40px;
            flex-shrink: 0;
        }
        .progress-bar-top {
            height: 4px;
            background: #e0e0e0;
            margin-bottom: 16px;
        }
        .progress-bar-top .progress {
            height: 100%;
            background: #1e88e5;
            transition: width 0.3s;
        }
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }
        .content-viewer { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
            max-width: 900px; 
            margin: 0 auto;
        }
        .pdf-container {
            background: #f5f5f5;
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 60px 40px;
            text-align: center;
            margin: 32px 0;
        }
        .pdf-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navigation-buttons { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid #f0f0f0;
        }
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .completion-banner {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 32px;
        }
        .completion-banner h4 {
            margin-bottom: 12px;
        }
        .badge-custom {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .badge-reading {
            background: #e3f2fd;
            color: #1e88e5;
        }
        .badge-quiz {
            background: #fff3e0;
            color: #fb8c00;
        }
        .btn-complete {
            background: #43a047;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-complete:hover {
            background: #388e3c;
            color: white;
        }
        .quiz-summary {
            background: #f8f9fa;
            padding: 24px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .quiz-summary .stat {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .quiz-summary .stat:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="course-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <a href="my_courses.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i> Back to My Courses
                </a>
                <div class="course-info">
                    <h6><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h6>
                    <p class="text-muted small mb-0">by <?php echo htmlspecialchars($enrollment['instructorName']); ?></p>
                </div>
                <div class="progress-container">
                    <div class="progress-bar-custom">
                        <div class="progress-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between progress-text">
                        <span><?php echo $completedItems; ?> of <?php echo $totalItems; ?> completed</span>
                        <span><?php echo $progressPercentage; ?>%</span>
                    </div>
                </div>

                <?php if ($enrollment['status'] === 'completed'): ?>
                <div class="locked-indicator">
                    <i class="bi bi-lock-fill d-block"></i>
                    <strong>Course Completed!</strong><br>
                    <small>Your view is locked to preserve your achievement</small>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="content-list">
                <?php if (!empty($lessons)): ?>
                    <div class="section-header">
                        <i class="bi bi-book"></i> LESSONS
                    </div>
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <div class="lesson-item <?php echo ($currentLessonID == $lesson['lessonID']) ? 'active' : ''; ?> <?php echo $lesson['isCompleted'] ? 'completed' : ''; ?>" 
                             onclick="window.location.href='?id=<?php echo $courseID; ?>&lesson_id=<?php echo $lesson['lessonID']; ?>'">
                            <div class="item-icon <?php echo $lesson['isCompleted'] ? 'completed' : 'lesson'; ?>">
                                <?php if ($lesson['isCompleted']): ?>
                                    <i class="bi bi-check-lg"></i>
                                <?php else: ?>
                                    <i class="bi bi-file-text"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <div class="item-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                <div class="item-meta">Lesson <?php echo $index + 1; ?> • PDF Document</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($quizzes)): ?>
                    <div class="section-header">
                        <i class="bi bi-clipboard-check"></i> ASSESSMENTS
                    </div>
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="quiz-item <?php echo ($currentQuizID == $quiz['quizID']) ? 'active' : ''; ?> <?php echo $quiz['hasPassed'] ? 'completed' : ''; ?>" 
                             onclick="window.location.href='?id=<?php echo $courseID; ?>&quiz_id=<?php echo $quiz['quizID']; ?>'">
                            <div class="item-icon <?php echo $quiz['hasPassed'] ? 'completed' : 'quiz'; ?>">
                                <?php if ($quiz['hasPassed']): ?>
                                    <i class="bi bi-check-lg"></i>
                                <?php else: ?>
                                    <i class="bi bi-clipboard-check"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <div class="item-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                                <div class="item-meta"><?php echo $quiz['questionCount']; ?> Questions • <?php echo $quiz['passingScore']; ?>% to pass</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <div class="progress-bar-top">
                    <div class="progress" style="width: <?php echo $progressPercentage; ?>%"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h5>
                    <span class="text-muted"><?php echo $progressPercentage; ?>% Complete</span>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($isCompleted): ?>
                    <div class="completion-banner">
                        <i class="bi bi-trophy" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <h4>Congratulations! 🎉</h4>
                        <p class="mb-3">You've completed all lessons and quizzes in this course!</p>
                        <button class="btn btn-light" onclick="window.location.href='view_certificate.php?id=<?php echo $certificateUUID; ?>'">
                            <i class="bi bi-award"></i> View Certificate
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($currentLesson): ?>
                    <!-- Lesson Content -->
                    <div class="content-viewer">
                        <span class="badge-custom badge-reading">
                            <i class="bi bi-file-pdf"></i> Reading Material
                        </span>
                        <h3><?php echo htmlspecialchars($currentLesson['title']); ?></h3>
                        <p class="text-muted">Read the attached PDF document to complete this lesson.</p>
                        
                        <?php 
                        $pdfUrl = getPdfUrl($currentLesson['filename']); 
                        ?>
                        
                        <div class="pdf-container">
                            <div class="pdf-icon">
                                <i class="bi bi-file-pdf" style="font-size: 40px; color: #d32f2f;"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($currentLesson['title']); ?>.pdf</h5>
                            <p class="text-muted">PDF Document</p>
                            <button class="btn btn-primary mt-3" onclick="window.open('<?php echo htmlspecialchars($pdfUrl); ?>', '_blank')">
                                <i class="bi bi-box-arrow-up-right"></i> Open PDF Viewer
                            </button>
                            <a href="<?php echo htmlspecialchars($pdfUrl); ?>" 
                               download="<?php echo htmlspecialchars($currentLesson['title']); ?>.pdf" 
                               class="btn btn-outline-secondary mt-3 ms-2">
                                <i class="bi bi-download"></i> Download PDF
                            </a>
                        </div>
                        
                        <?php if (!$currentLesson['isCompleted']): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Complete this lesson:</strong> Mark as complete after you've finished reading the material.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i>
                                <strong>Lesson completed!</strong> Great job finishing this lesson.
                            </div>
                        <?php endif; ?>
                        
                        <div class="navigation-buttons">
                            <?php 
                            $currentIndex = array_search($currentLesson, $lessons);
                            $prevLesson = $currentIndex > 0 ? $lessons[$currentIndex - 1] : null;
                            $nextLesson = $currentIndex < count($lessons) - 1 ? $lessons[$currentIndex + 1] : null;
                            ?>
                            
                            <?php if ($prevLesson): ?>
                                <button class="btn btn-outline-secondary" onclick="window.location.href='?id=<?php echo $courseID; ?>&lesson_id=<?php echo $prevLesson['lessonID']; ?>'">
                                    <i class="bi bi-arrow-left"></i> Previous Lesson
                                </button>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <?php if (!$currentLesson['isCompleted']): ?>
                                    <button class="btn btn-complete" onclick="markComplete(<?php echo $currentLesson['lessonID']; ?>)">
                                        <i class="bi bi-check-circle"></i> Mark as Complete
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($nextLesson): ?>
                                    <button class="btn btn-primary" onclick="window.location.href='?id=<?php echo $courseID; ?>&lesson_id=<?php echo $nextLesson['lessonID']; ?>'">
                                        Next Lesson <i class="bi bi-arrow-right"></i>
                                    </button>
                                <?php elseif (!empty($quizzes) && !$isCompleted): ?>
                                    <button class="btn btn-primary" onclick="window.location.href='?id=<?php echo $courseID; ?>&quiz_id=<?php echo $quizzes[0]['quizID']; ?>'">
                                        View Quiz <i class="bi bi-arrow-right"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($currentQuiz): ?>
                    <!-- Quiz Content -->
                    <div class="content-viewer">
                        <span class="badge-custom badge-quiz">
                            <i class="bi bi-clipboard-check"></i> Assessment
                        </span>
                        <h3><?php echo htmlspecialchars($currentQuiz['title']); ?></h3>
                        <p class="text-muted">Test your knowledge with this quiz.</p>
                        
                        <div class="quiz-summary">
                            <div class="stat">
                                <span><strong>Questions:</strong></span>
                                <span><?php echo $currentQuiz['questionCount']; ?></span>
                            </div>
                            <div class="stat">
                                <span><strong>Passing Score:</strong></span>
                                <span><?php echo $currentQuiz['passingScore']; ?>%</span>
                            </div>
                            <div class="stat">
                                <span><strong>Status:</strong></span>
                                <span>
                                    <?php if ($currentQuiz['hasPassed']): ?>
                                        <span class="badge bg-success">Passed (<?php echo $currentQuiz['lastScore']; ?>%)</span>
                                    <?php elseif ($currentQuiz['lastAttempt']): ?>
                                        <span class="badge bg-warning">Not Passed (<?php echo $currentQuiz['lastScore']; ?>%)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Attempted</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($currentQuiz['lastAttempt']): ?>
                            <div class="stat">
                                <span><strong>Last Attempt:</strong></span>
                                <span><?php echo date('M d, Y g:i A', strtotime($currentQuiz['lastAttempt'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($currentQuiz['hasPassed']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i>
                                <strong>Quiz passed!</strong> You've successfully completed this assessment with a score of <?php echo $currentQuiz['lastScore']; ?>%.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h4>No Content Available</h4>
                        <p>The instructor hasn't added any lessons yet. Please check back later.</p>
                        <button class="btn btn-outline-primary mt-3" onclick="window.location.href='my_courses.php'">
                            <i class="bi bi-arrow-left"></i> Back to My Courses
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markComplete(lessonID) {
            fetch('mark_lesson_complete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `lesson_id=${lessonID}&course_id=<?php echo $courseID; ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Lesson Completed!',
                        text: 'Great job! Keep learning.',
                        confirmButtonColor: '#1e88e5',
                        timer: 2000
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to mark lesson as complete',
                        confirmButtonColor: '#1e88e5'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#1e88e5'
                });
            });
        }
    </script>
</body>
</html>