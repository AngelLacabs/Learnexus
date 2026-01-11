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

// Get user info for pre-filling
$stmt = $conn->prepare("SELECT firstName, lastName, email FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userInfo = $stmt->fetch();
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
        .paypal-badge {
            background: #0070ba;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
        }
        .paypal-badge i {
            font-size: 32px;
            margin-bottom: 10px;
        }
        #paypal-button-container {
            margin-top: 20px;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-grid">
            <div class="order-summary">
                <h5>Order Summary</h5>
                <div class="course-img mt-3">
                    <i class="bi bi-book" style="font-size: 48px;"></i>
                </div>
                <h6 class="mt-3"><?php echo htmlspecialchars($course['title']); ?></h6>
                <p class="text-muted small">Instructor: <?php echo htmlspecialchars($course['instructorName']); ?></p>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total Amount</strong>
                    <strong style="color: #1e88e5;">₱<?php echo number_format($course['price'], 2); ?></strong>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="text-muted small">USD Equivalent</span>
                    <span class="text-muted small">$<?php echo number_format($course['price'] / 56, 2); ?></span>
                </div>
            </div>
            
            <div class="payment-form">
                <h4>Checkout</h4>
                
                <div class="paypal-badge">
                    <i class="bi bi-paypal"></i>
                    <h5 class="mb-0">Pay with PayPal</h5>
                    <small>Safe and secure payment</small>
                </div>
                
                <form id="payment-form">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="fullname" class="form-control" 
                               value="<?php echo htmlspecialchars($userInfo['firstName'] . ' ' . $userInfo['lastName']); ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" id="email" class="form-control" 
                               value="<?php echo htmlspecialchars($userInfo['email']); ?>" 
                               required>
                        <small class="form-text text-muted">We'll send the receipt to this email</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                        <input type="tel" id="mobile" class="form-control" placeholder="+63 XXX XXX XXXX" 
                               pattern="[0-9+\s\-()]+" required>
                        <small class="form-text text-muted">For order verification purposes</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Click the PayPal button below to complete your payment securely.
                    </div>

                    <div id="error-message" class="alert alert-danger" style="display: none;"></div>
                    
                    <!-- Loading Spinner -->
                    <div class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <p class="mt-2">Processing your payment...</p>
                    </div>
                    
                    <!-- PayPal Button Container -->
                    <div id="paypal-button-container"></div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-shield-check"></i> Your payment information is secure
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=AY6u4H6soXFMgZnUAuF6THuqPVIDeVmJ8X-bOXz-ZIwLAdeiJKyluuEtEmpKdS-I2zTD3aviw4EQHuPz&currency=USD"></script>

    <script>
        const courseID = <?php echo $courseID; ?>;
        const userID = <?php echo $userID; ?>;
        const coursePrice = <?php echo $course['price']; ?>;
        const usdAmount = (coursePrice / 56).toFixed(2);

        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            console.error(message);
        }

        function hideError() {
            document.getElementById('error-message').style.display = 'none';
        }

        function showLoading(show) {
            document.querySelector('.loading-spinner').style.display = show ? 'block' : 'none';
            document.getElementById('paypal-button-container').style.display = show ? 'none' : 'block';
        }

        function validateForm() {
            hideError();
            const name = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const mobile = document.getElementById('mobile').value.trim();

            if (!name || !email || !mobile) {
                showError('Please fill in all required fields before proceeding to PayPal.');
                return false;
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                showError('Please enter a valid email address.');
                return false;
            }

            return true;
        }

        paypal.Buttons({
            style: {
                color: 'gold',
                shape: 'pill',
                label: 'pay',
                height: 50
            },

            createOrder: function() {
                console.log('Creating order...');
                
                if (!validateForm()) {
                    return Promise.reject('Form validation failed');
                }

                showLoading(true);

                return fetch("paypal.php?action=create&amount=" + usdAmount)
                    .then(res => {
                        console.log('Response status:', res.status);
                        return res.json();
                    })
                    .then(data => {
                        console.log('Create order response:', data);
                        showLoading(false);
                        
                        if (data.id) {
                            return data.id;
                        } else if (data.error) {
                            throw new Error(data.error);
                        } else {
                            throw new Error('Failed to create PayPal order');
                        }
                    })
                    .catch(error => {
                        showLoading(false);
                        showError('Error creating PayPal order: ' + error.message);
                        console.error('Create order error:', error);
                        return Promise.reject(error);
                    });
            },

            onApprove: function(data) {
                console.log('Payment approved, capturing...');
                showLoading(true);

                return fetch("paypal.php?action=capture&orderID=" + data.orderID)
                    .then(res => {
                        console.log('Capture response status:', res.status);
                        return res.json();
                    })
                    .then(details => {
                        console.log('Capture details:', details);

                        if (details.error) {
                            throw new Error(details.error);
                        }

                        const transactionRef = data.orderID;
                        const name = document.getElementById('fullname').value;
                        const email = document.getElementById('email').value;
                        const mobile = document.getElementById('mobile').value;

                        return fetch('process_enrollment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                courseID: courseID,
                                userID: userID,
                                amount: coursePrice,
                                transactionRef: transactionRef,
                                payerName: details.payer.name.given_name + ' ' + details.payer.name.surname,
                                payerEmail: details.payer.email_address,
                                mobile: mobile
                            })
                        })
                        .then(res => res.json())
                        .then(result => {
                            console.log('Enrollment result:', result);
                            showLoading(false);
                            
                            if (result.success) {
                                alert("Payment completed successfully! Welcome to the course.");
                                window.location.href = 'payment_success.php?ref=' + transactionRef + '&course_id=' + courseID;
                            } else {
                                alert('Payment received but enrollment failed: ' + result.message + '\n\nTransaction ID: ' + transactionRef + '\n\nPlease contact support with this Transaction ID.');
                                showError('Enrollment failed: ' + result.message);
                                console.error('Enrollment error details:', result);
                            }
                        });
                    })
                    .catch(error => {
                        showLoading(false);
                        showError('Error processing payment: ' + error.message);
                        console.error('Approve error:', error);
                    });
            },

            onError: function(err) {
                showLoading(false);
                showError('An error occurred during the payment process. Please try again.');
                console.error('PayPal error:', err);
            },

            onCancel: function(data) {
                showLoading(false);
                showError('Payment was cancelled. Please try again when ready.');
                console.log('Payment cancelled:', data);
            }

        }).render('#paypal-button-container');
    </script>
</body>
</html>