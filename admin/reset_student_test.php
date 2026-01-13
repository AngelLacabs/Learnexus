<?php
session_start();
require_once '../database/db_connect.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentID = (int)$_POST['student_id'];
    $courseID = (int)$_POST['course_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get enrollment
        $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
        $stmt->execute([$studentID, $courseID]);
        $enrollment = $stmt->fetch();
        
        if (!$enrollment) {
            throw new Exception('Enrollment not found - student must be enrolled in the course first');
        }
        
        // 1. Delete certificate
        $stmt = $conn->prepare("DELETE FROM certificates WHERE userID = ? AND courseID = ?");
        $stmt->execute([$studentID, $courseID]);
        $certDeleted = $stmt->rowCount();
        
        // 2. Delete vouchers for this student/course
        $stmt = $conn->prepare("
            DELETE v FROM vouchers v
            JOIN certificates c ON v.certificateID = c.certificateID
            WHERE c.userID = ? AND c.courseID = ?
        ");
        $stmt->execute([$studentID, $courseID]);
        $vouchersDeleted = $stmt->rowCount();
        
        // 3. Delete lesson completions
        $stmt = $conn->prepare("DELETE FROM lesson_completions WHERE enrollmentID = ?");
        $stmt->execute([$enrollment['enrollmentID']]);
        $lessonsDeleted = $stmt->rowCount();
        
        // 4. Delete quiz results
        $stmt = $conn->prepare("
            DELETE qr FROM quiz_results qr
            JOIN quizzes q ON qr.quizID = q.quizID
            WHERE qr.userID = ? AND q.courseID = ?
        ");
        $stmt->execute([$studentID, $courseID]);
        $quizDeleted = $stmt->rowCount();
        
        // 5. Reset enrollment progress
        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET progressPercentage = 0,
                status = 'active',
                completedAt = NULL
            WHERE enrollmentID = ?
        ");
        $stmt->execute([$enrollment['enrollmentID']]);
        
        $conn->commit();
        
        $_SESSION['success'] = "✅ Progress reset successfully! Deleted: $certDeleted certificate(s), $vouchersDeleted voucher(s), $lessonsDeleted lesson completions, $quizDeleted quiz results.";
        error_log("Admin reset: Student $studentID / Course $courseID - Progress cleared for testing");
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = '❌ Reset failed: ' . $e->getMessage();
        error_log("Admin reset failed: " . $e->getMessage());
    }
    
    header('Location: reset_student_test.php');
    exit();
}

// Get all students and courses for the form
$students = $conn->query("
    SELECT userID, firstName, lastName, email 
    FROM users 
    WHERE role = 'student' 
    ORDER BY lastName, firstName
")->fetchAll();

$courses = $conn->query("
    SELECT courseID, title 
    FROM courses 
    ORDER BY title
")->fetchAll();

// Get active enrollments with progress info
$enrollments = $conn->query("
    SELECT 
        e.*,
        u.firstName,
        u.lastName,
        c.title as courseTitle,
        (SELECT COUNT(*) FROM certificates WHERE userID = e.userID AND courseID = e.courseID) as hasCertificate,
        (SELECT COUNT(*) FROM vouchers v 
         JOIN certificates cert ON v.certificateID = cert.certificateID 
         WHERE cert.userID = e.userID AND cert.courseID = e.courseID) as voucherCount
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    JOIN courses c ON e.courseID = c.courseID
    WHERE u.role = 'student'
    ORDER BY e.enrolledAt DESC
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Student Progress - Admin</title>
    <link rel="icon" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; }
        .main-content { padding: 30px; }
        .reset-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .enrollment-table { margin-top: 30px; }
        .badge-cert { background: #28a745; }
        .badge-voucher { background: #667eea; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-0">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="bi bi-arrow-clockwise"></i> Reset Student Progress</h2>
                            <p class="text-muted">Testing tool for voucher generation - quickly reset course completion</p>
                        </div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="reset-card">
                        <div class="warning-box">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Warning:</strong> This will permanently delete:
                            <ul class="mb-0 mt-2">
                                <li>Certificate for the selected course</li>
                                <li>All SoleSource vouchers linked to the certificate</li>
                                <li>All lesson completions</li>
                                <li>All quiz attempts and results</li>
                                <li>Reset enrollment progress to 0%</li>
                            </ul>
                            <small class="text-muted mt-2 d-block">Use this ONLY for testing. The student will keep their enrollment and can re-complete the course.</small>
                        </div>

                        <form method="POST" id="resetForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Student *</label>
                                    <select name="student_id" class="form-select" required>
                                        <option value="">-- Select Student --</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['userID']; ?>">
                                                <?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?>
                                                (<?php echo htmlspecialchars($student['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Course *</label>
                                    <select name="course_id" class="form-select" required>
                                        <option value="">-- Select Course --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['courseID']; ?>">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('⚠️ This will DELETE all progress, certificates, vouchers, and quiz results for this student/course combination.\n\nAre you sure you want to continue?')">
                                    <i class="bi bi-trash"></i> Reset Progress
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear Form
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Recent Enrollments Table -->
                    <div class="enrollment-table">
                        <h4 class="mb-3">Recent Enrollments (Last 20)</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Certificate</th>
                                        <th>Vouchers</th>
                                        <th>Enrolled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($enrollments)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No enrollments found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($enrollments as $enroll): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($enroll['firstName'] . ' ' . $enroll['lastName']); ?></td>
                                                <td><?php echo htmlspecialchars($enroll['courseTitle']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo $enroll['progressPercentage']; ?>%"
                                                             aria-valuenow="<?php echo $enroll['progressPercentage']; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo round($enroll['progressPercentage']); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $enroll['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                                        <?php echo ucfirst($enroll['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($enroll['hasCertificate']): ?>
                                                        <span class="badge badge-cert"><i class="bi bi-check-circle"></i> Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($enroll['voucherCount'] > 0): ?>
                                                        <span class="badge badge-voucher"><?php echo $enroll['voucherCount']; ?> voucher(s)</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">None</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($enroll['enrolledAt'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
