<?php
/**
 * SoleSource Voucher API Client
 * Handles all communication with SoleSource e-commerce platform
 * 
 * Setup:
 * 1. Add to .env or config: SOLESOURCE_API_KEY=your-shared-secret
 * 2. Set SOLESOURCE_API_BASE_URL (default: https://dev.art2cart.shop/api)
 */

// Require database connection
require_once __DIR__ . '/../database/db_connect.php';

// API Configuration (hardcoded for simplicity)
define('SOLESOURCE_API_BASE_URL', 'https://dev.art2cart.shop/api');
define('SOLESOURCE_API_KEY', '9ab34960972bd51c800304491a603c4705b675cfa956d1ff86c462bddf26b3c9');
define('COLLAB_WEBHOOK_SECRET', 'VOUCHER_NINJA_ACTIVATED_v2026_vouchers_go_brrrr');

/**
 * Generate a voucher for a student who completed a course
 * 
 * @param int $userId Learnexus user ID
 * @param int $certificateId Certificate ID (for tracking)
 * @param array $options Optional settings: discount-type, discount-value, expires-at
 * @return array Response from SoleSource API or error
 */
function solesource_generate_voucher($userId, $certificateId, $options = []) {
    global $conn;
    
    error_log("SOLESOURCE API: Function called with userId=$userId, certId=$certificateId");
    
    // Validate API key
    if (empty(SOLESOURCE_API_KEY)) {
        error_log('SoleSource API: ❌ Missing SOLESOURCE_API_KEY environment variable');
        return ['ok' => false, 'error' => 'missing-api-key'];
    }
    
    error_log("SOLESOURCE API: API Key present: " . substr(SOLESOURCE_API_KEY, 0, 10) . "...");
    
    // Build student identifier (you can customize this)
    $studentId = 'learnexus-' . $userId;
    
    error_log("SOLESOURCE API: Student ID: $studentId");
    
    // Default payload
    $payload = [
        'student-id' => $studentId,
        'discount-type' => $options['discount-type'] ?? 'percent',
        'discount-value' => $options['discount-value'] ?? 12, // 12% default
    ];
    
    error_log("SOLESOURCE API: Payload: " . json_encode($payload));
    
    // Optional expiry (defaults to 7 days on SoleSource side)
    if (isset($options['expires-at'])) {
        $payload['expires-at'] = $options['expires-at'];
    }
    
    // Make API call
    error_log("SOLESOURCE API: Calling API endpoint...");
    $response = solesource_api_request('/vouchers/generate.php', $payload);
    error_log("SOLESOURCE API: Raw response: " . json_encode($response));
    
    // If successful, store in database
    if ($response['ok'] ?? false) {
        $code = $response['code'];
        $expiresAt = $response['expires-at'];
        $discountType = $response['discount-type'];
        $discountValue = $response['discount-value'];
        
        // Insert voucher record
        $stmt = $conn->prepare("
            INSERT INTO vouchers (
                voucherCode, 
                userID, 
                certificateID, 
                discountPercentage, 
                discount_type,
                expiryDate,
                source,
                student_identifier,
                isUsed
            ) VALUES (?, ?, ?, ?, ?, ?, 'course', ?, 0)
        ");
        
        $expiryDate = date('Y-m-d', strtotime($expiresAt));
        
        if ($stmt->execute([
            $code, 
            $userId, 
            $certificateId, 
            $discountValue,
            $discountType,
            $expiryDate,
            $studentId
        ])) {
            $response['voucher_id'] = $conn->lastInsertId();
            error_log("SOLESOURCE API: ✅ Voucher $code saved to DB with ID " . $response['voucher_id']);
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log('SoleSource API: ❌ Failed to insert voucher - ' . print_r($errorInfo, true));
            $response['db_error'] = $errorInfo;
        }
    }
    
    return $response;
}

/**
 * Preview a voucher (validate without redeeming)
 * Useful for showing discount before student shops at SoleSource
 * 
 * @param string $voucherCode The voucher code
 * @param float $orderSubtotal Optional order amount to calculate discount
 * @return array Voucher details or error
 */
function solesource_preview_voucher($voucherCode, $orderSubtotal = null) {
    $payload = ['voucher-code' => $voucherCode];
    
    if ($orderSubtotal !== null) {
        $payload['order-subtotal'] = $orderSubtotal;
    }
    
    return solesource_api_request('/vouchers/preview.php', $payload);
}

/**
 * Internal: Make authenticated API request to SoleSource
 * 
 * @param string $endpoint API endpoint (e.g., /vouchers/generate.php)
 * @param array $payload Request body
 * @return array Decoded JSON response
 */
function solesource_api_request($endpoint, $payload) {
    $url = SOLESOURCE_API_BASE_URL . $endpoint;
    
    error_log("SOLESOURCE API: Requesting $url");
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SOLESOURCE_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false, // Disable for development (self-signed cert)
        CURLOPT_SSL_VERIFYHOST => false, // Disable for development
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("SOLESOURCE API: HTTP $httpCode | Response: " . substr($response, 0, 200));
    
    // Handle curl errors
    if ($error) {
        error_log("SoleSource API: ❌ CURL error - $error");
        return ['ok' => false, 'error' => 'network-error', 'details' => $error];
    }
    
    // Decode response
    $data = json_decode($response, true);
    
    // Log non-200 responses
    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("SoleSource API: ❌ HTTP $httpCode - " . $response);
    }
    
    return $data ?? ['ok' => false, 'error' => 'invalid-response', 'http_code' => $httpCode];
}
