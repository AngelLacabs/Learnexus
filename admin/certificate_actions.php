<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$action = $_GET['action'] ?? '';
$certificateID = $_GET['id'] ?? 0;

if (!$certificateID) {
    $_SESSION['error'] = 'No certificate specified';
    header('Location: certificates.php');
    exit();
}

try {
    switch ($action) {
        case 'delete':
            // Get certificate info for logging
            $stmt = $conn->prepare("SELECT * FROM certificates WHERE certificateID = ?");
            $stmt->execute([$certificateID]);
            $certificate = $stmt->fetch();
            
            if ($certificate) {
                // Delete certificate
                $stmt = $conn->prepare("DELETE FROM certificates WHERE certificateID = ?");
                $stmt->execute([$certificateID]);
                
                $_SESSION['success'] = "Certificate deleted successfully: " . $certificate['certificateUUID'];
            } else {
                $_SESSION['error'] = "Certificate not found";
            }
            break;
            
        case 'regenerate':
            // Regenerate certificate UUID
            $newUUID = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $stmt = $conn->prepare("UPDATE certificates SET certificateUUID = ? WHERE certificateID = ?");
            $stmt->execute([$newUUID, $certificateID]);
            
            $_SESSION['success'] = "Certificate UUID regenerated: " . $newUUID;
            break;
            
        case 'reset_downloads':
            // Reset download count
            $stmt = $conn->prepare("UPDATE certificates SET downloadCount = 0 WHERE certificateID = ?");
            $stmt->execute([$certificateID]);
            
            // Clear download history
            $stmt = $conn->prepare("DELETE FROM certificate_downloads WHERE certificateID = ?");
            $stmt->execute([$certificateID]);
            
            $_SESSION['success'] = "Download count and history reset";
            break;
            
        default:
            $_SESSION['error'] = "Invalid action";
            break;
    }
} catch (PDOException $e) {
    error_log("Certificate Action Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error performing action: ' . $e->getMessage();
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'certificates.php'));
exit();