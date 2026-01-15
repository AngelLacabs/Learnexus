<?php
/**
 * SMS Helper Functions
 * 
 * Refactored SMS sending functionality with logging to database
 * Simply include this file and call sendSMS() whenever you need to send a message.
 */

require_once __DIR__ . '/../database/db_connect.php';
require_once __DIR__ . '/../config/sms_config.php';

/**
 * Send SMS message via gateway and log to database
 * 
 * @param string $recipient Phone number (will be formatted to 63XXXXXXXXX for Philippines)
 * @param string $message The message content to send
 * @param int|null $adminID Admin user ID who sent the message (for logging)
 * @return array Response array with success status and details
 * 
 * @example
 * require_once 'helpers/sms_helper.php';
 * $result = sendSMS('639123456789', 'Your message here', $_SESSION['user_id']);
 */
function sendSMS($recipient, $message, $adminID = null)
{
    global $conn;
    
    // Validate inputs
    if (empty($recipient)) {
        error_log('SMS Error: Empty recipient phone number');
        return [
            'success' => false,
            'message' => 'Empty recipient phone number'
        ];
    }

    if (empty($message)) {
        error_log('SMS Error: Empty message content');
        return [
            'success' => false,
            'message' => 'Empty message content'
        ];
    }

    // Format phone number for Philippines (ensure starts with 63)
    $recipient = preg_replace('/[^0-9]/', '', $recipient);
    if (!preg_match('/^63/', $recipient)) {
        if (preg_match('/^0/', $recipient)) {
            $recipient = '63' . substr($recipient, 1);
        } else {
            $recipient = '63' . $recipient;
        }
    }

    // Prepare payload
    $payload = [
        "phoneNumbers" => [$recipient],
        "message" => $message,
    ];

    // Prepare headers with authentication
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(SMS_USERNAME . ':' . SMS_PASSWORD)
    ];

    $url = SMS_GATEWAY_URL . '/messages';
    $response = null;
    $httpCode = 0;
    $error = null;

    // Try cURL first (more reliable), fall back to file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            error_log('SMS Error (cURL): Failed to send SMS to ' . $recipient);
            error_log('cURL Error: ' . $error);
            error_log('Gateway URL: ' . $url);
            error_log('Payload: ' . json_encode($payload));
            
            // Log to database
            logSentSMS($recipient, $message, 'failed', $adminID, $error);
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $error,
                'http_code' => 0
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('SMS Error: Gateway returned status code ' . $httpCode);
            error_log('Response: ' . $response);
            
            // Log to database
            logSentSMS($recipient, $message, 'failed', $adminID, 'HTTP ' . $httpCode);
            
            return [
                'success' => false,
                'message' => 'Gateway returned status code ' . $httpCode,
                'http_code' => $httpCode,
                'response' => $response
            ];
        }

        error_log('SMS Success: Sent to ' . $recipient . ' (HTTP ' . $httpCode . ')');
        
        // Log to database
        $smsID = logSentSMS($recipient, $message, 'sent', $adminID);
        
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'http_code' => $httpCode,
            'response' => $response,
            'sms_id' => $smsID
        ];
    }

    // Fallback to file_get_contents if cURL is not available
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => json_encode($payload),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        $lastError = error_get_last();
        error_log('SMS Error: Failed to send SMS to ' . $recipient);
        error_log('Gateway URL: ' . $url);
        error_log('Error details: ' . ($lastError ? $lastError['message'] : 'Unknown error'));
        error_log('Payload: ' . json_encode($payload));
        
        // Log to database
        logSentSMS($recipient, $message, 'failed', $adminID, $lastError ? $lastError['message'] : 'Unknown error');
        
        return [
            'success' => false,
            'message' => 'Failed to send SMS',
            'error' => $lastError ? $lastError['message'] : 'Unknown error'
        ];
    }

    // Check HTTP response code
    if (isset($http_response_header)) {
        $status_line = $http_response_header[0];
        preg_match('/\d{3}/', $status_line, $matches);
        $status_code = isset($matches[0]) ? (int)$matches[0] : 0;

        if ($status_code < 200 || $status_code >= 300) {
            error_log('SMS Error: Gateway returned status code ' . $status_code);
            error_log('Response: ' . $response);
            
            // Log to database
            logSentSMS($recipient, $message, 'failed', $adminID, 'HTTP ' . $status_code);
            
            return [
                'success' => false,
                'message' => 'Gateway returned status code ' . $status_code,
                'http_code' => $status_code,
                'response' => $response
            ];
        }
    }

    error_log('SMS Success: Sent to ' . $recipient);
    
    // Log to database
    $smsID = logSentSMS($recipient, $message, 'sent', $adminID);
    
    return [
        'success' => true,
        'message' => 'SMS sent successfully',
        'response' => $response,
        'sms_id' => $smsID
    ];
}

/**
 * Log sent SMS to database
 * 
 * @param string $toNumber Recipient phone number
 * @param string $message Message content
 * @param string $status Status: 'sent' or 'failed'
 * @param int|null $adminID Admin user ID who sent the message
 * @param string|null $error Error message if failed
 * @return int|false SMS ID on success, false on failure
 */
function logSentSMS($toNumber, $message, $status = 'sent', $adminID = null, $error = null)
{
    global $conn;
    
    try {
        // Check if new columns exist, if not use basic insert
        $checkStmt = $conn->query("SHOW COLUMNS FROM sms_feedback LIKE 'direction'");
        $hasNewColumns = $checkStmt->rowCount() > 0;
        
        if ($hasNewColumns) {
            // Use new structure with direction, sent_by_admin_id, error_message
            $stmt = $conn->prepare("
                INSERT INTO sms_feedback (
                    from_number, 
                    message, 
                    sim_slot, 
                    status, 
                    createdAt,
                    direction,
                    sent_by_admin_id,
                    error_message
                ) 
                VALUES (?, ?, NULL, ?, NOW(), 'outbound', ?, ?)
            ");
            
            // For sent SMS, we store recipient in from_number field for consistency
            // and use status field to track sent/failed
            $dbStatus = $status === 'sent' ? 'sent' : 'failed';
            
            $stmt->execute([
                $toNumber, 
                $message, 
                $dbStatus, 
                $adminID,
                $error
            ]);
        } else {
            // Fallback to basic insert if migration not run yet
            $stmt = $conn->prepare("
                INSERT INTO sms_feedback (
                    from_number, 
                    message, 
                    sim_slot, 
                    status, 
                    createdAt
                ) 
                VALUES (?, ?, NULL, 'read', NOW())
            ");
            
            $stmt->execute([
                $toNumber, 
                $message
            ]);
        }
        
        return $conn->lastInsertId();
    } catch (PDOException $e) {
        error_log('SMS Log Error: ' . $e->getMessage());
        return false;
    }
}
