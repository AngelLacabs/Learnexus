<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $uploadDir = '../uploads/avatars/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['avatar'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($fileExt, $allowed)) {
        if ($file['error'] === 0) {
            if ($file['size'] < 5000000) {
                $newFileName = 'avatar_' . $userID . '_' . time() . '.' . $fileExt;
                $fileDestination = $uploadDir . $newFileName;
                
                if (!empty($user['avatar']) && file_exists($user['avatar'])) {
                    unlink($user['avatar']);
                }
                
                if (move_uploaded_file($file['tmp_name'], $fileDestination)) {
                    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE userID = ?");
                    $stmt->execute([$fileDestination, $userID]);
                    $_SESSION['success'] = "Avatar updated successfully!";
                    header("Location: settings.php");
                    exit();
                    
                    $stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
                    $stmt->execute([$userID]);
                    $user = $stmt->fetch();
                } else {
                    $error = "Failed to upload avatar.";
                }
            } else {
                $error = "File is too large. Maximum size is 5MB.";
            }
        } else {
            $error = "Error uploading file.";
        }
    } else {
        $error = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
    }
}

// Handle personal info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $firstName = trim($_POST['firstName']);
    $middleInitial = trim($_POST['middleInitial']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET firstName = ?, middleInitial = ?, lastName = ?, email = ?, phone = ?
        WHERE userID = ?
    ");
    $stmt->execute([$firstName, $middleInitial, $lastName, $email, $phone, $userID]);
    
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    
    $success = "Personal information updated successfully!";
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
    $stmt->execute([$userID]);
    $user = $stmt->fetch();
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    
    if (password_verify($currentPassword, $user['passwordHash'])) {
        if ($newPassword === $confirmPassword) {
            if (strlen($newPassword) >= 6) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET passwordHash = ? WHERE userID = ?");
                $stmt->execute([$newHash, $userID]);
                $success = "Password updated successfully!";
                
                $stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
                $stmt->execute([$userID]);
                $user = $stmt->fetch();
            } else {
                $error = "Password must be at least 6 characters";
            }
        } else {
            $error = "New passwords do not match";
        }
    } else {
        $error = "Current password is incorrect";
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirmPassword = $_POST['deletePassword'];
    
    if (password_verify($confirmPassword, $user['passwordHash'])) {
        // Delete avatar file if exists
        if (!empty($user['avatar']) && file_exists($user['avatar'])) {
            unlink($user['avatar']);
        }
        
        // Delete user account
        $stmt = $conn->prepare("DELETE FROM users WHERE userID = ?");
        if ($stmt->execute([$userID])) {
            // Destroy session
            session_destroy();
            
            // Start new session for redirect message
            session_start();
            $_SESSION['account_deleted'] = true;
            
            // Redirect immediately
            header('Location: ../index.php');
            exit();
        } else {
            $error = "Failed to delete account. Please try again.";
        }
    } else {
        $error = "Incorrect password. Account deletion cancelled.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .top-nav { background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .brand { font-size: 20px; font-weight: 700; color: #1a73e8; }
        .nav-menu { display: flex; gap: 30px; }
        .nav-link { color: #666; text-decoration: none; font-weight: 500; }
        .nav-link:hover { color: #1a73e8; }
        .container-main { max-width: 1000px; margin: 40px auto; padding: 0 40px; }
        .settings-layout { display: grid; grid-template-columns: 250px 1fr; gap: 30px; }
        .settings-sidebar { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); height: fit-content; }
        .avatar-section { text-align: center; padding: 20px 0; border-bottom: 1px solid #e0e0e0; margin-bottom: 20px; }
        .avatar { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: 600; margin: 0 auto 15px; overflow: hidden; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { padding: 12px 15px; cursor: pointer; border-radius: 8px; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .sidebar-menu li:hover { background: #f5f5f5; }
        .sidebar-menu li.active { background: #e3f2fd; color: #1a73e8; }
        .settings-content { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-title { font-size: 20px; font-weight: 600; margin-bottom: 5px; }
        .section-subtitle { color: #666; font-size: 14px; margin-bottom: 20px; }
        .form-label { font-weight: 500; margin-bottom: 8px; }
        .form-control { padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .btn-save { background: #1e88e5; color: white; padding: 10px 30px; border: none; border-radius: 8px; font-weight: 500; }
        .btn-save:hover { background: #1565c0; }
        .btn-logout { background: #f5f5f5; color: #666; padding: 10px 20px; border: none; border-radius: 8px; width: 100%; margin-top: 20px; }
        .btn-delete { background: #dc3545; color: white; padding: 10px 30px; border: none; border-radius: 8px; font-weight: 500; margin-top: 30px; }
        .btn-delete:hover { background: #c82333; }
        .danger-zone { border-top: 2px solid #fee; padding-top: 30px; margin-top: 40px; }
        .danger-zone-title { color: #dc3545; font-weight: 600; margin-bottom: 10px; }
        #avatarInput { display: none; }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="dashboard.php" class="brand text-decoration-none">
            LEARNEXUS
        </a>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="courses.php" class="nav-link">Courses</a>
            <a href="quizzes.php" class="nav-link">Quizzes</a>
            <a href="enrollees.php" class="nav-link">Enrollees</a>
        </div>
    </div>

    <div class="container-main">
        <div class="settings-layout">
            <div class="settings-sidebar">
                <div class="avatar-section">
                    <div class="avatar">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <h6 class="mb-0"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h6>
                    <small class="text-muted">Teacher ID: <?php echo htmlspecialchars($user['teacherNumber']); ?></small>
                    
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="document.getElementById('avatarInput').click()">
                            Change Avatar
                        </button>
                    </form>
                </div>
                
                <ul class="sidebar-menu">
                    <li class="active" data-tab="personal">
                        <i class="bi bi-person"></i> Personal Information
                    </li>
                    <li data-tab="security">
                        <i class="bi bi-lock"></i> Password & Security
                    </li>
                </ul>
                
                <button class="btn-logout" onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </button>
            </div>

            <div class="settings-content">
                <div class="tab-content active" id="personal-tab">
                    <div class="section-title">Personal Information</div>
                    <div class="section-subtitle">Update your personal details and information.</div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_info" value="1">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="firstName" class="form-control" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">M.I.</label>
                                <input type="text" name="middleInitial" class="form-control" maxlength="5" value="<?php echo htmlspecialchars($user['middleInitial']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="lastName" class="form-control" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> Email notifications will be sent to this address
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" maxlength="11" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            <small class="text-muted">
                                <i class="bi bi-telephone"></i> SMS notifications
                            </small>
                        </div>
                        
                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>

                <div class="tab-content" id="security-tab" style="display: none;">
                    <div class="section-title">Password and Security</div>
                    <div class="section-subtitle">Manage your password and login settings.</div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="currentPassword" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="newPassword" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Min. 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" name="confirmPassword" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-save">Update Password</button>
                    </form>
                    
                    <!-- Danger Zone -->
                    <div class="danger-zone">
                        <div class="danger-zone-title">
                            <i class="bi bi-exclamation-triangle"></i> Danger Zone
                        </div>
                        <p class="text-muted mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                        <button type="button" class="btn-delete" onclick="confirmDeleteAccount()">
                            <i class="bi bi-trash"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDeleteAccount() {
            Swal.fire({
                title: 'Delete Account?',
                html: `
                    <p style="color: #666; margin-bottom: 20px;">This action cannot be undone! All your data will be permanently deleted.</p>
                    <label style="display: block; text-align: left; margin-bottom: 8px; font-weight: 500;">Enter your password to confirm</label>
                    <div style="position: relative;">
                        <input type="password" id="deletePasswordInput" class="swal2-input" placeholder="Your password" style="width: 100%; padding-right: 45px;">
                        <button type="button" onclick="toggleDeletePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 5px;">
                            <i class="bi bi-eye" id="deletePasswordIcon" style="font-size: 18px; color: #666;"></i>
                        </button>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete my account',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const password = document.getElementById('deletePasswordInput').value;
                    if (!password) {
                        Swal.showValidationMessage('Password is required');
                    }
                    return password;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="delete_account" value="1">
                        <input type="hidden" name="deletePassword" value="${result.value}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function toggleDeletePassword() {
            const input = document.getElementById('deletePasswordInput');
            const icon = document.getElementById('deletePasswordIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        function togglePassword(button) {
            const input = button.previousElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        document.querySelectorAll('.sidebar-menu li').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
                this.classList.add('active');
                
                const tab = this.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                document.getElementById(tab + '-tab').style.display = 'block';
            });
        });

        <?php if (isset($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo $success; ?>',
            confirmButtonText: 'OK'
        });
        <?php endif; ?>

        <?php if (isset($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo $error; ?>'
        });
        <?php endif; ?>
    </script>
</body>
</html>