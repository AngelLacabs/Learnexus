<?php
session_start();
$page_title = "Verify Phone - Learnexus";
include 'header.php';

if (!isset($_SESSION['pending_registration']) || !isset($_SESSION['otp_phone'])) {
    $_SESSION['error'] = 'Invalid verification request.';
    header('Location: register.php');
    exit();
}

$phone = $_SESSION['otp_phone'];
$pendingData = $_SESSION['pending_registration'];

function maskPhone($phone)
{
    if (strlen($phone) <= 4) return $phone;
    return '*******' . substr($phone, -4);
}

$maskedPhone = maskPhone($phone);
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
                        <h2 class="h3 fw-bold text-center mb-4">Verify Your Phone</h2>

                        <div class="alert alert-success text-center">
                            <h5>SMS Verification Sent!</h5>
                            <p class="mb-0">The verification code has been sent to:</p>
                            <p class="mb-0"><strong><?php echo htmlspecialchars($maskedPhone); ?></strong></p>
                            <p>Please check your messages.</p>
                        </div>

                        <form method="POST" action="verify_sms_process.php" id="verifyForm">
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
                                <small class="text-muted">Enter the 6-digit code sent to your phone</small>
                                <small class="text-danger d-none" id="otpError">Please enter a valid 6-digit code</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3 py-2 fw-medium">Verify Account</button>

                            <div class="text-center mt-4">
                                <p class="text-muted mb-2">Didn't receive the code?</p>
                                <a href="resend_sms_otp.php" class="btn btn-outline-secondary btn-sm" id="resendBtn">Resend OTP</a>
                                <div id="resendTimer" class="mt-2 text-muted small"></div>
                            </div>

                            <div class="text-center mt-3">
                                <p class="text-muted mb-2">Prefer to verify via email?</p>
                                <a href="switch_to_email.php" class="btn btn-outline-primary btn-sm">Use Email Instead</a>
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="#007bff" class="bi bi-phone" viewBox="0 0 16 16">
                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/>
                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                    </svg>
                    <h4 class="mt-3 text-primary">SMS Verification</h4>
                    <p class="text-muted">Check your phone for the verification code</p>
                </div>
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
});
</script>

<?php include 'footer.php'; ?>