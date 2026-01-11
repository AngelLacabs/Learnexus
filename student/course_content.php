<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;
$userID = $_SESSION['user_id'];

// Check enrollment
$stmt = $conn->prepare("
    SELECT e.*, c.title as courseTitle
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    WHERE e.userID = ? AND e.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    header('Location: my_courses.php');
    exit();
}

// Get modules with contents
$stmt = $conn->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM contents WHERE moduleID = m.moduleID) as contentCount
    FROM modules m
    WHERE m.courseID = ?
    ORDER BY m.orderNumber
");
$stmt->execute([$courseID]);
$modules = $stmt->fetchAll();

// Get first content to display
$currentContentID = $_GET['content_id'] ?? null;
if (!$currentContentID && count($modules) > 0) {
    $stmt = $conn->prepare("SELECT contentID FROM contents WHERE moduleID = ? ORDER BY orderNumber LIMIT 1");
    $stmt->execute([$modules[0]['moduleID']]);
    $firstContent = $stmt->fetch();
    $currentContentID = $firstContent['contentID'] ?? null;
}

// Get current content details
$currentContent = null;
if ($currentContentID) {
    $stmt = $conn->prepare("SELECT * FROM contents WHERE contentID = ?");
    $stmt->execute([$currentContentID]);
    $currentContent = $stmt->fetch();
}

// Get quiz for this course
$stmt = $conn->prepare("SELECT quizID, title FROM quizzes WHERE courseID = ? LIMIT 1");
$stmt->execute([$courseID]);
$quiz = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Course Content - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { margin: 0; background: #f8f9fa; }
        .course-layout { display: grid; grid-template-columns: 280px 1fr; height: 100vh; }
        .sidebar { background: white; border-right: 1px solid #e0e0e0; overflow-y: auto; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #e0e0e0; }
        .module-item { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; cursor: pointer; }
        .module-item:hover { background: #f8f9fa; }
        .module-title { font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .content-item { padding: 10px 20px 10px 40px; display: flex; align-items: center; gap: 10px; font-size: 14px; cursor: pointer; }
        .content-item:hover { background: #f8f9fa; }
        .content-item.active { background: #e3f2fd; color: #1e88e5; }
        .main-content { overflow-y: auto; padding: 30px; }
        .progress-bar-top { height: 4px; background: #e0e0e0; }
        .progress-bar-top .progress { height: 100%; background: #1e88e5; }
        .content-viewer { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto; }
        .pdf-viewer { width: 100%; height: 600px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .navigation-buttons { display: flex; justify-content: space-between; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="course-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h6>Course Content</h6>
                <div class="progress mt-2" style="height: 8px;">
                    <div class="progress-bar" style="width: <?php echo $enrollment['progressPercentage']; ?>%"></div>
                </div>
                <small class="text-muted"><?php echo round($enrollment['progressPercentage']); ?>% Complete</small>
            </div>
            
            <?php foreach ($modules as $module): ?>
                <div class="module-item">
                    <div class="module-title">
                        <span><?php echo htmlspecialchars($module['title']); ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </div>
                
                <?php
                $stmt = $conn->prepare("SELECT * FROM contents WHERE moduleID = ? ORDER BY orderNumber");
                $stmt->execute([$module['moduleID']]);
                $contents = $stmt->fetchAll();
                
                foreach ($contents as $content):
                ?>
                    <div class="content-item <?php echo ($currentContentID == $content['contentID']) ? 'active' : ''; ?>" 
                         onclick="window.location.href='?id=<?php echo $courseID; ?>&content_id=<?php echo $content['contentID']; ?>'">
                        <i class="bi bi-file-earmark-pdf"></i>
                        <span><?php echo htmlspecialchars($content['title']); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <!-- Quiz Link -->
                <?php if ($quiz): ?>
                    <div class="content-item" onclick="window.location.href='take_quiz.php?quiz_id=<?php echo $quiz['quizID']; ?>'">
                        <i class="bi bi-patch-question"></i>
                        <span><?php echo htmlspecialchars($quiz['title']); ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="progress-bar-top">
                <div class="progress" style="width: <?php echo $enrollment['progressPercentage']; ?>%"></div>
            </div>
            
            <?php if ($currentContent): ?>
                <div class="content-viewer">
                    <span class="badge bg-primary mb-3">Reading Material</span>
                    <h3><?php echo htmlspecialchars($currentContent['title']); ?></h3>
                    <p class="text-muted">Read the attached PDF sheet.</p>
                    
                    <div class="pdf-viewer mt-4">
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f5f5f5; border-radius: 8px;">
                            <div class="text-center">
                                <i class="bi bi-file-earmark-pdf" style="font-size: 48px; color: #999;"></i>
                                <p class="mt-3 text-muted">// pdf logo here</p>
                                <p><strong><?php echo htmlspecialchars($currentContent['title']); ?>.pdf</strong></p>
                                <p class="text-muted small">PDF Document • 1.2 MB</p>
                                <button class="btn btn-outline-primary mt-2" onclick="window.open('<?php echo htmlspecialchars($currentContent['filePath']); ?>', '_blank')">
                                    <i class="bi bi-box-arrow-up-right"></i> Open in Viewer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Previous Lesson
                        </button>
                        
                        <?php if ($quiz): ?>
                            <button class="btn btn-primary" onclick="window.location.href='take_quiz.php?quiz_id=<?php echo $quiz['quizID']; ?>'">
                                Take Quiz
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary">
                                Next Lesson <i class="bi bi-arrow-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No content available yet.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>