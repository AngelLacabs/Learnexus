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
} catch (PDOException $e) {
    error_log("User Edit Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading user details';
    header('Location: users.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $middleInitial = trim($_POST['middleInitial']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $emailVerified = isset($_POST['emailVerified']) ? 1 : 0;
    $phoneVerified = isset($_POST['phoneVerified']) ? 1 : 0;
    
    // Role-specific fields
    if ($role === 'student') {
        $studentNumber = trim($_POST['studentNumber']);
        $teacherNumber = null;
    } elseif ($role === 'instructor') {
        $teacherNumber = trim($_POST['teacherNumber']);
        $studentNumber = null;
    } else {
        $studentNumber = null;
        $teacherNumber = null;
    }
    
    try {
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT userID FROM users WHERE email = ? AND userID != ?");
        $stmt->execute([$email, $userID]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Email already taken by another user';
        } else {
            // Update user
            $stmt = $conn->prepare("
                UPDATE users SET 
                    firstName = ?,
                    lastName = ?,
                    middleInitial = ?,
                    email = ?,
                    phone = ?,
                    role = ?,
                    status = ?,
                    emailVerified = ?,
                    phoneVerified = ?,
                    studentNumber = ?,
                    teacherNumber = ?
                WHERE userID = ?
            ");
            
            $stmt->execute([
                $firstName,
                $lastName,
                $middleInitial,
                $email,
                $phone,
                $role,
                $status,
                $emailVerified,
                $phoneVerified,
                $studentNumber,
                $teacherNumber,
                $userID
            ]);
            
            $_SESSION['success'] = 'User updated successfully!';
            header("Location: user_view.php?id=$userID");
            exit();
        }
    } catch (PDOException $e) {
        error_log("User Update Error: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating user: ' . $e->getMessage();
    }
}

$page_title = "Edit User - " . $user['firstName'] . ' ' . $user['lastName'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="bg-white rounded-3 shadow-sm p-3 w-100">
                <div class="d-flex align-items-center">
                    <a href="user_view.php?id=<?php echo $userID; ?>" class="btn btn-outline-secondary me-3" id="backButton">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div class="flex-grow-1">
                        <h1 class="h3 mb-0">Edit User</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" class="fw-bold text-primary">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="users.php" class="fw-bold text-primary">Users</a></li>
                                <li class="breadcrumb-item"><a href="user_view.php?id=<?php echo $userID; ?>" class="fw-bold text-primary"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></a></li>
                                <li class="breadcrumb-item active text-dark" aria-current="page">Edit</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold">
                            <i class="bi bi-pencil-square me-2"></i>Edit User Information
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <!-- Personal Information Section -->
                            <div class="mb-4">
                                <h6 class="text-muted mb-3 border-bottom pb-2">Personal Information</h6>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="firstName" 
                                               value="<?php echo htmlspecialchars($user['firstName']); ?>" 
                                               required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="lastName" 
                                               value="<?php echo htmlspecialchars($user['lastName']); ?>" 
                                               required>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label">Middle Initial</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="middleInitial" 
                                               value="<?php echo htmlspecialchars($user['middleInitial']); ?>"
                                               maxlength="5">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Account Information Section -->
                            <div class="mb-4">
                                <h6 class="text-muted mb-3 border-bottom pb-2">Account Information</h6>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" 
                                               class="form-control" 
                                               name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" 
                                               class="form-control" 
                                               name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Role *</label>
                                        <select class="form-select" name="role" required>
                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="instructor" <?php echo $user['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Status *</label>
                                        <select class="form-select" name="status" required>
                                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Role-specific fields -->
                            <div id="studentFields" style="display: <?php echo $user['role'] === 'student' ? 'block' : 'none'; ?>;" class="mb-4">
                                <h6 class="text-muted mb-3 border-bottom pb-2">Student Information</h6>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Student Number</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="studentNumber" 
                                               value="<?php echo htmlspecialchars($user['studentNumber'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div id="instructorFields" style="display: <?php echo $user['role'] === 'instructor' ? 'block' : 'none'; ?>;" class="mb-4">
                                <h6 class="text-muted mb-3 border-bottom pb-2">Instructor Information</h6>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Teacher Number</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="teacherNumber" 
                                               value="<?php echo htmlspecialchars($user['teacherNumber'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Verification Section -->
                            <div class="mb-4">
                                <h6 class="text-muted mb-3 border-bottom pb-2">Verification Status</h6>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="emailVerified" 
                                                   value="1" 
                                                   id="emailVerified"
                                                   role="switch"
                                                   <?php echo $user['emailVerified'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="emailVerified">
                                                Email Verified
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="phoneVerified" 
                                                   value="1" 
                                                   id="phoneVerified"
                                                   role="switch"
                                                   <?php echo $user['phoneVerified'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="phoneVerified">
                                                Phone Verified
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                                <a href="user_view.php?id=<?php echo $userID; ?>" class="btn btn-secondary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; font-weight: 500;">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; color: white; font-weight: 500;">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Additional Actions Sidebar -->
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold">
                            <i class="bi bi-gear me-2"></i>Additional Actions
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <div class="d-grid gap-2">
                            <!-- Reset Password -->
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                <i class="bi bi-key me-2"></i>Reset Password
                            </button>
                            
                            <!-- Delete User -->
                            <a href="user_actions.php?action=delete&id=<?php echo $userID; ?>" 
                               class="btn btn-outline-danger"
                               data-confirm-delete="Are you sure you want to delete this user? This will permanently delete all their data including courses, enrollments, and payments."
                               onclick="return confirm('Are you sure you want to delete this user? This will permanently delete all their data including courses, enrollments, and payments.');">
                                <i class="bi bi-trash me-2"></i>Delete User
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

@media (max-width: 991.98px) {
    .col-lg-4.mt-4.mt-lg-0 {
        margin-top: 1.5rem !important;
    }
}

@media (max-width: 575.98px) {
    .d-grid.gap-2 .btn {
        width: 100%;
    }
}

@media (max-width: 767.98px) {
    .card-body.p-4 {
        padding: 1.5rem !important;
    }
    
    .text-white.fw-bold[style*="font-size: 1.25rem"] {
        font-size: 1rem !important;
    }
    
    .bi[style*="font-size: 4rem"] {
        font-size: 3rem !important;
    }
}

@media (max-width: 575.98px) {
    .card-body.p-4 {
        padding: 1rem !important;
    }
    
    .text-white.fw-bold[style*="font-size: 1.25rem"] {
        font-size: 0.9rem !important;
    }
    
    .bi[style*="font-size: 4rem"] {
        font-size: 2.5rem !important;
    }
    
    h4.text-white.fw-bold {
        font-size: 1.25rem !important;
    }
}

.btn[style*="linear-gradient"] {
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn[style*="linear-gradient"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    opacity: 0.9;
}

.btn[style*="linear-gradient"]:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

.form-control,
.form-select {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus,
.form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.form-check-input {
    margin-top: 0.35rem;
}

.form-check-label {
    margin-left: 0.5rem;
}

h6.text-muted {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d !important;
}

@media (max-width: 767.98px) {
    .row.g-3 {
        --bs-gutter-y: 1rem;
    }
    
    .mb-4 {
        margin-bottom: 1.5rem !important;
    }
}

@media (max-width: 575.98px) {
    .form-label {
        font-size: 0.9rem;
    }
    
    .form-control,
    .form-select {
        font-size: 0.95rem;
        padding: 0.5rem 0.75rem;
    }
    
    h6.text-muted {
        font-size: 0.8rem;
    }

    .bg-white.rounded-3.shadow-sm.p-3 {
        padding: 1rem !important;
    }
    
    .h3.mb-0 {
        font-size: 1.25rem;
    }
    
    .breadcrumb {
        font-size: 0.85rem;
    }
    
    #backButton.btn-outline-secondary {
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .breadcrumb-item a.fw-bold.text-primary {
        font-weight: 600 !important;
        color: #0d6efd !important;
    }
    
    .breadcrumb-item.active.text-dark {
        color: #212529 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    const studentFields = document.getElementById('studentFields');
    const instructorFields = document.getElementById('instructorFields');
    
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'student') {
                studentFields.style.display = 'block';
                instructorFields.style.display = 'none';
            } else if (this.value === 'instructor') {
                studentFields.style.display = 'none';
                instructorFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
                instructorFields.style.display = 'none';
            }
        });
    }
    
    [studentFields, instructorFields].forEach(field => {
        if (field) {
            field.style.transition = 'opacity 0.3s ease';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>