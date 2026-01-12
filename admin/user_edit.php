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
            <div class="d-flex align-items-center">
                <a href="user_view.php?id=<?php echo $userID; ?>" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">Edit User</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                            <li class="breadcrumb-item"><a href="user_view.php?id=<?php echo $userID; ?>"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Edit User Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="firstName" 
                                           value="<?php echo htmlspecialchars($user['firstName']); ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="lastName" 
                                           value="<?php echo htmlspecialchars($user['lastName']); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Middle Initial</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="middleInitial" 
                                           value="<?php echo htmlspecialchars($user['middleInitial']); ?>"
                                           maxlength="5">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Role *</label>
                                    <select class="form-select" name="role" required>
                                        <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="instructor" <?php echo $user['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" 
                                           class="form-control" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Role-specific fields -->
                            <div id="studentFields" style="display: <?php echo $user['role'] === 'student' ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Student Number</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="studentNumber" 
                                               value="<?php echo htmlspecialchars($user['studentNumber'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div id="instructorFields" style="display: <?php echo $user['role'] === 'instructor' ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Teacher Number</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="teacherNumber" 
                                               value="<?php echo htmlspecialchars($user['teacherNumber'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="emailVerified" 
                                               value="1" 
                                               id="emailVerified"
                                               <?php echo $user['emailVerified'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="emailVerified">
                                            Email Verified
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="phoneVerified" 
                                               value="1" 
                                               id="phoneVerified"
                                               <?php echo $user['phoneVerified'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="phoneVerified">
                                            Phone Verified
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-flex justify-content-between">
                                <a href="user_view.php?id=<?php echo $userID; ?>" class="btn btn-secondary">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    const studentFields = document.getElementById('studentFields');
    const instructorFields = document.getElementById('instructorFields');
    
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
});
</script>

<?php include 'includes/footer.php'; ?>