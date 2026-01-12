<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Please login as admin first');
}

$message = '';

// Detect which server we're on
$currentPort = $_SERVER['SERVER_PORT'];
$isPort3000 = ($currentPort == 3000);
$isXAMPP = ($currentPort == 80 || $currentPort == 443);

// Apply the correct fix based on server
if (isset($_POST['apply_fix'])) {
    try {
        $stmt = $conn->query("SELECT lessonID, filename FROM lessons");
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        
        foreach ($lessons as $lesson) {
            $oldPath = $lesson['filename'];
            $filename = basename($oldPath);
            
            // For XAMPP (localhost/learnexus), use relative path from admin folder
            $newPath = '../uploads/lessons/' . $filename;
            
            if ($oldPath !== $newPath) {
                $updateStmt = $conn->prepare("UPDATE lessons SET filename = ? WHERE lessonID = ?");
                $updateStmt->execute([$newPath, $lesson['lessonID']]);
                $updated++;
            }
        }
        
        $message = "✓ Fixed {$updated} paths for XAMPP/Apache! Now test at: <a href='course_view.php?id=1' target='_blank'>localhost/learnexus/admin/course_view.php?id=1</a>";
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get lessons
$stmt = $conn->query("SELECT l.*, c.title as courseTitle FROM lessons l JOIN courses c ON l.courseID = c.courseID");
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Two Server Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .server-info { border: 3px solid; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .port-3000 { border-color: #28a745; background: #d4edda; }
        .port-80 { border-color: #007bff; background: #cfe2ff; }
        .file-ok { background: #d4edda; }
        .file-bad { background: #f8d7da; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    
    <h1 class="text-center mb-4">🔧 Two Server Problem Solver</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Current Server Detection -->
    <div class="server-info <?php echo $isPort3000 ? 'port-3000' : 'port-80'; ?>">
        <h3>
            <?php if ($isPort3000): ?>
                🟢 You are on PORT 3000 (Dev Server)
            <?php else: ?>
                🔵 You are on PORT <?php echo $currentPort; ?> (XAMPP/Apache)
            <?php endif; ?>
        </h3>
        <p class="mb-2"><strong>Current URL:</strong> <code><?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></p>
        <p class="mb-2"><strong>Document Root:</strong> <code><?php echo $_SERVER['DOCUMENT_ROOT']; ?></code></p>
        <p class="mb-0"><strong>Admin Folder:</strong> <code><?php echo __DIR__; ?></code></p>
    </div>

    <!-- The Problem Explanation -->
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning">
            <h5 class="mb-0">⚠️ The Problem</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>🟢 localhost:3000 (Working)</h6>
                    <ul>
                        <li>Dev server (npm/Vite/PHP built-in)</li>
                        <li>Probably running from project root</li>
                        <li>Files found at: <code>uploads/lessons/</code></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>🔵 localhost/learnexus (Not Working)</h6>
                    <ul>
                        <li>XAMPP Apache server</li>
                        <li>Running from: <code>C:/xampp/htdocs/Learnexus/</code></li>
                        <li>Needs path: <code>../uploads/lessons/</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- File Check -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">📁 File Location Check</h5>
        </div>
        <div class="card-body">
            <?php
            // Check different possible locations
            $checks = [
                'From Admin (../)' => dirname(__DIR__) . '/uploads/lessons/',
                'From Project Root' => dirname(dirname(__DIR__)) . '/uploads/lessons/',
                'XAMPP Default' => 'C:/xampp/htdocs/Learnexus/uploads/lessons/',
            ];
            
            foreach ($checks as $label => $path) {
                $exists = is_dir($path);
                $pdfCount = 0;
                if ($exists) {
                    $files = glob($path . '*.pdf');
                    $pdfCount = count($files);
                }
                ?>
                <div class="mb-3 p-3 border rounded <?php echo $exists ? 'file-ok' : 'file-bad'; ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo $label; ?>:</strong><br>
                            <code><?php echo $path; ?></code>
                        </div>
                        <div>
                            <?php if ($exists): ?>
                                <span class="badge bg-success fs-6">✓ EXISTS</span>
                                <span class="badge bg-info fs-6"><?php echo $pdfCount; ?> PDFs</span>
                            <?php else: ?>
                                <span class="badge bg-danger fs-6">✗ NOT FOUND</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Current Database Paths -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">💾 Current Database Paths</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Lesson</th>
                            <th>Current Path</th>
                            <th>File Exists?</th>
                            <th>Correct for XAMPP?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): 
                            $currentPath = $lesson['filename'];
                            $filename = basename($currentPath);
                            $correctPath = '../uploads/lessons/' . $filename;
                            $isCorrect = ($currentPath === $correctPath);
                            
                            // Check if file exists with correct path
                            $fullPath = __DIR__ . '/' . $correctPath;
                            $fileExists = file_exists($fullPath);
                            
                            $rowClass = ($isCorrect && $fileExists) ? 'file-ok' : 'file-bad';
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($lesson['title']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($lesson['courseTitle']); ?></small>
                                </td>
                                <td><code><?php echo htmlspecialchars($currentPath); ?></code></td>
                                <td>
                                    <?php if ($fileExists): ?>
                                        <span class="badge bg-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ No</span>
                                        <br><small class="text-muted">Checked: <?php echo htmlspecialchars($fullPath); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isCorrect): ?>
                                        <span class="badge bg-success">✓ Correct</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Needs Fix</span>
                                        <br><small>Should be: <code><?php echo htmlspecialchars($correctPath); ?></code></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Fix Button -->
    <div class="card border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">✅ Fix for XAMPP (localhost/learnexus)</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle me-2"></i>What this will do:</h6>
                <ul class="mb-0">
                    <li>Change all paths to: <code>../uploads/lessons/filename.pdf</code></li>
                    <li>This is the correct path for XAMPP Apache server</li>
                    <li>Files must be in: <code>C:/xampp/htdocs/Learnexus/uploads/lessons/</code></li>
                </ul>
            </div>

            <div class="alert alert-warning">
                <h6><i class="bi bi-exclamation-triangle me-2"></i>Before clicking Fix:</h6>
                <ol class="mb-0">
                    <li>Make sure your PDF files are in: <code><?php echo dirname(__DIR__); ?>/uploads/lessons/</code></li>
                    <li>Check the "File Location Check" section above - at least ONE should show "✓ EXISTS"</li>
                    <li>If no folder exists, create it and copy your PDFs there first!</li>
                </ol>
            </div>

            <div class="text-center">
                <form method="POST" onsubmit="return confirm('Fix all paths for XAMPP now?');">
                    <button type="submit" name="apply_fix" class="btn btn-success btn-lg px-5 py-3">
                        <i class="bi bi-wrench me-2"></i>FIX PATHS FOR XAMPP NOW
                    </button>
                </form>
                
                <div class="mt-4">
                    <a href="course_view.php?id=1" class="btn btn-outline-primary me-2" target="_blank">
                        Test on XAMPP (localhost/learnexus)
                    </a>
                    <?php if ($isXAMPP): ?>
                        <a href="http://localhost:3000/admin/course_view.php?id=1" class="btn btn-outline-success" target="_blank">
                            Test on Port 3000
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Steps -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">📝 Manual Steps (if automatic fix doesn't work)</h5>
        </div>
        <div class="card-body">
            <h6>Step 1: Make sure files are in the right place</h6>
            <pre>1. Open File Explorer
2. Navigate to: C:\xampp\htdocs\Learnexus\
3. Create folder if missing: uploads\lessons\
4. Copy ALL your PDF files into: uploads\lessons\</pre>

            <h6 class="mt-3">Step 2: Run this SQL in phpMyAdmin</h6>
            <pre>UPDATE lessons 
SET filename = CONCAT('../uploads/lessons/', SUBSTRING_INDEX(filename, '/', -1))
WHERE filename NOT LIKE '../uploads/lessons/%';</pre>

            <h6 class="mt-3">Step 3: Test</h6>
            <p>Go to: <a href="course_view.php?id=1" target="_blank">localhost/learnexus/admin/course_view.php?id=1</a></p>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>