<?php
// register.php
session_start();
$page_title = "Register - Learnexus";
include 'header.php';

// If coming from step 1, check role
$role = $_GET['role'] ?? '';
$valid_roles = ['student', 'instructor'];

if (!in_array($role, $valid_roles)) {
    $role = '';
}

// Retrieve submitted data from session if available
$submitted_data = $_SESSION['register_data'] ?? [];
$session_error = $_SESSION['error'] ?? null;

// Clear session data after retrieving
if (isset($_SESSION['register_data'])) {
    unset($_SESSION['register_data']);
}
if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}
?>

<div class="container-fluid vh-100">
  <div class="row h-100">
    <div class="col-md-6 d-flex align-items-center justify-content-center">
      <div class="w-100 px-4" style="max-width: 400px;">
        <div class="text-center mb-5">
          <h1 class="display-4 fw-bold text-primary">LEARNEXUS</h1>
        </div>

        <?php if (empty($role)): ?>
          <!-- STEP 1: Role Selection -->
          <h2 class="h3 fw-bold text-center mb-4">Create your account</h2>
          <p class="text-muted text-center mb-4">Join Learnexus to start managing your courses and tracking your progress today.</p>
          
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <h5 class="card-title text-center mb-4">What's your Role?</h5>
              
              <div class="row">
                <!-- Student Card -->
                <div class="col-md-6 mb-3">
                  <div class="card role-card h-100 border-2" data-role="student">
                    <div class="card-body text-center">
                      <div class="mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#0d6efd" class="bi bi-mortarboard" viewBox="0 0 16 16">
                          <path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5Z"/>
                          <path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466 4.176 9.032Z"/>
                        </svg>
                      </div>
                      <h6 class="card-title">Student</h6>
                    </div>
                  </div>
                </div>
                
                <!-- Instructor Card -->
                <div class="col-md-6 mb-3">
                  <div class="card role-card h-100 border-2" data-role="instructor">
                    <div class="card-body text-center">
                      <div class="mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#198754" class="bi bi-person-badge" viewBox="0 0 16 16">
                          <path d="M6.5 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3zM11 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                          <path d="M4.5 0A2.5 2.5 0 0 0 2 2.5V14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2.5A2.5 2.5 0 0 0 11.5 0h-7zM3 2.5A1.5 1.5 0 0 1 4.5 1h7A1.5 1.5 0 0 1 13 2.5v10.795a4.2 4.2 0 0 0-.776-.492C11.392 12.387 10.063 12 8 12s-3.392.387-4.224.803a4.2 4.2 0 0 0-.776.492V2.5z"/>
                        </svg>
                      </div>
                      <h6 class="card-title">Instructor</h6>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Hidden form for role selection -->
              <form id="roleForm" method="GET" action="register.php">
                <input type="hidden" name="role" id="selectedRole" value="">
              </form>
              
              <button type="button" class="btn btn-primary btn-lg w-100 mt-3" id="nextBtn" disabled>Next</button>
              
              <div class="text-center mt-3">
                <p class="text-muted">Already have an account? <a href="index.php" class="fw-medium text-decoration-none">Login here</a></p>
              </div>
            </div>
          </div>
          
        <?php else: ?>
          <!-- STEP 2: Registration Form -->
          <h2 class="h3 fw-bold text-center mb-4">
            <?php echo ucfirst($role); ?> Registration
          </h2>
          
          <form method="POST" action="register_process.php" id="registrationForm">
            <!-- Hidden role field -->
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="firstName" class="form-label fw-medium">First Name *</label>
                <input type="text" class="form-control form-control-lg" id="firstName" name="firstName" 
                  value="<?php echo htmlspecialchars($submitted_data['firstName'] ?? ''); ?>" required>
                <small class="text-danger" id="firstNameError" style="display: none;">First Name is required</small>
              </div>
              <div class="col-md-6">
                <label for="lastName" class="form-label fw-medium">Last Name *</label>
                <input type="text" class="form-control form-control-lg" id="lastName" name="lastName" 
                  value="<?php echo htmlspecialchars($submitted_data['lastName'] ?? ''); ?>" required>
                <small class="text-danger" id="lastNameError" style="display: none;">Last Name is required</small>
              </div>
            </div>

            <div class="mb-3">
              <label for="middleInitial" class="form-label fw-medium">Middle Initial</label>
              <input type="text" class="form-control form-control-lg" id="middleInitial" name="middleInitial" maxlength="5"
                value="<?php echo htmlspecialchars($submitted_data['middleInitial'] ?? ''); ?>">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label fw-medium">Email Address *</label>
              <input type="email" class="form-control form-control-lg" id="email" name="email" 
                value="<?php echo htmlspecialchars($submitted_data['email'] ?? ''); ?>"
                placeholder="<?php echo $role == 'student' ? 'student@learnexus.edu' : 'instructor@learnexus.edu'; ?>" required>
              <small class="text-danger" id="emailError" style="display: none;">Valid email is required</small>
            </div>

            <?php if ($role == 'student'): ?>
              <div class="mb-3">
                <label for="studentNumber" class="form-label fw-medium">Student Number *</label>
                <input type="text" class="form-control form-control-lg" id="studentNumber" name="studentNumber" 
                  value="<?php echo htmlspecialchars($submitted_data['studentNumber'] ?? ''); ?>" required>
                <small class="text-danger" id="studentNumberError" style="display: none;">Student Number is required</small>
              </div>
            <?php else: ?>
              <div class="mb-3">
                <label for="teacherNumber" class="form-label fw-medium">Teacher Number *</label>
                <input type="text" class="form-control form-control-lg" id="teacherNumber" name="teacherNumber" 
                  value="<?php echo htmlspecialchars($submitted_data['teacherNumber'] ?? ''); ?>" required>
                <small class="text-danger" id="teacherNumberError" style="display: none;">Teacher Number is required</small>
              </div>
            <?php endif; ?>

            <div class="mb-3">
              <label for="phone" class="form-label fw-medium">Phone Number *</label>
              <input type="text" class="form-control form-control-lg" id="phone" name="phone" 
                value="<?php echo htmlspecialchars($submitted_data['phone'] ?? ''); ?>" required>
              <small class="text-muted" id="phoneHelp">Must be 11 digits</small>
              <small class="text-danger" id="phoneError" style="display: none;">Phone number must be exactly 11 digits</small>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-medium">Password *</label>
              <input type="password" class="form-control form-control-lg" id="password" name="password" required>
              <small class="text-danger" id="passwordError" style="display: none;">Password is required</small>
            </div>

            <div class="mb-4">
              <label for="confirm_password" class="form-label fw-medium">Confirm Password *</label>
              <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required>
              <small class="text-danger" id="confirmPasswordError" style="display: none;">Passwords do not match</small>
            </div>

            <div class="d-flex gap-2">
              <a href="register.php" class="btn btn-outline-secondary btn-lg w-50">Back</a>
              <button type="submit" class="btn btn-primary btn-lg w-50">Create Account</button>
            </div>

            <div class="text-center mt-3">
              <p class="text-muted">Already have an account? <a href="index.php" class="fw-medium text-decoration-none">Login here</a></p>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-6 d-none d-md-block bg-light">
      <div class="h-100 d-flex align-items-center justify-content-center">
      </div>
    </div>
  </div>
