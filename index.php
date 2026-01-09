<?php
session_start();
$page_title = "Login - Learnexus";
include 'header.php';

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
?>
<div class="container-fluid vh-100">
  <div class="row h-100">
    <div class="col-md-6 d-flex align-items-center justify-content-center">
      <div class="w-100 px-4" style="max-width: 400px;">
        <div class="text-center mb-5">
          <h1 class="display-4 fw-bold text-primary">LEARNEXUS</h1>
        </div>

        <div class="text-center mb-4">
          <h2 class="h3 fw-bold">Welcome!</h2>
          <p class="text-muted">Access your courses and track your progress.</p>
        </div>

        <form method="POST" action="login_process.php">
          <div class="mb-3">
            <label for="identifier" class="form-label fw-medium">Enter ID</label>
            <input type="text" class="form-control form-control-lg" id="identifier" name="identifier" placeholder="Student/Teacher ID" required>
            <small class="text-muted">You can also use your email address</small>
          </div>

          <div class="mb-3">
            <label for="password" class="form-label fw-medium">Password</label>
            <input type="password" class="form-control form-control-lg" id="password" name="password" required>
          </div>

          <div class="mb-4">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="remember" name="remember">
              <label class="form-check-label" for="remember">Remember me</label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-100 mb-4 py-2 fw-medium">Login</button>

          <div class="text-center mb-4">
            <span class="text-muted">Or continue with</span>
          </div>

          <button type="button" class="btn btn-outline-dark btn-lg w-100 mb-4 py-2">
            <svg width="20" height="20" class="me-2">
              <text x="0" y="15" font-family="Arial" font-size="16" fill="currentColor">G</text>
            </svg>
            Google
          </button>

          <div class="text-center">
            <p class="text-muted">Don't have an account? <a href="register.php" class="fw-medium text-decoration-none">Sign up for free</a></p>
          </div>
        </form>
      </div>
    </div>

    <div class="col-md-6 d-none d-md-block bg-light">
      <div class="h-100 d-flex align-items-center justify-content-center"></div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>