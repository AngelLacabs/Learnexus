<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Admin Dashboard - Learnexus";

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

    // Recent users (last 5)
    $stmt = $conn->query("SELECT userID, firstName, lastName, role, createdAt FROM users ORDER BY createdAt DESC LIMIT 5");
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
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋</p>
            </div>
            <!-- <div>
                <span class="badge bg-success">System Online</span>
            </div> -->
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <!-- Total Users -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Users</h6>
                                <h2 class="mb-0"><?php echo number_format($totalUsers); ?></h2>
                                <small class="text-success">
                                    <i class="bi bi-arrow-up"></i> Students: <?php echo $totalStudents; ?>
                                </small>
                            </div>
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Courses -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Courses</h6>
                                <h2 class="mb-0"><?php echo number_format($totalCourses); ?></h2>
                                <div class="d-flex gap-2 justify-content-start align-items-center mt-2">
                                    <small class="text-info">
                                        <i class="bi bi-check-circle"></i> Published: <?php echo $publishedCourses; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="stat-icon bg-success">
                                <i class="bi bi-book-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Enrollments -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Enrollments</h6>
                                <h2 class="mb-0"><?php echo number_format($totalEnrollments); ?></h2>
                                <small class="text-warning">
                                    <i class="bi bi-journal-bookmark"></i> Active
                                </small>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-journal-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Revenue</h6>
                                <h2 class="mb-0">₱<?php echo number_format($totalRevenue, 2); ?></h2>
                                <small class="text-success">
                                    <i class="bi bi-clock"></i> Pending: <?php echo $pendingPayments; ?>
                                </small>
                            </div>
                            <div class="stat-icon bg-info">
                                <i class="bi bi-currency-dollar"></i>
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
                    <div class="card-body text-center">
                        <h6 class="text-muted">Teachers</h6>
                        <h3 class="text-primary"><?php echo number_format($totalTeachers); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Certificates</h6>
                        <h3 class="text-success"><?php echo number_format($totalCertificates); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Vouchers</h6>
                        <h3 class="text-warning"><?php echo number_format($totalVouchers); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm hover-stat">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Draft Courses</h6>
                        <h3 class="text-secondary"><?php echo number_format($draftCourses); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-4">
            <!-- Recent Users -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">Recent Users</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="flex-grow-1 overflow-auto">
                            <div class="list-group list-group-flush">
                                <?php if (empty($recentUsers)): ?>
                                    <p class="text-muted">No recent users</p>
                                <?php else: ?>
                                    <?php foreach ($recentUsers as $user): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($user['firstName'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h6>
                                                    <small class="text-muted">
                                                        <span class="badge bg-<?php echo $user['role'] === 'student' ? 'primary' : 'success'; ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                        <?php echo date('M d, Y', strtotime($user['createdAt'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="users.php" class="btn btn-sm btn-outline-primary w-100 mt-3 mt-auto">View All Users</a>
                    </div>
                </div>
            </div>

            <!-- Recent Courses -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">Recent Courses</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="flex-grow-1 overflow-auto">
                            <div class="list-group list-group-flush">
                                <?php if (empty($recentCourses)): ?>
                                    <p class="text-muted">No recent courses</p>
                                <?php else: ?>
                                    <?php foreach ($recentCourses as $course): ?>
                                        <div class="list-group-item px-0">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                            <small class="text-muted">
                                                By <?php echo htmlspecialchars($course['firstName'] . ' ' . $course['lastName']); ?>
                                                <span class="badge bg-<?php 
                                                    echo $course['status'] === 'published' ? 'success' : 
                                                        ($course['status'] === 'draft' ? 'warning' : 'secondary'); 
                                                ?> ms-2">
                                                    <?php echo ucfirst($course['status']); ?>
                                                </span>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="courses.php" class="btn btn-sm btn-outline-success w-100 mt-3 mt-auto">View All Courses</a>
                    </div>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="col-md-4 col-12">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0">Recent Enrollments</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="flex-grow-1 overflow-auto">
                            <div class="list-group list-group-flush">
                                <?php if (empty($recentEnrollments)): ?>
                                    <p class="text-muted">No recent enrollments</p>
                                <?php else: ?>
                                    <?php foreach ($recentEnrollments as $enrollment): ?>
                                        <div class="list-group-item px-0">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($enrollment['title']); ?>
                                                <br>
                                                <?php echo date('M d, Y', strtotime($enrollment['enrolledAt'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="enrollments.php" class="btn btn-sm btn-outline-warning w-100 mt-3 mt-auto">View All Enrollments</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>