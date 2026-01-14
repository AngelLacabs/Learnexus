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
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f6fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 22px;
            font-weight: 700;
            color: white;
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .navbar-user:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: 700;
            overflow: hidden;
        }

        .navbar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            height: calc(100vh - 68px);
            background: white;
            position: fixed;
            left: 0;
            top: 68px;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            color: #374151;
        }
        
        .sidebar-menu {
            padding: 16px 0;
        }

        .menu-item {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 15px;
        }

        .menu-item:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .menu-item.active {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
        }

        .menu-item i {
            font-size: 20px;
            width: 24px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #e5e7eb;
            margin-top: auto;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 68px;
            padding: 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Settings Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            max-width: 1200px;
        }

        .settings-sidebar {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            height: fit-content;
        }

        .avatar-section {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: 600;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .settings-sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .settings-sidebar-menu li {
            padding: 14px 16px;
            cursor: pointer;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
            color: #6b7280;
            font-size: 15px;
        }

        .settings-sidebar-menu li:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .settings-sidebar-menu li.active {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
        }

        .settings-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .section-subtitle {
            color: #6b7280;
            font-size: 15px;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-logout {
            background: #f8f9fa;
            color: #374151;
            padding: 12px 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            width: 100%;
            margin-top: 20px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-logout:hover {
            background: #e9ecef;
            color: #dc3545;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 30px;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .danger-zone {
            border-top: 2px solid #fee;
            padding-top: 30px;
            margin-top: 40px;
        }

        .danger-zone-title {
            color: #dc3545;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-group-text {
            background: white;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }

        #avatarInput {
            display: none;
        }

        .btn-change-avatar {
            background: #f8f9fa;
            color: #374151;
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 10px;
            transition: all 0.2s;
        }

        .btn-change-avatar:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-navbar">
        <a href="dashboard.php" class="navbar-brand">LEARNEXUS</a>
        <div class="navbar-user" onclick="window.location.href='settings.php'">
            <span style="font-weight: 600;">
                <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
            </span>
            <div class="navbar-avatar">
                <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Teacher Panel</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="courses.php" class="menu-item">
                <i class="bi bi-book"></i>
                <span>Courses</span>
            </a>
            <a href="quizzes.php" class="menu-item">
                <i class="bi bi-patch-question"></i>
                <span>Quizzes</span>
            </a>
            <a href="enrollees.php" class="menu-item">
                <i class="bi bi-people"></i>
                <span>Enrollees</span>
            </a>
            <a href="settings.php" class="menu-item active">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-item">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
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
                    <h6 class="mb-1"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h6>
                    <small class="text-muted">Teacher ID: <?php echo htmlspecialchars($user['teacherNumber']); ?></small>
                    
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        <button type="button" class="btn-change-avatar" onclick="document.getElementById('avatarInput').click()">
                            <i class="bi bi-camera"></i> Change Avatar
                        </button>
                    </form>
                </div>
                
                <ul class="settings-sidebar-menu">
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
                                <label class="form-label">Middle Initial</label>
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
                            <label class="form-label">Phone Number</label>
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
                            <small class="text-muted">Minimum 6 characters</small>
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

        document.querySelectorAll('.settings-sidebar-menu li').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.settings-sidebar-menu li').forEach(li => li.classList.remove('active'));
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>