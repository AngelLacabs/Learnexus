<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "User Management - Learnexus";

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$verified = isset($_GET['verified']) ? $_GET['verified'] : '';

// Build query
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(firstName LIKE ? OR lastName LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($role)) {
    $whereClauses[] = "role = ?";
    $params[] = $role;
}

if (!empty($status)) {
    $whereClauses[] = "status = ?";
    $params[] = $status;
}

if ($verified === '1') {
    $whereClauses[] = "emailVerified = 1 AND phoneVerified = 1";
} elseif ($verified === '0') {
    $whereClauses[] = "(emailVerified = 0 OR phoneVerified = 0)";
}

// Exclude the currently logged-in admin account from the users list
if (isset($_SESSION['user_id'])) {
    $whereClauses[] = "userID != ?";
    $params[] = $_SESSION['user_id'];
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count for pagination
$countSQL = "SELECT COUNT(*) FROM users $whereSQL";
$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Get users with pagination - SIMPLIFIED VERSION
$sql = "SELECT * FROM users $whereSQL ORDER BY createdAt DESC";
$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (exclude admin accounts from user counts)
$statsStmt = $conn->query("
    SELECT
        COUNT(CASE WHEN role != 'admin' THEN 1 END) AS total,
        COUNT(CASE WHEN role = 'student' THEN 1 END) AS students,
        COUNT(CASE WHEN role = 'instructor' THEN 1 END) AS instructors,
        COUNT(CASE WHEN role = 'admin' THEN 1 END) AS admins,
        COUNT(CASE WHEN status = 'active' AND role != 'admin' THEN 1 END) AS active,
        COUNT(CASE WHEN status = 'suspended' AND role != 'admin' THEN 1 END) AS suspended,
        COUNT(CASE WHEN emailVerified = 1 AND phoneVerified = 1 AND role != 'admin' THEN 1 END) AS verified
    FROM users
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">User Management</h1>
                <p class="text-muted mb-0">Manage all system users (Students, Teachers, Admins)</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New User
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Users</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                            </div>
                            <div class="text-primary">
                                <i class="bi bi-people-fill fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Students</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['students']); ?></h3>
                            </div>
                            <div class="text-success">
                                <i class="bi bi-mortarboard-fill fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Instructors</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['instructors']); ?></h3>
                            </div>
                            <div class="text-warning">
                                <i class="bi bi-person-badge-fill fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active Users</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['active']); ?></h3>
                            </div>
                            <div class="text-info">
                                <i class="bi bi-check-circle-fill fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Name, Email, or Phone"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="instructor" <?php echo $role === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Verification</label>
                        <select class="form-select" name="verified">
                            <option value="">All</option>
                            <option value="1" <?php echo $verified === '1' ? 'selected' : ''; ?>>Verified</option>
                            <option value="0" <?php echo $verified === '0' ? 'selected' : ''; ?>>Not Verified</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">Users List</h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people fs-1 text-muted"></i>
                        <h5 class="mt-3">No users found</h5>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email & Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Verified</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($user['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" 
                                                         class="rounded-circle me-3" 
                                                         width="40" 
                                                         height="40"
                                                         alt="Avatar">
                                                <?php else: ?>
                                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                        <?php echo strtoupper(substr($user['firstName'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h6>
                                                    <small class="text-muted">ID: <?php echo $user['userID']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($user['email']); ?></small>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === 'admin' ? 'danger' : 
                                                    ($user['role'] === 'instructor' ? 'warning' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                            <?php if ($user['role'] === 'student' && !empty($user['studentNumber'])): ?>
                                                <br><small class="text-muted"><?php echo $user['studentNumber']; ?></small>
                                            <?php elseif ($user['role'] === 'instructor' && !empty($user['teacherNumber'])): ?>
                                                <br><small class="text-muted"><?php echo $user['teacherNumber']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['status'] === 'active' ? 'success' : 
                                                    ($user['status'] === 'suspended' ? 'danger' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['emailVerified'] && $user['phoneVerified']): ?>
                                                <span class="badge bg-success">✓ Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <?php echo ($user['emailVerified'] ? 'Email✓' : 'Email✗') . ' / ' . ($user['phoneVerified'] ? 'Phone✓' : 'Phone✗'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($user['createdAt'])); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($user['createdAt'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="user_view.php?id=<?php echo $user['userID']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="user_edit.php?id=<?php echo $user['userID']; ?>" 
                                                   class="btn btn-outline-secondary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <?php if ($user['status'] === 'suspended'): ?>
                                                        <a href="user_actions.php?action=activate&id=<?php echo $user['userID']; ?>" 
                                                           class="btn btn-outline-success" 
                                                           data-bs-toggle="tooltip" 
                                                           title="Activate">
                                                            <i class="bi bi-check-circle"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="user_actions.php?action=suspend&id=<?php echo $user['userID']; ?>" 
                                                           class="btn btn-outline-warning" 
                                                           data-bs-toggle="tooltip" 
                                                           title="Suspend">
                                                            <i class="bi bi-pause-circle"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <a href="user_actions.php?action=delete&id=<?php echo $user['userID']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   data-confirm-delete="Are you sure you want to delete this user? This will permanently delete all their data including courses, enrollments, and payments."
                                                   data-bs-toggle="tooltip" 
                                                   title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addUserForm" method="POST" action="user_actions.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="firstName" required id="modalFirstName">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="lastName" required id="modalLastName">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Initial</label>
                            <input type="text" class="form-control" name="middleInitial" maxlength="5" id="modalMiddleInitial">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required id="modalRole">
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" required id="modalEmail">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" id="modalPhone">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required minlength="6" id="modalPassword">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6" id="modalConfirmPassword">
                            <div class="invalid-feedback" id="modalPasswordError" style="display: none;">Passwords do not match</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student/Teacher Number</label>
                            <input type="text" class="form-control" name="user_number" 
                                   placeholder="For students or teachers only" id="modalUserNumber">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Initial Status</label>
                            <select class="form-select" name="status" id="modalStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="emailVerified" value="1" id="modalEmailVerified">
                                <label class="form-check-label" for="modalEmailVerified">
                                    Email Verified
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="phoneVerified" value="1" id="modalPhoneVerified">
                                <label class="form-check-label" for="modalPhoneVerified">
                                    Phone Verified
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addUserForm = document.getElementById('addUserForm');
    const passwordField = document.getElementById('modalPassword');
    const confirmPasswordField = document.getElementById('modalConfirmPassword');
    const passwordError = document.getElementById('modalPasswordError');
    const submitBtn = document.getElementById('modalSubmitBtn');
    
    // Check password match on form submission
    addUserForm.addEventListener('submit', function(e) {
        const password = passwordField.value;
        const confirmPassword = confirmPasswordField.value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            confirmPasswordField.classList.add('is-invalid');
            passwordError.style.display = 'block';
            return false;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';
        submitBtn.disabled = true;
        
        return true;
    });
    
    // Clear password error when user types
    confirmPasswordField.addEventListener('input', function() {
        if (this.classList.contains('is-invalid')) {
            this.classList.remove('is-invalid');
            passwordError.style.display = 'none';
        }
    });
    
    // Reset form when modal is hidden
    const addUserModal = document.getElementById('addUserModal');
    addUserModal.addEventListener('hidden.bs.modal', function () {
        addUserForm.reset();
        submitBtn.innerHTML = 'Create User';
        submitBtn.disabled = false;
        if (confirmPasswordField.classList.contains('is-invalid')) {
            confirmPasswordField.classList.remove('is-invalid');
            passwordError.style.display = 'none';
        }
    });
    
    // Show/hide student/teacher number field based on role
    const roleSelect = document.getElementById('modalRole');
    const userNumberField = document.getElementById('modalUserNumber');
    
    function updateNumberField() {
        if (roleSelect.value === 'student') {
            userNumberField.placeholder = 'Student Number (e.g., 2023-00361-ST-0)';
        } else if (roleSelect.value === 'instructor') {
            userNumberField.placeholder = 'Teacher Number';
        } else {
            userNumberField.placeholder = 'Not applicable for admin';
        }
    }
    
    roleSelect.addEventListener('change', updateNumberField);
    updateNumberField(); // Initialize on page load
});
</script>

<?php include 'includes/footer.php'; ?>