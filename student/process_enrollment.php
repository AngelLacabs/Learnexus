<?php
session_start();
require_once '../database/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$courseID = $input['courseID'] ?? 0;
$userID = $input['userID'] ?? 0;
$amount = $input['amount'] ?? 0;
$transactionRef = $input['transactionRef'] ?? '';
$payerName = $input['payerName'] ?? '';
$payerEmail = $input['payerEmail'] ?? '';
$mobile = $input['mobile'] ?? '';

// Validate session user matches request
if ($userID != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'User mismatch']);
    exit();
}

// Validate required fields
if (!$courseID || !$userID || !$amount || !$transactionRef) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Check if course exists
    $stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        exit();
    }

    // Check if already enrolled
    $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
    $stmt->execute([$userID, $courseID]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
        exit();
    }

    // Start transaction
    $conn->beginTransaction();

    // STEP 1: Create enrollment first (without paymentID)
    $stmt = $conn->prepare("
        INSERT INTO enrollments (userID, courseID, paymentID, progressPercentage, status, enrolledAt)
        VALUES (?, ?, NULL, 0, 'active', NOW())
    ");
    $stmt->execute([$userID, $courseID]);
    $enrollmentID = $conn->lastInsertId();
    
    if (!$enrollmentID) {
        throw new Exception('Failed to create enrollment');
    }

    // STEP 2: Create payment record with enrollmentID
    $stmt = $conn->prepare("
        INSERT INTO payments (enrollmentID, userID, courseID, amount, transactionReference, status, paymentDate, createdAt)
        VALUES (?, ?, ?, ?, ?, 'completed', NOW(), NOW())
    ");
    $stmt->execute([$enrollmentID, $userID, $courseID, $amount, $transactionRef]);
    $paymentID = $conn->lastInsertId();
    
    if (!$paymentID) {
        throw new Exception('Failed to create payment');
    }

    // STEP 3: Update enrollment with paymentID
    $stmt = $conn->prepare("UPDATE enrollments SET paymentID = ? WHERE enrollmentID = ?");
    $stmt->execute([$paymentID, $enrollmentID]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Enrollment successful',
        'paymentID' => $paymentID,
        'enrollmentID' => $enrollmentID,
        'courseTitle' => $course['title']
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>