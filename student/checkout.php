<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['course_id'] ?? 0;
$userID = $_SESSION['user_id'];

// Get course
$stmt = $conn->prepare("
    SELECT c.*, CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ?
");
$stmt->execute([$courseID]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: course_catalog.php');
    exit();
}

// Check if already enrolled
$stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
if ($stmt->fetch()) {
    header('Location: my_courses.php');
    exit();
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $cardNumber = $_POST['card_number'] ?? '';
    $nameOnCard = $_POST['name_on_card'] ?? '';
    
    // Generate transaction reference
    $transactionRef = '#' . strtoupper(uniqid());
    
    try {
        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO payments (userID, courseID, amount, transactionReference, status, paymentDate, createdAt)
            VALUES (?, ?, ?, ?, 'completed', NOW(), NOW())
        ");
        $stmt->execute([$userID, $courseID, $course['price'], $transactionRef]);
        $paymentID = $conn->lastInsertId();
        
        // Create enrollment
        $stmt = $conn->prepare("
            INSERT INTO enrollments (userID, courseID, paymentID, progressPercentage, status, enrolledAt)
            VALUES (?, ?, ?, 0, 'active', NOW())
        ");
        $stmt->execute([$userID, $courseID, $paymentID]);
        
        // Redirect to success
        header('Location: payment_success.php?ref=' . urlencode($transactionRef) . '&course_id=' . $courseID);
        exit();
    } catch (Exception $e) {
        // Redirect to failure
        header('Location: payment_failed.php?course_id=' . $courseID);
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .checkout-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .checkout-grid { display: grid; grid-template-columns: 400px 1fr; gap: 30px; }
        .order-summary { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .course-img { width: 100%; height: 150px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999; }
        .payment-form { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .payment-method { border: 2px solid #e0e0e0; padding: 20px; border-radius: 8px; cursor: pointer; text-align: center; }
        .payment-method.active { border-color: #1e88e5; background: #f0f7ff; }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-grid">
            <div class="order-summary">
                <h5>Order Summary</h5>
                <div class="course-img mt-3">// photo</div>
                <h6 class="mt-3"><?php echo htmlspecialchars($course['title']); ?></h6>
                <p class="text-muted small">Instructor: <?php echo htmlspecialchars($course['instructorName']); ?></p>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total</strong>
                    <strong style="color: #1e88e5;">₱<?php echo number_format($course['price'], 2); ?></strong>
                </div>
            </div>
            
            <div class="payment-form">
                <h4>Checkout</h4>
                
                <form method="POST">
                    <h6 class="mt-4 mb-3">Select Payment Method</h6>
                    <div class="row mb-4">
                        <div class="col-4">
                            <div class="payment-method active" onclick="selectPayment(this)" data-method="credit">
                                <i class="bi bi-credit-card" style="font-size: 24px; color: #1e88e5;"></i>
                                <p class="mb-0 mt-2">Credit Card</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="payment-method" onclick="selectPayment(this)" data-method="paypal">
                                <i class="bi bi-paypal" style="font-size: 24px;"></i>
                                <p class="mb-0 mt-2">Paypal</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="payment-method" onclick="selectPayment(this)" data-method="gcash">
                                <i class="bi bi-wallet2" style="font-size: 24px;"></i>
                                <p class="mb-0 mt-2">GCash</p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="payment_method" id="paymentMethod" value="credit">
                    
                    <div class="mb-3">
                        <label class="form-label">Card Number</label>
                        <input type="text" name="card_number" class="form-control" placeholder="0000 0000 0000 0000" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="text" class="form-control" placeholder="MM / YY" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">CVC / CCC</label>
                            <input type="text" class="form-control" placeholder="123" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Name on Card</label>
                        <input type="text" name="name_on_card" class="form-control" placeholder="John Doe" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Billing Zip Code</label>
                        <input type="text" class="form-control" placeholder="10001" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" style="padding: 12px;">
                        Confirm Payment →
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function selectPayment(element) {
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('paymentMethod').value = element.dataset.method;
        }
    </script>
</body>
</html>