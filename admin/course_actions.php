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
$courseID = $_GET['id'] ?? $_POST['id'] ?? 0;

if (empty($courseID)) {
    $_SESSION['error'] = 'Course ID is required';
    header('Location: courses.php');
    exit();
}

try {
    switch ($action) {
        case 'publish':
            // Publish course
            $stmt = $conn->prepare("UPDATE courses SET status = 'published' WHERE courseID = ?");
            $stmt->execute([$courseID]);
            
            $_SESSION['success'] = 'Course published successfully!';
            header("Location: course_view.php?id=$courseID");
            exit();
            
        case 'archive':
            // Archive course
            $stmt = $conn->prepare("UPDATE courses SET status = 'archived' WHERE courseID = ?");
            $stmt->execute([$courseID]);
            
            $_SESSION['success'] = 'Course archived successfully!';
            header("Location: course_view.php?id=$courseID");
            exit();
            
        case 'reject':
            // Reject course (send back to draft or archive with reason)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $status = $_POST['status'] ?? 'draft';
                $rejectionReason = trim($_POST['rejection_reason'] ?? '');
                
                if (empty($rejectionReason)) {
                    $_SESSION['error'] = 'Rejection reason is required';
                    header("Location: course_view.php?id=$courseID");
                    exit();
                }
                
                // Update course status
                $stmt = $conn->prepare("UPDATE courses SET status = ? WHERE courseID = ?");
                $stmt->execute([$status, $courseID]);
                
                // Get course and teacher info for notification
                $stmt = $conn->prepare("
                    SELECT c.title, u.email, u.firstName 
                    FROM courses c 
                    JOIN users u ON c.teacherID = u.userID 
                    WHERE c.courseID = ?
                ");
                $stmt->execute([$courseID]);
                $courseInfo = $stmt->fetch();
                
                // In a real system, you would:
                // 1. Save rejection reason to a separate table
                // 2. Send email notification to teacher
                // 3. Log the admin action
                
                $_SESSION['success'] = 'Course has been ' . ($status === 'draft' ? 'sent back to draft' : 'archived') . ' with feedback provided.';
                header("Location: course_view.php?id=$courseID");
                exit();
            } else {
                $_SESSION['error'] = 'Invalid request method';
                header("Location: course_view.php?id=$courseID");
                exit();
            }
            break;
            
        case 'delete':
            // Delete course and all related data
            // Note: Due to foreign key constraints with CASCADE, deleting the course will delete:
            // - Modules, contents, quizzes, questions, choices
            // - Enrollments, payments, certificates
            // - Lesson completions, quiz results
            
            // First, check if course exists
            $stmt = $conn->prepare("SELECT title FROM courses WHERE courseID = ?");
            $stmt->execute([$courseID]);
            $course = $stmt->fetch();
            
            if (!$course) {
                $_SESSION['error'] = 'Course not found';
                header('Location: courses.php');
                exit();
            }
            
            // Log the deletion (optional - create an audit log table)
            $courseTitle = $course['title'];
            
            // Delete the course (cascade will handle related data)
            $stmt = $conn->prepare("DELETE FROM courses WHERE courseID = ?");
            $stmt->execute([$courseID]);
            
            $_SESSION['success'] = "Course '$courseTitle' and all related data deleted successfully!";
            header('Location: courses.php');
            exit();
            
        default:
            $_SESSION['error'] = 'Invalid action specified';
            header('Location: courses.php');
            exit();
    }
    
} catch (PDOException $e) {
    error_log("Course Action Error: " . $e->getMessage());
    
    // Check for foreign key constraint errors
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        $_SESSION['error'] = 'Cannot delete course because of existing dependencies. Please try again or contact support.';
    } else {
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    }
    
    if (strpos($_SERVER['HTTP_REFERER'], 'course_view.php') !== false) {
        header("Location: course_view.php?id=$courseID");
    } else {
        header('Location: courses.php');
    }
    exit();
}