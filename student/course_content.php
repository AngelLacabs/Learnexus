<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
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
    $stmt = $conn->prepare("
        SELECT l.*, 
               EXISTS(SELECT 1 FROM lessoncompletion WHERE lessonID = l.lessonID AND userID = ?) as isCompleted
        FROM lessons l
        WHERE l.courseID = ?
          AND l.uploadedAt <= ?
        ORDER BY l.lessonID ASC
    ");
    $stmt->execute([$userID, $courseID, $enrollment['completedAt']]);
} else {
    $stmt = $conn->prepare("
        SELECT l.*, 
               EXISTS(SELECT 1 FROM lessoncompletion WHERE lessonID = l.lessonID AND userID = ?) as isCompleted
        FROM lessons l
        WHERE l.courseID = ?
        ORDER BY l.lessonID ASC
    ");
    $stmt->execute([$userID, $courseID]);
}
$lessons = $stmt->fetchAll();

// 🔒 PROTECTION: Get quizzes based on completion status
if ($enrollment['status'] === 'completed' && !empty($enrollment['completedAt'])) {
    $stmt = $conn->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM quizquestions WHERE quizID = q.quizID) as questionCount,
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
    $stmt = $conn->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM quizquestions WHERE quizID = q.quizID) as questionCount,
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

if ($enrollment['status'] === 'completed') {
    $progressPercentage = 100;
    $isCompleted = true;
} else {
    $progressPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
    $isCompleted = ($progressPercentage >= 100);
}

// Update progress and check completion
$stmt = $conn->prepare("UPDATE enrollments SET progressPercentage = ?, completedAt = ? WHERE enrollmentID = ?");
$stmt->execute([$progressPercentage, $isCompleted ? date('Y-m-d H:i:s') : null, $enrollment['enrollmentID']]);

