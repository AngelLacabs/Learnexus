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
          LEFT JOIN quizresults qr ON e.enrollmentID = qr.enrollmentID AND qr.status = 'passed'
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
try {
    $stmt = $conn->prepare("SELECT * FROM certificate_downloads WHERE certificateID = ? ORDER BY downloadedAt DESC LIMIT 10");
    $stmt->execute([$certificateID]);
    $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Missing certificate_downloads table or query error: " . $e->getMessage());
    $downloads = [];
}
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
        
        .main-content {
            padding: 20px;
        }
        
        .certificate-preview {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            border-radius: 16px 16px 0 0 !important;
            padding: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .info-card h6 {
            color: #6c757d;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .uuid-display {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            word-break: break-all;
            font-size: 14px;
            border: 1px solid #dee2e6;
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
            background: linear-gradient(to bottom, #667eea, #764ba2);
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
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: 0;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a409c 100%);
            color: white;
        }
        
        /* Color layout for boxes */
        .box-primary {
            border-left: 4px solid #667eea;
        }
        
        .box-success {
            border-left: 4px solid #11998e;
        }
        
        .box-info {
            border-left: 4px solid #4facfe;
        }
        
        .box-warning {
            border-left: 4px solid #f093fb;
        }
        
        .header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
        .stat-card {
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            color: white;
        }
        
        .stat-card-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        /* Back button styling to match other pages */
        #backButton.btn-outline-secondary {
            border-color: #fbb6ce !important;
            color: #6c757d !important;
            transition: all 0.2s ease !important;
        }

        #backButton.btn-outline-secondary:active {
            background-color: #fbb6ce !important;
            color: #fff !important;
            border-color: #fbb6ce !important;
        }

        #backButton.btn-outline-secondary:hover {
            background-color: rgba(251, 182, 206, 0.1) !important;
        }

        /* Breadcrumb styling */
        .breadcrumb {
            background-color: transparent !important;
            padding: 0 !important;
            margin-bottom: 0 !important;
        }

        .breadcrumb-item a {
            text-decoration: none;
            color: #6c757d;
            transition: color 0.2s ease;
        }

        .breadcrumb-item a:hover {
            color: #0d6efd;
        }

        .breadcrumb-item a.fw-bold.text-primary {
            font-weight: 600 !important;
            color: #0d6efd !important;
        }

        .breadcrumb-item a.fw-bold.text-primary:hover {
            color: #0a58ca !important;
            text-decoration: underline;
        }

        .breadcrumb-item.active.text-dark {
            color: #212529 !important;
            font-weight: 500;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            color: #adb5bd;
            content: "›";
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <!-- Include header -->
    <?php
    $header_path = file_exists('includes/header.php') ? 'includes/header.php' : 'header.php';
    $sidebar_path = file_exists('includes/sidebar.php') ? 'includes/sidebar.php' : 'sidebar.php';
    include $header_path;
    include $sidebar_path;
    ?>

    <div class="main-content pb-3 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-3">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="bg-white rounded-3 shadow-sm p-3 w-100">
                    <div class="d-flex align-items-center">
                        <a href="certificates.php" class="btn btn-outline-secondary me-3" id="backButton">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <div class="flex-grow-1">
                            <h1 class="h3 mb-0">Certificate Details</h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php" class="fw-bold text-primary">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="certificates.php" class="fw-bold text-primary">Certificates</a></li>
                                    <li class="breadcrumb-item active text-dark" aria-current="page">Certificate #<?= $certificateID ?></li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column: Certificate Preview & Details -->
                <div class="col-lg-8">
                    <!-- Certificate Preview -->
                    <div class="card border-0 rounded-4 shadow-sm mb-5">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-award me-2"></i> Certificate Preview</h5>
                        </div>
                        <div class="card-body p-0">
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
                        </div>
                    </div>

                    <!-- Student & Course Information -->
                    <div class="row g-4">
                        <!-- Student Information -->
                        <div class="col-lg-6">
                            <div class="card border-0 rounded-4 shadow-sm h-100 box-primary">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i> Student Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-card">
                                        <h6>Full Name</h6>
                                        <p class="fw-bold"><?= htmlspecialchars($certificate['studentName']) ?></p>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6>Email Address</h6>
                                        <p><?= htmlspecialchars($certificate['student_email']) ?></p>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6>Phone Number</h6>
                                        <p><?= htmlspecialchars($certificate['student_phone'] ?? 'N/A') ?></p>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6>Member Since</h6>
                                        <p><?= $studentJoined ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Course Information -->
                        <div class="col-lg-6">
                            <div class="card border-0 rounded-4 shadow-sm h-100 box-success">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0"><i class="bi bi-book me-2"></i> Course Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-card">
                                        <h6>Course Title</h6>
                                        <p class="fw-bold"><?= htmlspecialchars($certificate['courseTitle']) ?></p>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6>Instructor</h6>
                                        <p><?= htmlspecialchars($certificate['instructorName']) ?></p>
                                        <small class="text-muted"><?= htmlspecialchars($certificate['instructor_email']) ?></small>
                                    </div>
                                    
                                    <div class="info-card">
                                        <h6>Course Description</h6>
                                        <p><?= htmlspecialchars($certificate['course_description'] ?? 'No description available') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Metadata & Actions -->
                <div class="col-lg-4">
                    <!-- Certificate Metadata -->
                    <div class="card border-0 rounded-4 shadow-sm box-info">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Certificate Metadata</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-card">
                                <h6>CERTIFICATE ID</h6>
                                <p class="fw-bold">#<?= $certificateID ?></p>
                            </div>
                            
                            <div class="info-card">
                                <h6>UNIQUE IDENTIFIER (UUID)</h6>
                                <div class="uuid-display mt-1">
                                    <?= strtoupper($certificate['certificateUUID']) ?>
                                </div>
                                <button onclick="copyToClipboard('<?= $certificate['certificateUUID'] ?>')" 
                                        class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="bi bi-clipboard me-1"></i> Copy UUID
                                </button>
                            </div>
                            
                            <div class="info-card">
                                <h6>ISSUED DATE & TIME</h6>
                                <p class="fw-bold"><?= $issuedDate ?></p>
                                <small class="text-muted"><?= $issuedTime ?></small>
                            </div>
                            
                            <div class="info-card">
                                <h6>ENROLLMENT DATE</h6>
                                <p><?= $enrolledDate ?></p>
                            </div>
                            
                            <div class="info-card">
                                <h6>COURSE COMPLETED</h6>
                                <p><?= $completedDate ?></p>
                            </div>
                            
                            <?php if ($certificate['quiz_score']): ?>
                            <div class="info-card">
                                <h6>QUIZ SCORE</h6>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2" style="padding: 8px 12px; font-size: 0.9em;"><?= $certificate['quiz_score'] ?>%</span>
                                    <small class="text-muted">Taken: <?= $quizTaken ?></small>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card border-0 rounded-4 shadow-sm box-warning">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="mailto:<?= htmlspecialchars($certificate['student_email']) ?>?subject=Your Certificate of Completion&body=Dear <?= urlencode($certificate['studentName']) ?>,%0D%0A%0D%0AYour certificate for '<?= urlencode($certificate['courseTitle']) ?>' is ready.%0D%0A%0D%0AYou can view it here: [LINK]%0D%0A%0D%0ABest regards,%0D%0AThe LearnNexus Team" 
                                   class="btn btn-gradient">
                                    <i class="bi bi-envelope me-2"></i> Email Student
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="card border-0 rounded-4 shadow-sm box-primary">
                        <div class="card-header bg-white">
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
    </div>

    <!-- Include footer -->
    <?php
    $footer_path = file_exists('includes/footer.php') ? 'includes/footer.php' : 'footer.php';
    include $footer_path;
    ?>

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
    </script>
</body>
</html>