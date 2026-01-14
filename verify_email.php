<?php
session_start();
$page_title = "Verify Email - Learnexus";
include 'header.php';

if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_email'])) {
    $_SESSION['error'] = 'Session expired. Please start registration again.';
    header('Location: register.php');
    exit();
}

$email = $_SESSION['otp_email'];
$pendingData = $_SESSION['pending_registration'];

function maskEmail($email) {
    $parts = explode("@", $email);
    $name = $parts[0];
    $length = strlen($name);
    if ($length < 3) return $email;
    return substr($name, 0, 2) . str_repeat('*', $length-2) . "@" . $parts[1];
}

$maskedEmail = maskEmail($email);
$userPhone = $pendingData['phone'] ?? '';
?>

<div class="container-fluid vh-100">
    <div class="row h-100">
        <div class="col-md-6 d-flex align-items-center justify-content-center">
            <div class="w-100 px-4" style="max-width: 400px;">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold text-primary">LEARNEXUS</h1>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h2 class="h3 fw-bold text-center mb-4">Verify Your Email</h2>

                        <div class="alert alert-info text-center">
                            <h5>Email Verification Sent!</h5>
                            <p class="mb-0">We've sent a verification code to:</p>
                            <p class="mb-0"><strong><?php echo htmlspecialchars($maskedEmail); ?></strong></p>
                            <p>Please check your inbox and spam folder.</p>
                        </div>

                        <form method="POST" action="verify_email_process.php" id="verifyForm">
                            <div class="mb-4">
                                <label for="otp" class="form-label fw-medium">Enter verification code</label>
                                <input type="text" 
                                       class="form-control form-control-lg text-center" 
                                       id="otp" 
                                       name="otp" 
                                       maxlength="6" 
                                       pattern="\d{6}" 
                                       placeholder="000000" 
                                       required 
                                       autocomplete="off">
                                <small class="text-muted">Enter the 6-digit code sent to your email</small>
                                <small class="text-danger d-none" id="otpError">Please enter a valid 6-digit code</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3 py-2 fw-medium">Verify Account</button>

                            <div class="text-center mt-4">
                                <p class="text-muted mb-2">Didn't receive the code?</p>
                                <a href="resend_otp.php" class="btn btn-outline-secondary btn-sm" id="resendBtn">Resend OTP</a>
                                <div id="resendTimer" class="mt-2 text-muted small"></div>
                            </div>

                            <div class="text-center mt-3">
                                <p class="text-muted mb-2">Prefer to verify via SMS?</p>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#smsModal">
                                    Use SMS Instead
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="register.php?role=<?php echo urlencode($pendingData['role']); ?>" class="text-decoration-none">← Back to registration</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 d-none d-md-block bg-light">
            <div class="h-100 d-flex align-items-center justify-content-center p-5">
                <div class="text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="#007bff" class="bi bi-envelope-check" viewBox="0 0 16 16">
                        <path d="M2 2a2 2 0 0 0-2 2v8.01A2 2 0 0 0 2 14h5.5a.5.5 0 0 0 0-1H2a1 1 0 0 1-.966-.741l5.64-3.471L8 9.583l7-4.2V8.5a.5.5 0 0 0 1 0V4a2 2 0 0 0-2-2H2Zm3.708 6.208L1 11.105V5.383l4.708 2.825ZM1 4.217V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v.217l-7 4.2-7-4.2Z"/>
                        <path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm-1.993-1.679a.5.5 0 0 0-.686.172l-1.17 1.95-.547-.547a.5.5 0 0 0-.708.708l.774.773a.75.75 0 0 0 1.174-.144l1.335-2.226a.5.5 0 0 0-.172-.686Z"/>
                    </svg>
                    <h4 class="mt-3 text-primary">Email Verification</h4>
                    <p class="text-muted">Check your inbox for the verification code</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SMS Confirmation Modal -->
<div class="modal fade" id="smsModal" tabindex="-1" aria-labelledby="smsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="smsModalLabel">Confirm Phone Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#0d6efd" class="bi bi-phone" viewBox="0 0 16 16">
                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/>
                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                    </svg>
                </div>
                <p class="text-center">We will send a verification code to:</p>
                <h4 class="text-center text-primary mb-4"><?php echo htmlspecialchars($userPhone); ?></h4>
                <div class="alert alert-warning">
                    <small><strong>Note:</strong> Standard SMS rates may apply. Make sure your phone can receive SMS.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendSmsBtn">
                    <span id="sendSmsText">Send SMS OTP</span>
                    <span id="sendSmsSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #otp {
        font-size: 24px;
        letter-spacing: 10px;
        font-weight: bold;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const otpInput = document.getElementById('otp');
    const otpError = document.getElementById('otpError');
    const resendBtn = document.getElementById('resendBtn');
    const resendTimer = document.getElementById('resendTimer');
    const sendSmsBtn = document.getElementById('sendSmsBtn');
    const sendSmsText = document.getElementById('sendSmsText');
    const sendSmsSpinner = document.getElementById('sendSmsSpinner');

    otpInput.focus();

    otpInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 6);

        if (this.value.length === 6) {
            otpError.classList.add('d-none');
            otpInput.classList.remove('is-invalid');
        } else if (this.value.length > 0 && this.value.length < 6) {
            otpError.classList.remove('d-none');
            otpInput.classList.add('is-invalid');
        }
    });

    let timer = 60;
    function updateTimer() {
        if (timer > 0) {
            resendBtn.disabled = true;
            resendBtn.classList.add('disabled');
            resendTimer.textContent = `Resend available in ${timer} seconds`;
            timer--;
            setTimeout(updateTimer, 1000);
        } else {
            resendBtn.disabled = false;
            resendBtn.classList.remove('disabled');
            resendTimer.textContent = '';
        }
    }
    updateTimer();

    document.getElementById('verifyForm').addEventListener('submit', function(e) {
        if (otpInput.value.length !== 6) {
            e.preventDefault();
            otpError.classList.remove('d-none');
            otpInput.classList.add('is-invalid');
            otpInput.focus();
        }
    });

    // SMS Modal Handler
    sendSmsBtn.addEventListener('click', function() {
        sendSmsText.classList.add('d-none');
        sendSmsSpinner.classList.remove('d-none');
        sendSmsBtn.disabled = true;

        fetch('switch_to_sms.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'SMS Sent!',
                    text: 'Verification code has been sent to your phone.',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    window.location.href = 'verify_sms.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'SMS Failed',
                    text: data.message || 'Failed to send SMS. Please try email verification.',
                    confirmButtonColor: '#3085d6'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Network error. Please try again.',
                confirmButtonColor: '#3085d6'
            });
        })
        .finally(() => {
            sendSmsText.classList.remove('d-none');
            sendSmsSpinner.classList.add('d-none');
            sendSmsBtn.disabled = false;
            bootstrap.Modal.getInstance(document.getElementById('smsModal')).hide();
        });
    });
});
</script>