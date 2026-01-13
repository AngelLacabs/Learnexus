<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Fetch all vouchers for this student
$stmt = $conn->prepare("
    SELECT 
        v.*,
        c.title as courseTitle,
        c.courseID,
        CASE 
            WHEN v.isUsed = 1 THEN 'redeemed'
            WHEN v.expiryDate < CURDATE() THEN 'expired'
            ELSE 'active'
        END as voucherStatus
    FROM vouchers v
    LEFT JOIN certificates cert ON v.certificateID = cert.certificateID
    LEFT JOIN courses c ON cert.courseID = c.courseID
    WHERE v.userID = ?
    ORDER BY v.generatedAt DESC
");
$stmt->execute([$userID]);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for newly generated voucher from session
$newVoucherCode = $_SESSION['new_voucher_code'] ?? null;
unset($_SESSION['new_voucher_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vouchers - Learnexus</title>
    <link rel="icon" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 0; }
        .voucher-card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .voucher-card::before { content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%; }
        .voucher-card.active::before { background: #28a745; }
        .voucher-card.redeemed::before { background: #6c757d; }
        .voucher-card.expired::before { background: #dc3545; }
        .voucher-code { font-size: 28px; font-weight: bold; color: #667eea; font-family: 'Courier New', monospace; letter-spacing: 2px; }
        .voucher-badge { position: absolute; top: 15px; right: 15px; }
        .copy-btn { cursor: pointer; transition: all 0.3s; }
        .copy-btn:hover { transform: scale(1.1); }
        .shop-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; }
        .shop-btn:hover { opacity: 0.9; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="text-white mb-2"><i class="bi bi-ticket-perforated"></i> My SoleSource Vouchers</h1>
                        <p class="text-white-50">Redeem these codes at SoleSource for exclusive discounts!</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php if ($newVoucherCode): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-gift-fill"></i> <strong>Congratulations!</strong> You earned a new voucher: <strong><?= htmlspecialchars($newVoucherCode) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (empty($vouchers)): ?>
        <div class="voucher-card text-center">
            <i class="bi bi-inbox" style="font-size: 60px; color: #ccc;"></i>
            <h4 class="mt-3">No Vouchers Yet</h4>
            <p class="text-muted">Complete courses to earn SoleSource discount vouchers!</p>
            <a href="browse_courses.php" class="btn btn-primary mt-3">
                <i class="bi bi-book"></i> Browse Courses
            </a>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($vouchers as $voucher): ?>
            <div class="col-md-6 mb-4">
                <div class="voucher-card <?= $voucher['voucherStatus'] ?>">
                    <span class="badge bg-<?= $voucher['voucherStatus'] === 'active' ? 'success' : ($voucher['voucherStatus'] === 'redeemed' ? 'secondary' : 'danger') ?> voucher-badge">
                        <?= strtoupper($voucher['voucherStatus']) ?>
                    </span>
                    
                    <h5 class="mb-3">
                        <i class="bi bi-award-fill text-warning"></i>
                        <?= htmlspecialchars($voucher['courseTitle'] ?? 'Course Completion Reward') ?>
                    </h5>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Voucher Code:</small>
                        <div class="d-flex align-items-center">
                            <span class="voucher-code me-3" id="code-<?= $voucher['voucherID'] ?>">
                                <?= htmlspecialchars($voucher['voucherCode']) ?>
                            </span>
                            <i class="bi bi-clipboard copy-btn" 
                               onclick="copyCode('<?= htmlspecialchars($voucher['voucherCode']) ?>', <?= $voucher['voucherID'] ?>)"
                               title="Copy code"></i>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Discount:</small><br>
                            <strong><?= $voucher['discountPercentage'] ?><?= $voucher['discount_type'] === 'percent' ? '%' : ' PHP' ?> OFF</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Expires:</small><br>
                            <strong><?= date('M d, Y', strtotime($voucher['expiryDate'])) ?></strong>
                        </div>
                    </div>

                    <?php if ($voucher['voucherStatus'] === 'redeemed'): ?>
                    <div class="alert alert-secondary mb-0">
                        <small>
                            <i class="bi bi-check-circle-fill"></i>
                            Redeemed on <?= date('M d, Y', strtotime($voucher['redeemed_at'])) ?>
                            <?php if ($voucher['redeemed_order']): ?>
                            <br>Order: <strong><?= htmlspecialchars($voucher['redeemed_order']) ?></strong>
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php elseif ($voucher['voucherStatus'] === 'active'): ?>
                    <a href="https://dev.art2cart.shop/pages/shop.php?voucher=<?= urlencode($voucher['voucherCode']) ?>" 
                       target="_blank" 
                       class="btn shop-btn w-100">
                        <i class="bi bi-bag-fill"></i> Shop at SoleSource
                    </a>
                    <?php else: ?>
                    <div class="alert alert-danger mb-0">
                        <small><i class="bi bi-exclamation-triangle-fill"></i> This voucher has expired</small>
                    </div>
                    <?php endif; ?>

                    <hr class="my-3">
                    <small class="text-muted">
                        <i class="bi bi-calendar"></i> Issued: <?= date('M d, Y', strtotime($voucher['generatedAt'])) ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyCode(code, voucherId) {
            navigator.clipboard.writeText(code).then(() => {
                const icon = event.target;
                const originalClass = icon.className;
                icon.className = 'bi bi-check-circle-fill copy-btn text-success';
                
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
                            Voucher code <strong>${code}</strong> copied to clipboard!
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    icon.className = originalClass;
                    toast.remove();
                }, 2000);
            }).catch(err => {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>