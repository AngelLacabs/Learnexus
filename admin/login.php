<?php
session_start();

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit();
}

$page_title = "Admin Login - Learnexus";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
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
            position: relative;
        }
        .welcome-text {
            color: #333;
            font-weight: 700;
            text-align: center;
        }
        .admin-panel-title {
            color: #333;
            font-weight: 600;
            text-align: center;
        }
        .admin-description {
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
        .back-btn {
            background: transparent;
            border: 1px solid #dee2e6;
            color: #667eea;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            padding: 10px;
            font-size: 1.2rem;
            width: 45px;
            height: 45px;
        }
        .back-btn:hover {
            background-color: #f8f9fa;
            border-color: #667eea;
            color: #5a67d8;
            text-decoration: none;
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
    </style>
</head>
<body>
    <?php
    if (isset($_SESSION['error'])) {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: "error",
                    title: "Login Failed",
                    text: "' . addslashes($_SESSION['error']) . '",
                    confirmButtonColor: "#667eea"
                });
            });
        </script>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-12 col-md-6 left-side d-flex align-items-center justify-content-center p-3 p-md-5">
                <img src="../images/Learnexus.png" alt="Learnexus Logo" class="logo-img">
            </div>

            <div class="col-12 col-md-6 d-flex align-items-center justify-content-center p-3 p-md-5 position-relative">
                <div class="position-absolute top-0 start-0 mt-3 ms-3 d-none d-md-block">
                    <a href="../index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                
                <div class="login-container">
                    <div class="mb-3 d-block d-md-none">
                        <a href="../index.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>

                    <div class="login-header text-center">
                        <div class="welcome-text fs-2 fs-md-1 mb-1">Welcome to the Learnexus</div>
                        <div class="admin-panel-title fs-3 fs-md-2 mb-2">Admin Panel</div>
                        <div class="admin-description fs-6 mb-4">
                            Access administrative tools to manage courses and oversee platform activity
                        </div>
                    </div>

                    <form method="POST" action="login_process.php" id="adminLoginForm">

                        <div class="mb-3">
                            <input type="email" 
                                   class="form-control" 
                                   name="email" 
                                   placeholder="Enter Admin Email"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label visually-hidden">Password</label>
                            <div class="password-input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter Admin password"
                                       required>
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>

                        <div class="remember-me">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login">
                            Login
                        </button>
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
            const eyeIcon = togglePassword.querySelector('i');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);

                if (type === 'text') {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            });
        });
    </script>
</body>
</html>