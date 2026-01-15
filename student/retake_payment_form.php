<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID   = $_SESSION['user_id'];
$courseID = (int)($_GET['id'] ?? 0);


// Get course + instructor + price (USED FOR PAYMENT)
$stmt = $conn->prepare("
    SELECT 
        c.title,
        c.price,
        CONCAT(u.firstName,' ',u.lastName) AS instructor
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ?
");
$stmt->execute([$courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die('Course not found.');
}

// ✅ SINGLE SOURCE OF TRUTH
$retakeFeePHP = (float)$course['price'];
$retakeFeeUSD = number_format($retakeFeePHP / 56, 2, '.', '');


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Course Retake Payment</title>

<!-- ✅ PAYPAL SANDBOX SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=AX4hen2XSpQQzdp6w9zGYDokMhG2vCsABiXO335LDFtN5crinmeCiyCXp3lIe5a9RWHkUJtQbIsU0PJt&currency=USD&intent=capture"></script>

<style>
body { background: linear-gradient(135deg,#6a78ff,#7f5ac8); font-family: Arial, sans-serif; }
.container { max-width: 600px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 12px; }
.loader { text-align: center; margin: 20px 0; font-weight: bold; }
.hidden { display: none; }
button.back-btn { padding: 8px 16px; margin-bottom: 20px; background: #ccc; border: none; border-radius: 5px; cursor: pointer; }
button.back-btn:hover { background: #bbb; }
.success-message { margin-top: 20px; padding: 15px; border-radius: 8px; background: #d4edda; color: #155724; display: none; font-weight: bold; text-align: center; }
.error-message { margin-top: 20px; padding: 15px; border-radius: 8px; background: #f8d7da; color: #721c24; display: none; font-weight: bold; text-align: center; }
</style>
</head>
<body>

<div class="container">

    <h3>Order Summary</h3>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title']) ?></p>
    <p><strong>Instructor:</strong> <?= htmlspecialchars($course['instructor']) ?></p>
    <p><strong>Type:</strong> Course Retake Fee</p>
    <hr>
    <h2>Total: ₱<?= number_format($retakeFeePHP, 2) ?> (~$<?= $retakeFeeUSD ?>)</h2>

    <button class="back-btn" onclick="history.back()">← Back</button>

    <div id="paypalLoader" class="loader">⏳ Loading PayPal...</div>
    <div id="paypal-button-container"></div>

    <div class="success-message" id="successMessage">✅ Payment successful! Redirecting...</div>
    <div class="error-message" id="errorMessage"></div>

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
    document.getElementById('paypalLoader').innerHTML = '❌ PayPal SDK failed to load.<br>Check Client ID.';
    throw new Error('PayPal SDK not loaded');
}

paypal.Buttons({
    onInit: function(data, actions) { actions.enable(); },
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                description: "Course Retake Fee",
                amount: { currency_code: "USD", value: "<?= $retakeFeeUSD ?>" }
            }]
        });
    },
    onApprove: function(data, actions) {
        document.querySelectorAll('#paypal-button-container button').forEach(btn => btn.disabled = true);

        return actions.order.capture().then(function() {
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
                    setTimeout(() => window.location.href = "course_learn.php?id=<?= $courseID ?>", 2000);
                } else {
                    const errorEl = document.getElementById('errorMessage');
                    errorEl.innerText = res.message || "Payment saved, but course reset failed.";
                    errorEl.style.display = 'block';
                    document.querySelectorAll('#paypal-button-container button').forEach(btn => btn.disabled = false);
                }
            })
            .catch(err => {
                console.error(err);
                const errorEl = document.getElementById('errorMessage');
                errorEl.innerText = "Payment processed but error occurred. Contact support.";
                errorEl.style.display = 'block';
                document.querySelectorAll('#paypal-button-container button').forEach(btn => btn.disabled = false);
            });
        });
    },
    onError: function(err) {
        console.error(err);
        const errorEl = document.getElementById('errorMessage');
        errorEl.innerText = "PayPal payment error. Check console for details.";
        errorEl.style.display = 'block';
    }
}).render('#paypal-button-container').then(() => {
    document.getElementById('paypalLoader').classList.add('hidden');
});
</script>
</body>
</html>
