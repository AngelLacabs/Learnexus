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

        /* Settings Specific Styles - Matching student design */
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .avatar-upload-section {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .avatar-circle {
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
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #667eea;
        }

        .avatar-upload-btn:hover {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }

        /* Status Tabs - Matching enrollees.php */
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

        .form-control {
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .danger-zone {
            border: 2px solid #fee;
            border-radius: 16px;
            padding: 30px;
            background: #fff5f5;
            margin-top: 40px;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }

        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #e8eaf6 100%);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .tab-content-section {
            display: none;
        }

        .tab-content-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .input-group .btn {
            border-radius: 0 12px 12px 0;
        }

        #avatarInput {
            display: none;
        }

        /* User Avatar in Header */
        .user-avatar-header {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        /* Settings Card */
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
        <div class="container-fluid" style="max-width: 1200px;">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Settings</h4>
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold user-avatar-header">
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

            <!-- Profile Header with Avatar -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm settings-header text-white">
                        <div class="card-body p-4 p-lg-5 text-center">
                            <div class="avatar-upload-section">
                                <div class="avatar-circle">
                                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                    <input type="file" name="avatar" id="avatarInput" accept="image/*"
                                           onchange="document.getElementById('avatarForm').submit()">
                                    <label for="avatarInput" class="avatar-upload-btn">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                </form>
                            </div>
                            <h3 class="fw-bold mt-3 mb-1"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h3>
                            <p class="mb-0 opacity-75">
                                <i class="bi bi-award me-2"></i>Teacher Account
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Tabs - Matching enrollees.php design -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="status-tab active" onclick="showTab('personal')">
                                    <i class="bi bi-person me-1"></i> Personal Information
                                </button>
                                <button class="status-tab" onclick="showTab('security')">
                                    <i class="bi bi-shield-lock me-1"></i> Security
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
                            <div class="info-card">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-info-circle fs-4 text-primary"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1">Keep your information up to date</h6>
                                        <p class="mb-0 small text-muted">Your personal information helps us provide you with a better teaching experience.</p>
                                    </div>
                                </div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="update_info" value="1">

                                <div class="row">
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-person text-primary me-2"></i>First Name
                                        </label>
                                        <input type="text" name="firstName" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label fw-semibold">M.I.</label>
                                        <input type="text" name="middleInitial" class="form-control" maxlength="5" 
                                               value="<?php echo htmlspecialchars($user['middleInitial']); ?>">
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-person text-primary me-2"></i>Last Name
                                        </label>
                                        <input type="text" name="lastName" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-envelope text-primary me-2"></i>Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <small class="text-muted">
                                        <i class="bi bi-shield-check me-1"></i>We'll send important notifications to this email
                                    </small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-telephone text-primary me-2"></i>Phone Number
                                    </label>
                                    <input type="text" name="phone" class="form-control" maxlength="11" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="09XXXXXXXXX">
                                    <small class="text-muted">
                                        <i class="bi bi-chat-dots me-1"></i>Optional - for SMS notifications
                                    </small>
                                </div>

                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-check-circle me-2"></i>Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="row d-none" id="security">
                <div class="col-12">
                    <div class="card settings-card">
                        <div class="card-body p-4">
                            <div class="info-card">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-shield-check fs-4 text-primary"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1">Protect your account</h6>
                                        <p class="mb-0 small text-muted">Use a strong password and update it regularly to keep your account secure.</p>
                                    </div>
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3">Change Password</h5>

                            <form method="POST">
                                <input type="hidden" name="update_password" value="1">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-key text-primary me-2"></i>Current Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="currentPassword" class="form-control" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-key-fill text-primary me-2"></i>New Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="newPassword" class="form-control" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>Minimum 6 characters
                                    </small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-check-circle text-primary me-2"></i>Confirm New Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="confirmPassword" class="form-control" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-save">
                                    <i class="bi bi-shield-check me-2"></i>Update Password
                                </button>
                            </form>

                            <!-- Danger Zone -->
                            <div class="danger-zone">
                                <h5 class="fw-bold text-danger mb-3">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Danger Zone
                                </h5>
                                <p class="text-muted mb-3">
                                    Once you delete your account, all your data including courses, students, and teaching history will be permanently removed. This action cannot be undone.
                                </p>
                                <button type="button" class="btn btn-delete" onclick="confirmDeleteAccount()">
                                    <i class="bi bi-trash me-2"></i>Delete My Account
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
function togglePassword(button) {
    const input = button.closest('.input-group').querySelector('input');
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
            <div class="text-start">
                <p class="text-secondary mb-3">
                    This action cannot be undone. All your data including:
                    <ul class="text-start ps-4 mb-3">
                        <li>Your personal information</li>
                        <li>Courses you created</li>
                        <li>Student enrollment data</li>
                        <li>Teaching history and statistics</li>
                    </ul>
                    will be permanently deleted.
                </p>
                <label class="form-label fw-medium mb-2 d-block">
                    <i class="bi bi-key me-2"></i>Enter your password to confirm
                </label>
                <div class="input-group">
                    <input type="password" id="deletePasswordInput" class="form-control" 
                           placeholder="Enter your current password">
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleDeletePassword()">
                        <i class="bi bi-eye" id="deletePasswordIcon"></i>
                    </button>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete My Account',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        allowOutsideClick: false,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const password = document.getElementById('deletePasswordInput').value;
            if (!password) {
                Swal.showValidationMessage('Please enter your password');
                return false;
            }
            return password;
        },
        didOpen: () => {
            const input = document.getElementById('deletePasswordInput');
            const button = input.nextElementSibling;
            if (button) {
                button.style.borderRadius = '0 8px 8px 0';
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const deleteAccountInput = document.createElement('input');
            deleteAccountInput.type = 'hidden';
            deleteAccountInput.name = 'delete_account';
            deleteAccountInput.value = '1';

            const passwordInput = document.createElement('input');
            passwordInput.type = 'hidden';
            passwordInput.name = 'deletePassword';
            passwordInput.value = result.value;

            form.appendChild(deleteAccountInput);
            form.appendChild(passwordInput);
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
    text: '<?php echo addslashes($success); ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
<?php endif; ?>

<?php if (isset($error)): ?>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo addslashes($error); ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
<?php endif; ?>
</script>
</body>
</html>