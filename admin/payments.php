<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Payment Management - Learnexus";

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$student = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$transaction_ref = isset($_GET['transaction_ref']) ? trim($_GET['transaction_ref']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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
    $whereClauses[] = "p.courseID = ?";
    $params[] = $course;
}

if (!empty($status)) {
    $whereClauses[] = "p.status = ?";
    $params[] = $status;
}

if ($student > 0) {
    $whereClauses[] = "p.userID = ?";
    $params[] = $student;
}

if (!empty($transaction_ref)) {
    $whereClauses[] = "p.transactionReference LIKE ?";
    $params[] = "%$transaction_ref%";
}

if (!empty($date_from)) {
    $whereClauses[] = "DATE(p.paymentDate) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereClauses[] = "DATE(p.paymentDate) <= ?";
    $params[] = $date_to;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count for pagination
$countSQL = "SELECT COUNT(*) 
            FROM payments p 
            JOIN users u ON p.userID = u.userID 
            JOIN courses c ON p.courseID = c.courseID 
            $whereSQL";
$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$totalPayments = $countStmt->fetchColumn();
$totalPages = ceil($totalPayments / $limit);

// Get payments with pagination
$sql = "SELECT 
            p.*,
            u.userID,
            u.firstName,
            u.lastName,
            u.email,
            u.phone,
            c.courseID,
            c.title as courseTitle,
            c.price,
            e.enrollmentID,
            e.status as enrollmentStatus
        FROM payments p 
        JOIN users u ON p.userID = u.userID 
        JOIN courses c ON p.courseID = c.courseID 
        LEFT JOIN enrollments e ON p.enrollmentID = e.enrollmentID
        $whereSQL 
        ORDER BY p.createdAt DESC";
$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(status = 'completed') as completed,
        SUM(status = 'pending') as pending,
        SUM(status = 'failed') as failed,
        SUM(status = 'refunded') as refunded,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
        AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_amount,
        COUNT(DISTINCT userID) as unique_payers,
        COUNT(DISTINCT courseID) as unique_courses
    FROM payments
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

        <!-- Box 1: Page Header -->
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Payment Management</h1>
                        <p class="text-muted mb-0">Manage all payment transactions and verify payments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 2: Statistics Cards - Separate Boxes -->
        <div class="row g-4 mb-5">
            <!-- Total Payments -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Total Payments</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['total']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-cash-stack" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Revenue -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Total Revenue</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-currency-dollar" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Completed Payments -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Completed</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['completed']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-check-circle" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Unique Payers -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Unique Payers</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['unique_payers']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-people-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 3: Filters Card -->
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-body p-4">
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
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
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

                    <div class="col-md-3">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" 
                               class="form-control" 
                               name="transaction_ref" 
                               placeholder="Transaction ID"
                               value="<?php echo htmlspecialchars($transaction_ref); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" 
                               class="form-control" 
                               name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" 
                               class="form-control" 
                               name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn w-100 text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-funnel me-2"></i>Filter
                            </button>
                            <a href="payments.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Box 4: Payments Table -->
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="mb-0">Payment Transactions</h5>
            </div>
            <div class="card-body px-4">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cash-stack fs-1 text-muted"></i>
                        <h5 class="mt-3">No payments found</h5>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction</th>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                    <th>Enrollment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($payment['transactionReference']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $payment['paymentID']; ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                    style="width: 40px; height: 40px;">
                                                    <?php echo strtoupper(substr($payment['firstName'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']); ?></h6>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($payment['email']); ?></small>
                                                    <?php if (!empty($payment['phone'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($payment['phone']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($payment['courseTitle']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $payment['courseID']; ?></small>
                                            <br><small class="text-success">₱<?php echo number_format($payment['price'], 2); ?></small>
                                        </td>
                                        <td>
                                            <h5 class="mb-0 text-success">₱<?php echo number_format($payment['amount'], 2); ?></h5>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $payment['status'] === 'completed' ? 'success' : 
                                                    ($payment['status'] === 'pending' ? 'warning' : 
                                                    ($payment['status'] === 'failed' ? 'danger' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['paymentDate']): ?>
                                                <small><?php echo date('M d, Y', strtotime($payment['paymentDate'])); ?></small>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($payment['paymentDate'])); ?></small>
                                            <?php else: ?>
                                                <span class="text-warning">Not paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['enrollmentID']): ?>
                                                <span class="badge bg-<?php 
                                                    echo $payment['enrollmentStatus'] === 'completed' ? 'success' : 
                                                        ($payment['enrollmentStatus'] === 'active' ? 'primary' : 
                                                        ($payment['enrollmentStatus'] === 'dropped' ? 'danger' : 'warning')); 
                                                ?>">
                                                    <?php echo ucfirst($payment['enrollmentStatus']); ?>
                                                </span>
                                                <br><small class="text-muted">ID: <?php echo $payment['enrollmentID']; ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">No enrollment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="payment_view.php?id=<?php echo $payment['paymentID']; ?>" 
                                               class="btn btn-outline-primary btn-sm" 
                                               data-bs-toggle="tooltip" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
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