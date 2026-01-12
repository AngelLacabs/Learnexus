<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Please login as admin first');
}

$message = '';

// STEP 1: Find where files actually are
function findFile($filename) {
    $searches = [
        __DIR__ . '/../uploads/lessons/' . $filename,
        __DIR__ . '/../../uploads/lessons/' . $filename,
        $_SERVER['DOCUMENT_ROOT'] . '/Learnexus/uploads/lessons/' . $filename,
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/lessons/' . $filename,
        'C:/xampp/htdocs/Learnexus/uploads/lessons/' . $filename,
        'C:/xampp/htdocs/uploads/lessons/' . $filename,
    ];
    
    foreach ($searches as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return false;
}

// STEP 2: Auto-fix with the correct path
if (isset($_POST['do_magic_fix'])) {
    try {
        $stmt = $conn->query("SELECT lessonID, filename FROM lessons");
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $fixed = 0;
        foreach ($lessons as $lesson) {
            $filename = basename($lesson['filename']);
            $foundPath = findFile($filename);
            
            if ($foundPath) {
                // Calculate relative path from admin directory
                $adminDir = __DIR__;
                $relativePath = str_replace('\\', '/', $foundPath);
                $adminDirNorm = str_replace('\\', '/', $adminDir);
                
                // Make it relative
                if (strpos($relativePath, $adminDirNorm) === 0) {
                    $newPath = substr($relativePath, strlen($adminDirNorm) + 1);
                } else {
                    // Default fallback
                    $newPath = '../uploads/lessons/' . $filename;
                }
                
                $updateStmt = $conn->prepare("UPDATE lessons SET filename = ? WHERE lessonID = ?");
                $updateStmt->execute([$newPath, $lesson['lessonID']]);
                $fixed++;
            }
        }
        
        $message = "🎉 FIXED {$fixed} files! <a href='course_view.php?id=1'>Click here to test</a>";
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get current state
$stmt = $conn->query("SELECT * FROM lessons ORDER BY lessonID");
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>SUPER Debug Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .found { background: #d4edda; }
        .notfound { background: #f8d7da; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 11px; }
        .big-btn { font-size: 20px; padding: 20px 40px; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    
    <div class="text-center mb-4">
        <h1>🔧 SUPER DEBUG TOOL</h1>
        <p class="lead">Let's find and fix your files!</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- System Info -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">📁 System Information</h5>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <tr>
                    <td><strong>Document Root:</strong></td>
                    <td><code><?php echo $_SERVER['DOCUMENT_ROOT']; ?></code></td>
                </tr>
                <tr>
                    <td><strong>This File Location:</strong></td>
                    <td><code><?php echo __FILE__; ?></code></td>
                </tr>
                <tr>
                    <td><strong>Admin Directory:</strong></td>
                    <td><code><?php echo __DIR__; ?></code></td>
                </tr>
                <tr>
                    <td><strong>Project Root:</strong></td>
                    <td><code><?php echo dirname(__DIR__); ?></code></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- File Detection -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">🔍 Where Are Your Files?</h5>
        </div>
        <div class="card-body">
            <?php
            // Check possible upload directories
            $possibleDirs = [
                'Option A' => dirname(__DIR__) . '/uploads/lessons/',
                'Option B' => dirname(dirname(__DIR__)) . '/uploads/lessons/',
                'Option C' => 'C:/xampp/htdocs/Learnexus/uploads/lessons/',
                'Option D' => 'C:/xampp/htdocs/uploads/lessons/',
                'Option E' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/lessons/',
            ];
            
            $foundDir = null;
            foreach ($possibleDirs as $label => $dir) {
                $exists = is_dir($dir);
                $color = $exists ? 'success' : 'secondary';
                echo "<div class='mb-2'>";
                echo "<span class='badge bg-{$color} me-2'>{$label}</span> ";
                echo "<code>{$dir}</code> ";
                
                if ($exists) {
                    $files = glob($dir . '*.pdf');
                    echo "<span class='badge bg-info'>" . count($files) . " PDFs found</span>";
                    $foundDir = $dir;
                    
                    // List first 3 files
                    if (count($files) > 0) {
                        echo "<div class='ms-4 mt-1'>";
                        foreach (array_slice($files, 0, 3) as $file) {
                            echo "<small>📄 " . basename($file) . "</small><br>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<span class='text-muted'>Not found</span>";
                }
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- Current Database Paths -->
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0">📋 Current Files in Database</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Lesson Title</th>
                            <th>Path in Database</th>
                            <th>File Found?</th>
                            <th>Found At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): 
                            $filename = basename($lesson['filename']);
                            $foundPath = findFile($filename);
                            $rowClass = $foundPath ? 'found' : 'notfound';
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo $lesson['lessonID']; ?></td>
                                <td><strong><?php echo htmlspecialchars($lesson['title']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($lesson['filename']); ?></code></td>
                                <td>
                                    <?php if ($foundPath): ?>
                                        <span class="badge bg-success">✓ YES</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ NO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($foundPath): ?>
                                        <small><code><?php echo htmlspecialchars($foundPath); ?></code></small>
                                    <?php else: ?>
                                        <small class="text-danger">File not found anywhere!</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- THE MAGIC FIX BUTTON -->
    <div class="card border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">🪄 MAGIC FIX</h5>
        </div>
        <div class="card-body text-center">
            <h4 class="mb-3">Ready to fix all paths automatically?</h4>
            <p class="text-muted mb-4">This will update all database paths to match where files were actually found</p>
            
            <form method="POST" onsubmit="return confirm('Fix all paths now?');">
                <button type="submit" name="do_magic_fix" class="btn btn-success btn-lg big-btn">
                    🪄 DO THE MAGIC FIX
                </button>
            </form>
            
            <div class="mt-4">
                <a href="course_view.php?id=1" class="btn btn-outline-primary">
                    Test Course View After Fix →
                </a>
            </div>
        </div>
    </div>

    <!-- Manual Instructions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">📝 If Magic Fix Doesn't Work...</h5>
        </div>
        <div class="card-body">
            <h6>Do this manually:</h6>
            <ol>
                <li>Copy ALL your PDF files to: <code><?php echo dirname(__DIR__); ?>/uploads/lessons/</code></li>
                <li>Make sure the <code>uploads</code> and <code>lessons</code> folders exist</li>
                <li>Come back here and click "DO THE MAGIC FIX" again</li>
            </ol>
            
            <div class="alert alert-info mt-3 mb-0">
                <strong>Quick Check:</strong> Does this folder exist?<br>
                <code><?php echo dirname(__DIR__); ?>/uploads/lessons/</code>
                <?php if (is_dir(dirname(__DIR__) . '/uploads/lessons/')): ?>
                    <span class="badge bg-success ms-2">✓ YES</span>
                <?php else: ?>
                    <span class="badge bg-danger ms-2">✗ NO - CREATE IT!</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>