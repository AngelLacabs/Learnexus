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

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userAvatar = $stmt->fetchColumn();

$page_title = "Checkout - Learnexus";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-accent: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation */
        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: #1a73e8;
            border-radius: 0 4px 4px 0;
            transition: height 0.25s ease;
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: var(--gradient-primary);
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active::before {
            display: none;
        }

        /* Hamburger */
        .hamburger-btn {
            width: 50px;
            height: 50px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .hamburger-icon span {
            display: block;
            width: 24px;
            height: 3px;
            background: #1a73e8;
            border-radius: 3px;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        /* Main Content Margin */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 992px) {
            .checkout-grid {
                grid-template-columns: 400px 1fr;
            }
        }

        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: none;
            position: sticky;
            top: 20px;
        }

        .course-img {
            width: 100%;
            height: 180px;
            background: var(--gradient-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            box-shadow: inset 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .payment-form {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .paypal-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        .paypal-badge i {
            font-size: 40px;
            margin-bottom: 10px;
            display: block;
        }

        .paypal-badge h5 {
            margin-bottom: 5px;
            font-weight: 700;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            padding: 12px 16px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 25px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #0d47a1;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            margin: 20px 0;
        }

        .loading-spinner .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        #paypal-button-container {
            margin-top: 20px;
        }

        .total-amount {
            font-size: 2rem;
            font-weight: 800;
            color: #1e88e5;
        }

        .usd-equivalent {
            color: #666;
            font-size: 0.9rem;
        }

        .security-badge {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-top: 20px;
        }

        .security-badge i {
            color: #28a745;
            font-size: 1.2rem;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 767px) {

            .order-summary,
            .payment-form {
                padding: 20px;
            }

            .course-img {
                height: 150px;
                font-size: 3rem;
            }
        }
    </style>
</head>

<body>
    <!-- Hamburger Button -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
            id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100"
        style="width: var(--sidebar-width);" id="sidebar" tabindex="-1">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>
            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="my_courses.php">
                    <i class="bi bi-journal-bookmark fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="certificates.php">
                    <i class="bi bi-award fs-5"></i><span>Certificates</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="vouchers.php">
                    <i class="bi bi-ticket-perforated fs-5"></i><span>Vouchers</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="ai_chatbot.php">
                    <i class="bi bi-robot fs-5"></i><span>AI Tutor</span>
                </a>
            </nav>
            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold"
                    onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <button class="btn btn-outline-secondary rounded-pill px-3"
                                    onclick="window.history.back()">
                                    <i class="bi bi-arrow-left me-2"></i>Back
                                </button>
                                <h4 class="mb-0 fw-bold">Secure Checkout</h4>
                            </div>
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'"
                                role="button">
                                <span
                                    class="fw-semibold d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                    style="width: 45px; height: 45px; background: var(--gradient-primary);">
                                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar"
                                            class="w-100 h-100 rounded-circle object-fit-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Checkout Content -->
            <div class="fade-in">
                <div class="row g-4">
                    <!-- Order Summary -->
                    <div class="col-lg-4 col-12">
                        <div class="order-summary">
                            <h5 class="fw-bold mb-4">
                                <i class="bi bi-receipt me-2"></i>Order Summary
                            </h5>
                            <div class="course-img mb-3">
                                <i class="bi bi-book"></i>
                            </div>
                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>
                            <p class="text-muted small mb-4">
                                <i class="bi bi-person me-1"></i>Instructor:
                                <?php echo htmlspecialchars($course['instructorName']); ?>
                            </p>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Total Amount</span>
                                <span class="total-amount">₱<?php echo number_format($course['price'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="usd-equivalent">USD Equivalent</span>
                                <span
                                    class="usd-equivalent">$<?php echo number_format($course['price'] / 56, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <div class="col-lg-8 col-12">
                        <div class="payment-form">
                            <h4 class="fw-bold mb-4">
                                <i class="bi bi-credit-card me-2"></i>Payment Details
                            </h4>
                            <div class="paypal-badge">
                                <i class="bi bi-paypal"></i>
                                <h5>Pay with PayPal</h5>
                                <small>Safe, secure, and instant payment processing</small>
                            </div>
                            <form id="payment-form">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-person me-1"></i>Full Name <span
                                                class="text-danger">*</span>
                                        </label>
                                        <input type="text" id="fullname" class="form-control"
                                            value="<?php echo htmlspecialchars($userInfo['firstName'] . ' ' . $userInfo['lastName']); ?>"
                                            required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-envelope me-1"></i>Email Address <span
                                                class="text-danger">*</span>
                                        </label>
                                        <input type="email" id="email" class="form-control"
                                            value="<?php echo htmlspecialchars($userInfo['email']); ?>" required>
                                        <small class="form-text text-muted">We'll send the receipt to this email</small>
                                    </div>
                                    <div class="col-12 mb-4">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-phone me-1"></i>Mobile Number <span
                                                class="text-danger">*</span>
                                        </label>
                                        <input type="tel" id="mobile" class="form-control"
                                            placeholder="+63 XXX XXX XXXX" pattern="[0-9+\s\-()]+" required>
                                        <small class="form-text text-muted">For order verification purposes</small>
                                    </div>
                                </div>
                                <div class="alert alert-info mb-4">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Click the PayPal button below to complete your payment securely. Your information is
                                    encrypted and protected.
                                </div>
                                <div id="error-message" class="alert alert-danger" style="display: none;"></div>
                                <!-- Loading Spinner -->
                                <div class="loading-spinner">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Processing...</span>
                                    </div>
                                    <p class="mt-3 fw-semibold">Processing your payment...</p>
                                </div>
                                <!-- PayPal Button Container -->
                                <div id="paypal-button-container"></div>
                                <div class="security-badge">
                                    <i class="bi bi-shield-check me-2"></i>
                                    <span class="fw-semibold">Your payment information is secure</span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- PayPal SDK -->
    <script
        src="https://www.paypal.com/sdk/js?client-id=AY6u4H6soXFMgZnUAuF6THuqPVIDeVmJ8X-bOXz-ZIwLAdeiJKyluuEtEmpKdS-I2zTD3aviw4EQHuPz&currency=USD"></script>

    <script>
        const courseID = <?php echo $courseID; ?>;
        const userID = <?php echo $userID; ?>;
        const coursePrice = <?php echo $course['price']; ?>;
        const usdAmount = (coursePrice / 56).toFixed(2);

        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + message;
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

            createOrder: function () {
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

            onApprove: function (data) {
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

            onError: function (err) {
                showLoading(false);
                showError('An error occurred during the payment process. Please try again.');
                console.error('PayPal error:', err);
            },

            onCancel: function (data) {
                showLoading(false);
                showError('Payment was cancelled. Please try again when ready.');
                console.log('Payment cancelled:', data);
            }

        }).render('#paypal-button-container');
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }
    </script>
</body>

</html>