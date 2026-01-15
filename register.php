<?php
session_start();
$page_title = "Register - Learnexus";
include 'header.php';

$role = $_GET['role'] ?? '';
$valid_roles = ['student', 'instructor'];

if (!in_array($role, $valid_roles)) {
  $role = '';
}

$submitted_data = $_SESSION['register_data'] ?? [];
$session_error = $_SESSION['error'] ?? null;

if (isset($_SESSION['register_data'])) unset($_SESSION['register_data']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        overflow-x: hidden;
    }
    .container-fluid {
        height: 100vh;
        overflow: hidden;
    }
    .row.min-vh-100 {
        height: 100vh;
        margin: 0;
    }
    .left-side {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 100vh;
        position: sticky;
        top: 0;
        box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .left-side::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
        z-index: 1;
    }
    /* Background pattern matching index.php */
    .left-side::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
            radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
            radial-gradient(circle at 40% 60%, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
            radial-gradient(circle at 60% 40%, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
        background-size: 100px 100px, 120px 120px, 80px 80px, 90px 90px;
        background-position: 0 0, 60px 30px, 30px 60px, 90px 90px;
        z-index: 0;
        animation: floatBackground 20s infinite linear;
    }
    @keyframes floatBackground {
        0% { background-position: 0 0, 60px 30px, 30px 60px, 90px 90px; }
        100% { background-position: 100px 100px, 160px 130px, 130px 160px, 190px 190px; }
    }
    .register-container {
        max-width: 500px;
        width: 100%;
        background: white;
        border-radius: 20px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15), 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2.5rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        transform: perspective(1000px) rotateY(0deg);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin: auto;
    }
    .register-container:hover {
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2), 0 6px 24px rgba(0, 0, 0, 0.12);
        transform: perspective(1000px) rotateY(1deg);
    }
    .welcome-text {
        color: #333;
        font-weight: 800;
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
    }
    .register-title {
        color: #333;
        font-weight: 700;
        text-align: center;
    }
    .register-description {
        color: #666;
        line-height: 1.5;
        text-align: center;
    }
    .form-control {
        padding: 0.75rem 1rem;
        border: 2px solid #dee2e6;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.2), 0 4px 12px rgba(102, 126, 234, 0.1);
        background: white;
        transform: translateY(-2px);
    }
    .btn-register {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.75rem;
        border: none;
        border-radius: 12px;
        width: 100%;
        font-size: 1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        position: relative;
        overflow: hidden;
    }
    .btn-register:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }
    .btn-register:active {
        transform: translateY(-1px);
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
    }
    /* Glint effect for Next button (matching index.php) */
    #nextBtn, .btn-register[type="submit"] {
        position: relative;
        overflow: hidden;
    }
    #nextBtn::before, .btn-register[type="submit"]::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.7s;
        z-index: 1;
    }
    #nextBtn:hover::before, .btn-register[type="submit"]:hover::before {
        left: 100%;
    }
    .btn-back {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        color: #666;
        border: 2px solid #dee2e6;
        padding: 0.75rem;
        border-radius: 12px;
        width: 100%;
        font-size: 1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    .btn-back:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        border-color: #667eea;
    }
    .register-header {
        margin-bottom: 2rem;
    }
    .role-card {
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid #dee2e6;
        border-radius: 16px;
        overflow: hidden;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transform-style: preserve-3d;
        perspective: 1000px;
    }
    .role-card:hover {
        transform: translateY(-8px) rotateX(5deg);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15), 0 6px 20px rgba(0, 0, 0, 0.08);
        border-color: #667eea;
    }
    .role-card.selected {
        border-color: #667eea !important;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2), 0 4px 16px rgba(102, 126, 234, 0.1);
    }
    .logo-img {
        max-width: 550px;
        width: 85%;
        height: auto;
        filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.15));
        transform: perspective(1000px) rotateY(-5deg);
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 2;
        display: block;
    }
    /* Logo hover animation matching index.php */
    .logo-img:hover {
        transform: perspective(1000px) rotateY(5deg) scale(1.05);
        filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.25)) 
                drop-shadow(0 0 30px rgba(255, 255, 255, 0.3));
    }
    /* Logo glow effect */
    .logo-img::after {
        content: '';
        position: absolute;
        top: -10%;
        left: -10%;
        right: -10%;
        bottom: -10%;
        background: radial-gradient(circle at center, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.8s ease;
        z-index: -1;
    }
    .logo-img:hover::after {
        opacity: 1;
    }
    /* Logo pulse animation */
    @keyframes logoPulse {
        0% { filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.15)); }
        50% { filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.25)) 
                     drop-shadow(0 0 20px rgba(255, 255, 255, 0.4)); }
        100% { filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.15)); }
    }
    .logo-img:hover {
        animation: logoPulse 2s infinite;
    }
    .password-toggle {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 5px;
        font-size: 1rem;
        z-index: 10;
        transition: all 0.3s ease;
    }
    .password-toggle:hover {
        color: #667eea;
        transform: translateY(-50%) scale(1.1);
    }
    .password-input-group {
        position: relative;
    }
    .password-input-group input {
        padding-right: 45px;
    }
    .login-link {
        color: #666;
        text-align: center;
        margin-top: 1.5rem;
    }
    .login-link a {
        color: #667eea;
        font-weight: 600;
        text-decoration: none;
        position: relative;
        transition: all 0.3s ease;
    }
    .login-link a:hover {
        text-decoration: none;
        color: #764ba2;
    }
    .login-link a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transform: scaleX(0);
        transform-origin: right;
        transition: transform 0.3s ease;
    }
    .login-link a:hover::after {
        transform: scaleX(1);
        transform-origin: left;
    }
    .role-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 50%;
        box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2);
        transition: all 0.3s ease;
    }
    .role-card:hover .role-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
    }
    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #333;
    }
    .text-danger {
        font-size: 0.875rem;
        margin-top: 0.25rem;
        font-weight: 500;
    }
    .input-group .btn {
        border-left: 0;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    .input-group .btn:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    }
    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.3rem rgba(220, 53, 69, 0.15) !important;
    }
    
    /* 3D effect for the left side image container */
    .left-side .d-flex {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        width: 100%;
    }
    
    /* Floating animation for role cards */
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotateX(0deg); }
        50% { transform: translateY(-10px) rotateX(5deg); }
    }
    
    .role-card {
        animation: float 6s ease-in-out infinite;
    }
    
    .role-card:nth-child(2) {
        animation-delay: 1s;
    }
    
    /* Enhanced focus states */
    input:focus, button:focus {
        outline: none;
    }
    
    /* Subtle glow effect */
    .register-container::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(45deg, #667eea, #764ba2, #667eea);
        border-radius: 22px;
        z-index: -1;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .register-container:hover::before {
        opacity: 0.1;
    }
    
    /* Right side container */
    .col-12.col-md-6.d-flex {
        height: 100vh;
        overflow-y: auto;
        padding: 2rem;
        align-items: center;
    }
    
    /* Custom scrollbar for right side */
    .col-12.col-md-6.d-flex::-webkit-scrollbar {
        width: 6px;
    }
    
    .col-12.col-md-6.d-flex::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .col-12.col-md-6.d-flex::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .container-fluid {
            height: auto;
        }
        .row.min-vh-100 {
            height: auto;
            flex-direction: column;
        }
        .left-side {
            height: 40vh;
            position: relative;
        }
        .col-12.col-md-6.d-flex {
            height: auto;
            min-height: 60vh;
            padding: 1.5rem;
        }
        .logo-img {
            max-width: 400px;
            width: 80%;
        }
    }
