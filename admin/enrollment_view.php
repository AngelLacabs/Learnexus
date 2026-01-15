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
        FROM quizresults qr 
        JOIN quizzes q ON qr.quizID = q.quizID
        WHERE qr.enrollmentID = ?
        ORDER BY qr.submittedAt DESC
    ");
    $stmt->execute([$enrollmentID]);
    $quizResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lesson completions
    $stmt = $conn->prepare("
        SELECT lc.*, l.title as lessonTitle, l.uploadedAt
        FROM lessoncompletion lc 
        JOIN lessons l ON lc.lessonID = l.lessonID
        WHERE lc.userID = ? AND l.courseID = ?
        ORDER BY lc.completedAt DESC
    ");
    $stmt->execute([$enrollment['userID'], $enrollment['courseID']]);
    $lessoncompletion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all lessons in course
    $stmt = $conn->prepare("SELECT COUNT(*) as totalLessons FROM lessons WHERE courseID = ?");
    $stmt->execute([$enrollment['courseID']]);
    $totalLessons = $stmt->fetchColumn();
    
    // Get all quizzes in course
    $stmt = $conn->prepare("SELECT COUNT(*) as totalQuizzes FROM quizzes WHERE courseID = ?");
    $stmt->execute([$enrollment['courseID']]);
    $totalQuizzes = $stmt->fetchColumn();

    // Calculate progress metrics
    $completedLessons = count($lessoncompletion);
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

<div class="main-content pb-3 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-3">
    <div class="container-fluid">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Box 1: Page Header - Updated breadcrumb only -->
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <a href="enrollments.php" class="btn btn-outline-secondary me-3" id="backButton">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="h3 mb-0">Enrollment Details</h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php" class="fw-bold text-primary">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="enrollments.php" class="fw-bold text-primary">Enrollments</a></li>
                                    <li class="breadcrumb-item active text-dark" aria-current="page"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <!-- Actions button stays in its original position -->
                    <div class="btn-group">
                        <button type="button" class="btn text-white border-0 dropdown-toggle" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="bi bi-gear me-2"></i>Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="enrollment_edit.php?id=<?php echo $enrollmentID; ?>" class="dropdown-item">
                                    <i class="bi bi-pencil me-2"></i>Edit Enrollment
                                </a>
                            </li>
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
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-8">
                <!-- Box 2: Enrollment Information -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="mb-0">Enrollment Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <h6 class="mb-3">Student Information</h6>
                                <div class="d-flex align-items-center mb-3">
                                    <?php if (!empty($enrollment['avatar']) && file_exists($enrollment['avatar'])): ?>
                                        <div class="avatar-lg me-3" style="overflow: hidden;">
                                            <img src="<?php echo htmlspecialchars($enrollment['avatar']); ?>" 
                                                 class="w-100 h-100 rounded-circle object-fit-cover"
                                                 alt="Avatar">
                                        </div>
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
                            
                            <div class="col-md-6 mb-4">
                                <h6 class="mb-3">Course Information</h6>
                                <h5 class="mb-2"><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h5>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($enrollment['courseDescription'], 0, 150)) . (strlen($enrollment['courseDescription']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Category</small>
                                        <p class="mb-0"><?php echo !empty($enrollment['category']) ? htmlspecialchars($enrollment['category']) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Price</small>
                                        <p class="mb-0 text-success">₱<?php echo number_format($enrollment['price'], 2); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block">Teacher</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($enrollment['teacherFirstName'] . ' ' . $enrollment['teacherLastName']); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($enrollment['teacherEmail']); ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-center p-3 rounded-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <h2 class="text-white mb-0"><?php echo number_format($enrollment['progressPercentage'], 1); ?>%</h2>
                                    <small class="text-white-50">Overall Progress</small>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-center p-3 rounded-3" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <h2 class="text-white mb-0"><?php echo $completedLessons; ?>/<?php echo $totalLessons; ?></h2>
                                    <small class="text-white-50">Lessons Completed</small>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-center p-3 rounded-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <h2 class="text-white mb-0"><?php echo $avgQuizScore; ?>%</h2>
                                    <small class="text-white-50">Avg Quiz Score</small>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="text-center p-3 rounded-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <span class="badge text-dark fs-5 px-3 py-2">
                                        <?php echo ucfirst($enrollment['status']); ?>
                                    </span>
                                    <small class="text-white-50 d-block mt-1">Status</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Box 3: Progress Timeline -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="mb-0">Progress Timeline</h5>
                    </div>
                    <div class="card-body p-4">
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
                            <?php foreach ($lessoncompletion as $index => $completion): ?>
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
                        
                        <?php if (count($lessoncompletion) > 3 || count($quizResults) > 3): ?>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm text-white border-0" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#fullTimeline"
                                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    Show All Activities
                                </button>
                            </div>
                            
                            <div class="collapse" id="fullTimeline">
                                <!-- Additional lesson completions -->
                                <?php foreach ($lessoncompletion as $index => $completion): ?>
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
                <!-- Box 4: Progress Details -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="mb-0">Progress Details</h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- Lesson Progress -->
                        <div class="mb-4">
                            <h6>Lesson Progress</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Completed</span>
                                <span><?php echo $completedLessons; ?>/<?php echo $totalLessons; ?> (<?php echo $lessonProgress; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-info rounded" 
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
                                    <div class="p-3 rounded-3" style="background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);">
                                        <h2 class="text-primary mb-1"><?php echo count($quizResults); ?></h2>
                                        <small class="text-muted">Attempts</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="p-3 rounded-3" style="background: linear-gradient(135deg, #11998e20 0%, #38ef7d20 100%);">
                                        <h2 class="text-success mb-1"><?php echo $passedQuizzes; ?></h2>
                                        <small class="text-muted">Passed</small>
                                    </div>
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
                                <div class="progress-bar rounded" 
                                     role="progressbar" 
                                     style="width: <?php echo min($enrollment['progressPercentage'], 100); ?>%;
                                            background: linear-gradient(135deg, 
                                            <?php 
                                                if ($enrollment['progressPercentage'] >= 100) echo '#11998e 0%, #38ef7d 100%';
                                                elseif ($enrollment['progressPercentage'] >= 70) echo '#667eea 0%, #764ba2 100%';
                                                elseif ($enrollment['progressPercentage'] >= 50) echo '#4facfe 0%, #00f2fe 100%';
                                                else echo '#f093fb 0%, #f5576c 100%';
                                            ?>);"
                                     aria-valuenow="<?php echo $enrollment['progressPercentage']; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Box 5: Recent Quiz Results -->
                <?php if (!empty($quizResults)): ?>
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 px-4">
                            <h5 class="mb-0">Recent Quiz Results</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="list-group list-group-flush">
                                <?php foreach ($quizResults as $index => $quiz): ?>
                                    <?php if ($index < 3): ?>
                                        <div class="list-group-item px-0 border-0 mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($quiz['quizTitle']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo date('M d', strtotime($quiz['submittedAt'])); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'success' : 'danger'; ?> px-3 py-2">
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
                                <a href="#" class="btn btn-sm text-white border-0 w-100 mt-3" 
                                   data-bs-toggle="modal" 
                                   data-bs-target="#quizResultsModal"
                                   style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
                                            <span class="badge bg-<?php echo (float)$quiz['percentage'] >= $quiz['passingScore'] ? 'success' : 'danger'; ?> fs-6 px-3 py-2">
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

/* ORIGINAL STYLES FROM YOUR FILE - KEEP THESE EXACTLY THE SAME */
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.timeline-content {
    padding-left: 10px;
}
.avatar-lg {
    width: 70px;
    height: 70px;
}
.avatar-sm {
    width: 40px;
    height: 40px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Confirm delete action
    const deleteLinks = document.querySelectorAll('[data-confirm-delete]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm-delete');
            const url = this.getAttribute('href');
            
            if (confirm(message)) {
                window.location.href = url;
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>