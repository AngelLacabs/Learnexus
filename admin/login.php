<?php
session_start();

// If already logged in as admin, redirect to dashboard
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .admin-header h1 {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
        }
        .admin-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .admin-body {
            padding: 40px 30px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
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

    <div class="admin-login-card">
        <div class="admin-header">
            <h1>🛡️ ADMIN PANEL</h1>
            <p>Learnexus Management System</p>
        </div>

        <div class="admin-body">
            <form method="POST" action="login_process.php" id="adminLoginForm">
                <div class="mb-4">
                    <label for="email" class="form-label fw-medium">Admin Email</label>
                    <input type="email" 
                           class="form-control form-control-lg" 
                           id="email" 
                           name="email" 
                           placeholder="admin@learnexus.com"
                           required 
                           autocomplete="email">
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-medium">Password</label>
                    <input type="password" 
                           class="form-control form-control-lg" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password">
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-admin btn-lg w-100">
                    Login to Admin Panel
                </button>
            </form>
        </div>
    </div>

    <div class="back-link">
        <a href="../index.php">← Back to Main Site</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>