</div>

<style>
.role-card {
  cursor: pointer;
  transition: all 0.3s;
}
.role-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.role-card.selected {
  border-color: #0d6efd !important;
  background-color: #f8f9fa;
}
.is-invalid {
  border-color: #dc3545 !important;
}
</style>

<script>
// Display SweetAlert if there's an error from session
<?php if ($session_error): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Registration Error',
        html: '<?php echo addslashes($session_error); ?>',
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'OK'
    });
});
<?php endif; ?>

// Role selection
document.querySelectorAll('.role-card').forEach(card => {
  card.addEventListener('click', function() {
    document.querySelectorAll('.role-card').forEach(c => {
      c.classList.remove('selected');
      c.style.borderColor = '';
    });
    
    this.classList.add('selected');
    this.style.borderColor = '#0d6efd';
    
    const role = this.getAttribute('data-role');
    document.getElementById('selectedRole').value = role;
    document.getElementById('nextBtn').disabled = false;
  });
});

// Next button
document.getElementById('nextBtn')?.addEventListener('click', function() {
  const role = document.getElementById('selectedRole').value;
  if (role) {
    document.getElementById('roleForm').submit();
  }
});

// Form validation for step 2
<?php if (!empty($role)): ?>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('registrationForm');
  
  // Form submission - just submit directly
  form?.addEventListener('submit', function(e) {
    // Let the form submit normally to register_process.php
    return true;
  });
});
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>