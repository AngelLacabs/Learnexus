<?php
// Save the received SMS
$data = file_get_contents('php://input');
$log = date('Y-m-d H:i:s') . " - " . $data . "\n";
file_put_contents('sms_log.txt', $log, FILE_APPEND);

// Send response back
echo json_encode(['status' => 'success']);
?>