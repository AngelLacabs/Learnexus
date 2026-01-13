<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invalid certificate ID');
}

$uuid   = $_GET['id'];
$userID = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        cert.*,
        c.title AS courseTitle,
        CONCAT(u.firstName, ' ', u.middleInitial, ' ', u.lastName) AS studentName,
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
$certificate = $stmt->fetch();

if (!$certificate) {
    die('Certificate not found or access denied');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?= htmlspecialchars($certificate['courseTitle']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Lato:wght@300;400;700&display=swap');
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Lato', sans-serif;
        }
        
        .certificate-container {
            background: white;
            max-width: 900px;
            width: 100%;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 20px solid #f8f9fa;
            position: relative;
        }
        
        .certificate-border {
            border: 3px solid #1a73e8;
            padding: 40px;
            position: relative;
        }
        
        .corner-decoration {
            position: absolute;
            width: 60px;
            height: 60px;
            border: 3px solid #1a73e8;
        }
        
        .corner-decoration.top-left {
            top: -3px;
            left: -3px;
            border-right: none;
            border-bottom: none;
        }
        
        .corner-decoration.top-right {
            top: -3px;
            right: -3px;
            border-left: none;
            border-bottom: none;
        }
        
        .corner-decoration.bottom-left {
            bottom: -3px;
            left: -3px;
            border-right: none;
            border-top: none;
        }
        
        .corner-decoration.bottom-right {
            bottom: -3px;
            right: -3px;
            border-left: none;
            border-top: none;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .certificate-logo {
            font-size: 36px;
            font-weight: 700;
            color: #1a73e8;
            margin-bottom: 10px;
            font-family: 'Playfair Display', serif;
        }
        
        .certificate-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            font-weight: 700;
            color: #2c3e50;
            margin: 20px 0;
            letter-spacing: 2px;
        }
        
        .certificate-subtitle {
            font-size: 18px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 30px;
        }
        
        .certificate-body {
            text-align: center;
            margin: 40px 0;
        }
        
        .recipient-label {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .recipient-name {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 700;
            color: #1a73e8;
            margin: 15px 0;
            border-bottom: 2px solid #1a73e8;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .course-info {
            margin: 30px 0;
            font-size: 18px;
            color: #2c3e50;
            line-height: 1.8;
        }
        
        .course-title {
            font-weight: 700;
            color: #1a73e8;
            font-size: 24px;
            margin: 10px 0;
        }
        
        .certificate-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #ecf0f1;
        }
        
        .signature-block {
            text-align: center;
            flex: 1;
        }
        
        .signature-line {
            border-bottom: 2px solid #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 5px;
            min-width: 200px;
        }
        
        .signature-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .signature-title {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .certificate-date {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .certificate-id {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #95a5a6;
            font-family: monospace;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn-print {
            background: #1a73e8;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print:hover {
            background: #1565c0;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #5a6268;
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
            
            .certificate-container {
                box-shadow: none;
                margin: 0;
                padding: 40px;
            }
        }
        
        @media (max-width: 768px) {
            .certificate-container {
                padding: 30px 20px;
            }
            
            .certificate-border {
                padding: 20px;
            }
            
            .certificate-title {
                font-size: 32px;
            }
            
            .recipient-name {
                font-size: 28px;
            }
            
            .course-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-border">
            <div class="corner-decoration top-left"></div>
            <div class="corner-decoration top-right"></div>
            <div class="corner-decoration bottom-left"></div>
            <div class="corner-decoration bottom-right"></div>
            
            <div class="certificate-header">
                <div class="certificate-logo">LEARNEXUS</div>
                <div class="certificate-subtitle">Presents This</div>
                <div class="certificate-title">Certificate of Completion</div>
            </div>
            
            <div class="certificate-body">
                <div class="recipient-label">This is to certify that</div>
                <div class="recipient-name"><?= htmlspecialchars($certificate['studentName']) ?></div>
                
                <div class="course-info">
                    has successfully completed the course
                    <div class="course-title"><?= htmlspecialchars($certificate['courseTitle']) ?></div>
                    demonstrating commitment to continuous learning and professional development
                </div>
            </div>
            
            <div class="certificate-footer">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name"><?= htmlspecialchars($certificate['instructorName']) ?></div>
                    <div class="signature-title">Course Instructor</div>
                </div>
                
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">Learnexus</div>
                    <div class="signature-title">Learning Platform</div>
                </div>
            </div>
            
            <div class="certificate-date">
                Issued on <?= date('F d, Y', strtotime($certificate['issuedAt'])) ?>
            </div>
            
            <div class="certificate-id">
                Certificate ID: <?= htmlspecialchars($certificate['certificateUUID']) ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="window.print()" class="btn-print">Print Certificate</button>
            <a href="my_courses.php" class="btn-back">Back to Courses</a>
        </div>
    </div>
</body>
</html>