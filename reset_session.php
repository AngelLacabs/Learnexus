<?php
session_start();
// Clear ONLY verification data, keep other session
unset($_SESSION['pending_registration']);
unset($_SESSION['otp_email']);
unset($_SESSION['sms_otp']);
unset($_SESSION['sms_phone']);
unset($_SESSION['error']);
unset($_SESSION['success']);

echo "Session cleared. <a href='register.php'>Register again</a>";
?>