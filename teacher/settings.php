<?php
// ========================================
// FILE: teacher/settings.php (and student/settings.php - same code)
// User Settings Page
// ========================================
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .top-nav {
            background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .container-main {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .settings-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        .settings-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
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
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 600;
            margin: 0 auto 15px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            padding: 12px 15px;
            cursor: pointer;
            border-radius: 8px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }
        
        .sidebar-menu li:hover {
            background: #f5f5f5;
        }
        
        .sidebar-menu li.active {
            background: #e3f2fd;
            color: #1a73e8;
        }
        
        .settings-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .section-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .btn-save {
            background: #1e88e5;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-save:hover {
            background: #1565c0;
        }
        
        .btn-logout {
            background: #f5f5f5;
            color: #666;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            width: 100%;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div style="font-size: 20px; font-weight: 700; color: #1a73e8;">LEARNEXUS</div>
        <div>
            <a href="dashboard.php" class="me-3 text-decoration-none text-muted">Dashboard</a>
            <?php if ($role == 'instructor'): ?>
                <a href="courses.php" class="me-3 text-decoration-none text-muted">Courses</a>
                <a href="quizzes.php" class="me-3 text-decoration-none text-muted">Quizzes</a>
                <a href="enrollees.php" class="me-3 text-decoration-none text-muted">Enrollees</a>
            <?php else: ?>
                <a href="my_courses.php" class="me-3 text-decoration-none text-muted">My Courses</a>
                <a href="certificates.php" class="me-3 text-decoration-none text-muted">Certificates</a>
            <?php endif; ?>
        </div>
        <span style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
    </div>

    <!-- Main Content -->
    <div class="container-main">
        <div class="settings-layout">
            <!-- Sidebar -->
            <div class="settings-sidebar">
                <div class="avatar-section">
                    <div class="avatar">
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                    </div>
                    <h6 class="mb-0"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h6>
                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                    <button class="btn btn-sm btn-outline-primary mt-2">Change Avatar</button>
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

            <!-- Content Area -->
            <div class="settings-content">
                <!-- Personal Information Tab -->
                <div class="tab-content active" id="personal-tab">
                    <div class="section-title">Personal Information</div>
                    <div class="section-subtitle">Update your personal details and information.</div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_info" value="1">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="firstName" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">M.I.</label>
                                    <input type="text" name="middleInitial" class="form-control" maxlength="5"
                                           value="<?php echo htmlspecialchars($user['middleInitial']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="lastName" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> Email notifications will be sent to this address
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" maxlength="11"
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                            <small class="text-muted">
                                <i class="bi bi-telephone"></i> SMS notifications will be sent to this number
                            </small>
                        </div>
                        
                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>

                <!-- Password & Security Tab -->
                <div class="tab-content" id="security-tab" style="display: none;">
                    <div class="section-title">Password and Security</div>
                    <div class="section-subtitle">Manage your password and login settings.</div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="currentPassword" class="form-control" id="currentPass" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPass')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="newPassword" class="form-control" id="newPass" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPass')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Min. 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" name="confirmPassword" class="form-control" id="confirmPass" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPass')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-save">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.sidebar-menu li').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
                this.classList.add('active');
                
                const tab = this.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                document.getElementById(tab + '-tab').style.display = 'block';
            });
        });

        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Success message
        <?php if (isset($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo $success; ?>',
            timer: 2000
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