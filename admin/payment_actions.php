<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$paymentID = $_GET['id'] ?? $_POST['id'] ?? 0;

if (empty($paymentID)) {
    $_SESSION['error'] = 'Payment ID is required';
    header('Location: payments.php');
    exit();
}

try {
    // Get payment details first
    $stmt = $conn->prepare("SELECT * FROM payments WHERE paymentID = ?");
    $stmt->execute([$paymentID]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $_SESSION['error'] = 'Payment not found';
        header('Location: payments.php');
        exit();
    }
    
    switch ($action) {
        case 'complete':
            // Mark payment as completed
            if ($payment['status'] !== 'completed') {
                $stmt = $conn->prepare("
                    UPDATE payments 
                    SET status = 'completed', 
                        paymentDate = COALESCE(paymentDate, NOW())
                    WHERE paymentID = ?
                ");
                $stmt->execute([$paymentID]);
                
                $_SESSION['success'] = 'Payment marked as completed successfully!';
            } else {
                $_SESSION['error'] = 'Payment is already completed';
            }
            break;
            
        case 'fail':
            // Mark payment as failed
            $stmt = $conn->prepare("
                UPDATE payments 
                SET status = 'failed'
                WHERE paymentID = ?
            ");
            $stmt->execute([$paymentID]);
            
            $_SESSION['success'] = 'Payment marked as failed!';
            break;
            
        case 'refund':
            // Mark payment as refunded
            $stmt = $conn->prepare("
                UPDATE payments 
                SET status = 'refunded'
                WHERE paymentID = ?
            ");
            $stmt->execute([$paymentID]);
            
            $_SESSION['success'] = 'Payment marked as refunded!';
            break;
            
        case 'pending':
            // Mark payment as pending
            $stmt = $conn->prepare("
                UPDATE payments 
                SET status = 'pending',
                    paymentDate = NULL
                WHERE paymentID = ?
            ");
            $stmt->execute([$paymentID]);
            
            $_SESSION['success'] = 'Payment marked as pending!';
            break;
            
        case 'delete':
            // Delete payment record
            // First, check if there's an enrollment linked to this payment
            $stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE paymentID = ?");
            $stmt->execute([$paymentID]);
            $enrollment = $stmt->fetch();
            
            if ($enrollment) {
                // Unlink enrollment from payment first
                $stmt = $conn->prepare("UPDATE enrollments SET paymentID = NULL WHERE enrollmentID = ?");
                $stmt->execute([$enrollment['enrollmentID']]);
            }
            
            // Get info for logging before deletion
            $stmt = $conn->prepare("
                SELECT u.firstName, u.lastName, c.title, p.transactionReference, p.amount 
                FROM payments p 
                JOIN users u ON p.userID = u.userID 
                JOIN courses c ON p.courseID = c.courseID 
                WHERE p.paymentID = ?
            ");
            $stmt->execute([$paymentID]);
            $info = $stmt->fetch();
            
            // Delete the payment
            $stmt = $conn->prepare("DELETE FROM payments WHERE paymentID = ?");
            $stmt->execute([$paymentID]);
            
            $_SESSION['success'] = "Payment record for {$info['firstName']} {$info['lastName']} (₱{$info['amount']}) deleted successfully!";
            break;
            
        case 'update':
            // Update payment details (if needed in the future)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $amount = (float)$_POST['amount'];
                $status = $_POST['status'] ?? $payment['status'];
                $transactionReference = $_POST['transactionReference'] ?? $payment['transactionReference'];
                $paymentDate = $_POST['paymentDate'] ?? $payment['paymentDate'];
                
                if ($amount <= 0) {
                    $_SESSION['error'] = 'Amount must be greater than 0';
                    header("Location: payment_view.php?id=$paymentID");
                    exit();
                }
                
                $stmt = $conn->prepare("
                    UPDATE payments 
                    SET amount = ?,
                        status = ?,
                        transactionReference = ?,
                        paymentDate = ?
                    WHERE paymentID = ?
                ");
                $stmt->execute([$amount, $status, $transactionReference, $paymentDate, $paymentID]);
                
                $_SESSION['success'] = 'Payment updated successfully!';
            }
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action specified';
            break;
    }
    
    // Redirect back to appropriate page
    if (strpos($_SERVER['HTTP_REFERER'], 'payment_view.php') !== false) {
        header("Location: payment_view.php?id=$paymentID");
    } else {
        header('Location: payments.php');
    }
    exit();
    
} catch (PDOException $e) {
    error_log("Payment Action Error: " . $e->getMessage());
    
    // Check for foreign key constraint errors
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $_SESSION['error'] = 'Cannot delete payment because it is linked to other records.';
    } else {
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    }
    
    if (strpos($_SERVER['HTTP_REFERER'], 'payment_view.php') !== false) {
        header("Location: payment_view.php?id=$paymentID");
    } else {
        header('Location: payments.php');
    }
    exit();
}