<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID   = $_SESSION['user_id'];
$courseID = (int)($_GET['id'] ?? 0);

// Get course + instructor
$stmt = $conn->prepare("
    SELECT c.title, CONCAT(u.firstName,' ',u.lastName) AS instructor
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ?
");
$stmt->execute([$courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die('Course not found.');
}

// Retake fee
$retakeFeePHP = 100;
$retakeFeeUSD = number_format($retakeFeePHP / 56, 2); // PHP → USD
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Course Retake Payment</title>

<!-- ✅ PAYPAL SDK: Replace with your Sandbox/Live Client ID -->
<script src="https://www.paypal.com/sdk/js?client-id=AWdwhlFGRCE7ZTivdRBY5lOp8_MGFaNoPDpUJZnNmm4TGJgR5MnpE4U9ijv7b98jQuL0tEGu8xDS4GQb&currency=USD&intent=capture"></script>

<style>
body {
    background: linear-gradient(135deg,#6a78ff,#7f5ac8);
    font-family: Arial, sans-serif;
}
.container {
    max-width: 600px;
    margin: 50px auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
}
.loader {
    text-align: center;
    margin: 20px 0;
    font-weight: bold;
}
.hidden { display: none; }
button.back-btn {
    padding: 8px 16px;
    margin-bottom: 20px;
    background: #ccc;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
button.back-btn:hover {
    background: #bbb;
}
.success-message {
    margin-top: 20px;
    padding: 15px;
    border-radius: 8px;
    background: #d4edda;
    color: #155724;
    display: none;
    font-weight: bold;
    text-align: center;
}
</style>
</head>

<body>

<div class="container">

    <h3>Order Summary</h3>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title']) ?></p>
    <p><strong>Instructor:</strong> <?= htmlspecialchars($course['instructor']) ?></p>
    <p><strong>Type:</strong> Course Retake Fee</p>
    <hr>
    <h2>Total: ₱<?= number_format($retakeFeePHP, 2) ?></h2>

    <!-- Back Button -->
    <button class="back-btn" onclick="history.back()">← Back</button>

    <!-- Loader -->
    <div id="paypalLoader" class="loader">
        ⏳ Loading PayPal...
    </div>

    <!-- PayPal Buttons -->
    <div id="paypal-button-container"></div>

    <!-- Success message -->
    <div class="success-message" id="successMessage">
        ✅ Payment successful! Redirecting...
    </div>

    <div style="margin-top:20px; background:#e6f7ff; padding:15px; border-radius:8px;">
        <strong>After payment:</strong>
        <ul>
            <li>Progress resets to 0%</li>
            <li>Quiz results cleared</li>
            <li>Course unlocked again</li>
        </ul>
    </div>

</div>

<script>
if (!window.paypal) {
    document.getElementById('paypalLoader').innerHTML =
        '❌ PayPal SDK failed to load.<br>Check Client ID or console.';
    throw new Error('PayPal SDK not loaded');
}

paypal.Buttons({

    onInit: function(data, actions) {
        actions.enable();
    },

    createOrder: function (data, actions) {
        return actions.order.create({
            purchase_units: [{
                description: "Course Retake Fee",
                amount: {
                    currency_code: "USD",
                    value: "<?= $retakeFeeUSD ?>"
                }
            }]
        });
    },

    onApprove: function (data, actions) {
        // Disable button while processing
        const container = document.getElementById('paypal-button-container');
        container.querySelectorAll('button').forEach(btn => btn.disabled = true);

        return actions.order.capture().then(function () {
            fetch('process_retake_payment.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    orderID: data.orderID,
                    courseID: <?= $courseID ?>,
                    amount: <?= $retakeFeePHP ?>
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('successMessage').style.display = 'block';
                    setTimeout(() => {
                        window.location.href = "course_learn.php?id=<?= $courseID ?>";
                    }, 2000);
                } else {
                    alert(res.message || "Payment saved, but course reset failed.");
                    container.querySelectorAll('button').forEach(btn => btn.disabled = false);
                }
            })
            .catch(err => {
                console.error(err);
                alert("Payment processed but error occurred. Contact support.");
                container.querySelectorAll('button').forEach(btn => btn.disabled = false);
            });
        });
    },

    onError: function (err) {
        console.error(err);
        alert("PayPal payment error. Check console for details.");
    }

}).render('#paypal-button-container').then(() => {
    document.getElementById('paypalLoader').classList.add('hidden');
});
</script>

</body>
</html>
