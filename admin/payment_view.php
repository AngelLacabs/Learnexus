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

<div class="main-content pb-3 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-3">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="bg-white rounded-3 shadow-sm p-3 w-100">
                <div class="d-flex align-items-center">
                    <a href="payments.php" class="btn btn-outline-secondary me-3" id="backButton">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div class="flex-grow-1">
                        <h1 class="h3 mb-0">Payment Details</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" class="fw-bold text-primary">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="payments.php" class="fw-bold text-primary">Payments</a></li>
                                <li class="breadcrumb-item active text-dark" aria-current="page"><?php echo htmlspecialchars($payment['transactionReference']); ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <!-- Payment Information -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 1rem 1rem 0 0;">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="bi bi-cash-stack me-2"></i>Payment Information
                        </h5>
                    </div>
                    <div class="card-body px-4">
                        <h4 class="mb-3">Transaction: <?php echo htmlspecialchars($payment['transactionReference']); ?></h4>
                        
                        <div class="mb-4">
                            <span class="badge bg-<?php 
                                echo $payment['status'] === 'completed' ? 'success' : 
                                    ($payment['status'] === 'pending' ? 'warning' : 
                                    ($payment['status'] === 'failed' ? 'danger' : 'secondary')); 
                            ?> fs-6 me-2">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                            <span class="badge bg-success fs-6">
                                ₱<?php echo number_format($payment['amount'], 2); ?>
                            </span>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Payment ID</small>
                                    <p class="mb-0"><?php echo $payment['paymentID']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Payment Date</small>
                                    <p class="mb-0">
                                        <?php if ($payment['paymentDate']): ?>
                                            <?php echo date('F d, Y', strtotime($payment['paymentDate'])); ?>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($payment['paymentDate'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-warning">Not paid yet</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Created</small>
                            <p class="mb-0">
                                <?php echo date('F d, Y', strtotime($payment['createdAt'])); ?>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($payment['createdAt'])); ?></small>
                            </p>
                        </div>
                        
                        <!-- Actions button -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="btn-group">
                                <button type="button" class="btn text-white border-0 dropdown-toggle" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false"
                                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="bi bi-gear me-2"></i>Actions
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($payment['status'] !== 'refunded'): ?>
                                        <li>
                                            <a href="payment_actions.php?action=refund&id=<?php echo $paymentID; ?>" 
                                               class="dropdown-item text-primary">
                                                <i class="bi bi-arrow-counterclockwise me-2"></i>Mark as Refunded
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
                    </div>
                </div>
                
                <!-- Course Information -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 1rem 1rem 0 0;">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="bi bi-book me-2"></i>Course Information
                        </h5>
                    </div>
                    <div class="card-body px-4">
                        <h5 class="mb-3"><?php echo htmlspecialchars($payment['courseTitle']); ?></h5>
                        
                        <?php if (!empty($payment['courseDescription'])): ?>
                            <div class="mb-4">
                                <small class="text-muted">Description</small>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($payment['courseDescription'], 0, 200)) . (strlen($payment['courseDescription']) > 200 ? '...' : '')); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Course ID</small>
                                    <p class="mb-0"><?php echo $payment['courseID']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Course Price</small>
                                    <p class="mb-0">₱<?php echo number_format($payment['coursePrice'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($payment['category'])): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block">Category</small>
                                <p class="mb-0"><?php echo htmlspecialchars($payment['category']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Teacher</small>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                    <?php echo strtoupper(substr($payment['teacherFirstName'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="mb-0"><?php echo htmlspecialchars($payment['teacherFirstName'] . ' ' . $payment['teacherLastName']); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['teacherEmail']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student Information -->
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 1rem 1rem 0 0;">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="bi bi-person-circle me-2"></i>Student Information
                        </h5>
                    </div>
                    <div class="card-body px-4">
                        <div class="d-flex align-items-center mb-4">
                            <?php if (!empty($payment['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($payment['avatar']); ?>" 
                                     class="rounded-circle me-3 object-fit-cover" 
                                     width="80" 
                                     height="80"
                                     alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="overflow: hidden; width: 80px; height: 80px;">
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
                        
                        <div class="mt-4">
                            <a href="user_view.php?id=<?php echo $payment['userID']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-sm btn-outline-primary me-2">
                                <i class="bi bi-person me-2"></i>View Student Profile
                            </a>
                            <?php if ($payment['enrollmentID']): ?>
                                <a href="enrollment_view.php?id=<?php echo $payment['enrollmentID']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-journal-check me-2"></i>View Enrollment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Enrollment Status -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 1rem 1rem 0 0;">
                        <h5 class="mb-0 text-white d-flex align-items-center">
                            <i class="bi bi-journal-check me-2"></i>Enrollment Status
                        </h5>
                    </div>
                    <div class="card-body px-4">
                        <?php if ($payment['enrollmentID']): ?>
                            <div class="text-center mb-4">
                                <span class="badge bg-<?php 
                                    echo $payment['enrollmentStatus'] === 'completed' ? 'success' : 
                                        ($payment['enrollmentStatus'] === 'active' ? 'primary' : 
                                        ($payment['enrollmentStatus'] === 'dropped' ? 'danger' : 'warning')); 
                                ?> fs-5 p-2 mb-3 d-inline-block">
                                    <?php echo ucfirst($payment['enrollmentStatus']); ?>
                                </span>
                                <h2 class="text-primary mb-2"><?php echo number_format($payment['progressPercentage'], 1); ?>%</h2>
                                <small class="text-muted">Progress</small>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h5 class="mb-1"><?php echo date('M d, Y', strtotime($payment['enrolledAt'])); ?></h5>
                                    <small class="text-muted">Enrolled On</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h5 class="mb-1">
                                        <?php if ($payment['completedAt']): ?>
                                            <?php echo date('M d, Y', strtotime($payment['completedAt'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">Completed On</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-journal-x text-muted fs-1 mb-3"></i>
                                <h5>No Enrollment</h5>
                                <p class="text-muted">This payment is not linked to any enrollment</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Status History -->
                <div class="card border-0 rounded-4 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 px-4 pt-4">
                        <h5 class="mb-0">Status Timeline</h5>
                    </div>
                    <div class="card-body px-4">
                        <div class="timeline">
                            <!-- Payment Created -->
                            <div class="timeline-item">
                                <div class="timeline-icon bg-primary">
                                    <i class="bi bi-plus-circle text-white"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Payment Created</h6>
                                    <p class="text-muted mb-0">
                                        <?php echo date('M d, Y', strtotime($payment['createdAt'])); ?>
                                        <small class="d-block"><?php echo date('h:i A', strtotime($payment['createdAt'])); ?></small>
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
                                        <h6 class="mb-1">Payment Recorded</h6>
                                        <p class="text-muted mb-0">
                                            <?php echo date('M d, Y', strtotime($payment['paymentDate'])); ?>
                                            <small class="d-block"><?php echo date('h:i A', strtotime($payment['paymentDate'])); ?></small>
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
                                    <h6 class="mb-1">Current Status</h6>
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
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-header bg-white border-0 px-4 pt-4">
                            <h5 class="mb-0">Related Payments</h5>
                        </div>
                        <div class="card-body px-4">
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
/* Back button styling to match other pages */
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

/* Original styles from your file */
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
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 2rem;
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