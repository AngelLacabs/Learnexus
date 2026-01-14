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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }

        /* Main Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Left side like the image */
        .sidebar {
            width: 250px;
            background: white;
            padding: 30px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 25px 30px;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 30px;
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3436;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 0 20px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: #636e72;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
        }

        .menu-item:hover {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.2);
        }

        .menu-item i {
            font-size: 18px;
            width: 24px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            padding: 0 25px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        /* Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 8px;
        }

        .header-left p {
            color: #636e72;
            font-size: 16px;
        }

        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: transform 0.2s;
            border: 1px solid #f0f0f0;
        }

        .user-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 12px;
            color: #666;
        }

        /* Settings Layout */
        .settings-layout {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .settings-header {
            text-align: center;
            padding: 20px 0;
            margin-bottom: 40px;
            border-bottom: 1px solid #eaeaea;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 5px;
        }

        .profile-role {
            font-size: 14px;
            color: #636e72;
            margin-bottom: 15px;
        }

        .avatar-form {
            margin-top: 15px;
        }

        .btn-change-avatar {
            background: #f8f9fa;
            color: #374151;
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-change-avatar:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }

        #avatarInput {
            display: none;
        }

        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            padding: 0 0 15px 0;
        }

        .settings-tab {
            padding: 12px 24px;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            border-radius: 6px 6px 0 0;
        }

        .settings-tab.active {
            color: #7d4fab;
            border-bottom-color: #7d4fab;
            background: rgba(125, 79, 171, 0.05);
        }

        .settings-tab:hover:not(.active) {
            color: #7d4fab;
            background: rgba(125, 79, 171, 0.05);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #2d3436;
        }

        .section-subtitle {
            color: #636e72;
            font-size: 15px;
            margin-bottom: 30px;
        }

        /* Forms */
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
            width: 100%;
        }

        .form-control:focus {
            outline: none;
            border-color: #7d4fab;
            box-shadow: 0 0 0 3px rgba(125, 79, 171, 0.1);
        }

        .form-text {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        /* Password Input Groups */
        .input-group {
            display: flex;
        }

        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group .btn-password-toggle {
            background: white;
            border: 1px solid #e5e7eb;
            border-left: none;
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            padding: 0 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        .input-group .btn-password-toggle:hover {
            background: #f8f9fa;
        }

        /* Buttons */
        .btn-save {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.3);
        }

        /* Danger Zone */
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

        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* UPDATED: Simple Red Hover Logout Button */
        .btn-logout-fixed {
            position: fixed;
            bottom: 40px;
            right: 40px;
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
            padding: 12px 24px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 998;
            overflow: hidden;
            text-decoration: none;
        }

        .btn-logout-fixed:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        /* UPDATED: Sidebar Logout Button - Simple Red Hover */
        .menu-item.logout-item {
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
            margin: 10px 16px;
            border-radius: 20px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item.logout-item:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }
            
            .sidebar-title, .menu-item span, .user-info h4, .user-info p {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-profile {
                align-self: flex-start;
            }
            
            .settings-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 36px;
            }
            
            .btn-logout-fixed {
                bottom: 20px;
                right: 20px;
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">LEARNEXUS</div>
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
                <a href="../logout.php" class="menu-item logout-item">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <h1>Settings</h1>
                    <p>Manage your account settings and preferences</p>
                </div>
                
                <!-- User Profile -->
                <div class="user-profile" onclick="window.location.href='settings.php'">
                    <div class="user-avatar">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h4>
                        <p>Teacher</p>
                    </div>
                </div>
            </div>

            <!-- Settings Layout -->
            <div class="settings-layout">
                <!-- Profile Header -->
                <div class="settings-header">
                    <div class="profile-avatar">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></div>
                    <div class="profile-role">Teacher</div>
                    
                    <form method="POST" enctype="multipart/form-data" id="avatarForm" class="avatar-form">
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        <button type="button" class="btn-change-avatar" onclick="document.getElementById('avatarInput').click()">
                            <i class="bi bi-camera"></i> Change Avatar
                        </button>
                    </form>
                </div>

                <!-- Tabs -->
                <div class="settings-tabs">
                    <div class="settings-tab active" data-tab="personal">Personal Information</div>
                    <div class="settings-tab" data-tab="security">Password & Security</div>
                </div>

                <!-- Personal Information Tab -->
                <div id="personal-tab" class="tab-content active">
                    <div class="section-title">Personal Information</div>
                    <div class="section-subtitle">Update your personal details and information.</div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_info" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input type="text" name="firstName" class="form-control" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Initial</label>
                                <input type="text" name="middleInitial" class="form-control" maxlength="5" value="<?php echo htmlspecialchars($user['middleInitial']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="lastName" class="form-control" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                <div class="form-text">
                                    <i class="bi bi-envelope"></i> Email notifications will be sent to this address
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" maxlength="11" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                <div class="form-text">
                                    <i class="bi bi-telephone"></i> SMS notifications
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn-save">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password & Security Tab -->
                <div id="security-tab" class="tab-content">
                    <div class="section-title">Password and Security</div>
                    <div class="section-subtitle">Manage your password and login settings.</div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" name="currentPassword" class="form-control" id="currentPassword" required>
                                    <button class="btn-password-toggle" type="button" onclick="togglePassword('currentPassword')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" name="newPassword" class="form-control" id="newPassword" required>
                                    <button class="btn-password-toggle" type="button" onclick="togglePassword('newPassword')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirmPassword" class="form-control" id="confirmPassword" required>
                                    <button class="btn-password-toggle" type="button" onclick="togglePassword('confirmPassword')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn-save">
                                <i class="bi bi-key"></i> Update Password
                            </button>
                        </div>
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

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
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

        // Tab functionality
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const tabId = this.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabId + '-tab').classList.add('active');
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