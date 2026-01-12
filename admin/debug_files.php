<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "File Debug - Learnexus";

// Get all lessons with their file paths
try {
    $stmt = $conn->query("
        SELECT l.*, c.title as courseTitle, c.courseID 
        FROM lessons l 
        JOIN courses c ON l.courseID = c.courseID 
        ORDER BY l.uploadedAt DESC
    ");
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Debug Error: " . $e->getMessage());
    $lessons = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">File Path Debug</h1>
                <p class="text-muted mb-0">Check lesson file paths and accessibility</p>
            </div>
            <div>
                <a href="courses.php" class="btn btn-secondary">Back to Courses</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Server Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Server Paths:</h6>
                        <pre class="bg-light p-3 rounded">
Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>

Current Directory: <?php echo __DIR__; ?>

Admin Directory: <?php echo dirname(__FILE__); ?>

Project Root: <?php echo dirname(dirname(__FILE__)); ?>
                        </pre>
                    </div>
                    <div class="col-md-6">
                        <h6>URL Information:</h6>
                        <pre class="bg-light p-3 rounded">
Protocol: <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http"); ?>

Host: <?php echo $_SERVER['HTTP_HOST']; ?>

Request URI: <?php echo $_SERVER['REQUEST_URI']; ?>

Script Name: <?php echo $_SERVER['SCRIPT_NAME']; ?>
                        </pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Lesson Files Check</h5>
            </div>
            <div class="card-body">
                <?php if (empty($lessons)): ?>
                    <p class="text-muted">No lessons found in database.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Lesson</th>
                                    <th>Course</th>
                                    <th>Database Path</th>
                                    <th>Server Path</th>
                                    <th>Web URL</th>
                                    <th>Exists</th>
                                    <th>Readable</th>
                                    <th>Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lessons as $lesson): ?>
                                    <?php
                                    $dbPath = $lesson['filename'];
                                    $serverPath = '';
                                    $webUrl = '';
                                    $exists = false;
                                    $readable = false;
                                    $size = 'N/A';
                                    
                                    // Try different path resolutions
                                    $possiblePaths = [];
                                    
                                    // 1. Direct path
                                    $possiblePaths[] = $dbPath;
                                    
                                    // 2. From document root
                                    $possiblePaths[] = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($dbPath, '/');
                                    
                                    // 3. Relative from admin directory
                                    $possiblePaths[] = __DIR__ . '/' . ltrim($dbPath, '/');
                                    
                                    // 4. Remove ../ prefixes
                                    if (strpos($dbPath, '../') === 0) {
                                        $possiblePaths[] = dirname(dirname(__DIR__)) . '/' . substr($dbPath, 3);
                                    }
                                    
                                    // 5. From project root
                                    $possiblePaths[] = dirname(dirname(__DIR__)) . '/' . ltrim($dbPath, '/');
                                    
                                    // Find first existing path
                                    foreach ($possiblePaths as $path) {
                                        if (file_exists($path)) {
                                            $serverPath = $path;
                                            $exists = true;
                                            $readable = is_readable($path);
                                            $size = filesize($path) ? round(filesize($path) / 1024, 2) . ' KB' : '0 KB';
                                            break;
                                        }
                                    }
                                    
                                    // Create web URL
                                    if ($exists) {
                                        // Convert server path to web URL
                                        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $serverPath);
                                        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                                        $webUrl = $baseUrl . $relativePath;
                                    }
                                    ?>
                                    
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                            <br><small>ID: <?php echo $lesson['lessonID']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($lesson['courseTitle']); ?>
                                            <br><small>ID: <?php echo $lesson['courseID']; ?></small>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($dbPath); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($serverPath): ?>
                                                <code><?php echo htmlspecialchars($serverPath); ?></code>
                                            <?php else: ?>
                                                <span class="text-danger">Not found</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($webUrl): ?>
                                                <a href="<?php echo htmlspecialchars($webUrl); ?>" target="_blank">
                                                    <?php echo htmlspecialchars($webUrl); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-danger">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($exists): ?>
                                                <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($readable): ?>
                                                <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $size; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Path Resolution Test:</h6>
                        <form method="POST" action="" class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Test a file path:</label>
                                <input type="text" class="form-control" name="test_path" 
                                       placeholder="Enter file path from database" 
                                       value="<?php echo isset($_POST['test_path']) ? htmlspecialchars($_POST['test_path']) : ''; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Test Path</button>
                            </div>
                        </form>
                        
                        <?php if (isset($_POST['test_path']) && !empty($_POST['test_path'])): ?>
                            <?php
                            $testPath = $_POST['test_path'];
                            $results = [];
                            
                            // Test different resolutions
                            $testCases = [
                                'Original Path' => $testPath,
                                'From Document Root' => $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($testPath, '/'),
                                'From Admin Directory' => __DIR__ . '/' . ltrim($testPath, '/'),
                                'From Project Root' => dirname(dirname(__DIR__)) . '/' . ltrim($testPath, '/'),
                            ];
                            
                            // If path starts with ../
                            if (strpos($testPath, '../') === 0) {
                                $testCases['Resolved from Admin'] = dirname(dirname(__DIR__)) . '/' . substr($testPath, 3);
                            }
                            
                            foreach ($testCases as $description => $path) {
                                $exists = file_exists($path);
                                $readable = $exists ? is_readable($path) : false;
                                $size = $exists && filesize($path) ? round(filesize($path) / 1024, 2) . ' KB' : 'N/A';
                                
                                $results[] = [
                                    'description' => $description,
                                    'path' => $path,
                                    'exists' => $exists,
                                    'readable' => $readable,
                                    'size' => $size
                                ];
                            }
                            ?>
                            
                            <div class="mt-3">
                                <h6>Test Results:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Path</th>
                                                <th>Exists</th>
                                                <th>Readable</th>
                                                <th>Size</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $result): ?>
                                                <tr class="<?php echo $result['exists'] ? 'table-success' : 'table-danger'; ?>">
                                                    <td><?php echo $result['description']; ?></td>
                                                    <td><code><?php echo htmlspecialchars($result['path']); ?></code></td>
                                                    <td>
                                                        <?php if ($result['exists']): ?>
                                                            <span class="badge bg-success">Yes</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($result['readable']): ?>
                                                            <span class="badge bg-success">Yes</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $result['size']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>