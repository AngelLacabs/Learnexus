<?php
// test_upload.php - Place this in your instructor folder to test uploads
session_start();
require_once '../database/db_connect.php';

echo "<h2>PDF Upload Debugging Tool</h2>";

// 1. Check PHP upload settings
echo "<h3>1. PHP Upload Configuration:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br><br>";

// 2. Check directory permissions
echo "<h3>2. Directory Check:</h3>";
$uploadDir = '../uploads/lessons/';
if (!file_exists($uploadDir)) {
    echo "❌ Directory does NOT exist: $uploadDir<br>";
    echo "Attempting to create...<br>";
    if (mkdir($uploadDir, 0777, true)) {
        echo "✅ Directory created successfully!<br>";
    } else {
        echo "❌ Failed to create directory!<br>";
    }
} else {
    echo "✅ Directory exists: $uploadDir<br>";
}

if (is_writable($uploadDir)) {
    echo "✅ Directory is writable<br>";
} else {
    echo "❌ Directory is NOT writable<br>";
    echo "Current permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";
}
echo "<br>";

// 3. Check existing files in database
echo "<h3>3. Existing Lessons in Database:</h3>";
$stmt = $conn->prepare("SELECT lessonID, title, filename FROM lessons ORDER BY uploadedAt DESC LIMIT 5");
$stmt->execute();
$lessons = $stmt->fetchAll();

if (count($lessons) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Filename (DB)</th><th>File Exists?</th><th>Full Path</th></tr>";
    foreach ($lessons as $lesson) {
        $fullPath = '../' . $lesson['filename'];
        $exists = file_exists($fullPath);
        echo "<tr>";
        echo "<td>" . $lesson['lessonID'] . "</td>";
        echo "<td>" . htmlspecialchars($lesson['title']) . "</td>";
        echo "<td>" . htmlspecialchars($lesson['filename']) . "</td>";
        echo "<td>" . ($exists ? '✅ Yes' : '❌ No') . "</td>";
        echo "<td>" . htmlspecialchars($fullPath) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No lessons found in database.<br>";
}
echo "<br>";

// 4. Test upload form
echo "<h3>4. Test Upload Form:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<div style='background:#f0f0f0; padding:10px; margin:10px 0;'>";
    echo "<strong>Upload Attempt Details:</strong><br>";
    
    $file = $_FILES['test_file'];
    echo "Original filename: " . $file['name'] . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "Tmp name: " . $file['tmp_name'] . "<br>";
    echo "Error code: " . $file['error'] . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        echo "File extension: $fileExt<br>";
        
        if ($fileExt === 'pdf') {
            $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
            $uploadPath = $uploadDir . $newFileName;
            $dbPath = 'uploads/lessons/' . $newFileName;
            
            echo "New filename: $newFileName<br>";
            echo "Upload path: $uploadPath<br>";
            echo "DB path: $dbPath<br>";
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                echo "<strong style='color:green;'>✅ FILE UPLOADED SUCCESSFULLY!</strong><br>";
                echo "File location: $uploadPath<br>";
                
                // Verify file exists
                if (file_exists($uploadPath)) {
                    echo "✅ File verified to exist at: $uploadPath<br>";
                    echo "File size on disk: " . filesize($uploadPath) . " bytes<br>";
                    
                    // Get a valid course ID first
                    $courseStmt = $conn->prepare("SELECT courseID FROM courses LIMIT 1");
                    $courseStmt->execute();
                    $testCourseID = $courseStmt->fetchColumn();
                    
                    if ($testCourseID) {
                        // Test database insert
                        $testTitle = "Test Upload " . date('Y-m-d H:i:s');
                        
                        $stmt = $conn->prepare("INSERT INTO lessons (courseID, title, filename, uploadedAt) VALUES (?, ?, ?, NOW())");
                        if ($stmt->execute([$testCourseID, $testTitle, $dbPath])) {
                            $lessonID = $conn->lastInsertId();
                            echo "✅ Database record created! Lesson ID: $lessonID<br>";
                            
                            // Test viewing path
                            echo "<br><strong>Test viewing the PDF:</strong><br>";
                            echo "<a href='../$dbPath' target='_blank'>Click here to view PDF</a><br>";
                            echo "Full URL path: ../$dbPath<br>";
                            
                            // Clean up test record
                            echo "<br><em>Note: You can delete this test lesson from manage_course.php</em><br>";
                        } else {
                            echo "❌ Database insert failed!<br>";
                            echo "Error: " . print_r($stmt->errorInfo(), true) . "<br>";
                        }
                    } else {
                        echo "⚠️ No courses found in database. Skipping database insert test.<br>";
                        echo "But the file upload works! File saved at: $uploadPath<br>";
                        
                        // Test viewing path anyway
                        echo "<br><strong>Test viewing the PDF:</strong><br>";
                        echo "<a href='../$dbPath' target='_blank'>Click here to view PDF</a><br>";
                        
                        // Delete the uploaded test file since we can't save to DB
                        if (unlink($uploadPath)) {
                            echo "<br><em>Test file deleted (no course to attach it to)</em><br>";
                        }
                    }
                } else {
                    echo "❌ File does NOT exist after upload!<br>";
                }
            } else {
                echo "<strong style='color:red;'>❌ FAILED TO MOVE UPLOADED FILE!</strong><br>";
                echo "Possible reasons:<br>";
                echo "- Directory doesn't exist<br>";
                echo "- No write permissions<br>";
                echo "- Disk full<br>";
            }
        } else {
            echo "❌ Not a PDF file!<br>";
        }
    } else {
        echo "<strong style='color:red;'>Upload Error!</strong><br>";
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
        ];
        echo $errors[$file['error']] ?? 'Unknown error';
        echo "<br>";
    }
    echo "</div>";
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file" accept=".pdf" required>
    <button type="submit">Test Upload PDF</button>
</form>

<hr>
<h3>5. File System Check:</h3>
<?php
// List files in uploads directory
if (file_exists($uploadDir) && is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo "Files in $uploadDir:<br>";
    if (count($files) > 2) { // More than . and ..
        echo "<ul>";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "<li>$file (" . filesize($uploadDir . $file) . " bytes)</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "Directory is empty<br>";
    }
} else {
    echo "Cannot read directory<br>";
}
?>