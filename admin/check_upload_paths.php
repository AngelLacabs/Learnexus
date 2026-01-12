<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

echo "<h2>Checking File Paths</h2>";

// Check uploads directory
$uploadsPath = dirname(dirname(__DIR__)) . '/uploads/';
echo "<h3>Uploads Directory: " . htmlspecialchars($uploadsPath) . "</h3>";

if (is_dir($uploadsPath)) {
    echo "✓ Uploads directory exists<br>";
    
    // Check lessons subdirectory
    $lessonsPath = $uploadsPath . 'lessons/';
    if (is_dir($lessonsPath)) {
        echo "✓ Lessons directory exists<br>";
        $files = scandir($lessonsPath);
        echo "Files in lessons directory:<br>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- " . htmlspecialchars($file) . "<br>";
            }
        }
    } else {
        echo "✗ Lessons directory not found<br>";
    }
    
    // Check avatars subdirectory
    $avatarsPath = $uploadsPath . 'avatars/';
    if (is_dir($avatarsPath)) {
        echo "✓ Avatars directory exists<br>";
        $files = scandir($avatarsPath);
        echo "Files in avatars directory:<br>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- " . htmlspecialchars($file) . "<br>";
            }
        }
    } else {
        echo "✗ Avatars directory not found<br>";
    }
} else {
    echo "✗ Uploads directory not found<br>";
}

// Check database paths
echo "<h3>Database File Paths</h3>";
try {
    $stmt = $conn->query("SELECT filename FROM lessons LIMIT 5");
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Lesson file paths in database:<br>";
    foreach ($lessons as $lesson) {
        echo "- " . htmlspecialchars($lesson['filename']) . "<br>";
        
        // Test if file exists
        $testPath1 = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($lesson['filename'], '/');
        $testPath2 = dirname(dirname(__DIR__)) . '/' . ltrim($lesson['filename'], '/');
        
        if (strpos($lesson['filename'], '../') === 0) {
            $testPath3 = dirname(dirname(__DIR__)) . '/' . substr($lesson['filename'], 3);
        } else {
            $testPath3 = $testPath1;
        }
        
        echo "&nbsp;&nbsp;Checking: " . htmlspecialchars($testPath1) . " - " . 
             (file_exists($testPath1) ? "✓ Exists" : "✗ Not found") . "<br>";
        echo "&nbsp;&nbsp;Checking: " . htmlspecialchars($testPath2) . " - " . 
             (file_exists($testPath2) ? "✓ Exists" : "✗ Not found") . "<br>";
        echo "&nbsp;&nbsp;Checking: " . htmlspecialchars($testPath3) . " - " . 
             (file_exists($testPath3) ? "✓ Exists" : "✗ Not found") . "<br>";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>