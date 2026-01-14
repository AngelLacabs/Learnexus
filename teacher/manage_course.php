<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// Get course details and verify ownership
$stmt = $conn->prepare("
    SELECT c.*, 
           CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ? AND c.teacherID = ?
");
$stmt->execute([$courseID, $teacherID]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found or you don't have permission to manage it.");
}

// Get course statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE courseID = ?");
$stmt->execute([$courseID]);
$enrolledStudents = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalLessons = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$totalQuizzes = $stmt->fetch()['count'];

// Get all lessons
$stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
$stmt->execute([$courseID]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all quizzes
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ? ORDER BY createdAt DESC");
$stmt->execute([$courseID]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get enrolled students
$stmt = $conn->prepare("
    SELECT u.*, e.enrolledAt, e.progressPercentage, e.status
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    WHERE e.courseID = ?
    ORDER BY e.enrolledAt DESC
");
$stmt->execute([$courseID]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle course update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $status = $_POST['status'];
    $passingScore = intval($_POST['passingScore']);
    
    $stmt = $conn->prepare("
        UPDATE courses 
        SET title = ?, description = ?, price = ?, category = ?, status = ?, passingScore = ?
        WHERE courseID = ? AND teacherID = ?
    ");
    $stmt->execute([$title, $description, $price, $category, $status, $passingScore, $courseID, $teacherID]);
    
    $success = "Course updated successfully!";
    
    // Refresh course data
    $stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Replace the lesson upload section in manage_course.php with this:

// Handle lesson upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_lesson']) && isset($_FILES['lesson_file'])) {
    $lessonTitle = trim($_POST['lesson_title']);
    $file = $_FILES['lesson_file'];
    
    // Detailed error tracking
    $uploadErrors = [];
    
    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/lessons/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            $error = "Failed to create upload directory!";
            $uploadErrors[] = "Directory creation failed";
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        $error = "Upload directory is not writable!";
        $uploadErrors[] = "Directory not writable";
    }
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $error = $errorMessages[$file['error']] ?? "Unknown upload error: " . $file['error'];
        $uploadErrors[] = $error;
    }
    
    // Validate file extension
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf'];
    
    if (!in_array($fileExt, $allowedExts)) {
        $error = "Only PDF files are allowed! You uploaded: .$fileExt";
        $uploadErrors[] = $error;
    }
    
    // Validate file size (10MB limit)
    if ($file['size'] > 10485760) {
        $error = "File size must be less than 10MB! Your file: " . round($file['size']/1048576, 2) . "MB";
        $uploadErrors[] = $error;
    }
    
    // If no errors, proceed with upload
    if (empty($uploadErrors)) {
        // Generate unique filename
        $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
        $uploadPath = $uploadDir . $newFileName;
        $dbPath = 'uploads/lessons/' . $newFileName;
        
        // Attempt to move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            
            // Verify file exists
            if (file_exists($uploadPath)) {
                
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO lessons (courseID, title, filename, uploadedAt) VALUES (?, ?, ?, NOW())");
                
                if ($stmt->execute([$courseID, $lessonTitle, $dbPath])) {
                    $success = "Lesson uploaded successfully! File: $newFileName";
                    
                    // Refresh lessons
                    $stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
                    $stmt->execute([$courseID]);
                    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Database insert failed - delete uploaded file
                    unlink($uploadPath);
                    $error = "Database error: Failed to save lesson record";
                }
            } else {
                $error = "File uploaded but cannot be found on disk!";
            }
        } else {
            $error = "Failed to move uploaded file! Check directory permissions.";
        }
    } else {
        // Show first error
        $error = $uploadErrors[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Course - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .top-nav {
            background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .brand {
            font-size: 20px;
            font-weight: 700;
            color: #1a73e8;
            cursor: pointer;
            text-decoration: none;
        }
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 40px;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-box .number {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
        }
        .stat-box .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .section-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .lesson-item {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .lesson-item:hover {
            background: #e9ecef;
        }
        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        .student-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .student-row:last-child {
            border-bottom: none;
        }
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e0e0e0;
            width: 150px;
        }
        .progress-bar-custom .fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <a href="dashboard.php" class="brand">LEARNEXUS</a>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="container-main">
        <!-- Page Header -->
        <div class="page-header">
            <h1><?php echo htmlspecialchars($course['title']); ?></h1>
            <p class="mb-0">Manage your course content, students, and settings</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number"><?php echo $enrolledStudents; ?></div>
                <div class="label"><i class="bi bi-people"></i> Enrolled Students</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $totalLessons; ?></div>
                <div class="label"><i class="bi bi-file-earmark-pdf"></i> Total Lessons</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo $totalQuizzes; ?></div>
                <div class="label"><i class="bi bi-patch-question"></i> Total Quizzes</div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="courseTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button">
                    <i class="bi bi-info-circle"></i> Course Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lessons-tab" data-bs-toggle="tab" data-bs-target="#lessons" type="button">
                    <i class="bi bi-file-earmark-pdf"></i> Lessons
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="quizzes-tab" data-bs-toggle="tab" data-bs-target="#quizzes" type="button">
                    <i class="bi bi-patch-question"></i> Quizzes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button">
                    <i class="bi bi-people"></i> Students
                </button>
            </li>
        </ul>

        <div class="tab-content" id="courseTabContent">
            <!-- Course Details Tab -->
            <div class="tab-pane fade show active" id="details" role="tabpanel">
                <div class="section-card">
                    <h5 class="section-title"><i class="bi bi-pencil"></i> Edit Course Information</h5>
                    <form method="POST">
                        <input type="hidden" name="update_course" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Course Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Price (PHP)</label>
                                <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $course['price']; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-control">
                                    <option value="Programming" <?php echo $course['category'] == 'Programming' ? 'selected' : ''; ?>>Programming</option>
                                    <option value="Design" <?php echo $course['category'] == 'Design' ? 'selected' : ''; ?>>Design</option>
                                    <option value="Business" <?php echo $course['category'] == 'Business' ? 'selected' : ''; ?>>Business</option>
                                    <option value="Marketing" <?php echo $course['category'] == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="draft" <?php echo $course['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo $course['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="archived" <?php echo $course['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Passing Score (%)</label>
                            <input type="number" name="passingScore" class="form-control" min="0" max="100" value="<?php echo $course['passingScore']; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Lessons Tab -->
            <div class="tab-pane fade" id="lessons" role="tabpanel">
                <div class="section-card">
                    <h5 class="section-title"><i class="bi bi-upload"></i> Upload New Lesson</h5>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_lesson" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lesson Title</label>
                                <input type="text" name="lesson_title" class="form-control" placeholder="e.g., Introduction to PHP" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Upload PDF</label>
                                <input type="file" name="lesson_file" class="form-control" accept=".pdf" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-cloud-upload"></i> Upload Lesson
                        </button>
                    </form>
                </div>

                <div class="section-card">
                    <h5 class="section-title"><i class="bi bi-list"></i> All Lessons (<?php echo count($lessons); ?>)</h5>
                    <?php if (count($lessons) > 0): ?>
                        <?php foreach ($lessons as $lesson): ?>
                            <div class="lesson-item">
                                <div>
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                    <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                    <small class="text-muted ms-2">Uploaded: <?php echo date('M d, Y', strtotime($lesson['uploadedAt'])); ?></small>
                                </div>
                                <div>
                                   <!-- Change this line in the lessons tab -->
<a href="../<?php echo $lesson['filename']; ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-action">
    <i class="bi bi-eye"></i> View
</a>
                                    <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteLesson(<?php echo $lesson['lessonID']; ?>)">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No lessons uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quizzes Tab -->
            <div class="tab-pane fade" id="quizzes" role="tabpanel">
                <div class="section-card">
                    <h5 class="section-title"><i class="bi bi-plus-circle"></i> Create New Quiz</h5>
                    <a href="create_quiz.php?course_id=<?php echo $courseID; ?>" class="btn btn-success">
                        <i class="bi bi-plus-lg"></i> Create Quiz
                    </a>
                </div>

                <div class="section-card">
                    <h5 class="section-title"><i class="bi bi-list"></i> All Quizzes (<?php echo count($quizzes); ?>)</h5>
                    <?php if (count($quizzes) > 0): ?>
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="lesson-item">
                                <div>
                                    <i class="bi bi-patch-question text-primary"></i>
                                    <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                                    <small class="text-muted ms-2">Passing: <?php echo $quiz['passingScore']; ?>%</small>
                                </div>
                                <div>
                                    <a href="edit_quiz.php?id=<?php echo $quiz['quizID']; ?>" class="btn btn-sm btn-outline-primary btn-action">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="quiz_results.php?id=<?php echo $quiz['quizID']; ?>" class="btn btn-sm btn-outline-info btn-action">
                                        <i class="bi bi-graph-up"></i> Results
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No quizzes created yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Students Tab -->
            <div class="tab-pane fade" id="students" role="tabpanel">
                <div class="section-card">
                    <h5 class="section-title"><i class="bi bi-people"></i> Enrolled Students (<?php echo count($students); ?>)</h5>
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $student): ?>
                            <div class="student-row">
                                <div>
                                    <strong><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                </div>
                                <div class="text-center">
                                    <div class="progress-bar-custom">
                                        <div class="fill" style="width: <?php echo $student['progressPercentage']; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo round($student['progressPercentage']); ?>% Complete</small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $student['status'] == 'completed' ? 'success' : 'primary'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No students enrolled yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php if (isset($success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo addslashes($success); ?>',
        timer: 3000,
        showConfirmButton: true
    });
    <?php endif; ?>

    <?php if (isset($error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Upload Failed!',
        html: '<div style="text-align:left;"><?php echo addslashes($error); ?></div>',
        showConfirmButton: true
    });
    <?php endif; ?>

    function deleteLesson(lessonID) {
        Swal.fire({
            title: 'Delete Lesson?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_lesson.php?id=' + lessonID + '&course_id=<?php echo $courseID; ?>';
            }
        });
    }
</script>
</body>
</html>