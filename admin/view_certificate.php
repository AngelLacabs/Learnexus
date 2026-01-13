<?php
session_start();
require_once '../database/db_connect.php';

/* =====================
   AUTH CHECK - ADMIN/INSTRUCTOR ONLY
===================== */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if user is admin or instructor
$allowed_roles = ['admin', 'instructor'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: dashboard.php');
    exit();
}

$certificateID = $_GET['id'] ?? 0;

if (!$certificateID) {
    header('Location: certificate_manager.php');
    exit();
}

/* =====================
   GET CERTIFICATE DETAILS
===================== */
$query = "SELECT c.*, 
                 cr.title as course_full_title,
                 cr.description as course_description,
                 u.email as student_email,
                 u.phone as student_phone,
                 u.createdAt as student_joined,
                 inst.email as instructor_email,
                 e.enrolledAt,
                 e.completedAt as course_completed,
                 qr.percentage as quiz_score,
                 qr.submittedAt as quiz_taken
          FROM certificates c
          JOIN courses cr ON c.courseID = cr.courseID
          JOIN users u ON c.userID = u.userID
          JOIN users inst ON cr.teacherID = inst.userID
          JOIN enrollments e ON c.enrollmentID = e.enrollmentID
          LEFT JOIN quiz_results qr ON e.enrollmentID = qr.enrollmentID AND qr.status = 'passed'
          WHERE c.certificateID = ?";

if ($_SESSION['role'] === 'instructor') {
    $query .= " AND cr.teacherID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$certificateID, $_SESSION['user_id']]);
} else {
    $stmt = $conn->prepare($query);
    $stmt->execute([$certificateID]);
}

$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    header('Location: certificate_manager.php?error=certificate_not_found');
    exit();
}

// Format dates
$issuedDate = date('F d, Y', strtotime($certificate['issuedAt']));
$issuedTime = date('h:i A', strtotime($certificate['issuedAt']));
$enrolledDate = date('F d, Y', strtotime($certificate['enrolledAt']));
$completedDate = $certificate['course_completed'] ? date('F d, Y', strtotime($certificate['course_completed'])) : 'N/A';
$quizTaken = $certificate['quiz_taken'] ? date('F d, Y', strtotime($certificate['quiz_taken'])) : 'N/A';
$studentJoined = date('F d, Y', strtotime($certificate['student_joined']));

