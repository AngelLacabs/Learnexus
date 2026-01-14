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

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Manage Vouchers</h1>
                <p class="text-muted mb-0">View and manage all voucher codes issued to students</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" class="d-flex">
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               placeholder="Search vouchers..." 
                               name="search"
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Vouchers</h6>
                                <h3 class="mb-0"><?php echo count($vouchers); ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-ticket-perforated text-primary" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active</h6>
                                <h3 class="mb-0 text-success">
                                    <?php 
                                        $activeCount = count(array_filter($vouchers, fn($v) => $v['voucherStatus'] === 'active'));
                                        echo $activeCount;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-check-circle text-success" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Redeemed</h6>
                                <h3 class="mb-0 text-secondary">
                                    <?php 
                                        $redeemedCount = count(array_filter($vouchers, fn($v) => $v['voucherStatus'] === 'redeemed'));
                                        echo $redeemedCount;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-secondary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-gift text-secondary" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Expired</h6>
                                <h3 class="mb-0 text-danger">
                                    <?php 
                                        $expiredCount = count(array_filter($vouchers, fn($v) => $v['voucherStatus'] === 'expired'));
                                        echo $expiredCount;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="bi bi-x-circle text-danger" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vouchers Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">All Vouchers</h5>
            </div>
            <div class="card-body">
                <?php if (empty($vouchers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                        <h4 class="mt-3">No Vouchers Found</h4>
                        <p class="text-muted">
                            <?php echo $search ? 'No vouchers match your search criteria.' : 'No vouchers have been generated yet.'; ?>
                        </p>
                        <?php if ($search): ?>
                            <a href="vouchers.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> View All Vouchers
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
                                    <th>Actions</th>
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
                                                <div><?php echo htmlspecialchars($voucher['firstName'] . ' ' . $voucher['lastName']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($voucher['email']); ?></small>
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
                                            <?php 
                                                $generatedDate = strtotime($voucher['generatedAt']);
                                                echo date('M d, Y', $generatedDate);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $expiryDate = strtotime($voucher['expiryDate']);
                                                $today = time();
                                                $daysLeft = ceil(($expiryDate - $today) / (60 * 60 * 24));
                                                
                                                if ($voucher['voucherStatus'] === 'expired') {
                                                    echo '<span class="text-danger">';
                                                    echo date('M d, Y', $expiryDate);
                                                    echo '</span>';
                                                } elseif ($daysLeft <= 7) {
                                                    echo '<span class="text-warning" title="Expires in ' . $daysLeft . ' days">';
                                                    echo date('M d, Y', $expiryDate);
                                                    echo ' <i class="bi bi-exclamation-triangle"></i>';
                                                    echo '</span>';
                                                } else {
                                                    echo '<span class="text-muted">';
                                                    echo date('M d, Y', $expiryDate);
                                                    echo '</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                        class="btn btn-outline-primary" 
                                                        onclick="copyToClipboard('<?php echo htmlspecialchars($voucher['voucherCode']); ?>')"
                                                        title="Copy code">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Export Option -->
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Showing <?php echo count($vouchers); ?> voucher<?php echo count($vouchers) !== 1 ? 's' : ''; ?>
                        </small>
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportToCSV()">
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Copy voucher code to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-header bg-success text-white">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong class="me-auto">Copied!</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    Voucher code <strong>${text}</strong> copied to clipboard!
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 2000);
    }).catch(err => {
        alert('Failed to copy: ' + err);
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
}
</script>