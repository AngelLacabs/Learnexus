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
          
          <form method="POST" action="register_process.php" id="registrationForm" novalidate>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="firstName" class="form-label fw-medium">First Name *</label>
                <input type="text" class="form-control form-control-lg" id="firstName" name="firstName" 
                  value="<?php echo htmlspecialchars($submitted_data['firstName'] ?? ''); ?>" required>
                <small class="text-danger d-none" id="firstNameError">First Name is required</small>
              </div>
              <div class="col-md-6">
                <label for="lastName" class="form-label fw-medium">Last Name *</label>
                <input type="text" class="form-control form-control-lg" id="lastName" name="lastName" 
                  value="<?php echo htmlspecialchars($submitted_data['lastName'] ?? ''); ?>" required>
                <small class="text-danger d-none" id="lastNameError">Last Name is required</small>
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
              <small class="text-danger d-none" id="emailError">Valid email is required</small>
            </div>

            <?php if ($role == 'student'): ?>
              <div class="mb-3">
                <label for="studentNumber" class="form-label fw-medium">Student Number *</label>
                <input type="text" class="form-control form-control-lg" id="studentNumber" name="studentNumber" 
                  value="<?php echo htmlspecialchars($submitted_data['studentNumber'] ?? ''); ?>" required>
                <small class="text-danger d-none" id="studentNumberError">Student Number is required</small>
              </div>
            <?php else: ?>
              <div class="mb-3">
                <label for="teacherNumber" class="form-label fw-medium">Teacher Number *</label>
                <input type="text" class="form-control form-control-lg" id="teacherNumber" name="teacherNumber" 
                  value="<?php echo htmlspecialchars($submitted_data['teacherNumber'] ?? ''); ?>" required>
                <small class="text-danger d-none" id="teacherNumberError">Teacher Number is required</small>
              </div>
            <?php endif; ?>

            <div class="mb-3">
              <label for="phone" class="form-label fw-medium">Phone Number *</label>
              <input type="text" class="form-control form-control-lg" id="phone" name="phone" 
                value="<?php echo htmlspecialchars($submitted_data['phone'] ?? ''); ?>" 
                maxlength="11" inputmode="numeric" required>
              <small class="text-muted">Must be exactly 11 digits (e.g., 09123456789)</small>
              <small class="text-danger d-none" id="phoneError">Phone number must be exactly 11 digits</small>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-medium">Password *</label>
              <div class="input-group">
                <input type="password" class="form-control form-control-lg" id="password" name="password" required
                  autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                  </svg>
                </button>
              </div>
              <small class="text-danger d-none" id="passwordError">Password is required</small>
            </div>

            <div class="mb-4">
              <label for="confirm_password" class="form-label fw-medium">Confirm Password *</label>
              <div class="input-group">
                <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required
                  autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false">
                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                  </svg>
                </button>
              </div>
              <small class="text-danger d-none" id="confirmPasswordError">Passwords do not match</small>
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
      <div class="h-100 d-flex align-items-center justify-content-center"></div>
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
.input-group .btn {
  border-left: 0;
  background-color: #f8f9fa;
}
.input-group .btn:hover {
  background-color: #e9ecef;
}

/* ITO ANG IDINAGDAG PARA ITAGO ANG BUILT-IN NA EYE NG EDGE */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
  display: none !important;
}
</style>

<script>
// SweetAlert for session error
<?php if ($session_error): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Registration Error',
        html: '<?php echo addslashes($session_error); ?>',
        confirmButtonColor: '#3085d6'
    });
});
<?php endif; ?>

// Role selection
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

// Full form validation + eye toggle
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

  // Remove non-digits from phone
  fields.phone.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
  });

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

  // Submit validation
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

  // Toggle Password Visibility - Fixed Eye Icon
  const eyeOpen = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
  </svg>`;

  const eyeClosed = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16">
    <path d="m13.498 5.414 1.415-1.415 1.415 1.415-1.415 1.415 1.415 1.415-1.415 1.415-1.415-1.415-1.415 1.415-1.415-1.415 1.415-1.415-1.415-1.415 1.415-1.415-1.415 1.415z"/>
    <path d="M8 3.5c-2.12 0-3.879 1.168-5.168 2.457A13.133 13.133 0 0 0 1.172 8c.058.087.122.183.195.288.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c2.12 0 3.879-1.168 5.168-2.457A13.134 13.134 0 0 0 14.828 8c-.058-.087-.122-.183-.195-.288-.335-.48-.83-1.12-1.465-1.755C11.879 4.668 10.119 3.5 8 3.5zM0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z"/>
  </svg>`;

  document.getElementById('togglePassword').addEventListener('click', function() {
    const type = fields.password.getAttribute('type') === 'password' ? 'text' : 'password';
    fields.password.setAttribute('type', type);
    this.innerHTML = type === 'password' ? eyeOpen : eyeClosed;
  });

  document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const type = fields.confirm_password.getAttribute('type') === 'password' ? 'text' : 'password';
    fields.confirm_password.setAttribute('type', type);
    this.innerHTML = type === 'password' ? eyeOpen : eyeClosed;
  });
});
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>