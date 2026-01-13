<?php
// payment_success.php
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

if (empty($transactionRef) || empty($courseID)) {
    $_SESSION['error'] = 'Invalid payment reference';
    header('Location: dashboard.php');
    exit();
}

// Get course and payment info
$stmt = $conn->prepare("
    SELECT c.title, c.courseID, p.amount, p.paymentDate, p.transactionReference, p.status
    FROM courses c
    JOIN payments p ON c.courseID = p.courseID
    WHERE c.courseID = ? AND p.transactionReference = ? AND p.userID = ?
");
$stmt->execute([$courseID, $transactionRef, $_SESSION['user_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = 'Payment not found';
    header('Location: dashboard.php');
    exit();
}

// Check if payment was successful
if ($payment['status'] !== 'completed') {
    header('Location: payment_failed.php?ref=' . urlencode($transactionRef) . '&course_id=' . $courseID);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .success-container { 
            max-width: 600px; 
            margin: 80px auto; 
            text-align: center;
            padding: 0 20px;
        }
        .check-icon { 
            width: 100px; 
            height: 100px; 
            background: #e8f5e9; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        .success-title {
            color: #43a047;
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
        .btn-primary {
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="check-icon">
            <i class="bi bi-check-lg" style="font-size: 56px; color: #43a047;"></i>
        </div>
        
        <h2 class="success-title">Payment Confirmed!</h2>
        <p class="text-muted mb-4">
            Thank you, <strong><?php echo htmlspecialchars($_SESSION['first_name']); ?></strong>. 
            You are now enrolled in <strong><?php echo htmlspecialchars($payment['title']); ?></strong>.
        </p>
        
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="card-title text-start mb-3">Transaction Summary</h6>
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <td class="text-start"><strong>Course:</strong></td>
                            <td class="text-end"><?php echo htmlspecialchars($payment['title']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Order ID:</strong></td>
                            <td class="text-end"><?php echo htmlspecialchars($payment['transactionReference']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Date:</strong></td>
                            <td class="text-end"><?php echo date('F d, Y', strtotime($payment['paymentDate'])); ?></td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Payment Method:</strong></td>
                            <td class="text-end">
                                <i class="bi bi-paypal text-primary"></i> PayPal
                            </td>
                        </tr>
                        <tr>
                            <td class="text-start"><strong>Total Paid:</strong></td>
                            <td class="text-end"><strong class="text-success">₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="alert alert-info mt-4 text-start">
            <i class="bi bi-info-circle"></i>
            <strong>What's Next?</strong><br>
            You can now access all course materials, complete lessons, and take quizzes. Start learning today!
        </div>
        
        <div class="d-grid gap-2 mt-4">
            <button class="btn btn-outline-secondary" onclick="window.location.href='my_courses.php'">
                Go to My Courses
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>