<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: enrollments.php');
    exit();
}

$enrollmentID = (int)$_GET['id'];

try {
    // Get enrollment details
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            u.userID,
            u.firstName,
            u.lastName,
            u.email,
            u.phone,
            u.avatar,
            u.studentNumber,
            c.courseID,
            c.title as courseTitle,
            c.description as courseDescription,
            c.price,
            c.category,
            c.passingScore,
            c.teacherID,
            ct.firstName as teacherFirstName,
            ct.lastName as teacherLastName,
            ct.email as teacherEmail,
            p.amount as paidAmount,
            p.transactionReference,
            p.paymentDate,
            p.status as paymentStatus,
            cert.certificateUUID,
            cert.issuedAt as certificateIssuedAt
        FROM enrollments e 
        JOIN users u ON e.userID = u.userID 
        JOIN courses c ON e.courseID = c.courseID 
        JOIN users ct ON c.teacherID = ct.userID
        LEFT JOIN payments p ON e.paymentID = p.paymentID
        LEFT JOIN certificates cert ON e.enrollmentID = cert.enrollmentID
        WHERE e.enrollmentID = ?
    ");
    $stmt->execute([$enrollmentID]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        $_SESSION['error'] = 'Enrollment not found';
        header('Location: enrollments.php');
        exit();
    }
    
    // Get quiz results for this enrollment
    $stmt = $conn->prepare("
        SELECT qr.*, q.title as quizTitle, q.passingScore
        FROM quiz_results qr 
        JOIN quizzes q ON qr.quizID = q.quizID
        WHERE qr.enrollmentID = ?
        ORDER BY qr.submittedAt DESC
    ");
    $stmt->execute([$enrollmentID]);
    $quizResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lesson completions
    $stmt = $conn->prepare("
        SELECT lc.*, l.title as lessonTitle, l.uploadedAt
        FROM lesson_completions lc 
        JOIN lessons l ON lc.lessonID = l.lessonID
        WHERE lc.userID = ? AND l.courseID = ?
        ORDER BY lc.completedAt DESC
    ");
    $stmt->execute([$enrollment['userID'], $enrollment['courseID']]);
    $lessonCompletions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all lessons in course
    $stmt = $conn->prepare("SELECT COUNT(*) as totalLessons FROM lessons WHERE courseID = ?");
    $stmt->execute([$enrollment['courseID']]);
    $totalLessons = $stmt->fetchColumn();
    
    // Get all quizzes in course
    $stmt = $conn->prepare("SELECT COUNT(*) as totalQuizzes FROM quizzes WHERE courseID = ?");
    $stmt->execute([$enrollment['courseID']]);
    $totalQuizzes = $stmt->fetchColumn();
    
    // Calculate progress metrics
    $completedLessons = count($lessonCompletions);
    $lessonProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;
    
    // Get average quiz score
    $avgQuizScore = 0;
    $passedQuizzes = 0;
    if (!empty($quizResults)) {
        $totalScore = 0;
        foreach ($quizResults as $quiz) {
            $totalScore += (float)$quiz['percentage'];
            if ((float)$quiz['percentage'] >= $enrollment['passingScore']) {
                $passedQuizzes++;
            }
        }
        $avgQuizScore = round($totalScore / count($quizResults), 1);
    }
    
} catch (PDOException $e) {
    error_log("Enrollment View Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading enrollment details';
    header('Location: enrollments.php');
    exit();
}

$page_title = "Enrollment Details - " . $enrollment['firstName'] . ' ' . $enrollment['lastName'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="enrollments.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">Enrollment Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="enrollments.php">Enrollments</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="btn-group">
                <a href="enrollment_edit.php?id=<?php echo $enrollmentID; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Edit Enrollment
                </a>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if ($enrollment['status'] !== 'completed' && $enrollment['progressPercentage'] >= 100): ?>
                        <li>
                            <a href="enrollment_actions.php?action=complete&id=<?php echo $enrollmentID; ?>" class="dropdown-item text-success">
                                <i class="bi bi-check-circle me-2"></i>Mark as Completed
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($enrollment['status'] !== 'dropped'): ?>
                        <li>
                            <a href="enrollment_actions.php?action=drop&id=<?php echo $enrollmentID; ?>" class="dropdown-item text-warning">
                                <i class="bi bi-x-circle me-2"></i>Drop Enrollment
                            </a>
                        </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="enrollment_actions.php?action=delete&id=<?php echo $enrollmentID; ?>" 
                           class="dropdown-item text-danger"
                           data-confirm-delete="Are you sure you want to delete this enrollment? This will permanently delete all related data including quiz results and progress tracking.">
                            <i class="bi bi-trash me-2"></i>Delete Enrollment
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Enrollment Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Enrollment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Student Information</h6>
                                <div class="d-flex align-items-center mb-3">
                                    <?php if (!empty($enrollment['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($enrollment['avatar']); ?>" 
                                             class="rounded-circle me-3" 
                                             width="60" 
                                             height="60"
                                             alt="Avatar">
                                    <?php else: ?>
                                        <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <?php echo strtoupper(substr($enrollment['firstName'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></h5>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($enrollment['email']); ?></p>
                                        <?php if (!empty($enrollment['studentNumber'])): ?>
                                            <small class="text-muted">Student #: <?php echo htmlspecialchars($enrollment['studentNumber']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted d-block">Phone</small>
                                    <p class="mb-0"><?php echo !empty($enrollment['phone']) ? htmlspecialchars($enrollment['phone']) : 'N/A'; ?></p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Course Information</h6>
                                <h5 class="mb-2"><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars(substr($enrollment['courseDescription'], 0, 150)) . (strlen($enrollment['courseDescription']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Category</small>
                                        <p class="mb-0"><?php echo !empty($enrollment['category']) ? htmlspecialchars($enrollment['category']) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Price</small>
                                        <p class="mb-0 text-success">₱<?php echo number_format($enrollment['price'], 2); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <small class="text-muted d-block">Teacher</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($enrollment['teacherFirstName'] . ' ' . $enrollment['teacherLastName']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h2 class="text-primary mb-0"><?php echo number_format($enrollment['progressPercentage'], 1); ?>%</h2>
                                    <small class="text-muted">Overall Progress</small>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h2 class="text-success mb-0"><?php echo $completedLessons; ?>/<?php echo $totalLessons; ?></h2>
                                    <small class="text-muted">Lessons Completed</small>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h2 class="text-info mb-0"><?php echo $avgQuizScore; ?>%</h2>
                                    <small class="text-muted">Avg Quiz Score</small>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $enrollment['status'] === 'completed' ? 'success' : 
                                            ($enrollment['status'] === 'active' ? 'primary' : 
                                            ($enrollment['status'] === 'dropped' ? 'danger' : 'warning')); 
                                    ?> fs-5">
                                        <?php echo ucfirst($enrollment['status']); ?>
                                    </span>
                                    <small class="text-muted d-block">Status</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Timeline -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Progress Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <!-- Enrollment Date -->
                            <div class="timeline-item">
                                <div class="timeline-icon bg-primary">
                                    <i class="bi bi-calendar-check text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Enrolled in Course</h6>
                                    <p class="text-muted mb-0">
                                        <?php echo date('F d, Y h:i A', strtotime($enrollment['enrolledAt'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Payment -->
                            <?php if ($enrollment['paymentStatus'] === 'completed'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon bg-success">
                                        <i class="bi bi-credit-card text-white"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>Payment Completed</h6>
                                        <p class="text-muted mb-0">
                                            ₱<?php echo number_format($enrollment['paidAmount'], 2); ?> • 
                                            Ref: <?php echo htmlspecialchars($enrollment['transactionReference']); ?>
                                            <br>
                                            <?php echo date('F d, Y h:i A', strtotime($enrollment['paymentDate'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Lesson Completions -->
                            <?php foreach ($lessonCompletions as $index => $completion): ?>
                                <?php if ($index < 3): // Show only last 3 ?>
                                    <div class="timeline-item">
                                        <div class="timeline-icon bg-info">
                                            <i class="bi bi-check-circle text-white"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6>Completed: <?php echo htmlspecialchars($completion['lessonTitle']); ?></h6>
                                            <p class="text-muted mb-0">
                                                <?php echo date('F d, Y h:i A', strtotime($completion['completedAt'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Quiz Attempts -->
                            <?php foreach ($quizResults as $index => $quiz): ?>
                                <?php if ($index < 3): // Show only last 3 ?>
                                    <div class="timeline-item">
                                        <div class="timeline-icon bg-<?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'success' : 'warning'; ?>">
                                            <i class="bi bi-patch-check text-white"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6>Quiz: <?php echo htmlspecialchars($quiz['quizTitle']); ?></h6>
                                            <p class="text-muted mb-0">
                                                Score: <?php echo number_format($quiz['percentage'], 1); ?>% • 
                                                Status: <?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'Passed' : 'Failed'; ?>
                                                <br>
                                                <?php echo date('F d, Y h:i A', strtotime($quiz['submittedAt'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Course Completion -->
                            <?php if ($enrollment['status'] === 'completed' && $enrollment['completedAt']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon bg-success">
                                        <i class="bi bi-award text-white"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>Course Completed</h6>
                                        <p class="text-muted mb-0">
                                            <?php echo date('F d, Y h:i A', strtotime($enrollment['completedAt'])); ?>
                                        </p>
                                        <?php if ($enrollment['certificateUUID']): ?>
                                            <p class="mb-0">
                                                <a href="#" class="text-success">
                                                    <i class="bi bi-file-earmark-pdf me-1"></i>Certificate Issued
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (count($lessonCompletions) > 3 || count($quizResults) > 3): ?>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#fullTimeline">
                                    Show All Activities
                                </button>
                            </div>
                            
                            <div class="collapse" id="fullTimeline">
                                <!-- Additional lesson completions -->
                                <?php foreach ($lessonCompletions as $index => $completion): ?>
                                    <?php if ($index >= 3): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon bg-info">
                                                <i class="bi bi-check-circle text-white"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6>Completed: <?php echo htmlspecialchars($completion['lessonTitle']); ?></h6>
                                                <p class="text-muted mb-0">
                                                    <?php echo date('F d, Y h:i A', strtotime($completion['completedAt'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <!-- Additional quiz attempts -->
                                <?php foreach ($quizResults as $index => $quiz): ?>
                                    <?php if ($index >= 3): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon bg-<?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'success' : 'warning'; ?>">
                                                <i class="bi bi-patch-check text-white"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6>Quiz: <?php echo htmlspecialchars($quiz['quizTitle']); ?></h6>
                                                <p class="text-muted mb-0">
                                                    Score: <?php echo number_format($quiz['percentage'], 1); ?>% • 
                                                    Status: <?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'Passed' : 'Failed'; ?>
                                                    <br>
                                                    <?php echo date('F d, Y h:i A', strtotime($quiz['submittedAt'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Detailed Progress Stats -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Progress Details</h5>
                    </div>
                    <div class="card-body">
                        <!-- Lesson Progress -->
                        <div class="mb-4">
                            <h6>Lesson Progress</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Completed</span>
                                <span><?php echo $completedLessons; ?>/<?php echo $totalLessons; ?> (<?php echo $lessonProgress; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-info" 
                                     role="progressbar" 
                                     style="width: <?php echo $lessonProgress; ?>%"
                                     aria-valuenow="<?php echo $lessonProgress; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quiz Performance -->
                        <div class="mb-4">
                            <h6>Quiz Performance</h6>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h2 class="text-primary mb-1"><?php echo count($quizResults); ?></h2>
                                    <small class="text-muted">Attempts</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h2 class="text-success mb-1"><?php echo $passedQuizzes; ?></h2>
                                    <small class="text-muted">Passed</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overall Progress -->
                        <div>
                            <h6>Overall Course Progress</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Progress</span>
                                <span><?php echo number_format($enrollment['progressPercentage'], 1); ?>%</span>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar bg-<?php 
                                    echo $enrollment['progressPercentage'] >= 100 ? 'success' : 
                                        ($enrollment['progressPercentage'] >= 70 ? 'primary' : 
                                        ($enrollment['progressPercentage'] >= 50 ? 'info' : 'warning')); 
                                ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min($enrollment['progressPercentage'], 100); ?>%"
                                     aria-valuenow="<?php echo $enrollment['progressPercentage']; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($enrollment['status'] !== 'completed' && $enrollment['progressPercentage'] >= 100): ?>
                                <a href="enrollment_actions.php?action=complete&id=<?php echo $enrollmentID; ?>" class="btn btn-success">
                                    <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($enrollment['status'] === 'active'): ?>
                                <a href="enrollment_actions.php?action=drop&id=<?php echo $enrollmentID; ?>" class="btn btn-warning">
                                    <i class="bi bi-x-circle me-2"></i>Drop Enrollment
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($enrollment['status'] === 'dropped'): ?>
                                <a href="enrollment_actions.php?action=activate&id=<?php echo $enrollmentID; ?>" class="btn btn-primary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reactivate
                                </a>
                            <?php endif; ?>
                            
                            <a href="user_view.php?id=<?php echo $enrollment['userID']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-primary">
                                <i class="bi bi-person me-2"></i>View Student Profile
                            </a>
                            
                            <a href="course_view.php?id=<?php echo $enrollment['courseID']; ?>" class="btn btn-outline-success">
                                <i class="bi bi-book me-2"></i>View Course Details
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quiz Results Summary -->
                <?php if (!empty($quizResults)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Recent Quiz Results</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($quizResults as $index => $quiz): ?>
                                    <?php if ($index < 3): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($quiz['quizTitle']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo date('M d', strtotime($quiz['submittedAt'])); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'success' : 'danger'; ?>">
                                                        <?php echo number_format($quiz['percentage'], 1); ?>%
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        Pass: <?php echo $quiz['passingScore']; ?>%
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($quizResults) > 3): ?>
                                <a href="#" class="btn btn-sm btn-outline-info w-100 mt-3" data-bs-toggle="modal" data-bs-target="#quizResultsModal">
                                    View All Quiz Results
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quiz Results Modal -->
<?php if (!empty($quizResults)): ?>
    <div class="modal fade" id="quizResultsModal" tabindex="-1" aria-labelledby="quizResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quizResultsModalLabel">All Quiz Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Passing Score</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizResults as $quiz): ?>
                                    <tr>
                                        <td>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($quiz['quizTitle']); ?></h6>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'success' : 'danger'; ?> fs-6">
                                                <?php echo number_format($quiz['percentage'], 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ((float)$quiz['percentage'] >= $quiz['passingScore']): ?>
                                                <span class="badge bg-success">Passed</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $quiz['passingScore']; ?>%</td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($quiz['submittedAt'])); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($quiz['submittedAt'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-icon {
    position: absolute;
    left: -30px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}
.timeline-content {
    padding-left: 10px;
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