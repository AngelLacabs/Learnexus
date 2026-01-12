<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: payments.php');
    exit();
}

$paymentID = (int)$_GET['id'];

try {
    // Get payment details with all related information
    $stmt = $conn->prepare("
        SELECT 
            p.*,
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
            c.price as coursePrice,
            c.category,
            c.teacherID,
            ct.firstName as teacherFirstName,
            ct.lastName as teacherLastName,
            ct.email as teacherEmail,
            e.enrollmentID,
            e.progressPercentage,
            e.status as enrollmentStatus,
            e.enrolledAt,
            e.completedAt
        FROM payments p 
        JOIN users u ON p.userID = u.userID 
        JOIN courses c ON p.courseID = c.courseID 
        JOIN users ct ON c.teacherID = ct.userID
        LEFT JOIN enrollments e ON p.enrollmentID = e.enrollmentID
        WHERE p.paymentID = ?
    ");
    $stmt->execute([$paymentID]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $_SESSION['error'] = 'Payment not found';
        header('Location: payments.php');
        exit();
    }
    
    // Get related payment history for the same user and course
    $stmt = $conn->prepare("
        SELECT *
        FROM payments 
        WHERE userID = ? AND courseID = ? AND paymentID != ?
        ORDER BY createdAt DESC
    ");
    $stmt->execute([$payment['userID'], $payment['courseID'], $paymentID]);
    $paymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Payment View Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading payment details';
    header('Location: payments.php');
    exit();
}

$page_title = "Payment Details - " . $payment['transactionReference'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="payments.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">Payment Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="payments.php">Payments</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($payment['transactionReference']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear me-2"></i>Actions
                </button>
                <ul class="dropdown-menu">
                    <?php if ($payment['status'] !== 'completed'): ?>
                        <li>
                            <a href="payment_actions.php?action=complete&id=<?php echo $paymentID; ?>" 
                               class="dropdown-item text-success">
                                <i class="bi bi-check-circle me-2"></i>Mark as Completed
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($payment['status'] !== 'failed'): ?>
                        <li>
                            <a href="payment_actions.php?action=fail&id=<?php echo $paymentID; ?>" 
                               class="dropdown-item text-danger">
                                <i class="bi bi-x-circle me-2"></i>Mark as Failed
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($payment['status'] !== 'refunded'): ?>
                        <li>
                            <a href="payment_actions.php?action=refund&id=<?php echo $paymentID; ?>" 
                               class="dropdown-item text-secondary">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Mark as Refunded
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($payment['status'] !== 'pending'): ?>
                        <li>
                            <a href="payment_actions.php?action=pending&id=<?php echo $paymentID; ?>" 
                               class="dropdown-item text-warning">
                                <i class="bi bi-clock me-2"></i>Mark as Pending
                            </a>
                        </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="payment_actions.php?action=delete&id=<?php echo $paymentID; ?>" 
                           class="dropdown-item text-danger"
                           data-confirm-delete="Are you sure you want to delete this payment record? This action cannot be undone.">
                            <i class="bi bi-trash me-2"></i>Delete Payment
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Payment Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Transaction Details</h6>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Transaction ID</small>
                                    <h4 class="mb-0"><?php echo htmlspecialchars($payment['transactionReference']); ?></h4>
                                    <small class="text-muted">Payment ID: <?php echo $payment['paymentID']; ?></small>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Status</small>
                                        <span class="badge bg-<?php 
                                            echo $payment['status'] === 'completed' ? 'success' : 
                                                ($payment['status'] === 'pending' ? 'warning' : 
                                                ($payment['status'] === 'failed' ? 'danger' : 'secondary')); 
                                        ?> fs-5">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Amount</small>
                                        <h3 class="text-success mb-0">₱<?php echo number_format($payment['amount'], 2); ?></h3>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted d-block">Payment Date</small>
                                    <p class="mb-0">
                                        <?php if ($payment['paymentDate']): ?>
                                            <?php echo date('F d, Y h:i A', strtotime($payment['paymentDate'])); ?>
                                        <?php else: ?>
                                            <span class="text-warning">Not paid yet</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted d-block">Created</small>
                                    <p class="mb-0"><?php echo date('F d, Y h:i A', strtotime($payment['createdAt'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Course Information</h6>
                                <h5 class="mb-2"><?php echo htmlspecialchars($payment['courseTitle']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars(substr($payment['courseDescription'], 0, 150)) . (strlen($payment['courseDescription']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Course Price</small>
                                        <p class="mb-0">₱<?php echo number_format($payment['coursePrice'], 2); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Category</small>
                                        <p class="mb-0"><?php echo !empty($payment['category']) ? htmlspecialchars($payment['category']) : 'N/A'; ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted d-block">Teacher</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($payment['teacherFirstName'] . ' ' . $payment['teacherLastName']); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['teacherEmail']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student Information -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <?php if (!empty($payment['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($payment['avatar']); ?>" 
                                     class="rounded-circle me-3" 
                                     width="80" 
                                     height="80"
                                     alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <?php echo strtoupper(substr($payment['firstName'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="mb-0"><?php echo htmlspecialchars($payment['firstName'] . ' ' . $payment['lastName']); ?></h4>
                                <p class="text-muted mb-1"><?php echo htmlspecialchars($payment['email']); ?></p>
                                <?php if (!empty($payment['studentNumber'])): ?>
                                    <small class="text-muted">Student #: <?php echo htmlspecialchars($payment['studentNumber']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Phone Number</small>
                                    <p class="mb-0"><?php echo !empty($payment['phone']) ? htmlspecialchars($payment['phone']) : 'N/A'; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">User ID</small>
                                    <p class="mb-0"><?php echo $payment['userID']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($payment['enrollmentID']): ?>
                            <hr class="my-4">
                            <h6>Enrollment Status</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Enrollment Status</small>
                                    <span class="badge bg-<?php 
                                        echo $payment['enrollmentStatus'] === 'completed' ? 'success' : 
                                            ($payment['enrollmentStatus'] === 'active' ? 'primary' : 
                                            ($payment['enrollmentStatus'] === 'dropped' ? 'danger' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst($payment['enrollmentStatus']); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Progress</small>
                                    <p class="mb-0"><?php echo number_format($payment['progressPercentage'], 1); ?>%</p>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Enrolled On</small>
                                    <p class="mb-0"><?php echo date('F d, Y', strtotime($payment['enrolledAt'])); ?></p>
                                </div>
                                <?php if ($payment['completedAt']): ?>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">Completed On</small>
                                        <p class="mb-0"><?php echo date('F d, Y', strtotime($payment['completedAt'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($payment['status'] !== 'completed'): ?>
                                <a href="payment_actions.php?action=complete&id=<?php echo $paymentID; ?>" 
                                   class="btn btn-success">
                                    <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($payment['status'] !== 'failed'): ?>
                                <a href="payment_actions.php?action=fail&id=<?php echo $paymentID; ?>" 
                                   class="btn btn-danger">
                                    <i class="bi bi-x-circle me-2"></i>Mark as Failed
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($payment['status'] !== 'refunded'): ?>
                                <a href="payment_actions.php?action=refund&id=<?php echo $paymentID; ?>" 
                                   class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Mark as Refunded
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($payment['status'] !== 'pending'): ?>
                                <a href="payment_actions.php?action=pending&id=<?php echo $paymentID; ?>" 
                                   class="btn btn-warning">
                                    <i class="bi bi-clock me-2"></i>Mark as Pending
                                </a>
                            <?php endif; ?>
                            
                            <a href="user_view.php?id=<?php echo $payment['userID']; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-person me-2"></i>View Student Profile
                            </a>
                            
                            <a href="course_view.php?id=<?php echo $payment['courseID']; ?>" class="btn btn-outline-success">
                                <i class="bi bi-book me-2"></i>View Course Details
                            </a>
                            
                            <?php if ($payment['enrollmentID']): ?>
                                <a href="enrollment_view.php?id=<?php echo $payment['enrollmentID']; ?>" class="btn btn-outline-info">
                                    <i class="bi bi-journal-check me-2"></i>View Enrollment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Status History -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Status History</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <!-- Payment Created -->
                            <div class="timeline-item">
                                <div class="timeline-icon bg-primary">
                                    <i class="bi bi-plus-circle text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Payment Created</h6>
                                    <p class="text-muted mb-0">
                                        <?php echo date('F d, Y h:i A', strtotime($payment['createdAt'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Payment Date -->
                            <?php if ($payment['paymentDate']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon bg-success">
                                        <i class="bi bi-calendar-check text-white"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>Payment Recorded</h6>
                                        <p class="text-muted mb-0">
                                            <?php echo date('F d, Y h:i A', strtotime($payment['paymentDate'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Current Status -->
                            <div class="timeline-item">
                                <div class="timeline-icon bg-<?php 
                                    echo $payment['status'] === 'completed' ? 'success' : 
                                        ($payment['status'] === 'pending' ? 'warning' : 
                                        ($payment['status'] === 'failed' ? 'danger' : 'secondary')); 
                                ?>">
                                    <i class="bi bi-check-circle text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6>Current Status</h6>
                                    <p class="text-muted mb-0">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Related Payments -->
                <?php if (!empty($paymentHistory)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Payment History for this Course</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($paymentHistory as $history): ?>
                                    <a href="payment_view.php?id=<?php echo $history['paymentID']; ?>" 
                                       class="list-group-item list-group-item-action px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($history['transactionReference']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($history['createdAt'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php 
                                                    echo $history['status'] === 'completed' ? 'success' : 
                                                        ($history['status'] === 'pending' ? 'warning' : 
                                                        ($history['status'] === 'failed' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($history['status']); ?>
                                                </span>
                                                <br>
                                                <small class="text-success">
                                                    ₱<?php echo number_format($history['amount'], 2); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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