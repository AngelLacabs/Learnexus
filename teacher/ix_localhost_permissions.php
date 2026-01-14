<?php
// fix_localhost_permissions.php
// Run this in your instructor folder

$uploadDir = '../uploads/lessons/';

echo "<h2>Fixing Localhost Permissions & Database</h2>";

// 1. Fix directory permissions (Windows)
echo "<h3>1. Directory Permissions Fix:</h3>";
if (file_exists($uploadDir)) {
    echo "Directory exists: $uploadDir<br>";
    
    // On Windows, chmod often doesn't work the same way
    // Try to create a test file to verify write access
    $testFile = $uploadDir . 'test_write.txt';
    $canWrite = @file_put_contents($testFile, 'test');
    
    if ($canWrite !== false) {
        echo "✅ Directory IS writable!<br>";
        @unlink($testFile); // Clean up test file
    } else {
        echo "❌ Directory is NOT writable<br>";
        echo "<p><strong>Windows Fix:</strong></p>";
        echo "<ol>";
        echo "<li>Right-click the 'uploads/lessons' folder in Windows Explorer</li>";
        echo "<li>Select 'Properties'</li>";
        echo "<li>Uncheck 'Read-only' if checked</li>";
        echo "<li>Click 'Apply'</li>";
        echo "<li>Select 'Apply changes to this folder, subfolders and files'</li>";
        echo "<li>Click OK</li>";
        echo "</ol>";
        
        // Try to fix programmatically
        if (chmod($uploadDir, 0777)) {
            echo "✅ Permissions changed via PHP<br>";
        }
    }
} else {
    echo "❌ Directory does not exist! Creating...<br>";
    if (mkdir($uploadDir, 0777, true)) {
        echo "✅ Directory created!<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

// 2. Fix database paths
echo "<h3>2. Database Path Fixes:</h3>";
require_once '../database/db_connect.php';

// Find broken paths
$stmt = $conn->prepare("SELECT lessonID, title, filename FROM lessons WHERE filename NOT LIKE 'uploads/lessons/%'");
$stmt->execute();
$brokenLessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($brokenLessons) > 0) {
    echo "<p>Found " . count($brokenLessons) . " lessons with incorrect paths:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Old Path</th><th>Action</th></tr>";
    
    foreach ($brokenLessons as $lesson) {
        echo "<tr>";
        echo "<td>" . $lesson['lessonID'] . "</td>";
        echo "<td>" . htmlspecialchars($lesson['title']) . "</td>";
        echo "<td>" . htmlspecialchars($lesson['filename']) . "</td>";
        
        // Extract just the filename
        $filename = basename($lesson['filename']);
        $newPath = 'uploads/lessons/' . $filename;
        $fullPath = '../' . $newPath;
        
        // Check if file exists with new path
        if (file_exists($fullPath)) {
            // Update database
            $updateStmt = $conn->prepare("UPDATE lessons SET filename = ? WHERE lessonID = ?");
            if ($updateStmt->execute([$newPath, $lesson['lessonID']])) {
                echo "<td style='color:green;'>✅ Fixed!</td>";
            } else {
                echo "<td style='color:red;'>❌ Update failed</td>";
            }
        } else {
            echo "<td style='color:orange;'>⚠️ File not found at: $fullPath</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✅ All lesson paths are correct!<br>";
}

// 3. Verify all lessons
echo "<h3>3. Verification - All Lessons:</h3>";
$stmt = $conn->prepare("SELECT lessonID, title, filename FROM lessons ORDER BY lessonID DESC");
$stmt->execute();
$allLessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Title</th><th>DB Path</th><th>Full Path</th><th>Exists?</th></tr>";

foreach ($allLessons as $lesson) {
    $fullPath = '../' . $lesson['filename'];
    $exists = file_exists($fullPath);
    
    echo "<tr>";
    echo "<td>" . $lesson['lessonID'] . "</td>";
    echo "<td>" . htmlspecialchars($lesson['title']) . "</td>";
    echo "<td>" . htmlspecialchars($lesson['filename']) . "</td>";
    echo "<td>" . htmlspecialchars($fullPath) . "</td>";
    echo "<td>" . ($exists ? "✅ Yes" : "❌ No") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<a href='test_upload.php'>Test Upload Again</a>";
?>