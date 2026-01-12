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
$enrollmentID = $_GET['id'] ?? $_POST['id'] ?? 0;

if (empty($enrollmentID)) {
    $_SESSION['error'] = 'Enrollment ID is required';
    header('Location: enrollments.php');
    exit();
}

try {
    // Get enrollment details first
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE enrollmentID = ?");
    $stmt->execute([$enrollmentID]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        $_SESSION['error'] = 'Enrollment not found';
        header('Location: enrollments.php');
        exit();
    }
    
    switch ($action) {
        case 'complete':
            // Mark enrollment as completed
            if ($enrollment['status'] !== 'completed') {
                $stmt = $conn->prepare("
                    UPDATE enrollments 
                    SET status = 'completed', 
                        completedAt = NOW(),
                        progressPercentage = 100
                    WHERE enrollmentID = ?
                ");
                $stmt->execute([$enrollmentID]);
                
                $_SESSION['success'] = 'Enrollment marked as completed successfully!';
            } else {
                $_SESSION['error'] = 'Enrollment is already completed';
            }
            break;
            
        case 'activate':
            // Reactivate enrollment
            $stmt = $conn->prepare("
                UPDATE enrollments 
                SET status = 'active',
                    completedAt = NULL
                WHERE enrollmentID = ?
            ");
            $stmt->execute([$enrollmentID]);
            
            $_SESSION['success'] = 'Enrollment reactivated successfully!';
            break;
            
        case 'drop':
            // Drop enrollment
            $stmt = $conn->prepare("
                UPDATE enrollments 
                SET status = 'dropped',
                    completedAt = NOW()
                WHERE enrollmentID = ?
            ");
            $stmt->execute([$enrollmentID]);
            
            $_SESSION['success'] = 'Enrollment dropped successfully!';
            break;
            
        case 'pending':
            // Set to pending
            $stmt = $conn->prepare("
                UPDATE enrollments 
                SET status = 'pending',
                    completedAt = NULL
                WHERE enrollmentID = ?
            ");
            $stmt->execute([$enrollmentID]);
            
            $_SESSION['success'] = 'Enrollment set to pending!';
            break;
            
        case 'delete':
            // Delete enrollment and all related data
            // Note: Foreign key constraints should handle cascading deletes
            
            // Get info for logging
            $stmt = $conn->prepare("
                SELECT u.firstName, u.lastName, c.title 
                FROM enrollments e 
                JOIN users u ON e.userID = u.userID 
                JOIN courses c ON e.courseID = c.courseID 
                WHERE e.enrollmentID = ?
            ");
            $stmt->execute([$enrollmentID]);
            $info = $stmt->fetch();
            
            // Delete the enrollment (cascade will handle related data)
            $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollmentID = ?");
            $stmt->execute([$enrollmentID]);
            
            $_SESSION['success'] = "Enrollment for {$info['firstName']} {$info['lastName']} in '{$info['title']}' deleted successfully!";
            break;
            
        case 'update_progress':
            // Update progress percentage
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $progress = (float)$_POST['progress'];
                $status = $_POST['status'] ?? $enrollment['status'];
                
                if ($progress < 0 || $progress > 100) {
                    $_SESSION['error'] = 'Progress percentage must be between 0 and 100';
                    header("Location: enrollment_edit.php?id=$enrollmentID");
                    exit();
                }
                
                $completedAt = $enrollment['completedAt'];
                if ($status === 'completed' && $enrollment['status'] !== 'completed') {
                    $completedAt = date('Y-m-d H:i:s');
                } elseif ($status !== 'completed') {
                    $completedAt = null;
                }
                
                $stmt = $conn->prepare("
                    UPDATE enrollments 
                    SET progressPercentage = ?,
                        status = ?,
                        completedAt = ?
                    WHERE enrollmentID = ?
                ");
                $stmt->execute([$progress, $status, $completedAt, $enrollmentID]);
                
                $_SESSION['success'] = 'Progress updated successfully!';
            }
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action specified';
            break;
    }
    
    // Redirect back to appropriate page
    if (strpos($_SERVER['HTTP_REFERER'], 'enrollment_view.php') !== false) {
        header("Location: enrollment_view.php?id=$enrollmentID");
    } else {
        header('Location: enrollments.php');
    }
    exit();
    
} catch (PDOException $e) {
    error_log("Enrollment Action Error: " . $e->getMessage());
    
    // Check for foreign key constraint errors
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $_SESSION['error'] = 'Cannot delete enrollment because of existing dependencies.';
    } else {
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    }
    
    if (strpos($_SERVER['HTTP_REFERER'], 'enrollment_view.php') !== false) {
        header("Location: enrollment_view.php?id=$enrollmentID");
    } else {
        header('Location: enrollments.php');
    }
    exit();
}