<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userAvatar = $stmt->fetchColumn();

// Fetch all vouchers
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

$newVoucherCode = $_SESSION['new_voucher_code'] ?? null;
unset($_SESSION['new_voucher_code']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vouchers - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-accent: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            width: var(--sidebar-width);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: #1a73e8;
            border-radius: 0 4px 4px 0;
            transition: height 0.25s ease;
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: var(--gradient-primary);
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active::before {
            display: none;
        }

        .hamburger-btn {
            width: 50px;
            height: 50px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .hamburger-icon span {
            display: block;
            width: 24px;
            height: 3px;
            background: #1a73e8;
            border-radius: 3px;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
        }

        .voucher-code {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .voucher-border-active {
            border-left: 4px solid #28a745 !important;
        }

        .voucher-border-redeemed {
            border-left: 4px solid #6c757d !important;
        }

        .voucher-border-expired {
            border-left: 4px solid #dc3545 !important;
        }
    </style>
</head>

<body>
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
            id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100"
        style="width: var(--sidebar-width);" id="sidebar">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>

            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="my_courses.php">
                    <i class="bi bi-journal-bookmark fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="certificates.php">
                    <i class="bi bi-award fs-5"></i><span>Certificates</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="vouchers.php">
                    <i class="bi bi-ticket-perforated fs-5"></i><span>Vouchers</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="ai_chatbot.php">
                    <i class="bi bi-robot fs-5"></i><span>AI Tutor</span>
                </a>
            </nav>

            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold"
                    onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0 fw-bold"><i class="bi bi-ticket-perforated me-2"></i>My Vouchers</h4>
                            </div>

                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'"
                                role="button">
                                <span class="fw-semibold d-none d-sm-inline">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                    style="width: 45px; height: 45px; background: var(--gradient-primary);">
                                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar"
                                            class="w-100 h-100 rounded-circle object-fit-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 fw-bold">SoleSource Vouchers</h1>
                    <p class="text-muted">Redeem these codes at SoleSource for exclusive discounts!</p>
                </div>
            </div>

            <?php if ($newVoucherCode): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-gift-fill me-2"></i>
                            <strong>Congratulations!</strong> You earned a new voucher:
                            <strong><?= htmlspecialchars($newVoucherCode) ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($vouchers)): ?>
                <div class="row g-4">
                    <?php foreach ($vouchers as $voucher): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div
                                class="card border-0 rounded-4 shadow-sm card-hover h-100 voucher-border-<?= $voucher['voucherStatus'] ?>">
                                <div class="card-body p-4">

                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span
                                            class="badge bg-<?= $voucher['voucherStatus'] === 'active' ? 'success' : ($voucher['voucherStatus'] === 'redeemed' ? 'secondary' : 'danger') ?>">
                                            <?= strtoupper($voucher['voucherStatus']) ?>
                                        </span>
                                        <?php if ($voucher['voucherStatus'] === 'active'): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock"></i>
                                                <?= floor((strtotime($voucher['expiryDate']) - time()) / 86400) ?> days left
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <h5 class="fw-bold mb-3">
                                        <i class="bi bi-award-fill text-warning me-2"></i>
                                        <?= htmlspecialchars($voucher['courseTitle'] ?? 'Course Completion Reward') ?>
                                    </h5>

                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Voucher Code:</small>
                                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                                            <span class="voucher-code"><?= htmlspecialchars($voucher['voucherCode']) ?></span>
                                            <button class="btn btn-sm btn-outline-primary rounded-circle"
                                                onclick="copyCode('<?= htmlspecialchars($voucher['voucherCode']) ?>')"
                                                title="Copy code">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Discount:</small>
                                            <strong class="text-success">
                                                <?= $voucher['discountPercentage'] ?>        <?= $voucher['discount_type'] === 'percent' ? '%' : ' PHP' ?>
                                                OFF
                                            </strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Expires:</small>
                                            <strong><?= date('M d, Y', strtotime($voucher['expiryDate'])) ?></strong>
                                        </div>
                                    </div>
                                    <?php if ($voucher['voucherStatus'] === 'redeemed'): ?>
                                        <div class="alert alert-secondary small mb-0">
                                            <i class="bi bi-check-circle-fill"></i>
                                            Redeemed on <?= date('M d, Y', strtotime($voucher['redeemed_at'])) ?>
                                        </div>
                                    <?php elseif ($voucher['voucherStatus'] === 'active'): ?>
                                        <a href="https://dev.art2cart.shop/pages/shop.php?voucher=<?= urlencode($voucher['voucherCode']) ?>"
                                            target="_blank" class="btn btn-primary w-100 rounded-pill fw-semibold">
                                            <i class="bi bi-bag-fill me-2"></i>Shop at SoleSource
                                        </a>
                                    <?php else: ?>
                                        <div class="alert alert-danger small mb-0">
                                            <i class="bi bi-exclamation-triangle-fill"></i> This voucher has expired
                                        </div>
                                    <?php endif; ?>

                                    <hr class="my-3">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> Issued:
                                        <?= date('M d, Y', strtotime($voucher['generatedAt'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-ticket-perforated display-1 text-muted mb-3"></i>
                                <h3 class="h5 fw-bold mb-3">No Vouchers Yet</h3>
                                <p class="text-muted mb-4">Complete courses to earn SoleSource discount vouchers!</p>
                                <a href="course_catalog.php" class="btn btn-primary rounded-pill px-4 fw-semibold border-0"
                                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
                                    <i class="bi bi-search me-2"></i>Browse Courses
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
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
                            Voucher code <strong>${code}</strong> copied!
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);

                setTimeout(() => toast.remove(), 3000);
            }).catch(() => {
                alert('Failed to copy code');
            });
        }
    </script>
</body>

</html>