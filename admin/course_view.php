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
               u.avatar as teacherAvatar,
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
        $stmt = $conn->prepare("SELECT COUNT(*) FROM quizquestions WHERE quizID = ?");
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
            <div class="bg-white rounded-3 shadow-sm p-3 w-100">
                <div class="d-flex align-items-center">
                    <a href="courses.php" class="btn btn-outline-secondary me-3" id="backButton">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div class="flex-grow-1">
                        <h1 class="h3 mb-0">Course Details</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" class="fw-bold text-primary">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="courses.php" class="fw-bold text-primary">Courses</a></li>
                                <li class="breadcrumb-item active text-dark" aria-current="page"><?php echo htmlspecialchars($course['title']); ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold d-flex align-items-center">
                            <i class="bi bi-info-circle me-2"></i>Course Information
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <h4 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h4>
                        
                        <div class="mb-4">
                            <span class="badge fs-6 px-3 py-2 me-2" style="background: <?php 
                                echo $course['status'] === 'published' ? 'rgba(40, 167, 69, 0.9)' : 
                                    ($course['status'] === 'draft' ? 'rgba(255, 193, 7, 0.9)' : 'rgba(108, 117, 125, 0.9)'); 
                            ?>; color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                            <span class="badge fs-6 px-3 py-2 me-2" style="background: rgba(13, 202, 240, 0.9); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                <?php echo !empty($course['category']) ? htmlspecialchars($course['category']) : 'Uncategorized'; ?>
                            </span>
                            <span class="badge fs-6 px-3 py-2" style="background: rgba(13, 110, 253, 0.9); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                ₱<?php echo number_format($course['price'], 2); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($course['description'])): ?>
                            <div class="mb-4">
                                <h6 class="text-muted mb-2">Description</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Course ID</small>
                                    <p class="mb-0 fw-medium"><?php echo $course['courseID']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Passing Score</small>
                                    <p class="mb-0 fw-medium"><?php echo $course['passingScore']; ?>%</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Created</small>
                            <p class="mb-0 fw-medium">
                                <?php echo date('F d, Y', strtotime($course['createdAt'])); ?>
                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($course['createdAt'])); ?></small>
                            </p>
                        </div>
                        
                        <!-- Delete button moved inside the card -->
                        <div class="mt-4 pt-3 border-top">
                            <a href="course_actions.php?action=delete&id=<?php echo $courseID; ?>" 
                               class="btn btn-danger"
                               data-confirm-delete="Are you sure you want to delete this course? This will permanently delete the course, all its modules, content, quizzes, and student enrollments."
                               onclick="return confirm('Are you sure you want to delete this course? This will permanently delete the course, all its modules, content, quizzes, and student enrollments.');">
                                <i class="bi bi-trash me-2"></i>Delete Course
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Course Content -->
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold d-flex align-items-center">
                            <i class="bi bi-file-earmark-text me-2"></i>Course Contents
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <!-- Modules -->
                        <?php if (!empty($modules)): ?>
                            <h6 class="text-muted mb-3 border-bottom pb-2">Modules (<?php echo count($modules); ?>)</h6>
                            <div class="list-group mb-4">
                                <?php foreach ($modules as $module): ?>
                                    <div class="list-group-item border-0 mb-2 rounded" style="background: rgba(0, 0, 0, 0.02);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 fw-medium"><?php echo htmlspecialchars($module['title']); ?></h6>
                                                <?php if (!empty($module['description'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($module['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge" style="background: rgba(108, 117, 125, 0.9); color: white;">Order: <?php echo $module['orderNumber']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lessons)): ?>
                            <h6 class="text-muted mb-3 border-bottom pb-2">Lessons (<?php echo count($lessons); ?>)</h6>
                            <div class="list-group mb-4">
                                <?php foreach ($lessons as $index => $lesson): ?>
                                    <div class="list-group-item border-0 mb-2 rounded" style="background: rgba(0, 0, 0, 0.02);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>
                                                    <div>
                                                        <h6 class="mb-1 fw-medium"><?php echo htmlspecialchars($lesson['title']); ?></h6>
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
                            <h6 class="text-muted mb-3 border-bottom pb-2">Quizzes (<?php echo count($quizzes); ?>) with <?php echo $quizQuestionsCount; ?> questions</h6>
                            <div class="list-group">
                                <?php foreach ($quizzes as $quiz): ?>
                                    <div class="list-group-item border-0 mb-2 rounded" style="background: rgba(0, 0, 0, 0.02);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 fw-medium"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                                <?php if (!empty($quiz['description'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($quiz['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="badge me-2" style="background: rgba(13, 202, 240, 0.9); color: white;">Passing: <?php echo $quiz['passingScore']; ?>%</span>
                                                <?php if ($quiz['allowRetake']): ?>
                                                    <span class="badge" style="background: rgba(40, 167, 69, 0.9); color: white;">Retake Allowed</span>
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
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold d-flex align-items-center">
                            <i class="bi bi-person-circle me-2"></i>Course Creator
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <div class="text-center mb-3">
                            <div class="avatar-lg mx-auto mb-3 border border-3 border-white rounded-circle d-flex align-items-center justify-content-center" 
                                 style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden; width: 100px; height: 100px;">
                                <?php if (!empty($course['teacherAvatar']) && file_exists($course['teacherAvatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($course['teacherAvatar']); ?>" alt="Avatar" 
                                         class="w-100 h-100 rounded-circle object-fit-cover">
                                <?php else: ?>
                                    <span class="fs-3 text-white fw-bold">
                                        <?php echo strtoupper(substr($course['teacherFirstName'], 0, 1)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($course['teacherFirstName'] . ' ' . $course['teacherLastName']); ?></h5>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($course['teacherEmail']); ?></p>
                            <?php if (!empty($course['teacherPhone'])): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($course['teacherPhone']); ?></p>
                            <?php endif; ?>
                            <a href="user_view.php?id=<?php echo $course['teacherID']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View Teacher Profile
                            </a>
                        </div>
                        <hr class="my-3">
                        <div class="text-center">
                            <small class="text-muted d-block">Teacher since</small>
                            <p class="mb-0 fw-medium"><?php echo date('F d, Y', strtotime($course['teacherCreatedAt'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Enrollment Statistics -->
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold d-flex align-items-center">
                            <i class="bi bi-graph-up-arrow me-2"></i>Enrollment Statistics
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h2 class="text-primary mb-1 display-6 fw-bold"><?php echo $enrollmentStats['totalEnrollments']; ?></h2>
                                <small class="text-muted">Total Students</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h2 class="text-success mb-1 display-6 fw-bold"><?php echo $enrollmentStats['completed']; ?></h2>
                                <small class="text-muted">Completed</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h2 class="text-info mb-1 display-6 fw-bold"><?php echo $enrollmentStats['active']; ?></h2>
                                <small class="text-muted">Active</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h2 class="text-warning mb-1 display-6 fw-bold"><?php echo $enrollmentStats['avgProgress'] ? number_format($enrollmentStats['avgProgress'], 1) : '0'; ?>%</h2>
                                <small class="text-muted">Avg Progress</small>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="text-center">
                            <h4 class="text-success mb-2 fw-bold">₱<?php echo number_format($revenue, 2); ?></h4>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Enrollments -->
                <?php if (!empty($recentEnrollments)): ?>
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0.75rem;">
                        <div class="card-header border-0" style="background: transparent;">
                            <h5 class="mb-0 text-white fw-bold d-flex align-items-center">
                                <i class="bi bi-clock-history me-2"></i>Recent Enrollments
                            </h5>
                        </div>
                        <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentEnrollments as $enrollment): ?>
                                    <div class="list-group-item px-0 border-0 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold"
                                                 style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <?php echo strtoupper(substr($enrollment['firstName'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-medium"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d', strtotime($enrollment['enrolledAt'])); ?>
                                                    <span class="badge ms-2 px-2 py-1" style="background: <?php 
                                                        echo $enrollment['status'] === 'completed' ? 'rgba(40, 167, 69, 0.9)' : 
                                                            ($enrollment['status'] === 'active' ? 'rgba(13, 110, 253, 0.9)' : 'rgba(255, 193, 7, 0.9)'); 
                                                    ?>; color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                                        <?php echo ucfirst($enrollment['status']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="enrollments.php?course=<?php echo $courseID; ?>" class="btn btn-outline-primary w-100 mt-3">
                                <i class="bi bi-list me-1"></i>View All Enrollments
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Back button styling to match user_edit.php */
#backButton.btn-outline-secondary {
    border-color: #fbb6ce !important;
    color: #6c757d !important;
    transition: all 0.2s ease !important;
}

#backButton.btn-outline-secondary:active {
    background-color: #fbb6ce !important;
    color: #fff !important;
    border-color: #fbb6ce !important;
}

#backButton.btn-outline-secondary:hover {
    background-color: rgba(251, 182, 206, 0.1) !important;
}

/* Breadcrumb styling */
.breadcrumb {
    background-color: transparent !important;
    padding: 0 !important;
    margin-bottom: 0 !important;
}

.breadcrumb-item a {
    text-decoration: none;
    color: #6c757d;
    transition: color 0.2s ease;
}

.breadcrumb-item a:hover {
    color: #0d6efd;
}

.breadcrumb-item a.fw-bold.text-primary {
    font-weight: 600 !important;
    color: #0d6efd !important;
}

.breadcrumb-item a.fw-bold.text-primary:hover {
    color: #0a58ca !important;
    text-decoration: underline;
}

.breadcrumb-item.active.text-dark {
    color: #212529 !important;
    font-weight: 500;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: #adb5bd;
    content: "›";
    font-size: 1.1em;
}

/* Card styling improvements */
.card-header h5 {
    font-size: 1.1rem;
}

.avatar-lg {
    width: 100px;
    height: 100px;
    font-size: 2.5rem;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 1rem;
}

/* Badge styling */
.badge {
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .col-lg-4 {
        margin-top: 1.5rem !important;
    }
}

@media (max-width: 767.98px) {
    .h3.mb-0 {
        font-size: 1.25rem;
    }
    
    .breadcrumb {
        font-size: 0.85rem;
    }
    
    #backButton.btn-outline-secondary {
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .display-6 {
        font-size: 1.5rem !important;
    }
    
    .avatar-lg {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }
}

@media (max-width: 575.98px) {
    .bg-white.rounded-3.shadow-sm.p-3 {
        padding: 1rem !important;
    }
    
    .display-6 {
        font-size: 1.25rem !important;
    }
    
    .avatar-lg {
        width: 70px;
        height: 70px;
        font-size: 1.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include 'includes/footer.php'; ?>