<?php
/**
 * Script to clean up expired OTP records from the database
 * Can be run manually or scheduled with a cron job
 */

require_once dirname(__DIR__) . '/database/db_connect.php';
require_once dirname(__DIR__) . '/helpers/otp_helper.php';

$otpHelper = new OTPHelper($conn);
$result = $otpHelper->deleteExpiredOTPs();

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Cleanup completed successfully',
        'emailOTPsDeleted' => $result['emailDeleted'],
        'smsOTPsDeleted' => $result['smsDeleted']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Cleanup failed: ' . $result['message']
    ]);
}
