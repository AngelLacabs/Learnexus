<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, 
           CONCAT(u.firstName, ' ', u.lastName) as instructorName,
           e.enrollmentID
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    LEFT JOIN enrollments e ON c.courseID = e.courseID AND e.userID = ?
    WHERE c.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: my_courses.php');
    exit();
}

// Get latest quiz result
$stmt = $conn->prepare("
    SELECT qr.*, q.title as quizTitle
    FROM quizresults qr
    JOIN quizzes q ON qr.quizID = q.quizID
    WHERE q.courseID = ? AND qr.userID = ?
    ORDER BY qr.takenAt DESC
    LIMIT 1
");
$stmt->execute([$courseID, $userID]);
$quizResult = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retake Course - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        .payment-header {
            background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
            color: white;
            padding: 40px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        .payment-body {
            padding: 40px;
        }
        .alert-failed {
            background: #ffebee;
            border: 2px solid #f44336;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .course-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .price-tag {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
            text-align: center;
            margin: 30px 0;
        }
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 12px;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<div class="payment-card">
    <div class="payment-header">
        <i class="bi bi-exclamation-triangle" style="font-size: 60px;"></i>
        <h2 class="mt-3 mb-0">Course Retake Required</h2>
    </div>

    <div class="payment-body">
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="alert-failed">
            <h5 class="text-danger mb-3">
                <i class="bi bi-x-circle-fill"></i> Quiz Failed
            </h5>
            <p class="mb-2"><strong>Your Score:</strong> <?php echo number_format($quizResult['percentage'] ?? 0, 1); ?>%</p>
            <p class="mb-0"><strong>Required:</strong> 70% to pass</p>
            <hr>
            <p class="mb-0 text-muted">
                <i class="bi bi-info-circle"></i> To retake this course and attempt the quiz again, you need to pay the course fee.
            </p>
        </div>

        <div class="course-info">
            <h5 class="mb-3"><i class="bi bi-book"></i> Course Details</h5>
            <div class="info-row">
                <span class="text-muted">Course Title:</span>
                <strong><?php echo htmlspecialchars($course['title']); ?></strong>
            </div>
            <div class="info-row">
                <span class="text-muted">Instructor:</span>
                <strong><?php echo htmlspecialchars($course['instructorName']); ?></strong>
            </div>
            <div class="info-row">
                <span class="text-muted">Category:</span>
                <strong><?php echo htmlspecialchars($course['category'] ?? 'General'); ?></strong>
            </div>
        </div>

        <div class="price-tag">
            ₱<?php echo number_format($course['price'], 2); ?>
        </div>

        <p class="text-center text-muted mb-4">
            Payment for course retake and quiz reattempt
        </p>

        <a href="retake_payment_form.php?id=<?php echo $courseID; ?>" class="btn btn-pay">
            <i class="bi bi-credit-card"></i> Pay Again to Retake Course
        </a>

        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- PayPal SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_PAYPAL_CLIENT_ID&currency=PHP"></script>

<script>
paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                amount: {
                    value: '<?php echo $course['price']; ?>',
                    currency_code: 'PHP'
                },
                description: 'Retake: <?php echo addslashes($course['title']); ?>'
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            // Send payment data to server
            fetch('process_retake_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    orderID: data.orderID,
                    courseID: <?php echo $courseID; ?>,
                    amount: <?php echo $course['price']; ?>,
                    transactionID: details.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Payment successful! You can now retake the course.');
                    window.location.href = 'course_learn.php?id=<?php echo $courseID; ?>';
                } else {
                    alert('Payment processing failed. Please contact support.');
                }
            });
        });
    },
    onError: function(err) {
        console.error('PayPal Error:', err);
        alert('Payment failed. Please try again.');
    }
}).render('#paypal-button-container');
</script>

</body>
</html>