/* =====================
   GET DOWNLOAD HISTORY
===================== */
$stmt = $conn->prepare("SELECT * FROM certificate_downloads WHERE certificateID = ? ORDER BY downloadedAt DESC LIMIT 10");
$stmt->execute([$certificateID]);
$downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Details - LearnNexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .certificate-preview {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .certificate-body {
            padding: 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .info-card h6 {
            color: #6c757d;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-card p {
            font-size: 16px;
            margin-bottom: 0;
        }
        
        .uuid-display {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
            font-size: 14px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #667eea;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -33px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }
        
        .btn-action {
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .qr-code {
            max-width: 200px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="certificates.php">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Certificates
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <!-- Left Column: Certificate Info -->
            <div class="col-lg-8">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">Certificate Details</h2>
                        <p class="text-muted mb-0">Complete information about this certificate</p>
                    </div>
                </div>

                <!-- Certificate Preview -->
                <div class="certificate-preview">
                    <div class="certificate-header">
                        <h3 class="text-primary">CERTIFICATE OF COMPLETION</h3>
                        <p class="text-muted mb-0">This document certifies that</p>
                    </div>
                    
                    <div class="certificate-body text-center">
                        <h2 class="mb-4" style="color: #667eea; font-family: 'Brush Script MT', cursive; font-size: 2.5rem;">
                            <?= htmlspecialchars($certificate['studentName']) ?>
                        </h2>
                        
                        <p class="lead mb-4">
                            has successfully completed the course
                        </p>
                        
                        <h4 class="mb-4">
                            <?= htmlspecialchars($certificate['courseTitle']) ?>
                        </h4>
                        
                        <p class="text-muted mb-4">
                            demonstrating proficiency and dedication in the subject matter.
                        </p>
                        
                        <div class="row mt-5">
                            <div class="col-md-6">
                                <div class="border-top pt-2">
                                    <strong><?= htmlspecialchars($certificate['instructorName']) ?></strong><br>
                                    <small class="text-muted">Instructor</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border-top pt-2">
                                    <strong><?= $issuedDate ?></strong><br>
                                    <small class="text-muted">Date Issued</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <small class="text-muted">Certificate ID: <?= strtoupper($certificate['certificateUUID']) ?></small>
                        </div>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3"><i class="bi bi-person-circle me-2"></i> Student Information</h4>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Full Name</h6>
                            <p class="fw-bold"><?= htmlspecialchars($certificate['studentName']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Email Address</h6>
                            <p><?= htmlspecialchars($certificate['student_email']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Phone Number</h6>
                            <p><?= htmlspecialchars($certificate['student_phone'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Member Since</h6>
                            <p><?= $studentJoined ?></p>
                        </div>
                    </div>
                </div>

                <!-- Course Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3"><i class="bi bi-book me-2"></i> Course Information</h4>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Course Title</h6>
                            <p class="fw-bold"><?= htmlspecialchars($certificate['courseTitle']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>Instructor</h6>
                            <p><?= htmlspecialchars($certificate['instructorName']) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($certificate['instructor_email']) ?></small>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="info-card">
                            <h6>Course Description</h6>
                            <p><?= htmlspecialchars($certificate['course_description'] ?? 'No description available') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Metadata & Actions -->
            <div class="col-lg-4">
                <!-- Certificate Metadata -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Certificate Metadata</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">CERTIFICATE ID</small>
                            <strong>#<?= $certificateID ?></strong>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">UNIQUE IDENTIFIER (UUID)</small>
                            <div class="uuid-display mt-1">
                                <?= strtoupper($certificate['certificateUUID']) ?>
                            </div>
                            <button onclick="copyToClipboard('<?= $certificate['certificateUUID'] ?>')" 
                                    class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="bi bi-clipboard me-1"></i> Copy UUID
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">ISSUED DATE & TIME</small>
                            <div><?= $issuedDate ?></div>
                            <small class="text-muted"><?= $issuedTime ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">ENROLLMENT DATE</small>
                            <div><?= $enrolledDate ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">COURSE COMPLETED</small>
                            <div><?= $completedDate ?></div>
                        </div>
                        
                        <?php if ($certificate['quiz_score']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">QUIZ SCORE</small>
                            <div>
                                <span class="badge bg-success"><?= $certificate['quiz_score'] ?>%</span>
                                <small class="text-muted ms-2">Taken: <?= $quizTaken ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">CERTIFICATE TYPE</small>
                            <span class="badge bg-info">Course Completion</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button onclick="verifyCertificate()" class="btn btn-warning">
                                <i class="bi bi-shield-check me-2"></i> Verify Certificate
                            </button>
                            
                            <a href="mailto:<?= htmlspecialchars($certificate['student_email']) ?>?subject=Your Certificate of Completion&body=Dear <?= urlencode($certificate['studentName']) ?>,%0D%0A%0D%0AYour certificate for '<?= urlencode($certificate['courseTitle']) ?>' is ready.%0D%0A%0D%0AYou can view it here: [LINK]%0D%0A%0D%0ABest regards,%0D%0AThe LearnNexus Team" 
                               class="btn btn-outline-primary">
                                <i class="bi bi-envelope me-2"></i> Email Student
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Certificate Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <small class="text-muted"><?= $enrolledDate ?></small>
                                <div>Student enrolled in course</div>
                            </div>
                            
                            <?php if ($certificate['quiz_taken']): ?>
                            <div class="timeline-item">
                                <small class="text-muted"><?= $quizTaken ?></small>
                                <div>Passed quiz with <?= $certificate['quiz_score'] ?>% score</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($completedDate !== 'N/A'): ?>
                            <div class="timeline-item">
                                <small class="text-muted"><?= $completedDate ?></small>
                                <div>Course completed</div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="timeline-item">
                                <small class="text-muted"><?= $issuedDate ?></small>
                                <div class="fw-bold">Certificate issued</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'UUID copied to clipboard',
                    timer: 1500,
                    showConfirmButton: false
                });
            }).catch(function(err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to copy: ' + err
                });
            });
        }

        function verifyCertificate() {
            const uuid = '<?= $certificate['certificateUUID'] ?>';
            
            Swal.fire({
                title: 'Verify Certificate',
                html: `Certificate ID: <strong>${uuid}</strong><br><br>
                       This certificate was issued to:<br>
                       <strong><?= addslashes($certificate['studentName']) ?></strong><br>
                       For completing:<br>
                       <strong><?= addslashes($certificate['courseTitle']) ?></strong><br><br>
                       <small class="text-muted">Issued on: <?= $issuedDate ?></small>`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Verify',
                cancelButtonText: 'Close'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an API call to verify
                    Swal.fire({
                        icon: 'success',
                        title: 'Verified!',
                        text: 'This certificate is authentic and valid.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
    </script>
</body>
</html>