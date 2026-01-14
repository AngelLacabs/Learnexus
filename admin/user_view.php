<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$userID = (int)$_GET['id'];

// Fetch user details
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        header('Location: users.php');
        exit();
    }
    
    // Fetch user statistics based on role
    if ($user['role'] === 'student') {
        // Get enrollment count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE userID = ?");
        $stmt->execute([$userID]);
        $enrollmentCount = $stmt->fetchColumn();
        
        // Get completed courses count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE userID = ? AND status = 'completed'");
        $stmt->execute([$userID]);
        $completedCourses = $stmt->fetchColumn();
        
        // Get certificates count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM certificates WHERE userID = ?");
        $stmt->execute([$userID]);
        $certificatesCount = $stmt->fetchColumn();
        
        // Get total payments
        $stmt = $conn->prepare("SELECT SUM(amount) FROM payments WHERE userID = ? AND status = 'completed'");
        $stmt->execute([$userID]);
        $totalSpent = $stmt->fetchColumn() ?? 0;
        
        // Recent enrollments
        $stmt = $conn->prepare("
            SELECT e.*, c.title, c.price 
            FROM enrollments e 
            JOIN courses c ON e.courseID = c.courseID 
            WHERE e.userID = ? 
            ORDER BY e.enrolledAt DESC 
            LIMIT 5
        ");
        $stmt->execute([$userID]);
        $recentEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user['role'] === 'instructor') {
        // Get courses count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE teacherID = ?");
        $stmt->execute([$userID]);
        $coursesCount = $stmt->fetchColumn();
        
        // Get published courses count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE teacherID = ? AND status = 'published'");
        $stmt->execute([$userID]);
        $publishedCourses = $stmt->fetchColumn();
        
        // Get total students
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT e.userID) 
            FROM enrollments e 
            JOIN courses c ON e.courseID = c.courseID 
            WHERE c.teacherID = ?
        ");
        $stmt->execute([$userID]);
        $totalStudents = $stmt->fetchColumn();
        
        // Get total revenue
        $stmt = $conn->prepare("
            SELECT SUM(p.amount) 
            FROM payments p 
            JOIN enrollments e ON p.enrollmentID = e.enrollmentID 
            JOIN courses c ON e.courseID = c.courseID 
            WHERE c.teacherID = ? AND p.status = 'completed'
        ");
        $stmt->execute([$userID]);
        $totalRevenue = $stmt->fetchColumn() ?? 0;
        
        // Recent courses
        $stmt = $conn->prepare("
            SELECT * FROM courses 
            WHERE teacherID = ? 
            ORDER BY createdAt DESC 
            LIMIT 5
        ");
        $stmt->execute([$userID]);
        $recentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user['role'] === 'admin') {
        // Get admin actions count (you can implement an audit log table)
        $adminActions = 0;
    }
    
} catch (PDOException $e) {
    error_log("User View Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading user details';
    header('Location: users.php');
    exit();
}

$page_title = "User Profile - " . $user['firstName'] . ' ' . $user['lastName'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="users.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">User Profile</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <!-- <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="users.php">Users</a></li> -->
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="btn-group">
                <a href="user_edit.php?id=<?php echo $userID; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Edit User
                </a>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if ($user['role'] !== 'admin'): ?>
                        <?php if ($user['status'] === 'suspended'): ?>
                            <li>
                                <a class="dropdown-item" href="user_actions.php?action=activate&id=<?php echo $userID; ?>">
                                    <i class="bi bi-check-circle me-2"></i>Activate Account
                                </a>
                            </li>
                        <?php else: ?>
                            <li>
                                <a class="dropdown-item" href="user_actions.php?action=suspend&id=<?php echo $userID; ?>">
                                    <i class="bi bi-pause-circle me-2"></i>Suspend Account
                                </a>
                            </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item text-danger" 
                           href="user_actions.php?action=delete&id=<?php echo $userID; ?>"
                           data-confirm-delete="Are you sure you want to delete this user? This will permanently delete all their data including courses, enrollments, and payments.">
                            <i class="bi bi-trash me-2"></i>Delete User
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- User Profile Card -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <!-- Avatar -->
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" 
                                 class="rounded-circle mb-3" 
                                 width="120" 
                                 height="120"
                                 alt="Avatar"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstName'] . '+' . $user['lastName']); ?>&background=667eea&color=fff'">
                        <?php else: ?>
                            <div class="mx-auto mb-3 rounded-circle bg-primary d-flex align-items-center justify-content-center" 
                                 style="width: 120px; height: 120px; font-size: 48px; color: white;">
                                <?php echo strtoupper(substr($user['firstName'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h4>
                        
                        <div class="mb-3">
                            <span class="badge bg-<?php 
                                echo $user['role'] === 'admin' ? 'danger' : 
                                    ($user['role'] === 'instructor' ? 'warning' : 'primary'); 
                            ?> fs-6">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                            <span class="badge bg-<?php 
                                echo $user['status'] === 'active' ? 'success' : 
                                    ($user['status'] === 'suspended' ? 'danger' : 'secondary'); 
                            ?> fs-6 ms-1">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-3 mb-3">
                            <?php if ($user['emailVerified']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-envelope-check me-1"></i>Email Verified
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="bi bi-envelope-x me-1"></i>Email Unverified
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($user['phoneVerified']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-phone-check me-1"></i>Phone Verified
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="bi bi-phone-x me-1"></i>Phone Unverified
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="text-start">
                            <div class="mb-2">
                                <small class="text-muted">User ID</small>
                                <p class="mb-0"><?php echo $user['userID']; ?></p>
                            </div>
                            
                            <?php if ($user['role'] === 'student' && !empty($user['studentNumber'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Student Number</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($user['studentNumber']); ?></p>
                                </div>
                            <?php elseif ($user['role'] === 'instructor' && !empty($user['teacherNumber'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Teacher Number</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($user['teacherNumber']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <small class="text-muted">Email Address</small>
                                <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Phone Number</small>
                                <p class="mb-0"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Registered</small>
                                <p class="mb-0">
                                    <?php echo date('F d, Y', strtotime($user['createdAt'])); ?><br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($user['createdAt'])); ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <!-- Role-specific statistics -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($user['role'] === 'student'): ?>
                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-primary"><?php echo $enrollmentCount; ?></h2>
                                        <small class="text-muted">Total Enrollments</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-success"><?php echo $completedCourses; ?></h2>
                                        <small class="text-muted">Completed Courses</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-warning"><?php echo $certificatesCount; ?></h2>
                                        <small class="text-muted">Certificates</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-info">₱<?php echo number_format($totalSpent, 2); ?></h2>
                                        <small class="text-muted">Total Spent</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($recentEnrollments)): ?>
                                <hr class="my-4">
                                <h6 class="mb-3">Recent Enrollments</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentEnrollments as $enrollment): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($enrollment['title']); ?></h6>
                                                    <small class="text-muted">
                                                        Enrolled: <?php echo date('M d, Y', strtotime($enrollment['enrolledAt'])); ?>
                                                        • ₱<?php echo number_format($enrollment['price'], 2); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $enrollment['status'] === 'completed' ? 'success' : 
                                                        ($enrollment['status'] === 'active' ? 'primary' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($enrollment['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($user['role'] === 'instructor'): ?>
                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-primary"><?php echo $coursesCount; ?></h2>
                                        <small class="text-muted">Total Courses</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-success"><?php echo $publishedCourses; ?></h2>
                                        <small class="text-muted">Published Courses</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-warning"><?php echo $totalStudents; ?></h2>
                                        <small class="text-muted">Total Students</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center">
                                        <h2 class="text-info">₱<?php echo number_format($totalRevenue, 2); ?></h2>
                                        <small class="text-muted">Total Revenue</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($recentCourses)): ?>
                                <hr class="my-4">
                                <h6 class="mb-3">Recent Courses</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentCourses as $course): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                    <small class="text-muted">
                                                        Created: <?php echo date('M d, Y', strtotime($course['createdAt'])); ?>
                                                        • ₱<?php echo number_format($course['price'], 2); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $course['status'] === 'published' ? 'success' : 
                                                        ($course['status'] === 'draft' ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($course['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-shield-check fs-1 text-primary mb-3"></i>
                                <h5>System Administrator</h5>
                                <p class="text-muted">This user has full administrative privileges</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="user_edit.php?id=<?php echo $userID; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-pencil me-2"></i>Edit Profile
                            </a>
                            
                            <?php if ($user['role'] === 'student'): ?>
                                <a href="enrollments.php?user=<?php echo $userID; ?>" class="btn btn-outline-success">
                                    <i class="bi bi-journal-check me-2"></i>View Enrollments
                                </a>
                            <?php elseif ($user['role'] === 'instructor'): ?>
                                <a href="courses.php?teacher=<?php echo $userID; ?>" class="btn btn-outline-success">
                                    <i class="bi bi-book me-2"></i>View Courses
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['role'] !== 'admin'): ?>
                                <?php if ($user['status'] === 'suspended'): ?>
                                    <a href="user_actions.php?action=activate&id=<?php echo $userID; ?>" class="btn btn-outline-success">
                                        <i class="bi bi-check-circle me-2"></i>Activate Account
                                    </a>
                                <?php else: ?>
                                    <a href="user_actions.php?action=suspend&id=<?php echo $userID; ?>" class="btn btn-outline-warning">
                                        <i class="bi bi-pause-circle me-2"></i>Suspend Account
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!$user['emailVerified']): ?>
                                <a href="user_actions.php?action=verify_email&id=<?php echo $userID; ?>" class="btn btn-outline-info">
                                    <i class="bi bi-envelope-check me-2"></i>Verify Email
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!$user['phoneVerified'] && !empty($user['phone'])): ?>
                                <a href="user_actions.php?action=verify_phone&id=<?php echo $userID; ?>" class="btn btn-outline-info">
                                    <i class="bi bi-phone-check me-2"></i>Verify Phone
                                </a>
                            <?php endif; ?>
                            
                            <a href="user_actions.php?action=reset_password&id=<?php echo $userID; ?>" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                <i class="bi bi-key me-2"></i>Reset Password
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="user_actions.php" method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" value="<?php echo $userID; ?>">
                
                <div class="modal-body">
                    <p>Reset password for <strong><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="force_logout" value="1" id="forceLogout" checked>
                        <label class="form-check-label" for="forceLogout">
                            Force user to logout from all devices
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>