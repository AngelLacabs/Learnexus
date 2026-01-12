<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

$stmt = $conn->prepare("
    SELECT c.*, CONCAT(u.firstName,' ',u.lastName) instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ?
");
$stmt->execute([$courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: my_courses.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pay Retake</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://www.paypal.com/sdk/js?client-id=YOUR_PAYPAL_CLIENT_ID&currency=USD"></script>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow mx-auto" style="max-width:600px">
        <div class="card-body text-center">

            <h4><?php echo htmlspecialchars($course['title']); ?></h4>
            <p class="text-muted">Instructor: <?php echo htmlspecialchars($course['instructorName']); ?></p>

            <h2 class="text-primary mb-4">
                ₱<?php echo number_format($course['price'], 2); ?>
            </h2>

            <button id="payNowBtn" class="btn btn-lg btn-primary mb-4">
                Pay Now
            </button>

            <div id="paypalSection" style="display:none">
                <div id="paypal-button-container"></div>
            </div>

        </div>
    </div>
</div>

<script>
document.getElementById('payNowBtn').onclick = function () {
    this.style.display = 'none';
    document.getElementById('paypalSection').style.display = 'block';
};

paypal.Buttons({

    // ✅ SERVER-SIDE ORDER CREATION
    createOrder: function () {
        return fetch(
            'paypal_api.php?action=create&amount=<?php echo $course['price']; ?>'
        )
        .then(res => res.json())
        .then(data => data.id);
    },

    // ✅ SERVER-SIDE CAPTURE
    onApprove: function (data) {
        return fetch(
            'paypal_api.php?action=capture&orderID=' + data.orderID
        )
        .then(res => res.json())
        .then(details => {

            if (details.status === 'COMPLETED') {
                window.location.href =
                    'payment_success.php?ref=' + data.orderID +
                    '&course_id=<?php echo $courseID; ?>';
            } else {
                window.location.href = 'payment_failed.php';
            }
        });
    },

    onCancel: () => {
        window.location.href = 'payment_failed.php';
    },

    onError: err => {
        console.error(err);
        window.location.href = 'payment_failed.php';
    }

}).render('#paypal-button-container');
</script>

</body>
</html>
