<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: certificates.php');
    exit();
}

$certificateUUID = $_GET['id'];
$userID = $_SESSION['user_id'];

// Get certificate data
$stmt = $conn->prepare("
    SELECT 
        cert.*,
        c.title as courseTitle,
        CONCAT(u.firstName, ' ', u.lastName) as instructorName,
        CONCAT(student.firstName, ' ', student.middleInitial, '. ', student.lastName) as studentName
    FROM certificates cert
    JOIN courses c ON cert.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    JOIN users student ON cert.userID = student.userID
    WHERE cert.certificateUUID = ? AND cert.userID = ?
");
$stmt->execute([$certificateUUID, $userID]);
$certificate = $stmt->fetch();

if (!$certificate) {
    header('Location: certificates.php');
    exit();
}

$issueDate = date('F d, Y', strtotime($certificate['issuedAt']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloading Certificate...</title>
    <link rel="icon" href="../images/Learnexus.png">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Georgia', serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .loading-screen {
            text-align: center;
            color: white;
            padding: 40px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .certificate-preview {
            display: none;
        }

        .certificate {
            width: 1056px;
            height: 816px;
            background: white;
            padding: 60px;
            position: relative;
            box-sizing: border-box;
        }

        .border-outer {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 8px solid #667eea;
            border-radius: 15px;
        }

        .border-inner {
            position: absolute;
            top: 30px;
            left: 30px;
            right: 30px;
            bottom: 30px;
            border: 2px solid #764ba2;
            border-radius: 10px;
        }

        .content {
            position: relative;
            z-index: 10;
            text-align: center;
            padding-top: 40px;
        }

        .logo {
            font-size: 42px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            letter-spacing: 3px;
        }

        .title {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 4px;
        }

        .subtitle {
            font-size: 18px;
            color: #666;
            font-style: italic;
            margin-bottom: 30px;
        }

        .divider {
            width: 200px;
            height: 2px;
            background: linear-gradient(to right, transparent, #667eea, transparent);
            margin: 20px auto;
        }

        .student-name {
            font-size: 52px;
            font-weight: bold;
            color: #667eea;
            margin: 30px 0;
            font-family: 'Brush Script MT', cursive;
        }

        .completion-text {
            font-size: 18px;
            color: #666;
            margin: 20px 0;
            line-height: 1.6;
        }

        .course-title {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin: 30px 0;
            line-height: 1.3;
        }

        .footer {
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
            padding: 0 100px;
        }

        .signature-block {
            text-align: center;
        }

        .signature-line {
            width: 250px;
            border-top: 2px solid #333;
            margin: 0 auto 10px;
            padding-top: 10px;
            font-weight: bold;
            color: #333;
            font-size: 18px;
        }

        .signature-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .certificate-id {
            position: absolute;
            bottom: 50px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 12px;
            color: #999;
            letter-spacing: 1px;
        }

        .seal {
            position: absolute;
            bottom: 60px;
            right: 60px;
            width: 120px;
            height: 120px;
            border: 6px solid #764ba2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(118, 75, 162, 0.1);
            font-size: 14px;
            color: #764ba2;
            font-weight: bold;
            text-align: center;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    <div class="loading-screen" id="loadingScreen">
        <div class="spinner"></div>
        <h2>Preparing your certificate...</h2>
        <p>Download will start automatically</p>
    </div>

    <div class="certificate-preview" id="certificatePreview">
        <div class="certificate" id="certificate">
            <div class="border-outer"></div>
            <div class="border-inner"></div>
            
            <div class="content">
                <div class="logo">LEARNEXUS</div>
                <div class="title">Certificate of Completion</div>
                <div class="subtitle">This is to certify that</div>
                
                <div class="divider"></div>
                
                <div class="student-name"><?= htmlspecialchars($certificate['studentName']) ?></div>
                
                <div class="completion-text">
                    has successfully completed the course
                </div>
                
                <div class="course-title"><?= htmlspecialchars($certificate['courseTitle']) ?></div>
                
                <div class="completion-text">
                    with a passing score, demonstrating proficiency and dedication<br>
                    in the subject matter.
                </div>
                
                <div class="footer">
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
                    <div>VERIFIED<br>LEARNEXUS</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure everything is loaded
            setTimeout(function() {
                const element = document.getElementById('certificate');
                const preview = document.getElementById('certificatePreview');
                
                // Show the certificate (but keep it hidden from view)
                preview.style.display = 'block';
                preview.style.position = 'absolute';
                preview.style.left = '-9999px';
                
                // Use html2canvas and jsPDF directly
                html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true,
                    backgroundColor: '#ffffff'
                }).then(function(canvas) {
                    const imgData = canvas.toDataURL('image/jpeg', 0.98);
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF({
                        orientation: 'landscape',
                        unit: 'in',
                        format: 'letter'
                    });
                    
                    const imgWidth = 11;
                    const imgHeight = (canvas.height * imgWidth) / canvas.width;
                    
                    pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);
                    pdf.save('Certificate_<?= str_replace([' ', "'", '"'], ['_', '', ''], $certificate['courseTitle']) ?>_<?= str_replace([' ', "'", '"'], ['_', '', ''], $certificate['studentName']) ?>.pdf');
                    
                    // After download starts, redirect back to certificates page
                    setTimeout(function() {
                        window.location.href = 'certificates.php';
                    }, 1500);
                }).catch(function(error) {
                    console.error('Error generating PDF:', error);
                    alert('Error generating certificate. Please try again.');
                    window.location.href = 'certificates.php';
                });
            }, 800);
        });
    </script>
</body>
</html>