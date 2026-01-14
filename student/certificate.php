<?php
session_start();
require_once '../database/db_connect.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID  = $_SESSION['user_id'];
$courseID = $_GET['course'] ?? 0;

/* =====================
   GET USER INFO
===================== */
$stmt = $conn->prepare("SELECT firstName, lastName, middleInitial FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$studentName = $user['firstName'] . ' ' . ($user['middleInitial'] ? $user['middleInitial'] . '. ' : '') . $user['lastName'];

/* =====================
   GET COURSE INFO
===================== */
$stmt = $conn->prepare("
    SELECT c.*, 
           CONCAT(u.firstName, ' ', u.lastName) as instructorName,
           e.enrollmentID,
           e.completedAt
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    LEFT JOIN enrollments e ON c.courseID = e.courseID AND e.userID = ?
    WHERE c.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found.");
}

/* =====================
   LESSON COMPLETION CHECK
===================== */
$stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM lesson_completions 
    WHERE userID = ?
      AND lessonID IN (
        SELECT lessonID FROM lessons WHERE courseID = ?
      )
");
$stmt->execute([$userID, $courseID]);
$completedLessons = (int)$stmt->fetchColumn();

$allLessonsCompleted = $totalLessons > 0 && $completedLessons === $totalLessons;

/* =====================
   QUIZ CHECK (single quiz per course)
===================== */
// fetch a single quiz for this course (one quiz per course policy)
$stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ? LIMIT 1");
$stmt->execute([$courseID]);
$quizID = $stmt->fetchColumn();


$quizPassed = true; // assume true if there are no quizzes
$quizScore = 0;

if ($quizID) {
    $stmt = $conn->prepare("
        SELECT status, percentage 
        FROM quiz_results 
        WHERE userID = ? AND quizID = ?
        ORDER BY takenAt DESC
        LIMIT 1
    ");
    $stmt->execute([$userID, $quizID]);
    $quizResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quizResult) {
        $quizPassed = $quizResult['status'] === 'passed';
        $quizScore = (float)$quizResult['percentage'];
    } else {
        // user hasn't taken the quiz yet
        $quizPassed = false;
        $quizScore = 0;
    }
}



/* =====================
   HARD BLOCK (SECURITY)
===================== */
if (!$allLessonsCompleted || !$quizPassed) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Certificate Locked</title>
        <link rel="icon" href="../images/Learnexus.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .lock-card { background: white; padding: 60px 40px; border-radius: 20px; text-align: center; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
            .lock-icon { font-size: 80px; color: #ff9800; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="lock-card">
            <i class="bi bi-lock-fill lock-icon"></i>
            <h3>Certificate Locked</h3>
            <p class="text-muted mb-4">
                <?php if (!$allLessonsCompleted): ?>
                    You must complete all <?= $totalLessons ?> lessons first.<br>
                    Progress: <?= $completedLessons ?>/<?= $totalLessons ?> completed
                <?php else: ?>
                    You must pass the quiz to unlock your certificate.<br>
                    Required: 70% | Your score: <?= round($quizScore, 1) ?>%
                <?php endif; ?>
            </p>
            <a href="course_learn.php?id=<?= $courseID ?>" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Go Back to Course
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/* =====================
   CHECK/CREATE CERTIFICATE
===================== */
$stmt = $conn->prepare("SELECT * FROM certificates WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

// Create certificate if it doesn't exist
if (!$certificate) {
    $certificateUUID = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $stmt = $conn->prepare("
        INSERT INTO certificates (enrollmentID, courseID, userID, certificateUUID, instructorName, studentName, courseTitle, issuedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $course['enrollmentID'],
        $courseID,
        $userID,
        $certificateUUID,
        $course['instructorName'],
        $studentName,
        $course['title']
    ]);
    
    // Fetch the newly created certificate
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE userID = ? AND courseID = ?");
    $stmt->execute([$userID, $courseID]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================
// SOLESOURCE INTEGRATION: Generate voucher for completed course
// Check if voucher already exists for this certificate
// =====================
error_log("=== SOLESOURCE DEBUG: Starting voucher generation for certificate " . $certificate['certificateID']);

try {
    require_once '../helpers/solesource_api.php';
    error_log("SOLESOURCE DEBUG: Helper file loaded successfully");
    
    // Check if voucher already exists for this certificate
    $stmt = $conn->prepare("SELECT voucherID FROM vouchers WHERE certificateID = ? LIMIT 1");
    $stmt->execute([$certificate['certificateID']]);
    $existingVoucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("SOLESOURCE DEBUG: Existing voucher check - " . ($existingVoucher ? "Found ID: " . $existingVoucher['voucherID'] : "None found"));
    
    if (!$existingVoucher) {
        error_log("SOLESOURCE DEBUG: Calling solesource_generate_voucher...");
        
        // Generate new voucher
        $voucherResponse = solesource_generate_voucher(
            $userID, 
            $certificate['certificateID'],
            [
                'discount-type' => 'percent',
                'discount-value' => 12  // 12% discount for course completion
            ]
        );
        
        error_log("SOLESOURCE DEBUG: API Response - " . json_encode($voucherResponse));
        
        if ($voucherResponse['ok'] ?? false) {
            $_SESSION['new_voucher_code'] = $voucherResponse['code'];
            $_SESSION['show_voucher_toast'] = true;
            error_log("SoleSource: ✅ Voucher generated for user $userID - Code: " . $voucherResponse['code']);
        } else {
            error_log("SoleSource: ❌ Failed to generate voucher for user $userID - " . json_encode($voucherResponse));
        }
    } else {
        error_log("SoleSource: Voucher already exists for certificate " . $certificate['certificateID']);
    }
} catch (Exception $e) {
    error_log("SoleSource: ❌ EXCEPTION - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("SoleSource: Stack trace - " . $e->getTraceAsString());
} catch (Error $e) {
    error_log("SoleSource: ❌ FATAL ERROR - " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
}

/* =====================
   TRACK CERTIFICATE VIEW
===================== */
function trackCertificateView($conn, $certificateID, $userID) {
    try {
        // Update view count in certificates table
        $stmt = $conn->prepare("
            UPDATE certificates 
            SET downloadCount = COALESCE(downloadCount, 0) + 1 
            WHERE certificateID = ?
        ");
        $stmt->execute([$certificateID]);
        
        // Log the download/view (only if table exists)
        $stmt = $conn->prepare("
            INSERT INTO certificate_downloads (certificateID, userID, ipAddress, userAgent, downloadedAt)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $certificateID,
            $userID,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail - don't break certificate display
        error_log("Certificate tracking error: " . $e->getMessage());
    }
}

// Track this certificate view
if (isset($certificate['certificateID'])) {
    trackCertificateView($conn, $certificate['certificateID'], $userID);
}

$issueDate = date('F d, Y', strtotime($certificate['issuedAt']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate - <?= htmlspecialchars($course['title']) ?></title>
<link rel="icon" href="../images/Learnexus.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="icon" type="image/png" href="../images/Learnexus.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 40px 20px;
    font-family: 'Georgia', serif;
}

.certificate-container {
    max-width: 900px;
    margin: 0 auto;
}

.certificate {
    background: white;
    padding: 60px;
    border: 15px solid #667eea;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.certificate::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    bottom: 20px;
    border: 3px solid #764ba2;
    border-radius: 10px;
    pointer-events: none;
}

.certificate-header {
    margin-bottom: 30px;
}

.certificate-logo {
    font-size: 48px;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 10px;
}

.certificate-title {
    font-size: 42px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 3px;
}

.certificate-subtitle {
    font-size: 18px;
    color: #666;
    font-style: italic;
}

.certificate-body {
    margin: 40px 0;
    padding: 30px 0;
}

.recipient-text {
    font-size: 20px;
    color: #666;
    margin-bottom: 15px;
}

.recipient-name {
    font-size: 48px;
    font-weight: bold;
    color: #667eea;
    margin: 20px 0;
    font-family: 'Brush Script MT', cursive;
}

.completion-text {
    font-size: 18px;
    color: #666;
    margin: 20px 0;
    line-height: 1.8;
}

.course-name {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin: 25px 0;
}

.certificate-footer {
    margin-top: 50px;
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
}

.signature-block {
    text-align: center;
    min-width: 200px;
}

.signature-line {
    border-top: 2px solid #333;
    margin-bottom: 8px;
    padding-top: 5px;
    font-weight: bold;
    color: #333;
}

.signature-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.certificate-id {
    margin-top: 30px;
    font-size: 12px;
    color: #999;
    letter-spacing: 1px;
}

.action-buttons {
    margin-top: 30px;
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-download {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-back {
    background: white;
    color: #667eea;
    padding: 12px 30px;
    border: 2px solid #667eea;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.btn-back:hover {
    background: #667eea;
    color: white;
}

.seal {
    position: absolute;
    bottom: 60px;
    right: 60px;
    width: 100px;
    height: 100px;
    border: 5px solid #764ba2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(118, 75, 162, 0.1);
    font-size: 12px;
    color: #764ba2;
    font-weight: bold;
    text-align: center;
    line-height: 1.2;
}

@media print {
    body {
        background: white;
        padding: 0;
    }
    .action-buttons {
        display: none;
    }
    .certificate {
        border: 10px solid #667eea;
        box-shadow: none;
    }
}

@media (max-width: 768px) {
    .certificate {
        padding: 30px 20px;
    }
    .certificate-title {
        font-size: 28px;
    }
    .recipient-name {
        font-size: 32px;
    }
    .course-name {
        font-size: 24px;
    }
    .seal {
        width: 80px;
        height: 80px;
        bottom: 30px;
        right: 30px;
        font-size: 10px;
    }
}
</style>
</head>
<body>

<!-- Voucher Toast Notification -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($_SESSION['show_voucher_toast'] ?? false): ?>
    Swal.fire({
        icon: 'success',
        title: 'Congratulations! 🎉',
        html: `
            <p style="margin: 10px 0; font-size: 16px;">You've completed this course!</p>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; margin: 15px 0; color: white;">
                <p style="margin-bottom: 10px; font-size: 11px; color: rgba(255,255,255,0.8); text-transform: uppercase; letter-spacing: 1px;">Your SoleSource Voucher Code</p>
                <p style="font-size: 24px; font-weight: bold; font-family: monospace; letter-spacing: 3px; margin: 0;">
                    <?php echo htmlspecialchars($_SESSION['new_voucher_code'] ?? ''); ?>
                </p>
            </div>
            <p style="font-size: 14px; color: #666; margin-bottom: 0;">Get 12% off your next purchase at <strong>SoleSource</strong>!<br><small style="color: #999;">View your vouchers page for more details.</small></p>
        `,
        confirmButtonText: 'View My Vouchers',
        confirmButtonColor: '#667eea',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'vouchers.php';
        }
    });
    <?php unset($_SESSION['show_voucher_toast']); ?>
    <?php endif; ?>
});
</script>

<div class="certificate-container">
    <div class="certificate" id="certificate">
        <div class="certificate-header">
            <div class="certificate-logo">LEARNEXUS</div>
            <div class="certificate-title">Certificate of Completion</div>
            <div class="certificate-subtitle">This is to certify that</div>
        </div>
        
        <div class="certificate-body">
            <div class="recipient-name"><?= htmlspecialchars($studentName) ?></div>
            
            <div class="completion-text">
                has successfully completed the course
            </div>
            
            <div class="course-name"><?= htmlspecialchars($course['title']) ?></div>
            
            <div class="completion-text">
                with a passing score, demonstrating proficiency and dedication<br>
                in the subject matter.
            </div>
        </div>
        
        <div class="certificate-footer">
            <div class="signature-block">
                <div class="signature-line"><?= htmlspecialchars($course['instructorName']) ?></div>
                <div class="signature-label">Instructor</div>
            </div>
            
            <div class="signature-block">
                <div class="signature-line"><?= $issueDate ?></div>
                <div class="signature-label">Date Issued</div>
            </div>
        </div>
        
        <div class="certificate-id">
            Certificate ID: <?= strtoupper($certificate['certificateUUID']) ?>
        </div>
        
        <div class="seal">
            <div>
                VERIFIED<br>
                LEARNEXUS
            </div>
        </div>
    </div>
    
    <div class="action-buttons">
        <button onclick="window.print()" class="btn-download">
            <i class="bi bi-printer"></i> Print Certificate
        </button>
        <button onclick="downloadCertificate()" class="btn-download">
            <i class="bi bi-download"></i> Download PDF
        </button>
        <a href="course_learn.php?id=<?= $courseID ?>" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Course
        </a>
        <a href="dashboard.php" class="btn-back">
            <i class="bi bi-house"></i> Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function downloadCertificate() {
    const element = document.getElementById('certificate');
    const opt = {
        margin: 0.5,
        filename: 'Certificate_<?= str_replace(' ', '_', $course['title']) ?>_<?= str_replace(' ', '_', $studentName) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, logging: false },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>