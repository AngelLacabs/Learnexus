<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Handle the fix
if (isset($_POST['fix_paths'])) {
    try {
        // Get all lessons
        $stmt = $conn->query("SELECT lessonID, filename FROM lessons");
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        $errors = [];
        
        foreach ($lessons as $lesson) {
            $oldPath = $lesson['filename'];
            
            // The correct path should be relative from the project root
            // Current: uploads/lessons/filename.pdf
            // Needed: ../uploads/lessons/filename.pdf (from admin folder perspective)
            // OR: uploads/lessons/filename.pdf (from root perspective)
            
            // Extract just the filename
            $filename = basename($oldPath);
            
            // Create new path relative to admin folder
            $newPath = '../uploads/lessons/' . $filename;
            
            // Update if different
            if ($oldPath !== $newPath) {
                $updateStmt = $conn->prepare("UPDATE lessons SET filename = ? WHERE lessonID = ?");
                $updateStmt->execute([$newPath, $lesson['lessonID']]);
                $updated++;
            }
        }
        
        $message = "Successfully updated {$updated} lesson file paths!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current lessons for display
try {
    $stmt = $conn->query("
        SELECT l.*, c.title as courseTitle 
        FROM lessons l 
        JOIN courses c ON l.courseID = c.courseID 
        ORDER BY l.lessonID
    ");
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lessons = [];
    $message = "Error loading lessons: " . $e->getMessage();
    $messageType = 'danger';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix File Paths - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Fix File Paths</h1>
                <p class="text-muted">Correct lesson file paths for proper access</p>
            </div>
            <a href="courses.php" class="btn btn-secondary">Back to Courses</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Current File Paths</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Lesson</th>
                                <th>Course</th>
                                <th>Current Path in DB</th>
                                <th>File Status</th>
                                <th>Suggested Fix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lessons as $lesson): ?>
                                <?php
                                $currentPath = $lesson['filename'];
                                $filename = basename($currentPath);
                                $suggestedPath = '../uploads/lessons/' . $filename;
                                
                                // Check if file exists at different locations
                                $adminPerspective = __DIR__ . '/' . $suggestedPath;
                                $rootPerspective = dirname(__DIR__) . '/uploads/lessons/' . $filename;
                                
                                $existsFromAdmin = file_exists($adminPerspective);
                                $existsFromRoot = file_exists($rootPerspective);
                                
                                $statusBadge = 'danger';
                                $statusText = 'Not Found';
                                
                                if ($existsFromAdmin || $existsFromRoot) {
                                    $statusBadge = 'success';
                                    $statusText = 'File Exists';
                                } elseif ($currentPath === $suggestedPath) {
                                    $statusBadge = 'warning';
                                    $statusText = 'Path OK, File Missing';
                                } else {
                                    $statusBadge = 'danger';
                                    $statusText = 'Wrong Path';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                        <br><small class="text-muted">ID: <?php echo $lesson['lessonID']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($lesson['courseTitle']); ?></td>
                                    <td><code><?php echo htmlspecialchars($currentPath); ?></code></td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusBadge; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                        <?php if ($existsFromAdmin): ?>
                                            <br><small class="text-success">✓ Accessible from admin</small>
                                        <?php endif; ?>
                                        <?php if ($existsFromRoot): ?>
                                            <br><small class="text-success">✓ Exists in uploads</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($currentPath !== $suggestedPath): ?>
                                            <code><?php echo htmlspecialchars($suggestedPath); ?></code>
                                        <?php else: ?>
                                            <span class="text-success">Path is correct</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Fix Action -->
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-wrench me-2"></i>Apply Fix</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>What this will do:</h6>
                    <ul class="mb-0">
                        <li>Update all lesson file paths to use: <code>../uploads/lessons/filename.pdf</code></li>
                        <li>This makes paths relative to the <code>admin</code> folder</li>
                        <li>Files will be accessible from course_view.php</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Important Notes:</h6>
                    <ul class="mb-0">
                        <li>Make sure your PDF files are actually in: <code><?php echo dirname(__DIR__); ?>/uploads/lessons/</code></li>
                        <li>This will update ALL lesson paths in the database</li>
                        <li>The fix is safe and can be re-run if needed</li>
                    </ul>
                </div>

                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to update all file paths?');">
                    <input type="hidden" name="fix_paths" value="1">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="bi bi-wrench me-2"></i>Fix All File Paths Now
                    </button>
                </form>
            </div>
        </div>

        <!-- Directory Structure Info -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-folder-tree me-2"></i>Expected Directory Structure</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3">
learnexus/
├── admin/
│   ├── course_view.php  ← You are here
│   ├── courses.php
│   └── fix_file_paths.php
├── uploads/
│   ├── lessons/
│   │   ├── 6964564bcf1e6_Angelica Journey Sia Sad Web.pdf
│   │   ├── 69645689239dc_FINAL FINAL LMS.drawio.pdf
│   │   └── ... (other PDFs)
│   └── avatars/
├── database/
│   └── db_connect.php
└── ...
                </pre>
                
                <div class="alert alert-info mt-3 mb-0">
                    <strong>Path from admin folder to uploads:</strong> <code>../uploads/lessons/filename.pdf</code><br>
                    <strong>Current admin directory:</strong> <code><?php echo __DIR__; ?></code><br>
                    <strong>Expected uploads directory:</strong> <code><?php echo dirname(__DIR__) . '/uploads/lessons/'; ?></code>
                </div>
            </div>
        </div>

        <!-- Manual File Check -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-search me-2"></i>Verify Files Manually</h5>
            </div>
            <div class="card-body">
                <?php
                $uploadsDir = dirname(__DIR__) . '/uploads/lessons/';
                if (is_dir($uploadsDir)):
                    $files = scandir($uploadsDir);
                    $pdfFiles = array_filter($files, function($file) {
                        return pathinfo($file, PATHINFO_EXTENSION) === 'pdf';
                    });
                ?>
                    <p class="text-success"><i class="bi bi-check-circle me-2"></i>Uploads directory exists!</p>
                    <p><strong>Found <?php echo count($pdfFiles); ?> PDF files:</strong></p>
                    <div class="row">
                        <?php foreach ($pdfFiles as $file): ?>
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-2">
                                    <i class="bi bi-file-pdf text-danger me-2"></i>
                                    <code><?php echo htmlspecialchars($file); ?></code>
                                    <br>
                                    <small class="text-muted">
                                        Size: <?php echo round(filesize($uploadsDir . $file) / 1024, 2); ?> KB
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Uploads directory not found!</strong><br>
                        Expected location: <code><?php echo $uploadsDir; ?></code><br>
                        <strong>Action needed:</strong> Create this directory and upload your PDF files there.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>