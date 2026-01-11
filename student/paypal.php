<?php
header('Content-Type: application/json');

// PayPal API Credentials
$clientID = "AY6u4H6soXFMgZnUAuF6THuqPVIDeVmJ8X-bOXz-ZIwLAdeiJKyluuEtEmpKdS-I2zTD3aviw4EQHuPz";
$secret = "ECH6Xw-MpT4hUhFaG1W_4kIcqDPSq2WUxD5r-mHg1HecPJEOjiMfBYIr70GtAetXhbN9UEqS_BmXZAXJ"; // Replace with your actual secret key from PayPal Dashboard

// Use Sandbox for testing, Live for production
$paypalURL = "https://api-m.sandbox.paypal.com"; // Change to https://api-m.paypal.com for live

// Get access token using file_get_contents (no cURL needed)
function getAccessToken($clientID, $secret, $paypalURL) {
    $auth = base64_encode($clientID . ":" . $secret);
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Authorization: Basic ' . $auth
            ],
            'content' => 'grant_type=client_credentials',
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($paypalURL . "/v1/oauth2/token", false, $context);
    
    if ($result === false) {
        return null;
    }
    
    $data = json_decode($result);
    return $data->access_token ?? null;
}

$action = $_GET['action'] ?? '';

// CREATE ORDER
if ($action === 'create') {
    $amount = $_GET['amount'] ?? '0.00';
    
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid amount']);
        exit;
    }
    
    $accessToken = getAccessToken($clientID, $secret, $paypalURL);
    
    if (!$accessToken) {
        echo json_encode(['error' => 'Failed to get access token. Check your PayPal credentials.']);
        exit;
    }
    
    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($amount, 2, '.', '')
                ]
            ]
        ]
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            'content' => json_encode($orderData),
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($paypalURL . "/v2/checkout/orders", false, $context);
    
    if ($result === false) {
        echo json_encode(['error' => 'Failed to create order. Network error.']);
        exit;
    }
    
    $response = json_decode($result);
    
    if (isset($response->id)) {
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Failed to create order', 'details' => $response]);
    }
}

// CAPTURE ORDER
elseif ($action === 'capture') {
    $orderID = $_GET['orderID'] ?? '';
    
    if (empty($orderID)) {
        echo json_encode(['error' => 'No order ID provided']);
        exit;
    }
    
    $accessToken = getAccessToken($clientID, $secret, $paypalURL);
    
    if (!$accessToken) {
        echo json_encode(['error' => 'Failed to get access token. Check your PayPal credentials.']);
        exit;
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($paypalURL . "/v2/checkout/orders/" . $orderID . "/capture", false, $context);
    
    if ($result === false) {
        echo json_encode(['error' => 'Failed to capture order. Network error.']);
        exit;
    }
    
    $response = json_decode($result);
    
    if (isset($response->status) && $response->status === 'COMPLETED') {
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Failed to capture order', 'details' => $response]);
    }
}

else {
    echo json_encode(['error' => 'Invalid action']);
}
?>