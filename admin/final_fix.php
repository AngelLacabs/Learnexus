<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Please login first');
}

$message = '';
$messageType = '';

// THE FIX
if (isset($_POST['fix_now'])) {
    try {
        // Simple fix: Change ALL paths to ../uploads/lessons/filename
        $sql = "UPDATE lessons SET filename = CONCAT('../uploads/lessons/', SUBSTRING_INDEX(filename, '/', -1))";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $updated = $stmt->rowCount();
        
        $message = "✅ FIXED! Updated {$updated} files. <strong><a href='course_view.php?id=1' class='alert-link'>TEST IT NOW</a></strong>";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check where files should be
$uploadDir = dirname(__DIR__) . '/uploads/lessons/';
$uploadsExist = is_dir($uploadDir);

// Get current lessons
$stmt = $conn->query("SELECT lessonID, title, filename FROM lessons ORDER BY lessonID");
$lessons = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FINAL FIX - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 15px; margin-bottom: 30px; }
        .step { background: white; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .step h3 { color: #667eea; margin-bottom: 15px; }
        .file-path { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; border: 2px dashed #dee2e6; }
        .big-button { font-size: 24px; padding: 20px 50px; font-weight: bold; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class="container py-5">
    
    <!-- Hero Section -->
    <div class="hero text-center">
        <h1 class="display-4 mb-3">🔧 FINAL FIX</h1>
        <p class="lead mb-0">Let's fix your file paths once and for all!</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- STEP 1: Check Files -->
    <div class="step">
        <h3>📁 STEP 1: Where Are Your Files?</h3>
        
        <div class="file-path mb-3">
            <strong>Expected Location:</strong><br>
            <code><?php echo $uploadDir; ?></code>
        </div>

        <?php if ($uploadsExist): 
            $files = glob($uploadDir . '*.pdf');
            $fileCount = count($files);
        ?>
            <div class="alert alert-success">
                <h5 class="status-good">✅ FOLDER EXISTS!</h5>
                <p class="mb-2">Found <strong><?php echo $fileCount; ?> PDF files</strong></p>
                
                <?php if ($fileCount > 0): ?>
                    <details class="mt-3">
                        <summary style="cursor: pointer;"><strong>Show all files</strong></summary>
                        <ul class="mt-2">
                            <?php foreach ($files as $file): ?>
                                <li><code><?php echo basename($file); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php else: ?>
                    <div class="alert alert-warning mt-3">
                        ⚠️ Folder exists but NO PDF files found! Copy your PDFs there first!
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h5 class="status-bad">❌ FOLDER DOES NOT EXIST!</h5>
                <p><strong>YOU NEED TO:</strong></p>
                <ol>
                    <li>Open File Explorer</li>
                    <li>Go to: <code><?php echo dirname(__DIR__); ?></code></li>
                    <li>Create folders: <code>uploads</code> → <code>lessons</code></li>
                    <li>Copy ALL your PDF files into <code>lessons</code> folder</li>
                    <li>Come back and refresh this page</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>

    <!-- STEP 2: Current Status -->
    <div class="step">
        <h3>💾 STEP 2: Current Database Status</h3>
        
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Lesson</th>
                        <th>Current Path</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): 
                        $currentPath = $lesson['filename'];
                        $filename = basename($currentPath);
                        $shouldBe = '../uploads/lessons/' . $filename;
                        $isCorrect = ($currentPath === $shouldBe);
                        
                        // Check if file exists
                        $fullPath = __DIR__ . '/' . $shouldBe;
                        $fileExists = file_exists($fullPath);
                    ?>
                        <tr class="<?php echo ($isCorrect && $fileExists) ? 'table-success' : 'table-danger'; ?>">
                            <td><?php echo $lesson['lessonID']; ?></td>
                            <td><strong><?php echo htmlspecialchars($lesson['title']); ?></strong></td>
                            <td>
                                <small><code><?php echo htmlspecialchars($currentPath); ?></code></small>
                                <?php if (!$isCorrect): ?>
                                    <br><small class="text-danger">Should be: <code><?php echo htmlspecialchars($shouldBe); ?></code></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isCorrect && $fileExists): ?>
                                    <span class="badge bg-success">✅ Perfect</span>
                                <?php elseif ($isCorrect && !$fileExists): ?>
                                    <span class="badge bg-warning">⚠️ Path OK, File Missing</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">❌ Wrong Path</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- STEP 3: The Fix -->
    <div class="step">
        <h3>🚀 STEP 3: Apply The Fix</h3>
        
        <?php if (!$uploadsExist): ?>
            <div class="alert alert-danger">
                <h5>⛔ WAIT! Cannot fix yet!</h5>
                <p class="mb-0">Create the <code>uploads/lessons/</code> folder and add your PDF files first (see STEP 1)</p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <h6>This will:</h6>
                <ul class="mb-0">
                    <li>Change ALL lesson paths to: <code>../uploads/lessons/filename.pdf</code></li>
                    <li>Make files accessible from: <code>localhost/learnexus/admin/course_view.php</code></li>
                    <li>Takes 1 second to run</li>
                </ul>
            </div>

            <div class="text-center mt-4">
                <form method="POST" onsubmit="return confirm('Apply fix now?');">
                    <button type="submit" name="fix_now" class="btn btn-primary big-button">
                        🔧 FIX IT NOW
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- STEP 4: Test -->
    <div class="step">
        <h3>✅ STEP 4: Test It</h3>
        <p>After clicking "FIX IT NOW" above, test your course:</p>
        <div class="d-grid gap-2">
            <a href="course_view.php?id=1" class="btn btn-success btn-lg" target="_blank">
                🎯 TEST COURSE VIEW NOW
            </a>
        </div>
    </div>

    <!-- Alternative: Manual SQL -->
    <div class="step">
        <h3>🔧 Alternative: Manual SQL Fix</h3>
        <p>Or run this SQL directly in phpMyAdmin:</p>
        <div class="file-path">
UPDATE lessons 
SET filename = CONCAT('../uploads/lessons/', SUBSTRING_INDEX(filename, '/', -1));
        </div>
        <small class="text-muted mt-2 d-block">Copy this → Open phpMyAdmin → Select "lmslearnexus" database → SQL tab → Paste → Go</small>
    </div>

</div>
</body>
</html>