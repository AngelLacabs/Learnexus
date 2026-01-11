<?php
session_start();
header('Content-Type: application/json');
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$teacherID = $_SESSION['user_id'];

// Check if the action is correct
if ($_POST['action'] !== 'create_course') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Validate required fields
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$category = trim($_POST['category'] ?? 'Other');
$price = floatval($_POST['price'] ?? 0);

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Course title is required']);
    exit;
}

// Start transaction
$conn->beginTransaction();

try {
    // Insert course first
    $stmt = $conn->prepare("INSERT INTO courses (title, description, category, price, teacherID, status, createdAt) VALUES (?, ?, ?, ?, ?, 'draft', NOW())");
    $stmt->execute([$title, $description, $category, $price, $teacherID]);

    $courseID = $conn->lastInsertId();

    // Handle lesson file upload if exists
    if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['lesson_file']['tmp_name'];
        $fileName = $_FILES['lesson_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Only PDF files are allowed');
        }

        $uploadDir = '../uploads/lessons/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $newFileName = uniqid('lesson_') . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception('Failed to upload file');
        }

        // Insert lesson record
        $stmt = $conn->prepare("INSERT INTO lessons (courseID, title, filename, uploadedAt) VALUES (?, ?, ?, NOW())");
        $lessonTitle = pathinfo($fileName, PATHINFO_FILENAME); // Use file name as lesson title
        $stmt->execute([$courseID, $lessonTitle, $destPath]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'courseID' => $courseID]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
