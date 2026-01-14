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

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Payment Management</h1>
                <p class="text-muted mb-0">Manage all payment transactions and verify payments</p>
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
                                <h6 class="text-muted mb-1">Total Payments</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                            </div>
                            <div class="text-primary">
                                <i class="bi bi-cash-stack fs-2"></i>
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
                                <h6 class="text-muted mb-1">Total Revenue</h6>
                                <h3 class="mb-0">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="text-success">
                                <i class="bi bi-currency-dollar fs-2"></i>
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
                                <h6 class="text-muted mb-1">Completed</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['completed']); ?></h3>
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
                                <h6 class="text-muted mb-1">Failed</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['failed']); ?></h3>
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
                                <h6 class="text-muted mb-1">Refunded</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['refunded']); ?></h3>
                            </div>
                            <div class="text-secondary">
                                <span class="badge bg-secondary">●</span>
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
                    <div class="col-md-2">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course">
                            <option value="0">Select Course</option>
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
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary w-100">
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

        <!-- Payments Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">Payment Transactions</h5>
            </div>
            <div class="card-body">
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
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($payment['firstName'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']); ?></h6>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($payment['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($payment['courseTitle']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $payment['courseID']; ?></small>
                                            <br><small class="text-muted">Price: ₱<?php echo number_format($payment['price'], 2); ?></small>
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
                                            <div class="btn-group btn-group-sm">
                                                <a href="payment_view.php?id=<?php echo $payment['paymentID']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown" 
                                                        aria-expanded="false">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($payment['status'] !== 'completed'): ?>
                                                        <li>
                                                            <a href="payment_actions.php?action=complete&id=<?php echo $payment['paymentID']; ?>" 
                                                               class="dropdown-item text-success">
                                                                <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($payment['status'] !== 'failed'): ?>
                                                        <li>
                                                            <a href="payment_actions.php?action=fail&id=<?php echo $payment['paymentID']; ?>" 
                                                               class="dropdown-item text-danger">
                                                                <i class="bi bi-x-circle me-2"></i>Mark as Failed
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($payment['status'] !== 'refunded'): ?>
                                                        <li>
                                                            <a href="payment_actions.php?action=refund&id=<?php echo $payment['paymentID']; ?>" 
                                                               class="dropdown-item text-secondary">
                                                                <i class="bi bi-arrow-counterclockwise me-2"></i>Mark as Refunded
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($payment['status'] !== 'pending'): ?>
                                                        <li>
                                                            <a href="payment_actions.php?action=pending&id=<?php echo $payment['paymentID']; ?>" 
                                                               class="dropdown-item text-warning">
                                                                <i class="bi bi-clock me-2"></i>Mark as Pending
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a href="payment_actions.php?action=delete&id=<?php echo $payment['paymentID']; ?>" 
                                                           class="dropdown-item text-danger"
                                                           data-confirm-delete="Are you sure you want to delete this payment record? This action cannot be undone.">
                                                            <i class="bi bi-trash me-2"></i>Delete Payment
                                                        </a>
                                                    </li>
                                                </ul>
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
                                        <a class="page-link" href="?<?php 
                                            $query = $_GET;
                                            $query['page'] = $page - 1;
                                            echo http_build_query($query); 
                                        ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php 
                                                $query = $_GET;
                                                $query['page'] = $i;
                                                echo http_build_query($query); 
                                            ?>">
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
                                        <a class="page-link" href="?<?php 
                                            $query = $_GET;
                                            $query['page'] = $page + 1;
                                            echo http_build_query($query); 
                                        ?>">
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

    // Confirm delete for payment deletion
    const deleteLinks = document.querySelectorAll('[data-confirm-delete]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm-delete'))) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>