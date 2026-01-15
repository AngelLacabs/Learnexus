<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Admin Dashboard - Learnexus";

// Get admin avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$_SESSION['user_id']]);
$userAvatar = $stmt->fetchColumn();

// Admin motivational phrases
$adminMotivations = [
    "Empowering education through effective administration.",
    "Your leadership shapes the future of learning.",
    "Excellence in administration drives educational success.",
    "Managing with vision, leading with purpose.",
    "Building bridges between students, teachers, and success.",
    "Your dedication ensures quality education for all.",
    "Administrative excellence creates educational opportunities.",
    "Guiding the platform that transforms lives through learning.",
    "Every decision you make impacts countless learners.",
    "Thank you for maintaining excellence in education!"
];

$dayOfYear = date('z');
$dailyMotivationAdmin = $adminMotivations[$dayOfYear % count($adminMotivations)];

// Fetch statistics
try {
    // Total users by role
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $userStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $totalStudents = $userStats['student'] ?? 0;
    $totalTeachers = $userStats['instructor'] ?? 0;
    $totalAdmins = $userStats['admin'] ?? 0;
    // Exclude admin accounts from the Total Users count
    $totalUsers = $totalStudents + $totalTeachers;

    // Total courses by status
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM courses GROUP BY status");
    $courseStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $publishedCourses = $courseStats['published'] ?? 0;
    $draftCourses = $courseStats['draft'] ?? 0;
    $archivedCourses = $courseStats['archived'] ?? 0;
    $totalCourses = $publishedCourses + $draftCourses + $archivedCourses;

    // Total enrollments
    $stmt = $conn->query("SELECT COUNT(*) FROM enrollments");
    $totalEnrollments = $stmt->fetchColumn();

    // Total revenue
    $stmt = $conn->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'");
    $totalRevenue = $stmt->fetchColumn() ?? 0;

    // Pending payments
    $stmt = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
    $pendingPayments = $stmt->fetchColumn();

    // Total certificates issued
    $stmt = $conn->query("SELECT COUNT(*) FROM certificates");
    $totalCertificates = $stmt->fetchColumn();

    // Total vouchers
    $stmt = $conn->query("SELECT COUNT(*) as total, SUM(isUsed) as used FROM vouchers");
    $voucherStats = $stmt->fetch();
    $totalVouchers = $voucherStats['total'] ?? 0;
    $usedVouchers = $voucherStats['used'] ?? 0;

    // Recent users (last 5) - exclude admins
    $stmt = $conn->query("SELECT userID, firstName, lastName, role, createdAt, avatar FROM users WHERE role != 'admin' ORDER BY createdAt DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent courses (last 5)
    $stmt = $conn->query("
        SELECT c.courseID, c.title, c.status, c.createdAt, u.firstName, u.lastName 
        FROM courses c 
        JOIN users u ON c.teacherID = u.userID 
        ORDER BY c.createdAt DESC 
        LIMIT 5
    ");
    $recentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent enrollments (last 5)
    $stmt = $conn->query("
        SELECT e.enrollmentID, e.enrolledAt, u.firstName, u.lastName, c.title 
        FROM enrollments e 
        JOIN users u ON e.userID = u.userID 
        JOIN courses c ON e.courseID = c.courseID 
        ORDER BY e.enrolledAt DESC 
        LIMIT 5
    ");
    $recentEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

include 'includes/header.php';
?>
    <!-- Hamburger Button (Mobile) -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content pb-5 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-3">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4 mt-3">
            <div class="col-12">
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-body p-3 d-flex justify-content-end align-items-center">
                        <div class="d-flex align-items-center gap-3" onclick="window.location.href='user_view.php?id=<?php echo urlencode($_SESSION['user_id']); ?>'" role="button" style="flex-shrink: 0;">
                            <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                            </span>
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                 style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
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

        <!-- Welcome Banner -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 rounded-4 shadow text-white" 
                     style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-4 p-lg-5">
                        <h2 class="h3 fw-bold mb-0"><?php echo htmlspecialchars($dailyMotivationAdmin); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <!-- Total Users -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Total Users</h6>
                                <h2 class="mb-0"><?php echo number_format($totalUsers); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Courses -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-success bg-opacity-10 text-success">
                                <i class="bi bi-book-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Total Courses</h6>
                                <h2 class="mb-0"><?php echo number_format($totalCourses); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Enrollments -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Total Enrollments</h6>
                                <h2 class="mb-0"><?php echo number_format($totalEnrollments); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-info bg-opacity-10 text-info">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Total Revenue</h6>
                                <h2 class="mb-0">₱<?php echo number_format($totalRevenue, 2); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Teachers</h6>
                                <h3 class="mb-0 text-primary"><?php echo number_format($totalTeachers); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-info bg-opacity-10 text-info">
                                <i class="bi bi-person-badge-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Students</h6>
                                <h3 class="mb-0 text-info"><?php echo number_format($totalStudents); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-success bg-opacity-10 text-success">
                                <i class="bi bi-award-fill"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Certificates</h6>
                                <h3 class="mb-0 text-success"><?php echo number_format($totalCertificates); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-circle bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-ticket-perforated"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Vouchers</h6>
                                <h3 class="mb-0 text-warning"><?php echo number_format($totalVouchers); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-4">
            <!-- Recent Users -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-lg h-100 recent-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px !important;">
                    <div class="card-header border-0 py-4 px-4" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold" style="font-size: 1.1rem;">Recent Users</h5>
                    </div>
                    <div class="card-body d-flex flex-column px-4 pb-4">
                        <div class="flex-grow-1 overflow-auto">
                            <?php if (empty($recentUsers)): ?>
                                <p class="text-white-50 mb-0">No recent users</p>
                            <?php else: ?>
                                <?php foreach ($recentUsers as $user): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <div class="avatar-recent bg-white text-dark rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 48px; height: 48px; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.15); overflow: hidden;">
                                            <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" 
                                                     class="w-100 h-100 rounded-circle object-fit-cover">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['firstName'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 text-white fw-bold" style="font-size: 0.95rem;"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h6>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-white text-dark border-0 fw-semibold" style="font-size: 0.75rem; padding: 4px 10px;">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                                <small class="text-white-50" style="font-size: 0.8rem;">
                                                    <?php echo date('M d, Y', strtotime($user['createdAt'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="users.php" class="btn btn-light w-100 mt-3 mt-auto fw-bold rounded-pill" style="padding: 10px; font-size: 0.9rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">View All Users</a>
                    </div>
                </div>
            </div>

            <!-- Recent Courses -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-lg h-100 recent-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 20px !important;">
                    <div class="card-header border-0 py-4 px-4" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold" style="font-size: 1.1rem;">Recent Courses</h5>
                    </div>
                    <div class="card-body d-flex flex-column px-4 pb-4">
                        <div class="flex-grow-1 overflow-auto">
                            <?php if (empty($recentCourses)): ?>
                                <p class="text-white-50 mb-0">No recent courses</p>
                            <?php else: ?>
                                <?php foreach ($recentCourses as $course): ?>
                                    <div class="mb-3 pb-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <h6 class="mb-2 text-white fw-bold" style="font-size: 0.95rem; line-height: 1.4;"><?php echo htmlspecialchars($course['title']); ?></h6>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <small class="text-white-50" style="font-size: 0.85rem;">
                                                By <?php echo htmlspecialchars($course['firstName'] . ' ' . $course['lastName']); ?>
                                            </small>
                                            <span class="badge bg-white text-dark border-0 fw-semibold" style="font-size: 0.75rem; padding: 4px 10px;">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="courses.php" class="btn btn-light w-100 mt-3 mt-auto fw-bold rounded-pill" style="padding: 10px; font-size: 0.9rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">View All Courses</a>
                    </div>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-lg h-100 recent-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 20px !important;">
                    <div class="card-header border-0 py-4 px-4" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold" style="font-size: 1.1rem;">Recent Enrollments</h5>
                    </div>
                    <div class="card-body d-flex flex-column px-4 pb-4">
                        <div class="flex-grow-1 overflow-auto">
                            <?php if (empty($recentEnrollments)): ?>
                                <p class="text-white-50 mb-0">No recent enrollments</p>
                            <?php else: ?>
                                <?php foreach ($recentEnrollments as $enrollment): ?>
                                    <div class="mb-3 pb-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <h6 class="mb-2 text-white fw-bold" style="font-size: 0.95rem;"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></h6>
                                        <div class="d-flex flex-column gap-1">
                                            <small class="text-white-50 fw-medium" style="font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($enrollment['title']); ?>
                                            </small>
                                            <small class="text-white-50" style="font-size: 0.8rem;">
                                                <?php echo date('M d, Y', strtotime($enrollment['enrolledAt'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="enrollments.php" class="btn btn-light w-100 mt-3 mt-auto fw-bold rounded-pill" style="padding: 10px; font-size: 0.9rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">View All Enrollments</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Hamburger button animation
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    
    if (hamburgerBtn && sidebar) {
        sidebar.addEventListener('show.bs.offcanvas', () => {
            hamburgerBtn.classList.add('active');
        });
        
        sidebar.addEventListener('hide.bs.offcanvas', () => {
            hamburgerBtn.classList.remove('active');
        });
    }
    
    // Close sidebar when clicking nav links on mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 991.98) {
                const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                if (offcanvas) offcanvas.hide();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>