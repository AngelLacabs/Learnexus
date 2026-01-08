<?php
// index.php
session_start();
$page_title = "Login - Learnexus";
include 'header.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
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

// Show success message if redirected from registration
$successMessage = $_SESSION['success'] ?? null;
if ($successMessage) {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: "success",
            title: "Registration Successful",
            html: "' . addslashes($successMessage) . '",
            confirmButtonColor: "#3085d6"
        });
    });
    </script>';
    unset($_SESSION['success']);
}
?>
<div class="container-fluid vh-100">
  <div class="row h-100">
    <!-- Left Side - Login Form -->
    <div class="col-md-6 d-flex align-items-center justify-content-center">
      <div class="w-100 px-4" style="max-width: 400px;">
        <!-- Logo -->
        <div class="text-center mb-5">
          <h1 class="display-4 fw-bold text-primary">LEARNEXUS</h1>
        </div>

        <!-- Welcome Message -->
        <div class="text-center mb-4">
          <h2 class="h3 fw-bold">Welcome!</h2>
          <p class="text-muted">Access your courses and track your progress.</p>
        </div>

        <!-- Login Form -->
        <form method="POST" action="login_process.php">
          <!-- Identifier (Email/Student ID/Teacher ID) -->
          <div class="mb-3">
            <label for="identifier" class="form-label fw-medium">Enter ID</label>
            <input type="text" class="form-control form-control-lg" id="identifier" name="identifier" placeholder="Student/Teacher ID" required>
            <small class="text-muted">You can also use your email address</small>
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label for="password" class="form-label fw-medium">Password</label>
            <input type="password" class="form-control form-control-lg" id="password" name="password" required>
          </div>

          <!-- Remember Me -->
          <div class="mb-4">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="remember" name="remember">
              <label class="form-check-label" for="remember">Remember me</label>
            </div>
          </div>

          <!-- Login Button -->
          <button type="submit" class="btn btn-primary btn-lg w-100 mb-4 py-2 fw-medium">Login</button>

          <!-- Divider -->
          <div class="text-center mb-4">
            <span class="text-muted">Or continue with</span>
          </div>

          <!-- Google Button -->
          <button type="button" class="btn btn-outline-dark btn-lg w-100 mb-4 py-2">
            <svg width="20" height="20" class="me-2">
              <text x="0" y="15" font-family="Arial" font-size="16" fill="currentColor">G</text>
            </svg>
            Google
          </button>

          <!-- Sign Up Link -->
          <div class="text-center">
            <p class="text-muted">Don't have an account? <a href="register.php" class="fw-medium text-decoration-none">Sign up for free</a></p>
          </div>
        </form>
      </div>
    </div>

    <!-- Right Side - Image/Design Placeholder -->
    <div class="col-md-6 d-none d-md-block bg-light">
      <div class="h-100 d-flex align-items-center justify-content-center">
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>