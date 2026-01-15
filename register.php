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
            background-color: #ffffff;
        }
        .left-side {
            background-color: #D7DFF0;
            min-height: 100vh;
        }
        .register-container {
            max-width: 500px;
            width: 100%;
        }
        .welcome-text {
            color: #333;
            font-weight: 700;
            text-align: center;
        }
        .register-title {
            color: #333;
            font-weight: 600;
            text-align: center;
        }
        .register-description {
            color: #666;
            line-height: 1.5;
            text-align: center;
        }
        .form-control {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background-color: #667eea;
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 0.375rem;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn-register:hover {
            background-color: #5a67d8;
        }
        .btn-back {
            background-color: #f8f9fa;
            color: #666;
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            border-radius: 0.375rem;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn-back:hover {
            background-color: #e9ecef;
        }
        .register-header {
            margin-bottom: 2rem;
        }
        .role-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #dee2e6;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .role-card.selected {
            border-color: #667eea !important;
            background-color: rgba(102, 126, 234, 0.05);
        }
        .logo-img {
            max-width: 550px;
            width: 100%;
            height: auto;
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
        }
        .password-toggle:hover {
            color: #667eea;
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
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .role-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .text-danger {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .input-group .btn {
            border-left: 0;
            background-color: #f8f9fa;
        }
        .input-group .btn:hover {
            background-color: #e9ecef;
        }
        .is-invalid {
            border-color: #dc3545;
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