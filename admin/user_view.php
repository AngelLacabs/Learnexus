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

$fromEdit = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'user_edit.php') !== false;
?>

<style>
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
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="bg-white rounded-3 shadow-sm p-3 w-100">
                <div class="d-flex align-items-center">
                    <a href="users.php" class="btn btn-outline-secondary me-3" id="backButton">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div class="flex-grow-1">
                        <h1 class="h3 mb-0">User Profile</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Profile Card -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fed7e2 0%, #fbb6ce 100%); border-radius: 0.75rem;">
                    <div class="card-body text-center">
                        <!-- Avatar -->
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" 
                                 class="rounded-circle mb-3 border border-3 border-white" 
                                 width="120" 
                                 height="120"
                                 alt="Avatar"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstName'] . '+' . $user['lastName']); ?>&background=f687b3&color=fff'">
                        <?php else: ?>
                            <div class="mx-auto mb-3 rounded-circle bg-white d-flex align-items-center justify-content-center border border-3 border-white" 
                                 style="width: 120px; height: 120px; font-size: 48px; color: #f687b3;">
                                <?php echo strtoupper(substr($user['firstName'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h4>
                        
                        <div class="mb-3">
                            <span class="badge fs-6 px-3 py-2" style="background: rgba(255, 255, 255, 0.9); color: #d53f8c; border: 1px solid rgba(255, 255, 255, 0.5);">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                            <span class="badge fs-6 ms-2 px-3 py-2" style="background: <?php 
                                echo $user['status'] === 'active' ? 'rgba(40, 167, 69, 0.9)' : 
                                    ($user['status'] === 'suspended' ? 'rgba(220, 53, 69, 0.9)' : 'rgba(108, 117, 125, 0.9)'); 
                            ?>; color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <?php if ($user['emailVerified']): ?>
                                <span class="badge px-3 py-2" style="background: rgba(40, 167, 69, 0.9); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                    <i class="bi bi-envelope-check me-1"></i>Email Verified
                                </span>
                            <?php else: ?>
                                <span class="badge px-3 py-2" style="background: rgba(255, 193, 7, 0.9); color: #212529; border: 1px solid rgba(255, 255, 255, 0.3);">
                                    <i class="bi bi-envelope-x me-1"></i>Email Unverified
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($user['phoneVerified']): ?>
                                <span class="badge px-3 py-2" style="background: rgba(40, 167, 69, 0.9); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                                    <i class="bi bi-phone-check me-1"></i>Phone Verified
                                </span>
                            <?php else: ?>
                                <span class="badge px-3 py-2" style="background: rgba(255, 193, 7, 0.9); color: #212529; border: 1px solid rgba(255, 255, 255, 0.3);">
                                    <i class="bi bi-phone-x me-1"></i>Phone Unverified
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="my-3" style="background-color: rgba(214, 63, 140, 0.3);">
                        
                        <div class="text-start">
                            <div class="mb-2">
                                <small class="text-muted">User ID</small>
                                <p class="mb-0 fw-medium"><?php echo $user['userID']; ?></p>
                            </div>
                            
                            <?php if ($user['role'] === 'student' && !empty($user['studentNumber'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Student Number</small>
                                    <p class="mb-0 fw-medium"><?php echo htmlspecialchars($user['studentNumber']); ?></p>
                                </div>
                            <?php elseif ($user['role'] === 'instructor' && !empty($user['teacherNumber'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Teacher Number</small>
                                    <p class="mb-0 fw-medium"><?php echo htmlspecialchars($user['teacherNumber']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <small class="text-muted">Email Address</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Phone Number</small>
                                <p class="mb-0 fw-medium"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Registered</small>
                                <p class="mb-0 fw-medium">
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
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold">
                            <i class="bi bi-bar-chart me-2"></i>User Statistics
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <?php if ($user['role'] === 'student'): ?>
                            <!-- Statistics Row - Improved responsiveness -->
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(13, 110, 253, 0.1);">
                                        <h2 class="text-primary mb-1 display-6 fw-bold"><?php echo $enrollmentCount; ?></h2>
                                        <small class="text-muted d-block">Total Enrollments</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(25, 135, 84, 0.1);">
                                        <h2 class="text-success mb-1 display-6 fw-bold"><?php echo $completedCourses; ?></h2>
                                        <small class="text-muted d-block">Completed Courses</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(255, 193, 7, 0.1);">
                                        <h2 class="text-warning mb-1 display-6 fw-bold"><?php echo $certificatesCount; ?></h2>
                                        <small class="text-muted d-block">Certificates</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(13, 202, 240, 0.1);">
                                        <h2 class="text-info mb-1 display-6 fw-bold">₱<?php echo number_format($totalSpent, 2); ?></h2>
                                        <small class="text-muted d-block">Total Spent</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($recentEnrollments)): ?>
                                <!-- Recent Enrollments - Improved layout -->
                                <div class="mt-4">
                                    <h6 class="mb-3 fw-bold d-flex align-items-center">
                                        <i class="bi bi-clock-history me-2"></i>Recent Enrollments
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead style="background: rgba(0, 0, 0, 0.02);">
                                                <tr>
                                                    <th scope="col">Course</th>
                                                    <th scope="col" class="text-center">Date</th>
                                                    <th scope="col" class="text-center">Price</th>
                                                    <th scope="col" class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentEnrollments as $enrollment): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <i class="bi bi-book text-primary"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0 fw-medium" style="font-size: 0.95rem;"><?php echo htmlspecialchars($enrollment['title']); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center" style="color: #6c757d; font-size: 0.9rem;">
                                                        <?php echo date('M d, Y', strtotime($enrollment['enrolledAt'])); ?>
                                                    </td>
                                                    <td class="text-center fw-medium" style="color: #198754;">
                                                        ₱<?php echo number_format($enrollment['price'], 2); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php 
                                                            echo $enrollment['status'] === 'completed' ? 'success' : 
                                                                ($enrollment['status'] === 'active' ? 'primary' : 'warning'); 
                                                        ?> px-3 py-2" style="font-size: 0.85rem; min-width: 90px;">
                                                            <?php echo ucfirst($enrollment['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($user['role'] === 'instructor'): ?>
                            <!-- Statistics Row - Improved responsiveness -->
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(13, 110, 253, 0.1);">
                                        <h2 class="text-primary mb-1 display-6 fw-bold"><?php echo $coursesCount; ?></h2>
                                        <small class="text-muted d-block">Total Courses</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(25, 135, 84, 0.1);">
                                        <h2 class="text-success mb-1 display-6 fw-bold"><?php echo $publishedCourses; ?></h2>
                                        <small class="text-muted d-block">Published Courses</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(255, 193, 7, 0.1);">
                                        <h2 class="text-warning mb-1 display-6 fw-bold"><?php echo $totalStudents; ?></h2>
                                        <small class="text-muted d-block">Total Students</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-center p-3 border rounded" style="background: rgba(13, 202, 240, 0.1);">
                                        <h2 class="text-info mb-1 display-6 fw-bold">₱<?php echo number_format($totalRevenue, 2); ?></h2>
                                        <small class="text-muted d-block">Total Revenue</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($recentCourses)): ?>
                                <!-- Recent Courses - Improved layout -->
                                <div class="mt-4">
                                    <h6 class="mb-3 fw-bold d-flex align-items-center">
                                        <i class="bi bi-collection me-2"></i>Recent Courses
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead style="background: rgba(0, 0, 0, 0.02);">
                                                <tr>
                                                    <th scope="col">Course Title</th>
                                                    <th scope="col" class="text-center">Created</th>
                                                    <th scope="col" class="text-center">Price</th>
                                                    <th scope="col" class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentCourses as $course): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <i class="bi bi-journal-text text-primary"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0 fw-medium" style="font-size: 0.95rem;"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center" style="color: #6c757d; font-size: 0.9rem;">
                                                        <?php echo date('M d, Y', strtotime($course['createdAt'])); ?>
                                                    </td>
                                                    <td class="text-center fw-medium" style="color: #198754;">
                                                        ₱<?php echo number_format($course['price'], 2); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php 
                                                            echo $course['status'] === 'published' ? 'success' : 
                                                                ($course['status'] === 'draft' ? 'warning' : 'secondary'); 
                                                        ?> px-3 py-2" style="font-size: 0.85rem; min-width: 90px;">
                                                            <?php echo ucfirst($course['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold">
                            <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <div class="d-grid gap-2">
                            <a href="user_edit.php?id=<?php echo $userID; ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; color: white; font-weight: 500;">
                                <i class="bi bi-pencil me-2"></i>Edit User
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

<script>
(function() {
    // Check if we came from edit page using referrer
    const fromEdit = document.referrer.includes('user_edit.php');
    
    if (fromEdit) {
        // When coming from edit, intercept back button to go to users.php instead of edit page
        let isNavigatingBack = false;
        
        window.addEventListener('popstate', function(event) {
            if (!isNavigatingBack) {
                isNavigatingBack = true;
                // Redirect to users.php instead of going back to edit page
                window.location.href = 'users.php';
            }
        });
        
        // Push users.php into history before current page
        // This ensures back button goes to users.php instead of edit page
        window.history.pushState({page: 'users'}, '', 'users.php');
        window.history.pushState({page: 'user_view'}, '', window.location.href);
    }
})();
</script>

<?php include 'includes/footer.php'; ?>