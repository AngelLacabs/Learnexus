<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get export parameters
$format = $_POST['format'] ?? 'csv';
$dateFrom = $_POST['export_date_from'] ?? '';
$dateTo = $_POST['export_date_to'] ?? '';
$includeDownloads = isset($_POST['include_downloads']);

try {
    // Build query based on filters
    $whereConditions = [];
    $params = [];
    
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(cert.issuedAt) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(cert.issuedAt) <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $query = "
        SELECT 
            cert.certificateID,
            cert.certificateUUID,
            cert.studentName,
            cert.courseTitle,
            cert.instructorName,
            cert.issuedAt,
            cert.downloadCount,
            u.email as studentEmail,
            u.studentNumber,
            c.price as coursePrice,
            c.category
        FROM certificates cert
        JOIN users u ON cert.userID = u.userID
        JOIN courses c ON cert.courseID = c.courseID
        $whereClause
        ORDER BY cert.issuedAt DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($certificates, $includeDownloads);
    } elseif ($format === 'json') {
        exportJSON($certificates, $includeDownloads);
    } elseif ($format === 'pdf') {
        exportPDF($certificates, $includeDownloads);
    }
    
} catch (PDOException $e) {
    error_log("Certificate Export Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error exporting certificate data';
    header('Location: certificates.php');
    exit();
}

function exportCSV($data, $includeDownloads) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=certificates_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    $headers = [
        'Certificate ID',
        'UUID',
        'Student Name',
        'Student Email',
        'Student Number',
        'Course Title',
        'Course Price',
        'Category',
        'Instructor',
        'Issue Date',
        'Download Count'
    ];
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['certificateID'],
            $row['certificateUUID'],
            $row['studentName'],
            $row['studentEmail'],
            $row['studentNumber'] ?? 'N/A',
            $row['courseTitle'],
            $row['coursePrice'],
            $row['category'],
            $row['instructorName'],
            $row['issuedAt'],
            $row['downloadCount']
        ]);
    }
    
    fclose($output);
    exit();
}

function exportJSON($data, $includeDownloads) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=certificates_' . date('Y-m-d') . '.json');
    
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

function exportPDF($data, $includeDownloads) {
    // For PDF export, you would need a PDF library like TCPDF or mPDF
    // This is a basic example structure
    
    $_SESSION['export_data'] = $data;
    $_SESSION['export_type'] = 'certificates_pdf';
    $_SESSION['export_date_from'] = $dateFrom ?? 'All Time';
    $_SESSION['export_date_to'] = $dateTo ?? 'All Time';
    
    // Redirect to a PDF generation page
    header('Location: certificate_pdf.php');
    exit();
}