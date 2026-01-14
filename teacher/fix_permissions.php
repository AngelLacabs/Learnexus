<?php
// fix_permissions.php - Run this ONCE to fix directory permissions
// Place in your instructor folder

$uploadDir = '../uploads/lessons/';

echo "<h2>Fixing Directory Permissions</h2>";

if (file_exists($uploadDir)) {
    echo "Directory exists: $uploadDir<br>";
    echo "Current permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br><br>";
    
    // Attempt to fix permissions
    if (chmod($uploadDir, 0777)) {
        echo "<strong style='color:green;'>✅ SUCCESS! Permissions changed to 0777</strong><br>";
        echo "New permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";
        
        // Verify writable
        if (is_writable($uploadDir)) {
            echo "✅ Directory is now WRITABLE!<br>";
        } else {
            echo "❌ Still not writable. You may need to change permissions via FTP/cPanel<br>";
        }
    } else {
        echo "<strong style='color:red;'>❌ FAILED to change permissions via PHP</strong><br>";
        echo "<p>You need to change permissions manually:</p>";
        echo "<h3>Option 1: Via cPanel File Manager</h3>";
        echo "<ol>";
        echo "<li>Login to cPanel</li>";
        echo "<li>Open File Manager</li>";
        echo "<li>Navigate to 'uploads/lessons' folder</li>";
        echo "<li>Right-click folder → 'Change Permissions'</li>";
        echo "<li>Set to: <strong>755</strong> or <strong>777</strong></li>";
        echo "<li>Check 'Recurse into subdirectories' if available</li>";
        echo "<li>Click 'Change Permissions'</li>";
        echo "</ol>";
        
        echo "<h3>Option 2: Via FTP (FileZilla)</h3>";
        echo "<ol>";
        echo "<li>Connect via FTP</li>";
        echo "<li>Navigate to 'uploads/lessons' folder</li>";
        echo "<li>Right-click folder → 'File permissions'</li>";
        echo "<li>Set numeric value to: <strong>755</strong> or <strong>777</strong></li>";
        echo "<li>Check 'Recurse into subdirectories'</li>";
        echo "<li>Click OK</li>";
        echo "</ol>";
        
        echo "<h3>Option 3: Via SSH/Terminal</h3>";
        echo "<pre>";
        echo "chmod 755 uploads/lessons/\n";
        echo "# or\n";
        echo "chmod 777 uploads/lessons/";
        echo "</pre>";
    }
} else {
    echo "❌ Directory does not exist!<br>";
}
?>

<hr>
<a href="test_upload.php">← Back to Test Upload</a>