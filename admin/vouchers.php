<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Manage Vouchers - Admin Panel";

// Fetch all vouchers with user and course info
try {
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            u.firstName,
            u.lastName,
            u.email,
            c.title as courseTitle,
            CASE 
                WHEN v.isUsed = 1 THEN 'redeemed'
                WHEN v.expiryDate < CURDATE() THEN 'expired'
                ELSE 'active'
            END as voucherStatus
        FROM vouchers v
        LEFT JOIN users u ON v.userID = u.userID
        LEFT JOIN certificates cert ON v.certificateID = cert.certificateID
        LEFT JOIN courses c ON cert.courseID = c.courseID
        ORDER BY v.generatedAt DESC
    ");
    $stmt->execute();
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Vouchers Error: " . $e->getMessage());
    $vouchers = [];
}

// Handle search
$search = $_GET['search'] ?? '';
if ($search) {
    $searchTerm = "%$search%";
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            u.firstName,
            u.lastName,
            u.email,
            c.title as courseTitle,
            CASE 
                WHEN v.isUsed = 1 THEN 'redeemed'
                WHEN v.expiryDate < CURDATE() THEN 'expired'
                ELSE 'active'
            END as voucherStatus
        FROM vouchers v
        LEFT JOIN users u ON v.userID = u.userID
        LEFT JOIN certificates cert ON v.certificateID = cert.certificateID
        LEFT JOIN courses c ON cert.courseID = c.courseID
        WHERE v.voucherCode LIKE ? 
           OR u.email LIKE ? 
           OR u.firstName LIKE ? 
           OR u.lastName LIKE ? 
           OR c.title LIKE ?
        ORDER BY v.generatedAt DESC
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$totalVouchers = count($vouchers);
$activeCount = count(array_filter($vouchers, fn($v) => $v['voucherStatus'] === 'active'));
$redeemedCount = count(array_filter($vouchers, fn($v) => $v['voucherStatus'] === 'redeemed'));
$expiredCount = count(array_filter($vouchers, fn($v) => $v['voucherStatus'] === 'expired'));

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content pb-3 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-3">
    <div class="container-fluid">
        <!-- Box 1: Page Header -->
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Manage Vouchers</h1>
                        <p class="text-muted mb-0">View and manage all voucher codes issued to students</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 2: Statistics Cards - Separate Boxes with gradient styling -->
        <div class="row g-4 mb-5">
            <!-- Total Vouchers -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Total Vouchers</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($totalVouchers); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-ticket-perforated" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Vouchers -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Active</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($activeCount); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-check-circle" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Redeemed Vouchers -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Redeemed</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($redeemedCount); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-gift" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Expired Vouchers -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Expired</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($expiredCount); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-x-circle" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 3: Search & Filter Card -->
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i> Filter Vouchers</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-10">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Voucher Code, Student, Email, or Course..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn w-100 text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="vouchers.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search)): ?>
                    <div class="mt-3">
                        <a href="vouchers.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Clear Filters
                        </a>
                        <span class="ms-2 text-muted">
                            Showing <?php echo count($vouchers); ?> of <?php echo number_format($totalVouchers); ?> vouchers
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Box 4: Vouchers Table -->
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-white border-0 py-3 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">All Vouchers</h5>
                        <small class="text-muted"><?php echo number_format($totalVouchers); ?> total vouchers found</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV()">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body px-4">
                <?php if (empty($vouchers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <h5 class="mt-3">No vouchers found</h5>
                        <p class="text-muted">
                            <?php echo $search ? 'No vouchers match your search criteria.' : 'No vouchers have been generated yet.'; ?>
                        </p>
                        <?php if ($search): ?>
                            <a href="vouchers.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-1"></i> View All Vouchers
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Voucher Code</th>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Discount</th>
                                    <th>Status</th>
                                    <th>Generated Date</th>
                                    <th>Expiry Date</th>
                                    <th width="60">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vouchers as $voucher): ?>
                                    <tr>
                                        <td>
                                            <code class="text-primary fw-bold"><?php echo htmlspecialchars($voucher['voucherCode']); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($voucher['firstName']): ?>
                                                <div class="fw-bold"><?php echo htmlspecialchars($voucher['firstName'] . ' ' . $voucher['lastName']); ?></div>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($voucher['email']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($voucher['courseTitle']): ?>
                                                <?php echo htmlspecialchars($voucher['courseTitle']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Manual Issue</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $voucher['discountPercentage']; ?>
                                                <?php echo $voucher['discount_type'] === 'percent' ? '%' : ' PHP'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = [
                                                    'active' => 'success',
                                                    'redeemed' => 'secondary',
                                                    'expired' => 'danger'
                                                ][$voucher['voucherStatus']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo strtoupper($voucher['voucherStatus']); ?>
                                            </span>
                                            <?php if ($voucher['isUsed'] && $voucher['redeemed_order']): ?>
                                                <br>
                                                <small class="text-muted">Order: <?php echo htmlspecialchars($voucher['redeemed_order']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo date('M d, Y', strtotime($voucher['generatedAt'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($voucher['generatedAt'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                                $expiryDate = strtotime($voucher['expiryDate']);
                                                $today = time();
                                                $daysLeft = ceil(($expiryDate - $today) / (60 * 60 * 24));
                                                
                                                if ($voucher['voucherStatus'] === 'expired') {
                                                    echo '<div class="fw-bold text-danger">';
                                                    echo date('M d, Y', $expiryDate);
                                                    echo '</div>';
                                                } elseif ($daysLeft <= 7) {
                                                    echo '<div class="fw-bold text-warning" title="Expires in ' . $daysLeft . ' days">';
                                                    echo date('M d, Y', $expiryDate);
                                                    echo '</div>';
                                                    echo '<small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Expires soon</small>';
                                                } else {
                                                    echo '<div class="fw-bold">';
                                                    echo date('M d, Y', $expiryDate);
                                                    echo '</div>';
                                                    echo '<small class="text-muted">' . $daysLeft . ' days left</small>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($voucher['voucherCode']); ?>')"
                                                    title="Copy voucher code"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Export Option -->
                    <div class="mt-4 d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Showing <?php echo count($vouchers); ?> voucher<?php echo count($vouchers) !== 1 ? 's' : ''; ?>
                        </small>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV()">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Copy voucher code to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Voucher code copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    }).catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to copy: ' + err
        });
    });
}

// Export to CSV function
function exportToCSV() {
    // Create CSV content
    let csv = 'Voucher Code,Student,Email,Course,Discount,Status,Generated Date,Expiry Date,Redeemed Order\n';
    
    <?php foreach ($vouchers as $voucher): ?>
        csv += `"<?php echo addslashes($voucher['voucherCode']); ?>",`;
        csv += `"<?php echo addslashes($voucher['firstName'] . ' ' . $voucher['lastName']); ?>",`;
        csv += `"<?php echo addslashes($voucher['email']); ?>",`;
        csv += `"<?php echo addslashes($voucher['courseTitle']); ?>",`;
        csv += `"<?php echo $voucher['discountPercentage']; ?><?php echo $voucher['discount_type'] === 'percent' ? '%' : ' PHP'; ?>",`;
        csv += `"<?php echo strtoupper($voucher['voucherStatus']); ?>",`;
        csv += `"<?php echo date('M d, Y', strtotime($voucher['generatedAt'])); ?>",`;
        csv += `"<?php echo date('M d, Y', strtotime($voucher['expiryDate'])); ?>",`;
        csv += `"<?php echo addslashes($voucher['redeemed_order'] ?? ''); ?>"\n`;
    <?php endforeach; ?>
    
    // Create download link
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `vouchers_export_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    Swal.fire({
        icon: 'success',
        title: 'Exported!',
        text: 'Voucher data exported to CSV',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>

<style>
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}

.table td {
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.badge {
    font-size: 0.85em;
    padding: 5px 10px;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.table-hover tbody tr {
    transition: background-color 0.2s ease;
}

code {
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    color: #667eea;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
}
</style>