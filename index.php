<?php
session_start();
$page_title = "Login - Learnexus";
include 'header.php';

// Redirect logged-in users based on role (guard against missing session keys)
if (isset($_SESSION['user_id'])) {
  $role = $_SESSION['role'] ?? null;
  if ($role) {
    switch ($role) {
      case 'student':
        header('Location: student/dashboard.php');
        exit();
      case 'instructor':
        header('Location: teacher/dashboard.php');
        exit();
      case 'admin':
        header('Location: admin/dashboard.php');
        exit();
    }
  }
}

$successMessage = $_SESSION['success'] ?? null;
if ($successMessage) {
  echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: "success",
            title: "Account Created Successfully!",
            html: "' . addslashes($successMessage) . '",
            confirmButtonColor: "#3085d6",
            confirmButtonText: "OK",
            timer: 3000,
            timerProgressBar: true,
            willClose: () => {
                window.location.href = "index.php";
            }
        });
    });
    </script>';
  unset($_SESSION['success']);
}

// Check if account was deleted
if (isset($_SESSION['account_deleted'])) {
  echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: "success",
            title: "Account Deleted",
            text: "Your account has been successfully deleted.",
            confirmButtonColor: "#3085d6",
            confirmButtonText: "OK",
            timer: 3000,
            timerProgressBar: true
        });
    });
    </script>';
  unset($_SESSION['account_deleted']);
}
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
        .login-container {
            max-width: 400px;
            width: 100%;
        }
        .welcome-text {
            color: #333;
            font-weight: 700;
            text-align: center;
        }
        .login-title {
            color: #333;
            font-weight: 600;
            text-align: center;
        }
        .login-description {
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
        .btn-login {
            background-color: #667eea;
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 0.375rem;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn-login:hover {
            background-color: #5a67d8;
        }
        .login-header {
            margin-bottom: 2.5rem;
        }
        .remember-me {
            margin-bottom: 1.5rem;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
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
        .signup-link {
            color: #666;
            text-align: center;
            margin-top: 1.5rem;
        }
        .signup-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }
        .signup-link a:hover {
            text-decoration: underline;
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
                <div class="login-container">
                    <div class="login-header text-center">
                        <div class="welcome-text fs-2 fs-md-1 mb-1">Welcome to Learnexus</div>
                        <div class="login-title fs-3 fs-md-2 mb-2">Login to Your Account</div>
                        <div class="login-description fs-6 mb-4">
                            Access your courses and track your progress
                        </div>
                    </div>

                    <form method="POST" action="login_process.php" id="loginForm">
                        <div class="mb-3">
                            <input type="text" 
                                   class="form-control" 
                                   name="identifier" 
                                   placeholder="Student/Teacher ID or Email"
                                   required>
                            <small class="text-muted mt-1 d-block">You can also use your email address</small>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label visually-hidden">Password</label>
                            <div class="password-input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter your password"
                                       required>
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>

                        <div class="remember-me mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login mb-4">
                            Login
                        </button>

                        <div class="signup-link">
                            <p class="mb-0">Don't have an account? <a href="register.php">Sign up for free</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);

                const icon = togglePassword.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    icon.title = "Hide password";
                } else {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    icon.title = "Show password";
                }
            });
        });
    </script>
</body>
</html>