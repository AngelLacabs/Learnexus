<?php
session_start();
require_once '../database/db_connect.php';

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invalid certificate ID');
}

$uuid   = $_GET['id'];
$userID = $_SESSION['user_id'];

/* =====================
   FETCH CERTIFICATE DATA
===================== */
$stmt = $conn->prepare("
    SELECT 
        cert.*,
        c.title AS courseTitle,
        c.courseID,
        CONCAT(u.firstName, ' ', IFNULL(CONCAT(u.middleInitial, '. '), ''), u.lastName) AS studentName,
        CONCAT(t.firstName, ' ', t.lastName) AS instructorName,
        e.completedAt
    FROM certificates cert
    JOIN enrollments e ON cert.enrollmentID = e.enrollmentID
    JOIN courses c ON cert.courseID = c.courseID
    JOIN users u ON cert.userID = u.userID
    JOIN users t ON c.teacherID = t.userID
    WHERE cert.certificateUUID = ?
      AND cert.userID = ?
");
$stmt->execute([$uuid, $userID]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Certificate Not Found</title>
        <link rel="icon" href="../images/Learnexus.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .error-card { background: white; padding: 60px 40px; border-radius: 20px; text-align: center; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
            .error-icon { font-size: 80px; color: #f44336; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <i class="bi bi-exclamation-circle error-icon"></i>
            <h3>Certificate Not Found</h3>
            <p class="text-muted mb-4">The certificate you're looking for doesn't exist or you don't have access to it.</p>
            <a href="certificates.php" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Back to Certificates
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/* =====================
   TRACK CERTIFICATE VIEW
===================== */
try {
    // Update view count
    $stmt = $conn->prepare("
        UPDATE certificates 
        SET downloadCount = COALESCE(downloadCount, 0) + 1 
        WHERE certificateID = ?
    ");
    $stmt->execute([$certificate['certificateID']]);
    
    // Log the view
    $stmt = $conn->prepare("
        INSERT INTO certificate_downloads (certificateID, userID, ipAddress, userAgent, downloadedAt)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $certificate['certificateID'],
        $userID,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
} catch (Exception $e) {
    error_log("Certificate tracking error: " . $e->getMessage());
}

$issueDate = date('F d, Y', strtotime($certificate['issuedAt']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?= htmlspecialchars($certificate['courseTitle']) ?></title>
    <link rel="icon" href="../images/Learnexus.png">
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
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-download, .btn-back {
                width: 100%;
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
            <div class="recipient-name"><?= htmlspecialchars($certificate['studentName']) ?></div>
            
            <div class="completion-text">
                has successfully completed the course
            </div>
            
            <div class="course-name"><?= htmlspecialchars($certificate['courseTitle']) ?></div>
            
            <div class="completion-text">
                with a passing score, demonstrating proficiency and dedication<br>
                in the subject matter.
            </div>
        </div>
        
        <div class="certificate-footer">
            <div class="signature-block">
                <div class="signature-line"><?= htmlspecialchars($certificate['instructorName']) ?></div>
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
        <a href="certificates.php" class="btn-back">
            <i class="bi bi-award"></i> My Certificates
        </a>
        <a href="course_learn.php?id=<?= $certificate['courseID'] ?>" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Course
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
function downloadCertificate() {
    const element = document.getElementById('certificate');
    const courseName = '<?= addslashes($certificate['courseTitle']) ?>';
    const studentName = '<?= addslashes($certificate['studentName']) ?>';
    
    const opt = {
        margin: 0.5,
        filename: `Certificate_${courseName.replace(/[^a-z0-9]/gi, '_')}_${studentName.replace(/[^a-z0-9]/gi, '_')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, logging: false },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>