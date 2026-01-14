<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: courses.php');
    exit();
}

$courseID = (int)$_GET['id'];

// Fetch course details
try {
    // Get basic course info with teacher details
    $stmt = $conn->prepare("
        SELECT c.*, 
               u.firstName as teacherFirstName,
               u.lastName as teacherLastName,
               u.email as teacherEmail,
               u.phone as teacherPhone,
               u.createdAt as teacherCreatedAt
        FROM courses c 
        JOIN users u ON c.teacherID = u.userID 
        WHERE c.courseID = ?
    ");
    $stmt->execute([$courseID]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $_SESSION['error'] = 'Course not found';
        header('Location: courses.php');
        exit();
    }
    
    // Get enrollment statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as totalEnrollments,
            SUM(status = 'completed') as completed,
            SUM(status = 'active') as active,
            SUM(status = 'dropped') as dropped,
            AVG(progressPercentage) as avgProgress
        FROM enrollments 
        WHERE courseID = ?
    ");
    $stmt->execute([$courseID]);
    $enrollmentStats = $stmt->fetch();
    
    // Get recent enrollments
    $stmt = $conn->prepare("
        SELECT e.*, u.firstName, u.lastName, u.email 
        FROM enrollments e 
        JOIN users u ON e.userID = u.userID 
        WHERE e.courseID = ? 
        ORDER BY e.enrolledAt DESC 
        LIMIT 10
    ");
    $stmt->execute([$courseID]);
    $recentEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get course modules
    $stmt = $conn->prepare("SELECT * FROM modules WHERE courseID = ? ORDER BY orderNumber");
    $stmt->execute([$courseID]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lessons
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt");
    $stmt->execute([$courseID]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get quizzes
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get quiz questions count
    $quizQuestionsCount = 0;
    foreach ($quizzes as $quiz) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quizID = ?");
        $stmt->execute([$quiz['quizID']]);
        $quizQuestionsCount += $stmt->fetchColumn();
    }
    
    // Get revenue from this course
    $stmt = $conn->prepare("
        SELECT SUM(p.amount) as totalRevenue 
        FROM payments p 
        JOIN enrollments e ON p.enrollmentID = e.enrollmentID 
        WHERE e.courseID = ? AND p.status = 'completed'
    ");
    $stmt->execute([$courseID]);
    $revenue = $stmt->fetchColumn() ?? 0;
    
} catch (PDOException $e) {
    error_log("Course View Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading course details';
    header('Location: courses.php');
    exit();
}

$page_title = "Course Details - " . $course['title'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="courses.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">Course Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($course['title']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="btn-group">
                <?php if ($course['status'] === 'draft'): ?>
                    <a href="course_actions.php?action=publish&id=<?php echo $courseID; ?>" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Publish Course
                    </a>
                <?php elseif ($course['status'] === 'published'): ?>
                    <button type="button" 
                            class="btn btn-warning reject-course-btn" 
                            data-course-id="<?php echo $courseID; ?>"
                            data-course-title="<?php echo htmlspecialchars($course['title']); ?>">
                        <i class="bi bi-x-circle me-2"></i>Reject/Archive
                    </button>
                <?php endif; ?>
                
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if ($course['status'] === 'published'): ?>
                        <li>
                            <a class="dropdown-item" href="course_actions.php?action=archive&id=<?php echo $courseID; ?>">
                                <i class="bi bi-archive me-2"></i>Archive
                            </a>
                        </li>
                    <?php elseif ($course['status'] === 'archived'): ?>
                        <li>
                            <a class="dropdown-item" href="course_actions.php?action=publish&id=<?php echo $courseID; ?>">
                                <i class="bi bi-check-circle me-2"></i>Publish
                            </a>
                        </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" 
                           href="course_actions.php?action=delete&id=<?php echo $courseID; ?>"
                           data-confirm-delete="Are you sure you want to delete this course? This will permanently delete the course, all its modules, content, quizzes, and student enrollments.">
                            <i class="bi bi-trash me-2"></i>Delete Course
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Course Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Course Information</h5>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h4>
                        
                        <div class="mb-4">
                            <span class="badge bg-<?php 
                                echo $course['status'] === 'published' ? 'success' : 
                                    ($course['status'] === 'draft' ? 'warning' : 'secondary'); 
                            ?> fs-6 me-2">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                            <span class="badge bg-info fs-6 me-2">
                                <?php echo !empty($course['category']) ? htmlspecialchars($course['category']) : 'Uncategorized'; ?>
                            </span>
                            <span class="badge bg-primary fs-6">
                                ₱<?php echo number_format($course['price'], 2); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($course['description'])): ?>
                            <div class="mb-4">
                                <h6>Description</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Course ID</small>
                                    <p class="mb-0"><?php echo $course['courseID']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Passing Score</small>
                                    <p class="mb-0"><?php echo $course['passingScore']; ?>%</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Created</small>
                            <p class="mb-0">
                                <?php echo date('F d, Y', strtotime($course['createdAt'])); ?>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($course['createdAt'])); ?></small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Course Content -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Course Contentss</h5>
                    </div>
                    <div class="card-body">
                        <!-- Modules -->
                        <?php if (!empty($modules)): ?>
                            <h6 class="mb-3">Modules (<?php echo count($modules); ?>)</h6>
                            <div class="list-group mb-4">
                                <?php foreach ($modules as $module): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($module['title']); ?></h6>
                                                <?php if (!empty($module['description'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($module['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-secondary">Order: <?php echo $module['orderNumber']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lessons)): ?>
    <h6 class="mb-3">Lessons (<?php echo count($lessons); ?>)</h6>
    <div class="list-group mb-4">
        <?php foreach ($lessons as $index => $lesson): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                <small class="text-muted">
                                    File: <?php echo htmlspecialchars(basename($lesson['filename'])); ?>
                                    • Uploaded: <?php echo date('M d, Y', strtotime($lesson['uploadedAt'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?php
                        // Get file path from database
                        $filePath = $lesson['filename'];
                        
                        // Build web URL for browser access
                        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                        $projectPath = dirname(dirname($_SERVER['PHP_SELF'])); // Gets /Learnexus
                        
                        // Normalize stored file path to remove any leading ../ or ./ and leading slashes
                        $normalizedPath = preg_replace('#^(\./|\.\./)+#', '', $filePath);
                        $normalizedPath = ltrim($normalizedPath, '/');
                        
                        // If filename is only a basename (no directories), assume uploads/lessons/
                        if (basename($normalizedPath) === $normalizedPath) {
                            $normalizedPath = 'uploads/lessons/' . $normalizedPath;
                        }
                        
                        // Create web URL
                        $fileUrl = $baseUrl . $projectPath . '/' . $normalizedPath;
                        
                        // Create server path from project root and check existence
                        $serverPath = realpath(__DIR__ . '/../' . $normalizedPath) ?: (__DIR__ . '/../' . $normalizedPath);
                        $fileExists = file_exists($serverPath);
                        ?>
                        
                        <!-- Preview PDF Button -->
                        <button type="button" 
                                class="btn btn-outline-primary preview-pdf-btn"
                                data-pdf-url="<?php echo htmlspecialchars($fileUrl); ?>"
                                data-pdf-title="<?php echo htmlspecialchars($lesson['title']); ?>"
                                <?php echo !$fileExists ? 'disabled title="File not found on server"' : ''; ?>
                                data-bs-toggle="tooltip" 
                                title="Preview PDF">
                            <i class="bi bi-eye"></i>
                        </button>
                        
                        <!-- Download PDF Button -->
                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" 
                           class="btn btn-outline-success <?php echo !$fileExists ? 'disabled' : ''; ?>"
                           download="<?php echo htmlspecialchars($lesson['title'] . '.pdf'); ?>"
                           <?php echo !$fileExists ? 'onclick="return false;" title="File not found on server"' : ''; ?>
                           data-bs-toggle="tooltip" 
                           title="Download PDF">
                            <i class="bi bi-download"></i>
                        </a>
                        
                        <!-- View in New Tab -->
                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" 
                           target="_blank"
                           class="btn btn-outline-info <?php echo !$fileExists ? 'disabled' : ''; ?>"
                           <?php echo !$fileExists ? 'onclick="return false;" title="File not found on server"' : ''; ?>
                           data-bs-toggle="tooltip" 
                           title="Open in New Tab">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
                
                <!-- File status indicator -->
                <div class="mt-2">
                    <small>
                        <?php if ($fileExists): ?>
                            <span class="text-success">
                                <i class="bi bi-check-circle me-1"></i>File available
                            </span>
                            <a href="#" 
                               class="text-primary preview-pdf-link ms-2"
                               data-pdf-url="<?php echo htmlspecialchars($fileUrl); ?>"
                               data-pdf-title="<?php echo htmlspecialchars($lesson['title']); ?>">
                                <i class="bi bi-play-circle me-1"></i>Preview this lesson
                            </a>
                        <?php else: ?>
                            <span class="text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                File not found. Path: <?php echo htmlspecialchars($serverPath); ?>
                            </span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
                        
                        <!-- Quizzes -->
                        <?php if (!empty($quizzes)): ?>
                            <h6 class="mb-3">Quizzes (<?php echo count($quizzes); ?>) with <?php echo $quizQuestionsCount; ?> questions</h6>
                            <div class="list-group">
                                <?php foreach ($quizzes as $quiz): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                                <?php if (!empty($quiz['description'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($quiz['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="badge bg-info me-2">Passing: <?php echo $quiz['passingScore']; ?>%</span>
                                                <?php if ($quiz['allowRetake']): ?>
                                                    <span class="badge bg-success">Retake Allowed</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($modules) && empty($lessons) && empty($quizzes)): ?>
                            <p class="text-muted mb-0">No content added to this course yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Teacher Information -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Course Creator</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                <?php echo strtoupper(substr($course['teacherFirstName'], 0, 1)); ?>
                            </div>
                            <h5><?php echo htmlspecialchars($course['teacherFirstName'] . ' ' . $course['teacherLastName']); ?></h5>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($course['teacherEmail']); ?></p>
                            <?php if (!empty($course['teacherPhone'])): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($course['teacherPhone']); ?></p>
                            <?php endif; ?>
                            <a href="user_view.php?id=<?php echo $course['teacherID']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-sm btn-outline-primary">View Teacher Profile</a>
                        </div>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">Teacher since</small>
                            <p class="mb-0"><?php echo date('F d, Y', strtotime($course['teacherCreatedAt'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Enrollment Statistics -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Enrollment Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h2 class="text-primary mb-1"><?php echo $enrollmentStats['totalEnrollments']; ?></h2>
                                <small class="text-muted">Total Students</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h2 class="text-success mb-1"><?php echo $enrollmentStats['completed']; ?></h2>
                                <small class="text-muted">Completed</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h2 class="text-info mb-1"><?php echo $enrollmentStats['active']; ?></h2>
                                <small class="text-muted">Active</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h2 class="text-warning mb-1"><?php echo $enrollmentStats['avgProgress'] ? number_format($enrollmentStats['avgProgress'], 1) : '0'; ?>%</h2>
                                <small class="text-muted">Avg Progress</small>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <h4 class="text-success mb-2">₱<?php echo number_format($revenue, 2); ?></h4>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Enrollments -->
                <?php if (!empty($recentEnrollments)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Recent Enrollments</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentEnrollments as $enrollment): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <?php echo strtoupper(substr($enrollment['firstName'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d', strtotime($enrollment['enrolledAt'])); ?>
                                                    <span class="badge bg-<?php 
                                                        echo $enrollment['status'] === 'completed' ? 'success' : 
                                                            ($enrollment['status'] === 'active' ? 'primary' : 'warning'); 
                                                    ?> ms-2">
                                                        <?php echo ucfirst($enrollment['status']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="enrollments.php?course=<?php echo $courseID; ?>" class="btn btn-sm btn-outline-primary w-100 mt-3">View All Enrollments</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reject Course Modal -->
<div class="modal fade" id="rejectCourseModal" tabindex="-1" aria-labelledby="rejectCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectCourseModalLabel">Reject/Archive Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectCourseForm" method="POST" action="course_actions.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?php echo $courseID; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action *</label>
                        <select class="form-select" name="status" required>
                            <option value="draft">Send back to Draft (for revisions)</option>
                            <option value="archived">Archive Course (hide from students)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason/Feedback *</label>
                        <textarea class="form-control" name="rejection_reason" rows="4" required 
                                  placeholder="Provide specific feedback to the teacher about what needs to be improved..."></textarea>
                        <small class="text-muted">This feedback will be sent to the course creator</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Submit Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="pdfPreviewModalLabel">
                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                    <span id="pdfModalTitle">PDF Preview</span>
                </h5>
                <div class="d-flex align-items-center">
                    <span class="badge bg-secondary me-2" id="pdfPageInfo">Page: 1/1</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="zoomOutBtn">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="zoomInBtn">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid h-100">
                    <div class="row h-100">
                        <!-- PDF Navigation Sidebar (Collapsible) -->
                        <div class="col-lg-3 col-md-4 border-end bg-light d-none d-md-block" id="pdfSidebar">
                            <div class="p-3">
                                <h6 class="mb-3">Thumbnails</h6>
                                <div class="nav flex-column nav-pills" id="pdfThumbnails" role="tablist" aria-orientation="vertical">
                                    <!-- Thumbnails will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- PDF Viewer Area -->
                        <div class="col-lg-9 col-md-8" id="pdfViewerArea">
                            <div class="position-relative h-100">
                                <!-- PDF Canvas Container -->
                                <div id="pdfCanvasContainer" class="overflow-auto" style="height: calc(100vh - 200px);">
                                    <canvas id="pdfCanvas" class="mx-auto d-block"></canvas>
                                </div>
                                
                                <!-- PDF Controls -->
                                <div class="position-fixed bottom-0 start-0 end-0 bg-white border-top p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="prevPageBtn">
                                                <i class="bi bi-chevron-left"></i> Previous
                                            </button>
                                            <span class="mx-2">
                                                Page: <span id="currentPage">1</span> of <span id="totalPages">1</span>
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="nextPageBtn">
                                                Next <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                        <div>
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="pageJumpInput" 
                                                       min="1" 
                                                       value="1" 
                                                       style="width: 60px;">
                                                <button class="btn btn-outline-secondary" type="button" id="goToPageBtn">
                                                    Go
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebarToggleBtn">
                                                    <i class="bi bi-layout-sidebar"></i>
                                                </button>
                                                <a href="#" class="btn btn-sm btn-outline-success" id="downloadPdfBtn">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                                <a href="#" class="btn btn-sm btn-outline-info" id="openInNewTabBtn" target="_blank">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Zoom Control -->
                                    <div class="mt-2">
                                        <label class="form-label small">Zoom: <span id="zoomLevel">100</span>%</label>
                                        <input type="range" 
                                               class="form-range" 
                                               id="zoomSlider" 
                                               min="25" 
                                               max="500" 
                                               value="100" 
                                               step="25">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // PDF.js Configuration
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    
    let pdfDoc = null;
    let currentPage = 1;
    let totalPages = 1;
    let scale = 1;
    let pdfUrl = '';
    let pdfTitle = '';
    
    // PDF Preview Modal Elements
    const pdfPreviewModal = new bootstrap.Modal(document.getElementById('pdfPreviewModal'));
    const pdfCanvas = document.getElementById('pdfCanvas');
    const ctx = pdfCanvas.getContext('2d');
    const pdfModalTitle = document.getElementById('pdfModalTitle');
    const currentPageSpan = document.getElementById('currentPage');
    const totalPagesSpan = document.getElementById('totalPages');
    const pageJumpInput = document.getElementById('pageJumpInput');
    const pdfPageInfo = document.getElementById('pdfPageInfo');
    const zoomLevelSpan = document.getElementById('zoomLevel');
    const zoomSlider = document.getElementById('zoomSlider');
    const downloadPdfBtn = document.getElementById('downloadPdfBtn');
    const openInNewTabBtn = document.getElementById('openInNewTabBtn');
    
    // Navigation Elements
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const goToPageBtn = document.getElementById('goToPageBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const pdfSidebar = document.getElementById('pdfSidebar');
    const pdfViewerArea = document.getElementById('pdfViewerArea');
    const pdfThumbnails = document.getElementById('pdfThumbnails');
    
    // Handle PDF Preview Button Clicks
    const previewButtons = document.querySelectorAll('.preview-pdf-btn, .preview-pdf-link');
    previewButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            pdfUrl = this.getAttribute('data-pdf-url');
            pdfTitle = this.getAttribute('data-pdf-title');
            
            if (!pdfUrl) {
                alert('PDF URL not found');
                return;
            }
            
            // Set modal title
            pdfModalTitle.textContent = pdfTitle;
            downloadPdfBtn.href = pdfUrl;
            openInNewTabBtn.href = pdfUrl;
            
            // Reset PDF viewer
            resetPdfViewer();
            
            // Load and display the PDF
            loadAndDisplayPdf(pdfUrl);
            
            // Show modal
            pdfPreviewModal.show();
        });
    });
    
    // Reset PDF viewer state
    function resetPdfViewer() {
        pdfDoc = null;
        currentPage = 1;
        scale = 1;
        pdfCanvas.width = 0;
        pdfCanvas.height = 0;
        currentPageSpan.textContent = '1';
        totalPagesSpan.textContent = '1';
        pageJumpInput.value = '1';
        pdfPageInfo.textContent = 'Page: 1/1';
        zoomLevelSpan.textContent = '100';
        zoomSlider.value = '100';
        pdfThumbnails.innerHTML = '';
    }
    
    // Load and display PDF
    async function loadAndDisplayPdf(url) {
        try {
            // Show loading state
            pdfModalTitle.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Loading PDF...`;
            
            // Load the PDF
            const loadingTask = pdfjsLib.getDocument(url);
            pdfDoc = await loadingTask.promise;
            
            totalPages = pdfDoc.numPages;
            totalPagesSpan.textContent = totalPages;
            pdfPageInfo.textContent = `Page: 1/${totalPages}`;
            
            // Reset modal title
            pdfModalTitle.textContent = pdfTitle;
            
            // Render first page
            await renderPage(currentPage);
            
            // Generate thumbnails
            await generateThumbnails();
            
        } catch (error) {
            console.error('Error loading PDF:', error);
            pdfModalTitle.textContent = 'Error Loading PDF';
            alert('Unable to load PDF. Please try downloading the file instead.');
        }
    }
    
    // Render a specific page
    async function renderPage(pageNum) {
        if (!pdfDoc || pageNum < 1 || pageNum > totalPages) {
            return;
        }
        
        currentPage = pageNum;
        currentPageSpan.textContent = currentPage;
        pageJumpInput.value = currentPage;
        pdfPageInfo.textContent = `Page: ${currentPage}/${totalPages}`;
        
        // Highlight active thumbnail
        const thumbnails = pdfThumbnails.querySelectorAll('.nav-link');
        thumbnails.forEach((thumb, index) => {
            thumb.classList.remove('active');
            if (index + 1 === pageNum) {
                thumb.classList.add('active');
            }
        });
        
        try {
            const page = await pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({ scale: scale });
            
            // Set canvas dimensions
            pdfCanvas.width = viewport.width;
            pdfCanvas.height = viewport.height;
            
            // Render PDF page
            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            
            await page.render(renderContext).promise;
            
        } catch (error) {
            console.error('Error rendering page:', error);
        }
    }
    
    // Generate thumbnail previews
    async function generateThumbnails() {
        pdfThumbnails.innerHTML = '';
        
        for (let i = 1; i <= Math.min(totalPages, 10); i++) { // Limit to 10 thumbnails for performance
            const li = document.createElement('div');
            li.className = 'nav-item mb-2';
            
            const button = document.createElement('button');
            button.className = 'nav-link text-start';
            button.type = 'button';
            button.setAttribute('data-page', i);
            button.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="me-2 text-center" style="width: 40px;">
                        <i class="bi bi-file-earmark"></i>
                    </div>
                    <div>
                        <small class="d-block">Page ${i}</small>
                        <small class="text-muted">Click to view</small>
                    </div>
                </div>
            `;
            
            button.addEventListener('click', () => {
                renderPage(i);
                // Scroll to top of canvas
                document.getElementById('pdfCanvasContainer').scrollTop = 0;
            });
            
            li.appendChild(button);
            pdfThumbnails.appendChild(li);
        }
        
        // Mark first page as active
        if (pdfThumbnails.firstChild) {
            pdfThumbnails.firstChild.querySelector('.nav-link').classList.add('active');
        }
    }
    
    // Navigation Event Listeners
    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            renderPage(currentPage - 1);
        }
    });
    
    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            renderPage(currentPage + 1);
        }
    });
    
    goToPageBtn.addEventListener('click', () => {
        const pageNum = parseInt(pageJumpInput.value);
        if (pageNum >= 1 && pageNum <= totalPages) {
            renderPage(pageNum);
        } else {
            alert(`Please enter a page number between 1 and ${totalPages}`);
            pageJumpInput.value = currentPage;
        }
    });
    
    pageJumpInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            goToPageBtn.click();
        }
    });
    
    // Zoom Controls
    zoomInBtn.addEventListener('click', () => {
        if (scale < 5) {
            scale += 0.25;
            updateZoom();
        }
    });
    
    zoomOutBtn.addEventListener('click', () => {
        if (scale > 0.25) {
            scale -= 0.25;
            updateZoom();
        }
    });
    
    zoomSlider.addEventListener('input', (e) => {
        scale = parseInt(e.target.value) / 100;
        updateZoom();
    });
    
    function updateZoom() {
        zoomLevelSpan.textContent = Math.round(scale * 100);
        zoomSlider.value = Math.round(scale * 100);
        renderPage(currentPage);
    }
    
    // Toggle Sidebar
    sidebarToggleBtn.addEventListener('click', () => {
        const sidebar = document.getElementById('pdfSidebar');
        const viewerArea = document.getElementById('pdfViewerArea');
        
        if (sidebar.classList.contains('d-none')) {
            sidebar.classList.remove('d-none');
            viewerArea.classList.remove('col-lg-12');
            viewerArea.classList.add('col-lg-9', 'col-md-8');
            sidebarToggleBtn.innerHTML = '<i class="bi bi-layout-sidebar"></i>';
        } else {
            sidebar.classList.add('d-none');
            viewerArea.classList.remove('col-lg-9', 'col-md-8');
            viewerArea.classList.add('col-lg-12');
            sidebarToggleBtn.innerHTML = '<i class="bi bi-layout-sidebar-inset"></i>';
        }
    });
    
    // Handle reject course button click
    const rejectBtn = document.querySelector('.reject-course-btn');
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectCourseModal'));
    const rejectCourseForm = document.getElementById('rejectCourseForm');
    
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
            rejectModal.show();
        });
    }
    
    if (rejectCourseForm) {
        rejectCourseForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            submitBtn.disabled = true;
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Reset PDF viewer when modal is closed
    document.getElementById('pdfPreviewModal').addEventListener('hidden.bs.modal', function () {
        resetPdfViewer();
    });
});
</script>

<?php include 'includes/footer.php'; ?>