</style>
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-12 col-md-6 left-side d-flex align-items-center justify-content-center p-3 p-md-5">
                <img src="images/Learnexus.png" alt="Learnexus Logo" class="logo-img">
            </div>

            <div class="col-12 col-md-6 d-flex align-items-center justify-content-center p-3 p-md-5">
                <div class="register-container">
                    <?php if (empty($role)): ?>
                        <!-- Role Selection Page -->
                        <div class="register-header text-center">
                            <div class="welcome-text fs-2 fs-md-1 mb-1">Join Learnexus</div>
                            <div class="register-title fs-3 fs-md-2 mb-2">Create Your Account</div>
                            <div class="register-description fs-6 mb-4">
                                Start managing your courses and tracking your progress today
                            </div>
                        </div>

                        <div class="text-center mb-4">
                            <h5 class="mb-3">What's your Role?</h5>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12 col-sm-6 mb-3">
                                <div class="card role-card h-100" data-role="student">
                                    <div class="card-body text-center">
                                        <div class="role-icon">
                                            <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                                        </div>
                                        <h6 class="card-title fw-bold">Student</h6>
                                        <p class="text-muted small mb-0">Access courses and track progress</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 mb-3">
                                <div class="card role-card h-100" data-role="instructor">
                                    <div class="card-body text-center">
                                        <div class="role-icon">
                                            <i class="fas fa-chalkboard-teacher fa-2x text-success"></i>
                                        </div>
                                        <h6 class="card-title fw-bold">Instructor</h6>
                                        <p class="text-muted small mb-0">Create and manage courses</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form id="roleForm" method="GET" action="register.php">
                            <input type="hidden" name="role" id="selectedRole" value="">
                        </form>

                        <button type="button" class="btn btn-register mb-3" id="nextBtn" disabled>Next</button>

                        <div class="login-link">
                            <p class="mb-0">Already have an account? <a href="index.php">Login here</a></p>
                        </div>

                    <?php else: ?>
                        <!-- Registration Form Page -->
                        <div class="register-header text-center">
                            <div class="welcome-text fs-2 fs-md-1 mb-1"><?php echo ucfirst($role); ?> Registration</div>
                            <div class="register-description fs-6 mb-4">
                                Please fill in your details to create your account
                            </div>
                        </div>

                        <form method="POST" action="register_process.php" id="registrationForm" novalidate>
                            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

                            <div class="row mb-3">
                                <div class="col-12 col-md-6 mb-3">
                                    <label for="firstName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="firstName" name="firstName"
                                        value="<?php echo htmlspecialchars($submitted_data['firstName'] ?? ''); ?>" required>
                                    <small class="text-danger d-none" id="firstNameError">First Name is required</small>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <label for="lastName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName"
                                        value="<?php echo htmlspecialchars($submitted_data['lastName'] ?? ''); ?>" required>
                                    <small class="text-danger d-none" id="lastNameError">Last Name is required</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="middleInitial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="middleInitial" name="middleInitial" maxlength="5"
                                    value="<?php echo htmlspecialchars($submitted_data['middleInitial'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($submitted_data['email'] ?? ''); ?>"
                                    placeholder="<?php echo $role == 'student' ? 'student@learnexus.edu' : 'instructor@learnexus.edu'; ?>" required>
                                <small class="text-danger d-none" id="emailError">Valid email is required</small>
                            </div>

                            <?php if ($role == 'student'): ?>
                                <div class="mb-3">
                                    <label for="studentNumber" class="form-label">Student Number *</label>
                                    <input type="text" class="form-control" id="studentNumber" name="studentNumber"
                                        value="<?php echo htmlspecialchars($submitted_data['studentNumber'] ?? ''); ?>" required>
                                    <small class="text-danger d-none" id="studentNumberError">Student Number is required</small>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="teacherNumber" class="form-label">Teacher Number *</label>
                                    <input type="text" class="form-control" id="teacherNumber" name="teacherNumber"
                                        value="<?php echo htmlspecialchars($submitted_data['teacherNumber'] ?? ''); ?>" required>
                                    <small class="text-danger d-none" id="teacherNumberError">Teacher Number is required</small>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($submitted_data['phone'] ?? ''); ?>"
                                    maxlength="11" inputmode="numeric" required>
                                <small class="text-muted">Must be exactly 11 digits (e.g., 09123456789)</small>
                                <small class="text-danger d-none" id="phoneError">Phone number must be exactly 11 digits</small>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <div class="password-input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                        autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
                                    <button type="button" class="password-toggle" id="togglePassword">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                                <small class="text-danger d-none" id="passwordError">Password is required</small>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="password-input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                        autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
                                    <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                                <small class="text-danger d-none" id="confirmPasswordError">Passwords do not match</small>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <a href="register.php" class="btn btn-back">Back</a>
                                </div>
                                <div class="col-6">
                                    <button type="submit" class="btn btn-register">Create Account</button>
                                </div>
                            </div>

                            <div class="login-link">
                                <p class="mb-0">Already have an account? <a href="index.php">Login here</a></p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($session_error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Registration Error',
                    html: '<?php echo addslashes($session_error); ?>',
                    confirmButtonColor: '#667eea'
                });
            });
        <?php endif; ?>

        // Role selection functionality
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedRole').value = this.getAttribute('data-role');
                document.getElementById('nextBtn').disabled = false;
            });
        });

        document.getElementById('nextBtn')?.addEventListener('click', () => {
            if (document.getElementById('selectedRole').value) {
                document.getElementById('roleForm').submit();
            }
        });

        <?php if (!empty($role)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('registrationForm');
                const fields = {
                    firstName: document.getElementById('firstName'),
                    lastName: document.getElementById('lastName'),
                    email: document.getElementById('email'),
                    phone: document.getElementById('phone'),
                    password: document.getElementById('password'),
                    confirm_password: document.getElementById('confirm_password')
                };

                <?php if ($role == 'student'): ?>
                    fields.studentNumber = document.getElementById('studentNumber');
                <?php else: ?>
                    fields.teacherNumber = document.getElementById('teacherNumber');
                <?php endif; ?>

                const errors = {
                    firstName: document.getElementById('firstNameError'),
                    lastName: document.getElementById('lastNameError'),
                    email: document.getElementById('emailError'),
                    phone: document.getElementById('phoneError'),
                    password: document.getElementById('passwordError'),
                    confirmPassword: document.getElementById('confirmPasswordError'),
                    <?php if ($role == 'student'): ?>
                        studentNumber: document.getElementById('studentNumberError')
                    <?php else: ?>
                        teacherNumber: document.getElementById('teacherNumberError')
                    <?php endif; ?>
                };

                // Phone number validation (digits only)
                fields.phone.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '');
                });

                // Field validation functions
                function validateField(field, errorElement) {
                    const value = field.value.trim();
                    if (value === '') {
                        errorElement.classList.remove('d-none');
                        field.classList.add('is-invalid');
                        return false;
                    } else {
                        errorElement.classList.add('d-none');
                        field.classList.remove('is-invalid');
                        return true;
                    }
                }

                function validateEmail() {
                    const value = fields.email.value.trim();
                    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (value === '' || !regex.test(value)) {
                        errors.email.classList.remove('d-none');
                        fields.email.classList.add('is-invalid');
                        return false;
                    } else {
                        errors.email.classList.add('d-none');
                        fields.email.classList.remove('is-invalid');
                        return true;
                    }
                }

                function validatePhone() {
                    const value = fields.phone.value.trim();
                    if (value.length !== 11) {
                        errors.phone.classList.remove('d-none');
                        fields.phone.classList.add('is-invalid');
                        return false;
                    } else {
                        errors.phone.classList.add('d-none');
                        fields.phone.classList.remove('is-invalid');
                        return true;
                    }
                }

                function validatePasswordMatch() {
                    if (fields.password.value === '' || fields.confirm_password.value === '' || fields.password.value !== fields.confirm_password.value) {
                        errors.confirmPassword.classList.remove('d-none');
                        fields.confirm_password.classList.add('is-invalid');
                        if (fields.password.value === '') {
                            errors.password.classList.remove('d-none');
                            fields.password.classList.add('is-invalid');
                        }
                        return false;
                    } else {
                        errors.confirmPassword.classList.add('d-none');
                        errors.password.classList.add('d-none');
                        fields.confirm_password.classList.remove('is-invalid');
                        fields.password.classList.remove('is-invalid');
                        return true;
                    }
                }

                // Real-time validation
                fields.firstName.addEventListener('input', () => validateField(fields.firstName, errors.firstName));
                fields.lastName.addEventListener('input', () => validateField(fields.lastName, errors.lastName));
                fields.email.addEventListener('input', validateEmail);
                fields.phone.addEventListener('input', validatePhone);
                fields.password.addEventListener('input', validatePasswordMatch);
                fields.confirm_password.addEventListener('input', validatePasswordMatch);

                <?php if ($role == 'student'): ?>
                    fields.studentNumber.addEventListener('input', () => validateField(fields.studentNumber, errors.studentNumber));
                <?php else: ?>
                    fields.teacherNumber.addEventListener('input', () => validateField(fields.teacherNumber, errors.teacherNumber));
                <?php endif; ?>

                // Form submission validation
                form.addEventListener('submit', function(e) {
                    let isValid = true;

                    isValid &= validateField(fields.firstName, errors.firstName);
                    isValid &= validateField(fields.lastName, errors.lastName);
                    isValid &= validateEmail();
                    isValid &= validatePhone();
                    isValid &= validatePasswordMatch();
                    <?php if ($role == 'student'): ?>
                        isValid &= validateField(fields.studentNumber, errors.studentNumber);
                    <?php else: ?>
                        isValid &= validateField(fields.teacherNumber, errors.teacherNumber);
                    <?php endif; ?>

                    if (!isValid) {
                        e.preventDefault();
                    }
                });

                // Password toggle functionality
                document.getElementById('togglePassword').addEventListener('click', function() {
                    const type = fields.password.getAttribute('type') === 'password' ? 'text' : 'password';
                    fields.password.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    } else {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                });

                document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
                    const type = fields.confirm_password.getAttribute('type') === 'password' ? 'text' : 'password';
                    fields.confirm_password.setAttribute('type', type);
                    
                    const icon = this.querySelector('i');
                    if (type === 'text') {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    } else {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    }
                });
            });
        <?php endif; ?>
    </script>
</body>
</html>