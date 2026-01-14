<?php
session_start();
header('Content-Type: application/json');
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['action']) || $_POST['action'] !== 'create_course') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$teacherID   = $_SESSION['user_id'];
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$category    = trim($_POST['category'] ?? 'Other');
$price       = floatval($_POST['price'] ?? 0);

if ($title === '') {
    echo json_encode(['success' => false, 'message' => 'Course title is required']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1️⃣ Create course (DRAFT)
    $stmt = $conn->prepare("
        INSERT INTO courses (title, description, category, price, teacherID, status, createdAt)
        VALUES (?, ?, ?, ?, ?, 'draft', NOW())
    ");
    $stmt->execute([$title, $description, $category, $price, $teacherID]);

    $courseID = $conn->lastInsertId();

    // 2️⃣ Optional: upload first lesson (PDF only)
    if (false) { // lesson upload disabled - use Manage Course

        $ext = strtolower(pathinfo($_FILES['lesson_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('Only PDF files are allowed');
        }

        $uploadDir = '../uploads/lessons/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $fileName = uniqid('lesson_') . '.pdf';
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['lesson_file']['tmp_name'], $filePath)) {
            throw new Exception('Failed to upload lesson file');
        }

        $stmt = $conn->prepare("
            INSERT INTO lessons (courseID, title, filename)
            VALUES (?, ?, ?)
        ");
        // Store with consistent relative path: uploads/lessons/filename
        $stmt->execute([$courseID, 'Introduction', 'uploads/lessons/' . $fileName]);
    }

    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
