<?php
// payment_failed.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$transactionRef = $_GET['ref'] ?? '';
$courseID = $_GET['course_id'] ?? 0;

// Get course info
$courseTitle = 'Unknown Course';
$coursePrice = 0;

if ($courseID) {
    $stmt = $conn->prepare("SELECT title, price FROM courses WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $course = $stmt->fetch();
    if ($course) {
        $courseTitle = $course['title'];
        $coursePrice = $course['price'];
    }
}

// Check if there's a failed payment record
$paymentStatus = 'Failed';
$paymentDate = date('F d, Y');
if (!empty($transactionRef)) {
    $stmt = $conn->prepare("SELECT status, paymentDate FROM payments WHERE transactionReference = ? AND userID = ?");
    $stmt->execute([$transactionRef, $_SESSION['user_id']]);
    $payment = $stmt->fetch();
    if ($payment) {
        $paymentStatus = ucfirst($payment['status']);
        $paymentDate = date('F d, Y', strtotime($payment['paymentDate']));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .failed-container { 
            max-width: 600px; 
            margin: 80px auto; 
            text-align: center;
            padding: 0 20px;
        }
        .error-icon { 
            width: 100px; 
            height: 100px; 
            background: #ffebee; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 24px;
            animation: shake 0.5s ease-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .error-title {
            color: #f44336;
            margin-bottom: 16px;
        }
        .card {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
            border-radius: 12px;
        }
        .table td {
            padding: 12px 8px;
            border: none;
        }
        .table tr:not(:last-child) {
            border-bottom: 1px solid #f0f0f0;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
        }
        .status-failed {
            color: #f44336;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="failed-container">
        <div class="error-icon">
            <i class="bi bi-x-circle" style="font-size: 56px; color: #f44336;"></i>
        </div>
        
        <h2 class="error-title">Payment Unsuccessful</h2>
        <p class="text-muted mb-4">
            Oops, something went wrong with your transaction. Your enrollment in 
            <strong><?php echo htmlspecialchars($courseTitle); ?></strong> is pending until payment is resolved.
        </p>
        
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="card-title text-start mb-3">Transaction Details</h6>
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td class="text-start"><strong>Course:</strong></td>
                            <td class="text-end"><?php echo htmlspecialchars($courseTitle); ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Order ID:</strong></td>
                            <td class="text-end"><?php echo !empty($transactionRef) ? htmlspecialchars($transactionRef) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Date:</strong></td>
                            <td class="text-end"><?php echo $paymentDate; ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Payment Method:</strong></td>
                            <td class="text-end">
                                <i class="bi bi-paypal text-primary"></i> PayPal
                            </td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Amount:</strong></td>
                            <td class="text-end">₱<?php echo number_format($coursePrice, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Status:</strong></td>
                            <td class="text-end">
                                <span class="status-failed">● <?php echo $paymentStatus; ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="alert alert-warning mt-4 text-start">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Common Reasons for Payment Failure:</strong><br>
            <ul class="mb-0 mt-2 text-start">
                <li>Insufficient funds in your account</li>
                <li>Payment was cancelled during checkout</li>
                <li>Technical issue with payment provider</li>
                <li>Card or account verification required</li>
            </ul>
        </div>
        
        <div class="d-grid gap-2 mt-4">
            <button class="btn btn-primary" onclick="window.location.href='checkout.php?course_id=<?php echo $courseID; ?>'">
                <i class="bi bi-arrow-clockwise"></i> Retry Payment
            </button>
            <button class="btn btn-outline-secondary" onclick="window.location.href='course_details.php?id=<?php echo $courseID; ?>'">
                <i class="bi bi-arrow-left"></i> Back to Course
            </button>
            <button class="btn btn-outline-secondary" onclick="window.location.href='dashboard.php'">
                Go to Dashboard
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>