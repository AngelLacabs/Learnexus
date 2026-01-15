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
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }
    .left-side {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        position: relative;
        overflow: hidden;
    }
    .left-side::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><circle cx="700" cy="200" r="120" fill="white" opacity="0.1"/><circle cx="800" cy="600" r="80" fill="white" opacity="0.1"/><circle cx="300" cy="400" r="150" fill="white" opacity="0.1"/></svg>');
        z-index: 1;
    }
    .login-container {
        max-width: 450px;
        width: 100%;
        background: white;
        border-radius: 24px;
        box-shadow: 0 20px 60px rgba(102, 126, 234, 0.15),
                    0 10px 30px rgba(102, 126, 234, 0.1),
                    0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        padding: 2.5rem;
        position: relative;
        z-index: 2;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .login-container:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 70px rgba(102, 126, 234, 0.2),
                    0 15px 40px rgba(102, 126, 234, 0.15),
                    0 0 0 1px rgba(255, 255, 255, 0.2) inset;
    }
    .welcome-text {
        color: #1a73e8;
        font-weight: 800;
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 2.2rem;
        margin-bottom: 0.5rem;
    }
    .login-title {
        color: #333;
        font-weight: 700;
        text-align: center;
        font-size: 1.5rem;
        margin-bottom: 0.75rem;
    }
    .login-description {
        color: #666;
        line-height: 1.5;
        text-align: center;
        font-size: 1rem;
        margin-bottom: 2rem;
    }
    .form-control {
        padding: 0.875rem 1rem;
        border: 2px solid #e8f0fe;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        background: white;
        transform: translateY(-2px);
    }
    .btn-login {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
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
    .btn-login:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }
    .btn-login:active {
        transform: translateY(-1px);
    }
    .btn-login::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s ease;
    }
    .btn-login:hover::after {
        left: 100%;
    }
    .login-header {
        margin-bottom: 2.5rem;
    }
    .remember-me {
        margin-bottom: 1.5rem;
    }
    .form-check-input:checked {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
    }
    .form-check-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
    }
    .logo-img {
        max-width: 500px;
        width: 100%;
        height: auto;
        filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.1));
        transition: transform 0.3s ease;
        position: relative;
        z-index: 2;
    }
    .logo-img:hover {
        transform: scale(1.05);
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
        transition: color 0.3s ease;
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
        position: relative;
        transition: color 0.3s ease;
    }
    .signup-link a:hover {
        color: #764ba2;
        text-decoration: none;
    }
    .signup-link a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s ease;
    }
    .signup-link a:hover::after {
        width: 100%;
    }
    .floating-element {
        position: absolute;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        z-index: 1;
    }
    .floating-element-1 {
        width: 150px;
        height: 150px;
        top: 10%;
        left: 10%;
        animation: float 20s infinite ease-in-out;
    }
    .floating-element-2 {
        width: 100px;
        height: 100px;
        bottom: 20%;
        right: 15%;
        animation: float 15s infinite ease-in-out reverse;
    }
    @keyframes float {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        33% {
            transform: translateY(-20px) rotate(120deg);
        }
        66% {
            transform: translateY(20px) rotate(240deg);
        }
    }
    .container-fluid {
        position: relative;
        overflow: hidden;
    }
    .row.min-vh-100 {
        position: relative;
    }
    .left-side {
        position: relative;
        z-index: 1;
    }
    .left-side::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        z-index: 1;
    }
    .left-side > * {
        position: relative;
        z-index: 2;
    }
    .right-side {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
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
                            <p class="mb-0">Don't have an account? <a href="register.php">Create Account</a></p>
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