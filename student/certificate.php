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
   QUIZ CHECK
===================== */
$stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quizID = $stmt->fetchColumn();

$quizPassed = false;
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
        $quizScore = $quizResult['percentage'];
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

$issueDate = date('F d, Y', strtotime($certificate['issuedAt']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate - <?= htmlspecialchars($course['title']) ?></title>
<link rel="icon" type="image/png" href="../images/Learnexus.png">
<!-- Save this file as: student/certificate.php -->
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
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
}
</style>
</head>
<body>

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
        <a href="dashboard.php" class="btn-back">
            <i class="bi bi-house"></i> Back to Dashboard
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