<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Course Access Diagnostic - Learnexus";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .test-section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Course Access Diagnostic Tool</h1>
            <a href="courses.php" class="btn btn-primary">Try Courses Page</a>
        </div>

        <?php
        $diagnostics = [];
        
        // Test 1: Database Connection
        $diagnostics['database'] = [
            'title' => 'Database Connection',
            'status' => 'success',
            'message' => 'Database connected successfully'
        ];
        
        try {
            $conn->query("SELECT 1");
        } catch (PDOException $e) {
            $diagnostics['database']['status'] = 'error';
            $diagnostics['database']['message'] = 'Database connection failed: ' . $e->getMessage();
        }
        
        // Test 2: Session Check
        $diagnostics['session'] = [
            'title' => 'Session Information',
            'status' => 'success',
            'data' => [
                'User ID' => $_SESSION['user_id'] ?? 'Not set',
                'Role' => $_SESSION['role'] ?? 'Not set',
                'Email' => $_SESSION['email'] ?? 'Not set'
            ]
        ];
        
        // Test 3: Course Query
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM courses");
            $courseCount = $stmt->fetchColumn();
            $diagnostics['courses'] = [
                'title' => 'Courses in Database',
                'status' => 'success',
                'message' => "Found {$courseCount} courses in database"
            ];
            
            // Get sample course
            $stmt = $conn->query("SELECT * FROM courses LIMIT 1");
            $sampleCourse = $stmt->fetch(PDO::FETCH_ASSOC);
            $diagnostics['courses']['data'] = $sampleCourse;
            
        } catch (PDOException $e) {
            $diagnostics['courses'] = [
                'title' => 'Courses Query',
                'status' => 'error',
                'message' => 'Error querying courses: ' . $e->getMessage()
            ];
        }
        
        // Test 4: File Paths
        $diagnostics['paths'] = [
            'title' => 'File System Paths',
            'status' => 'success',
            'data' => [
                'Document Root' => $_SERVER['DOCUMENT_ROOT'],
                'Current File' => __FILE__,
                'Admin Directory' => __DIR__,
                'Project Root' => dirname(dirname(__DIR__)),
                'courses.php exists' => file_exists(__DIR__ . '/courses.php') ? 'Yes' : 'No'
            ]
        ];
        
        // Test 5: Database Tables
        try {
            $tables = ['courses', 'users', 'enrollments', 'lessons', 'quizzes', 'payments'];
            $tableStatus = [];
            
            foreach ($tables as $table) {
                $stmt = $conn->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $tableStatus[$table] = $count . ' records';
            }
            
            $diagnostics['tables'] = [
                'title' => 'Database Tables',
                'status' => 'success',
                'data' => $tableStatus
            ];
            
        } catch (PDOException $e) {
            $diagnostics['tables'] = [
                'title' => 'Database Tables',
                'status' => 'error',
                'message' => 'Error checking tables: ' . $e->getMessage()
            ];
        }
        
        // Test 6: Lessons and File Access
        try {
            $stmt = $conn->query("SELECT l.*, c.title as courseTitle FROM lessons l JOIN courses c ON l.courseID = c.courseID LIMIT 3");
            $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $lessonStatus = [];
            foreach ($lessons as $lesson) {
                $filePath = $lesson['filename'];
                
                // Try multiple path resolutions
                $paths = [
                    'Original' => $filePath,
                    'From Doc Root' => $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($filePath, '/'),
                    'From Project' => dirname(dirname(__DIR__)) . '/' . ltrim($filePath, '/')
                ];
                
                if (strpos($filePath, '../') === 0) {
                    $paths['Resolved ../'] = dirname(dirname(__DIR__)) . '/' . substr($filePath, 3);
                }
                
                $exists = false;
                $workingPath = null;
                
                foreach ($paths as $desc => $path) {
                    if (file_exists($path)) {
                        $exists = true;
                        $workingPath = $path;
                        break;
                    }
                }
                
                $lessonStatus[] = [
                    'title' => $lesson['title'],
                    'course' => $lesson['courseTitle'],
                    'db_path' => $filePath,
                    'exists' => $exists ? 'Yes' : 'No',
                    'working_path' => $workingPath
                ];
            }
            
            $diagnostics['lessons'] = [
                'title' => 'Lesson Files',
                'status' => count($lessons) > 0 ? 'success' : 'warning',
                'message' => count($lessons) . ' lessons found',
                'data' => $lessonStatus
            ];
            
        } catch (PDOException $e) {
            $diagnostics['lessons'] = [
                'title' => 'Lesson Files',
                'status' => 'error',
                'message' => 'Error checking lessons: ' . $e->getMessage()
            ];
        }
        
        // Test 7: PHP Errors
        $diagnostics['php'] = [
            'title' => 'PHP Configuration',
            'status' => 'success',
            'data' => [
                'PHP Version' => phpversion(),
                'Display Errors' => ini_get('display_errors') ? 'On' : 'Off',
                'Error Reporting' => error_reporting(),
                'Max Upload Size' => ini_get('upload_max_filesize'),
                'Max Post Size' => ini_get('post_max_size'),
                'Memory Limit' => ini_get('memory_limit')
            ]
        ];
        
        // Test 8: Check specific course access
        if (isset($_GET['test_course'])) {
            $courseID = (int)$_GET['test_course'];
            try {
                $stmt = $conn->prepare("
                    SELECT c.*, 
                           u.firstName as teacherFirstName,
                           u.lastName as teacherLastName
                    FROM courses c 
                    JOIN users u ON c.teacherID = u.userID 
                    WHERE c.courseID = ?
                ");
                $stmt->execute([$courseID]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($course) {
                    $diagnostics['test_course'] = [
                        'title' => 'Test Course Access (ID: ' . $courseID . ')',
                        'status' => 'success',
                        'message' => 'Course found and accessible',
                        'data' => $course
                    ];
                } else {
                    $diagnostics['test_course'] = [
                        'title' => 'Test Course Access (ID: ' . $courseID . ')',
                        'status' => 'error',
                        'message' => 'Course not found'
                    ];
                }
                
            } catch (PDOException $e) {
                $diagnostics['test_course'] = [
                    'title' => 'Test Course Access',
                    'status' => 'error',
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }
        
        // Display all diagnostics
        foreach ($diagnostics as $key => $test) {
            $statusClass = $test['status'] === 'success' ? 'success' : ($test['status'] === 'error' ? 'error' : 'warning');
            $icon = $test['status'] === 'success' ? 'check-circle-fill' : ($test['status'] === 'error' ? 'x-circle-fill' : 'exclamation-triangle-fill');
            ?>
            
            <div class="test-section">
                <h3 class="<?php echo $statusClass; ?>">
                    <i class="bi bi-<?php echo $icon; ?>"></i>
                    <?php echo $test['title']; ?>
                </h3>
                
                <?php if (isset($test['message'])): ?>
                    <p><?php echo htmlspecialchars($test['message']); ?></p>
                <?php endif; ?>
                
                <?php if (isset($test['data'])): ?>
                    <pre><?php print_r($test['data']); ?></pre>
                <?php endif; ?>
            </div>
            
        <?php } ?>
        
        <!-- Quick Actions -->
        <div class="test-section">
            <h3>Quick Actions</h3>
            <div class="btn-group" role="group">
                <a href="?refresh=1" class="btn btn-primary">Refresh Diagnostics</a>
                <a href="courses.php" class="btn btn-success">Go to Courses Page</a>
                <a href="?test_course=1" class="btn btn-info">Test Course ID 1</a>
                <a href="debug_files.php" class="btn btn-warning">File Debug Tool</a>
            </div>
        </div>
        
        <!-- Error Log Check -->
        <div class="test-section">
            <h3>Recent PHP Errors (if available)</h3>
            <?php
            $errorLog = ini_get('error_log');
            if ($errorLog && file_exists($errorLog)) {
                $errors = file($errorLog);
                $recentErrors = array_slice($errors, -10);
                echo '<pre>' . htmlspecialchars(implode('', $recentErrors)) . '</pre>';
            } else {
                echo '<p class="text-muted">Error log location: ' . ($errorLog ?: 'Not configured') . '</p>';
            }
            ?>
        </div>
        
        <!-- What to do next -->
        <div class="alert alert-info">
            <h4>What to Check Next:</h4>
            <ol>
                <li>If database connection failed, check your db_connect.php file</li>
                <li>If course query failed, verify your database structure matches the SQL dump</li>
                <li>If file paths are wrong, use the "Fix File Paths" tool</li>
                <li>If you see PHP errors, enable display_errors in php.ini temporarily</li>
                <li>Check your web server error logs for more details</li>
            </ol>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>