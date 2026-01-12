<?php
echo "<h2>Checking Uploads Directory</h2>";

$uploadPath = dirname(__DIR__) . '/uploads/lessons/';
echo "<p>Checking: <code>" . htmlspecialchars($uploadPath) . "</code></p>";

if (is_dir($uploadPath)) {
    echo "✅ Directory exists<br>";
    $files = scandir($uploadPath);
    $pdfs = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'pdf';
    });
    
    echo "<h3>PDF Files Found:</h3>";
    if (count($pdfs) > 0) {
        foreach ($pdfs as $pdf) {
            $fullPath = $uploadPath . $pdf;
            echo "- " . htmlspecialchars($pdf) . " (" . round(filesize($fullPath)/1024, 2) . " KB)";
            echo file_exists($fullPath) ? " ✅" : " ❌";
            echo "<br>";
        }
    } else {
        echo "❌ No PDF files found!<br>";
    }
} else {
    echo "❌ Directory doesn't exist!<br>";
    echo "Please create: <code>$uploadPath</code>";
}
?>