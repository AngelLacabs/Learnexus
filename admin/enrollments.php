<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Enrollment Management - Learnexus";

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$student = isset($_GET['student']) ? (int)$_GET['student'] : 0;

// Build query
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(u.firstName LIKE ? OR u.lastName LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($course > 0) {
    $whereClauses[] = "e.courseID = ?";
    $params[] = $course;
}

if (!empty($status)) {
    $whereClauses[] = "e.status = ?";
    $params[] = $status;
}

if ($student > 0) {
    $whereClauses[] = "e.userID = ?";
    $params[] = $student;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count for pagination
$countSQL = "SELECT COUNT(DISTINCT e.enrollmentID) 
            FROM enrollments e 
            JOIN users u ON e.userID = u.userID 
            JOIN courses c ON e.courseID = c.courseID 
            $whereSQL";
$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$totalEnrollments = $countStmt->fetchColumn();
$totalPages = ceil($totalEnrollments / $limit);

// Get enrollments with pagination
$sql = "SELECT 
            e.*,
            u.userID,
            u.firstName,
            u.lastName,
            u.email,
            u.phone,
            c.courseID,
            c.title as courseTitle,
            c.price,
            c.teacherID,
            ct.firstName as teacherFirstName,
            ct.lastName as teacherLastName,
            COUNT(DISTINCT lc.id) as completedLessons,
            COUNT(DISTINCT l.lessonID) as totalLessons,
            COALESCE(SUM(p.amount), 0) as paidAmount
        FROM enrollments e 
        JOIN users u ON e.userID = u.userID 
        JOIN courses c ON e.courseID = c.courseID 
        JOIN users ct ON c.teacherID = ct.userID
        LEFT JOIN lessons l ON c.courseID = l.courseID
        LEFT JOIN lessoncompletion lc ON l.lessonID = lc.lessonID AND lc.userID = e.userID
        LEFT JOIN payments p ON e.paymentID = p.paymentID AND p.status = 'completed'
        $whereSQL 
        GROUP BY e.enrollmentID 
        ORDER BY e.enrolledAt DESC";
$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(status = 'active') as active,
        SUM(status = 'completed') as completed,
        SUM(status = 'dropped') as dropped,
        SUM(status = 'pending') as pending,
        AVG(progressPercentage) as avgProgress,
        COUNT(DISTINCT userID) as uniqueStudents,
        COUNT(DISTINCT courseID) as uniqueCourses
    FROM enrollments
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get all courses for filter
$courseStmt = $conn->query("SELECT courseID, title FROM courses WHERE status = 'published' ORDER BY title");
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students for filter
$studentStmt = $conn->query("SELECT userID, firstName, lastName, email FROM users WHERE role = 'student' ORDER BY firstName, lastName");
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Enrollment Management</h1>
                <p class="text-muted mb-0">Manage all course enrollments and student progress</p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Enrollments</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                            </div>
                            <div class="text-primary">
                                <i class="bi bi-journal-check fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active Students</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['uniqueStudents']); ?></h3>
                            </div>
                            <div class="text-success">
                                <i class="bi bi-people-fill fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Avg. Progress</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['avgProgress'], 1); ?>%</h3>
                            </div>
                            <div class="text-info">
                                <i class="bi bi-graph-up fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Completed</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['completed']); ?></h3>
                            </div>
                            <div class="text-warning">
                                <i class="bi bi-award fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Breakdown -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['active']); ?></h3>
                            </div>
                            <div class="text-success">
                                <span class="badge bg-success">●</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Dropped</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['dropped']); ?></h3>
                            </div>
                            <div class="text-danger">
                                <span class="badge bg-danger">●</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Pending</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['pending']); ?></h3>
                            </div>
                            <div class="text-warning">
                                <span class="badge bg-warning">●</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Unique Courses</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['uniqueCourses']); ?></h3>
                            </div>
                            <div class="text-info">
                                <i class="bi bi-book-fill fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Student, Email, or Course"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course">
                            <option value="0">All Courses</option>
                            <?php foreach ($courses as $courseData): ?>
                                <option value="<?php echo $courseData['courseID']; ?>" 
                                    <?php echo $course == $courseData['courseID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($courseData['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="dropped" <?php echo $status === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Student</label>
                        <select class="form-select" name="student">
                            <option value="0">All Students</option>
                            <?php foreach ($students as $studentData): ?>
                                <option value="<?php echo $studentData['userID']; ?>" 
                                    <?php echo $student == $studentData['userID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($studentData['firstName'] . ' ' . $studentData['lastName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel me-2"></i>Filter
                            </button>
                            <a href="enrollments.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enrollments Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">Enrollments List</h5>
            </div>
            <div class="card-body">
                <?php if (empty($enrollments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-check fs-1 text-muted"></i>
                        <h5 class="mt-3">No enrollments found</h5>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Teacher</th>
                                    <th>Payment</th>
                                    <th>Enrollment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enrollment): 
                                    $progress = (float)$enrollment['progressPercentage'];
                                    $completedLessons = (int)$enrollment['completedLessons'];
                                    $totalLessons = (int)$enrollment['totalLessons'];
                                    $lessonProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($enrollment['firstName'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></h6>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                                    <?php if (!empty($enrollment['phone'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($enrollment['phone']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($enrollment['courseTitle']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $enrollment['courseID']; ?></small>
                                            <br><small class="text-success">₱<?php echo number_format($enrollment['price'], 2); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3" style="min-width: 100px;">
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-<?php 
                                                            echo $progress >= 100 ? 'success' : 
                                                                ($progress >= 50 ? 'info' : 
                                                                ($progress > 0 ? 'warning' : 'secondary')); 
                                                        ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($progress, 100); ?>%"
                                                             aria-valuenow="<?php echo $progress; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="fw-bold"><?php echo number_format($progress, 1); ?>%</span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $completedLessons; ?>/<?php echo $totalLessons; ?> lessons
                                                        (<?php echo $lessonProgress; ?>%)
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $enrollment['status'] === 'completed' ? 'success' : 
                                                    ($enrollment['status'] === 'active' ? 'primary' : 
                                                    ($enrollment['status'] === 'dropped' ? 'danger' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($enrollment['status']); ?>
                                            </span>
                                            <?php if ($enrollment['completedAt']): ?>
                                                <br><small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($enrollment['completedAt'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($enrollment['teacherFirstName'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($enrollment['teacherFirstName'] . ' ' . $enrollment['teacherLastName']); ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($enrollment['paidAmount'] > 0): ?>
                                                <span class="text-success fw-bold">₱<?php echo number_format($enrollment['paidAmount'], 2); ?></span>
                                                <br><small class="text-muted">Paid</small>
                                            <?php else: ?>
                                                <span class="text-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($enrollment['enrolledAt'])); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($enrollment['enrolledAt'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="enrollment_view.php?id=<?php echo $enrollment['enrollmentID']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="enrollment_edit.php?id=<?php echo $enrollment['enrollmentID']; ?>" 
                                                   class="btn btn-outline-secondary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="enrollment_actions.php?action=delete&id=<?php echo $enrollment['enrollmentID']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   data-confirm-delete="Are you sure you want to delete this enrollment? This will permanently delete all related data including quiz results and progress tracking."
                                                   data-bs-toggle="tooltip" 
                                                   title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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