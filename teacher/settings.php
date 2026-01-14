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
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Sidebar - Matching student design */
        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation - Matching student design */
        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: #1a73e8;
            border-radius: 0 4px 4px 0;
            transition: height 0.25s ease;
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active::before {
            display: none;
        }

        /* Hamburger - Matching student design */
        .hamburger-btn {
            width: 50px;
            height: 50px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .hamburger-icon span {
            display: block;
            width: 24px;
            height: 3px;
            background: #1a73e8;
            border-radius: 3px;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        /* Main Content Margin */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Cards - Updated to match student design */
        .stat-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Settings Cards */
        .settings-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .settings-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Search Input */
        .search-input {
            border: 1px solid #dee2e6;
            background: rgba(255, 255, 255, 0.9);
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .search-icon {
            color: #6c757d;
        }

        /* User Avatar */
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* Status Tabs */
        .status-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
        }

        .status-tab:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .status-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Action Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            color: white;
        }

        /* Profile Avatar */
        .profile-avatar-lg {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            margin: 0 auto 20px;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        /* Form Styling */
        .form-label {
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        /* Password Toggle */
        .password-toggle {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: none;
            border-radius: 0 12px 12px 0;
            padding: 0 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .password-toggle:hover {
            background: #e9ecef;
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
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
    </style>
</head>
<body>
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

    <!-- Sidebar -->
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100" style="width: var(--sidebar-width);" id="sidebar">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>
            
            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="courses.php">
                    <i class="bi bi-book fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
                    <i class="bi bi-patch-question fs-5"></i><span>Quizzes</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="enrollees.php">
                    <i class="bi bi-people fs-5"></i><span>Enrollees</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
            </nav>
            
            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold" onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid">
            <!-- Header with Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h1 class="h3 fw-bold mb-1"><i class="bi bi-gear me-2"></i>Settings</h1>
                                <p class="text-muted mb-0">Manage your account settings and preferences</p>
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold user-avatar">
                                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" 
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

            <!-- Profile Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card settings-card">
                        <div class="card-body p-4 text-center">
                            <div class="profile-avatar-lg">
                                <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h3>
                            <p class="text-muted mb-3"><i class="bi bi-award me-1"></i> Teacher</p>
                            
                            <form method="POST" enctype="multipart/form-data" id="avatarForm" class="avatar-form">
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="document.getElementById('avatarForm').submit()" class="d-none">
                                <button type="button" class="btn btn-outline-primary rounded-pill px-4" onclick="document.getElementById('avatarInput').click()">
                                    <i class="bi bi-camera me-2"></i> Change Avatar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="status-tab active" onclick="showTab('personal')">
                                    <i class="bi bi-person me-1"></i> Personal Information
                                </button>
                                <button class="status-tab" onclick="showTab('security')">
                                    <i class="bi bi-shield-lock me-1"></i> Password & Security
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information Tab -->
            <div class="row" id="personal">
                <div class="col-12">
                    <div class="card settings-card">
                        <div class="card-body p-4">
                            <h4 class="fw-bold mb-4"><i class="bi bi-person me-2"></i> Personal Information</h4>
                            <p class="text-muted mb-4">Update your personal details and information.</p>
                            
                            <form method="POST">
                                <input type="hidden" name="update_info" value="1">
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="firstName" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle Initial</label>
                                        <input type="text" name="middleInitial" class="form-control" maxlength="5" 
                                               value="<?php echo htmlspecialchars($user['middleInitial']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="lastName" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Email address</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <small class="text-muted">
                                            <i class="bi bi-envelope me-1"></i> Email notifications will be sent to this address
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="phone" class="form-control" maxlength="11" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                                        <small class="text-muted">
                                            <i class="bi bi-telephone me-1"></i> SMS notifications
                                        </small>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient rounded-pill px-4">
                                    <i class="bi bi-check-circle me-2"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password & Security Tab -->
            <div class="row d-none" id="security">
                <div class="col-12">
                    <div class="card settings-card">
                        <div class="card-body p-4">
                            <h4 class="fw-bold mb-4"><i class="bi bi-shield-lock me-2"></i> Password and Security</h4>
                            <p class="text-muted mb-4">Manage your password and login settings.</p>
                            
                            <form method="POST">
                                <input type="hidden" name="update_password" value="1">
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" name="currentPassword" class="form-control" 
                                                   id="currentPassword" required>
                                            <button class="password-toggle" type="button" onclick="togglePassword('currentPassword')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="newPassword" class="form-control" 
                                                   id="newPassword" required>
                                            <button class="password-toggle" type="button" onclick="togglePassword('newPassword')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm Password</label>
                                        <div class="input-group">
                                            <input type="password" name="confirmPassword" class="form-control" 
                                                   id="confirmPassword" required>
                                            <button class="password-toggle" type="button" onclick="togglePassword('confirmPassword')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-gradient rounded-pill px-4">
                                    <i class="bi bi-key me-2"></i> Update Password
                                </button>
                            </form>
                            
                            <!-- Danger Zone -->
                            <div class="danger-zone">
                                <h5 class="danger-zone-title">
                                    <i class="bi bi-exclamation-triangle"></i> Danger Zone
                                </h5>
                                <p class="text-muted mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                                <button type="button" class="btn btn-delete rounded-pill px-4" onclick="confirmDeleteAccount()">
                                    <i class="bi bi-trash me-2"></i> Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hamburger animation
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');

if (hamburgerBtn && sidebar) {
    sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
    sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
}

// Active nav state
const navLinks = document.querySelectorAll('.sidebar .nav-link');
const currentPage = window.location.pathname.split('/').pop();

navLinks.forEach(link => {
    if (link.getAttribute('href') === currentPage) {
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
    }
    
    // Close sidebar on mobile after click
    link.addEventListener('click', () => {
        if (window.innerWidth <= 992) {
            const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
            if (offcanvas) offcanvas.hide();
        }
    });
});

// Tab switching function
function showTab(tabId) {
    // Update active tab button
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Show/hide tab contents
    const personalTab = document.getElementById('personal');
    const securityTab = document.getElementById('security');
    
    if (tabId === 'personal') {
        personalTab.classList.remove('d-none');
        securityTab.classList.add('d-none');
    } else {
        personalTab.classList.add('d-none');
        securityTab.classList.remove('d-none');
    }
}

// Password toggle function
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

// Delete account confirmation
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

// Show success/error messages
<?php if (isset($success)): ?>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: '<?php echo $success; ?>',
    confirmButtonText: 'OK',
    confirmButtonColor: '#667eea'
});
<?php endif; ?>

<?php if (isset($error)): ?>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo $error; ?>',
    confirmButtonColor: '#667eea'
});
<?php endif; ?>
</script>
</body>
</html>