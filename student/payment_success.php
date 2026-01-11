<?php
// ==========================================
// FILE: student/payment_success.php (Picture 5)
// ==========================================
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$transactionRef = $_GET['ref'] ?? '';
$courseID = $_GET['course_id'] ?? 0;

// Get course and payment info
$stmt = $conn->prepare("
    SELECT c.title, p.amount, p.paymentDate, p.transactionReference
    FROM courses c
    JOIN payments p ON c.courseID = p.courseID
    WHERE c.courseID = ? AND p.transactionReference = ? AND p.userID = ?
");
$stmt->execute([$courseID, $transactionRef, $_SESSION['user_id']]);
$payment = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .success-container { max-width: 600px; margin: 80px auto; text-align: center; }
        .check-icon { width: 80px; height: 80px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="check-icon">
            <i class="bi bi-check-lg" style="font-size: 48px; color: #43a047;"></i>
        </div>
        <h2>Payment Confirmed!</h2>
        <p class="text-muted">Thank you, <?php echo htmlspecialchars($_SESSION['first_name']); ?>. You are now enrolled in <strong><?php echo htmlspecialchars($payment['title']); ?></strong>.</p>
        
        <div class="card mt-4" style="max-width: 500px; margin: 0 auto;">
            <div class="card-body">
                <h6 class="card-title">Transaction Summary</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Course:</strong></td>
                        <td><?php echo htmlspecialchars($payment['title']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Order ID:</strong></td>
                        <td><?php echo htmlspecialchars($transactionRef); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date:</strong></td>
                        <td><?php echo date('F d, Y', strtotime($payment['paymentDate'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td><i class="bi bi-credit-card"></i> Visa ending in 4242</td>
                    </tr>
                    <tr>
                        <td><strong>Total Paid:</strong></td>
                        <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <button class="btn btn-primary btn-lg mt-4" onclick="window.location.href='my_courses.php'">
            Start Learning Now
        </button>
    </div>
</body>
</html>

<?php
// ==========================================
// FILE: student/payment_failed.php (Picture 6)
// ==========================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .failed-container { max-width: 600px; margin: 80px auto; text-align: center; }
        .error-icon { width: 80px; height: 80px; background: #ffebee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    </style>
</head>
<body>
    <div class="failed-container">
        <div class="error-icon">
            <i class="bi bi-credit-card-2-front" style="font-size: 48px; color: #f44336; text-decoration: line-through;"></i>
        </div>
        <h2>Payment Unsuccessful</h2>
        <p class="text-muted">Oops, something went wrong with your transaction. Your enrollment in <strong>Advanced UX Design Principles</strong> is pending until payment is resolved.</p>
        
        <div class="card mt-4" style="max-width: 500px; margin: 0 auto;">
            <div class="card-body">
                <h6 class="card-title">Transaction Details</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Course:</strong></td>
                        <td>Advanced UX Design Principles</td>
                    </tr>
                    <tr>
                        <td><strong>Order ID:</strong></td>
                        <td>#1234E-LX</td>
                    </tr>
                    <tr>
                        <td><strong>Date:</strong></td>
                        <td>January 02, 2026</td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td><i class="bi bi-credit-card"></i> Visa ending in 4242</td>
                    </tr>
                    <tr>
                        <td><strong>Amount:</strong></td>
                        <td>₱99.99</td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span style="color: #f44336;">● Failed</span></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="d-flex gap-2 justify-content-center mt-4">
            <button class="btn btn-outline-secondary" onclick="window.location.href='checkout.php?course_id=<?php echo $courseID; ?>'">
                Change Method
            </button>
            <button class="btn btn-primary" onclick="window.location.href='checkout.php?course_id=<?php echo $courseID; ?>'">
                Retry Payment
            </button>
        </div>
    </div>
</body>
</html>

<?php
// ==========================================
// FILE: student/my_courses.php (Picture 7)
// ==========================================
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get enrolled courses
$stmt = $conn->prepare("
    SELECT c.*, e.progressPercentage, e.status, e.enrollmentID,
           CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    WHERE e.userID = ?
    ORDER BY e.enrolledAt DESC
");
$stmt->execute([$userID]);
$courses = $stmt->fetchAll();

// Separate by status
$inProgress = array_filter($courses, fn($c) => $c['status'] == 'active');
$completed = array_filter($courses, fn($c) => $c['status'] == 'completed');

// Get certificate count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE userID = ?");
$stmt->execute([$userID]);
$certCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Courses - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .container-main { max-width: 1200px; margin: 40px auto; padding: 0 40px; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .course-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .course-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .course-image { height: 180px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); display: flex; align-items: center; justify-content: center; color: #999; position: relative; }
        .status-badge { position: absolute; top: 12px; left: 12px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-badge.ongoing { background: #e3f2fd; color: #1e88e5; }
        .status-badge.completed { background: #e8f5e9; color: #43a047; }
        .progress { height: 8px; border-radius: 10px; background: #f0f0f0; }
        .progress-bar { background: #1e88e5; border-radius: 10px; }
        .progress-bar.completed { background: #43a047; }
    </style>
</head>
<body>
    <div class="container-main">
        <h2>My Courses</h2>
        <p class="text-muted">Manage and continue your learning journey.</p>
        
        <div class="stats-row">
            <div class="stat-card">
                <div style="color: #1e88e5; margin-bottom: 10px;"><i class="bi bi-clock-history" style="font-size: 24px;"></i></div>
                <h3><?php echo count($inProgress); ?></h3>
                <p class="text-muted mb-0">Courses in Progress</p>
            </div>
            <div class="stat-card">
                <div style="color: #43a047; margin-bottom: 10px;"><i class="bi bi-check-circle" style="font-size: 24px;"></i></div>
                <h3><?php echo count($completed); ?></h3>
                <p class="text-muted mb-0">Completed Courses</p>
            </div>
            <div class="stat-card">
                <div style="color: #fb8c00; margin-bottom: 10px;"><i class="bi bi-award" style="font-size: 24px;"></i></div>
                <h3><?php echo $certCount; ?></h3>
                <p class="text-muted mb-0">Certificates Earned</p>
            </div>
        </div>
        
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#all">All Courses</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#ongoing">On Going</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#completed">Completed</a>
            </li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="all">
                <div class="course-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-image">
                                <span class="status-badge <?php echo $course['status'] == 'completed' ? 'completed' : 'ongoing'; ?>">
                                    • <?php echo $course['status'] == 'completed' ? 'Completed' : 'Ongoing'; ?>
                                </span>
                                // photo
                            </div>
                            <div class="p-3">
                                <h6><?php echo htmlspecialchars($course['title']); ?></h6>
                                <p class="text-muted small">by <?php echo htmlspecialchars($course['instructorName']); ?></p>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small"><?php echo $course['status'] == 'completed' ? 'Score' : 'Progress'; ?></span>
                                        <span class="small"><?php echo round($course['progressPercentage']); ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar <?php echo $course['status'] == 'completed' ? 'completed' : ''; ?>" 
                                             style="width: <?php echo $course['progressPercentage']; ?>%"></div>
                                    </div>
                                </div>
                                
                                <?php if ($course['status'] == 'completed'): ?>
                                    <button class="btn btn-success btn-sm w-100" onclick="window.location.href='view_certificate.php?enrollment_id=<?php echo $course['enrollmentID']; ?>'">
                                        <i class="bi bi-download"></i> View Certificate
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm w-100" onclick="window.location.href='course_content.php?id=<?php echo $course['courseID']; ?>'">
                                        Continue Learning →
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>