// Check if certificate exists
$certificateUUID = null;
if ($isCompleted) {
    $stmt = $conn->prepare("SELECT certificateUUID FROM certificates WHERE enrollmentID = ?");
    $stmt->execute([$enrollment['enrollmentID']]);
    $cert = $stmt->fetch();
    
    if (!$cert) {
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
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            margin: 0; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .course-layout { 
            display: grid; 
            grid-template-columns: 340px 1fr; 
            height: 100vh; 
        }
        .sidebar { 
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            border-right: 1px solid #e0e0e0; 
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
        }
        .sidebar-header { 
            padding: 24px; 
            border-bottom: 1px solid #e0e0e0;
            flex-shrink: 0;
            background: white;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            margin-bottom: 20px;
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .course-info h6 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        .course-info .text-muted {
            font-size: 13px;
        }
        .progress-container {
            margin-top: 20px;
        }
        .progress-bar-custom {
            height: 10px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        .progress-text {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        .locked-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px;
            border-radius: 12px;
            margin-top: 16px;
            font-size: 13px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .locked-indicator i {
            font-size: 18px;
            margin-bottom: 6px;
        }
        .content-list {
            flex: 1;
            overflow-y: auto;
            background: white;
        }
        .section-header {
            padding: 16px 24px;
            font-weight: 700;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            border-top: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .lesson-item, .quiz-item {
            padding: 18px 24px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .lesson-item:hover, .quiz-item:hover {
            background: linear-gradient(90deg, #f8f9fa 0%, #e8f0fe 100%);
            transform: translateX(4px);
        }
        .lesson-item.active, .quiz-item.active {
            background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 100%);
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }
        .lesson-item.completed, .quiz-item.completed {
            background: linear-gradient(90deg, #f1f8f4 0%, #e8f5e9 100%);
        }
        .item-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
            transition: all 0.2s ease;
        }
        .item-icon.lesson {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }
        .item-icon.quiz {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #f57c00;
        }
        .item-icon.completed {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .item-info {
            flex: 1;
            min-width: 0;
        }
        .item-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #1a1a1a;
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
            border-bottom: 2px solid #e0e0e0;
            padding: 20px 40px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .progress-bar-top {
            height: 6px;
            background: #e0e0e0;
            margin-bottom: 16px;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar-top .progress {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
            border-radius: 10px;
        }
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }
        .content-viewer { 
            background: white; 
            padding: 48px; 
            border-radius: 16px; 
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08); 
            max-width: 900px; 
            margin: 0 auto;
        }
        .pdf-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f0fe 100%);
            border: 2px dashed #667eea;
            border-radius: 16px;
            padding: 60px 40px;
            text-align: center;
            margin: 32px 0;
            transition: all 0.3s ease;
        }
        .pdf-container:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
        }
        .pdf-icon {
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        .navigation-buttons { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 48px;
            padding-top: 32px;
            border-top: 2px solid #f0f0f0;
            gap: 16px;
        }
        .empty-state {
            text-align: center;
            padding: 100px 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.5;
        }
        .completion-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 32px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        .completion-banner h4 {
            margin-bottom: 16px;
            font-weight: 700;
        }
        .badge-custom {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .badge-reading {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }
        .badge-quiz {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #f57c00;
        }
        .btn-complete {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 25px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        .btn-outline-secondary {
            border-radius: 25px;
            padding: 12px 28px;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }
        .quiz-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f0fe 100%);
            padding: 28px;
            border-radius: 16px;
            margin: 28px 0;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }
        .quiz-summary .stat {
            display: flex;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .quiz-summary .stat:last-child {
            border-bottom: none;
        }
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .course-layout {
                grid-template-columns: 1fr;
                height: auto;
            }
            .sidebar {
                display: none;
            }
            .content-viewer {
                padding: 24px;
            }
            .navigation-buttons {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="course-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
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
                        <span class="fw-bold"><?php echo $progressPercentage; ?>%</span>
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
                        <i class="bi bi-book me-2"></i> LESSONS
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
                        <i class="bi bi-clipboard-check me-2"></i> ASSESSMENTS
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
                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h5>
                    <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 14px; padding: 8px 16px;"><?php echo $progressPercentage; ?>% Complete</span>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($isCompleted): ?>
                    <div class="completion-banner">
                        <i class="bi bi-trophy" style="font-size: 56px; margin-bottom: 20px;"></i>
                        <h4>Congratulations! 🎉</h4>
                        <p class="mb-4">You've completed all lessons and quizzes in this course!</p>
                        <button class="btn btn-light btn-lg rounded-pill fw-bold px-4" onclick="window.location.href='view_certificate.php?id=<?php echo $certificateUUID; ?>'">
                            <i class="bi bi-award me-2"></i> View Certificate
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($currentLesson): ?>
                    <!-- Lesson Content -->
                    <div class="content-viewer">
                        <span class="badge-custom badge-reading">
                            <i class="bi bi-file-pdf me-1"></i> Reading Material
                        </span>
                        <h3 class="fw-bold mb-3"><?php echo htmlspecialchars($currentLesson['title']); ?></h3>
                        <p class="text-muted">Read the attached PDF document to complete this lesson.</p>
                        
                        <div class="pdf-container">
                            <div class="pdf-icon">
                                <i class="bi bi-file-pdf" style="font-size: 48px; color: #d32f2f;"></i>
                            </div>
                            <h5 class="fw-bold"><?php echo htmlspecialchars($currentLesson['title']); ?>.pdf</h5>
                            <p class="text-muted mb-0">PDF Document</p>
                            <button class="btn btn-primary btn-lg mt-4 rounded-pill fw-bold px-5" onclick="window.open('<?php echo htmlspecialchars($currentLesson['filename']); ?>', '_blank')">
                                <i class="bi bi-box-arrow-up-right me-2"></i> Open PDF Viewer
                            </button>
                        </div>
                        
                        <?php if (!$currentLesson['isCompleted']): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Complete this lesson:</strong> Mark as complete after you've finished reading the material.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
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
                                    <i class="bi bi-arrow-left me-2"></i> Previous Lesson
                                </button>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-3">
                                <?php if (!$currentLesson['isCompleted']): ?>
                                    <button class="btn btn-complete" onclick="markComplete(<?php echo $currentLesson['lessonID']; ?>)">
                                        <i class="bi bi-check-circle me-2"></i> Mark as Complete
                                    </button>
                                <?php endif; ?>
                                                                <?php if ($nextLesson): ?>
                                    <button class="btn btn-primary" onclick="window.location.href='?id=<?php echo $courseID; ?>&lesson_id=<?php echo $nextLesson['lessonID']; ?>'">
                                        Next Lesson <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                <?php elseif (!empty($quizzes) && !$isCompleted): ?>
                                    <button class="btn btn-primary" onclick="window.location.href='?id=<?php echo $courseID; ?>&quiz_id=<?php echo $quizzes[0]['quizID']; ?>'">
                                        Take Quiz <i class="bi bi-clipboard-check ms-2"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($currentQuiz): ?>
                    <!-- Quiz Content -->
                    <div class="content-viewer">
                        <span class="badge-custom badge-quiz">
                            <i class="bi bi-clipboard-check me-1"></i> Assessment
                        </span>
                        <h3 class="fw-bold mb-3"><?php echo htmlspecialchars($currentQuiz['title']); ?></h3>
                        <p class="text-muted">Test your knowledge with this quiz. You'll need <?php echo $currentQuiz['passingScore']; ?>% to pass.</p>
                        
                        <div class="quiz-summary">
                            <div class="stat">
                                <span><strong><i class="bi bi-question-circle me-2"></i>Questions:</strong></span>
                                <span class="fw-bold"><?php echo $currentQuiz['questionCount']; ?></span>
                            </div>
                            <div class="stat">
                                <span><strong><i class="bi bi-flag me-2"></i>Passing Score:</strong></span>
                                <span class="fw-bold text-primary"><?php echo $currentQuiz['passingScore']; ?>%</span>
                            </div>
                            <div class="stat">
                                <span><strong><i class="bi bi-info-circle me-2"></i>Status:</strong></span>
                                <span>
                                    <?php if ($currentQuiz['hasPassed']): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2">
                                            <i class="bi bi-check-circle-fill me-1"></i> Passed (<?php echo $currentQuiz['lastScore']; ?>%)
                                        </span>
                                    <?php elseif ($currentQuiz['lastAttempt']): ?>
                                        <span class="badge bg-warning rounded-pill px-3 py-2">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Not Passed (<?php echo $currentQuiz['lastScore']; ?>%)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill px-3 py-2">
                                            <i class="bi bi-clock me-1"></i> Not Attempted
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($currentQuiz['lastAttempt']): ?>
                            <div class="stat">
                                <span><strong><i class="bi bi-calendar me-2"></i>Last Attempt:</strong></span>
                                <span><?php echo date('M d, Y g:i A', strtotime($currentQuiz['lastAttempt'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($currentQuiz['hasPassed']): ?>
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                <div>
                                    <strong>Quiz passed!</strong> You've successfully completed this assessment with a score of <?php echo $currentQuiz['lastScore']; ?>%.
                                </div>
                            </div>
                        <?php elseif ($currentQuiz['lastAttempt']): ?>
                            <div class="alert alert-warning d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                                <div>
                                    <strong>Try again!</strong> You scored <?php echo $currentQuiz['lastScore']; ?>%, but need <?php echo $currentQuiz['passingScore']; ?>% to pass.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="navigation-buttons">
                            <?php if (!empty($lessons)): ?>
                                <button class="btn btn-outline-secondary" onclick="window.location.href='?id=<?php echo $courseID; ?>&lesson_id=<?php echo $lessons[count($lessons)-1]['lessonID']; ?>'">
                                    <i class="bi bi-arrow-left me-2"></i> Back to Lessons
                                </button>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-3">
                                <?php if (!$currentQuiz['hasPassed'] || ($currentQuiz['lastAttempt'] && !$currentQuiz['hasPassed'])): ?>
                                    <button class="btn btn-primary btn-lg" onclick="window.location.href='take_quiz.php?quiz_id=<?php echo $currentQuiz['quizID']; ?>&course_id=<?php echo $courseID; ?>'">
                                        <i class="bi bi-play-circle me-2"></i> Start Quiz
                                    </button>
                                <?php endif; ?>
                                
                                <?php 
                                $currentQuizIndex = array_search($currentQuiz, $quizzes);
                                $nextQuiz = $currentQuizIndex < count($quizzes) - 1 ? $quizzes[$currentQuizIndex + 1] : null;
                                ?>
                                
                                <?php if ($nextQuiz): ?>
                                    <button class="btn btn-outline-primary" onclick="window.location.href='?id=<?php echo $courseID; ?>&quiz_id=<?php echo $nextQuiz['quizID']; ?>'">
                                        Next Quiz <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                <?php elseif ($isCompleted && !$currentQuiz['hasPassed']): ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox display-1"></i>
                        <h4 class="fw-bold mt-2">No Content Available</h4>
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
                        confirmButtonColor: '#667eea',
                        background: '#ffffff',
                        color: '#1a1a1a',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to mark lesson as complete',
                        confirmButtonColor: '#667eea',
                        background: '#ffffff',
                        color: '#1a1a1a'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#667eea',
                    background: '#ffffff',
                    color: '#1a1a1a'
                });
            });
        }

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');
                const topBar = document.querySelector('.top-bar');
                
                // Create mobile header
                const mobileHeader = document.createElement('div');
                mobileHeader.className = 'mobile-header d-flex align-items-center justify-content-between p-3 bg-white border-bottom';
                mobileHeader.innerHTML = `
                    <button class="btn btn-outline-primary rounded-pill" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i> Menu
                    </button>
                    <h6 class="mb-0 fw-bold text-truncate mx-2"><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h6>
                    <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><?php echo $progressPercentage; ?>%</span>
                `;
                
                document.body.insertBefore(mobileHeader, document.querySelector('.course-layout'));
                
                // Add back button to sidebar for mobile
                const sidebarHeader = document.querySelector('.sidebar-header');
                const backBtn = sidebarHeader.querySelector('.back-btn');
                if (backBtn) {
                    backBtn.innerHTML = '<i class="bi bi-arrow-left"></i> Back';
                    backBtn.style.marginBottom = '10px';
                }
            }
        });

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.style.display = sidebar.style.display === 'flex' ? 'none' : 'flex';
        }
    </script>
</body>
</html>
