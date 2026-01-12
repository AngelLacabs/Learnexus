<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Handle AJAX course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $status = 'draft';

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Course title is required']);
        exit();
    }

    try {
        // Insert course
        $stmt = $conn->prepare("
            INSERT INTO courses (teacherID, title, description, price, category, status, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$teacherID, $title, $description, $price, $category, $status]);
        $courseID = $conn->lastInsertId();

        // Handle PDF lesson upload
if (!empty($_FILES['lesson_file']['name'])) {
    $file = $_FILES['lesson_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext === 'pdf' && $file['error'] === 0) {
        $uploadDir = '../uploads/lessons/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $newFilename = uniqid() . "_" . basename($file['name']);
        $destination = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $lessonTitle = $title . " - Intro Lesson";
            $stmt = $conn->prepare("INSERT INTO lessons (courseID, title, filename) VALUES (?, ?, ?)");
            
            // FIX: Save the CORRECT relative path
            $stmt->execute([$courseID, $lessonTitle, '../uploads/lessons/' . $newFilename]);
            
            // Alternatively, if you want to save the actual path used:
            // $stmt->execute([$courseID, $lessonTitle, $destination]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF allowed']);
        exit();
    }
}

        // Return newly created course info
        echo json_encode(['success' => true, 'course' => [
            'courseID' => $courseID,
            'title' => $title,
            'description' => $description,
            'category' => $category
        ]]);
        exit();

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Get existing courses
$stmt = $conn->prepare("SELECT * FROM courses WHERE teacherID = ? ORDER BY createdAt DESC");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Course - Learnexus</title>
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
    margin-bottom: 30px;
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
    margin: 0 auto;
    padding: 0 40px;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 16px;
    margin-bottom: 30px;
}

.page-header h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 700;
}

.page-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
}

.add-course-card {
    background: white;
    border: 2px dashed #ccc;
    border-radius: 12px;
    padding: 60px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 30px;
}

.add-course-card:hover {
    border-color: #1a73e8;
    background: #f8f9fa;
    transform: translateY(-2px);
}

.add-course-card i {
    font-size: 48px;
    color: #1a73e8;
    margin-bottom: 10px;
}

.course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.course-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.course-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.course-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.course-card h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.course-card-actions {
    display: flex;
    gap: 10px;
}

.course-card-actions i {
    cursor: pointer;
    font-size: 18px;
    color: #666;
    transition: color 0.2s;
}

.course-card-actions i:hover {
    color: #1a73e8;
}

.course-meta {
    color: #666;
    font-size: 14px;
    margin-top: 10px;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 10px;
}

.status-badge.draft {
    background: #fff3e0;
    color: #f57c00;
}

.status-badge.published {
    background: #e8f5e9;
    color: #43a047;
}

.btn-back {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-back:hover {
    background: #667eea;
    color: white;
}
</style>
</head>
<body>

<!-- Top Navigation -->
<div class="top-nav">
    <a href="dashboard.php" class="brand">LEARNEXUS</a>
    <div>
        <a href="dashboard.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="container-main">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Manage Your Courses</h1>
        <p>Create and organize your educational content</p>
    </div>

    <!-- Add New Course Card -->
    <div class="add-course-card" onclick="showCreateModal()">
        <i class="bi bi-plus-circle"></i>
        <h5>Create New Course</h5>
        <p class="text-muted mb-0">Click to add a new course</p>
    </div>

    <!-- Existing Courses -->
    <?php if (count($courses) > 0): ?>
        <h4 class="mb-3">Your Courses (<?php echo count($courses); ?>)</h4>
        <div class="course-grid" id="course-list">
            <?php foreach ($courses as $course): ?>
                <div class="course-card" id="course-<?= $course['courseID'] ?>">
                    <div class="course-card-header">
                        <div>
                            <h5><?= htmlspecialchars($course['title']) ?></h5>
                            <span class="status-badge <?= $course['status'] ?>">
                                <?= ucfirst($course['status']) ?>
                            </span>
                        </div>
                        <div class="course-card-actions">
                            <i class="bi bi-pencil" 
                               onclick="window.location.href='manage_course.php?id=<?= $course['courseID'] ?>'"
                               title="Edit Course"></i>
                            <i class="bi bi-trash" 
                               onclick="deleteCourse(<?= $course['courseID'] ?>)"
                               title="Delete Course"></i>
                        </div>
                    </div>
                    <div class="course-meta">
                        <i class="bi bi-tag"></i> <?= htmlspecialchars($course['category']) ?> | 
                        <i class="bi bi-currency-dollar"></i> ₱<?= number_format($course['price'], 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle"></i>
            You haven't created any courses yet. Click the "+" card above to get started!
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showCreateModal() {
    Swal.fire({
        title: 'Create New Course',
        html: `
            <form id="createCourseForm" enctype="multipart/form-data" style="text-align:left;">
                <div class="mb-3">
                    <label class="form-label"><strong>Course Title *</strong></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Introduction to Programming" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Description</strong></label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of your course"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Category</strong></label>
                    <select name="category" class="form-control">
                        <option value="Programming">Programming</option>
                        <option value="Design">Design</option>
                        <option value="Business">Business</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Price (₱)</strong></label>
                    <input type="number" name="price" class="form-control" step="0.01" value="0" min="0">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>First Lesson File (PDF only)</strong></label>
                    <input type="file" name="lesson_file" class="form-control" accept=".pdf">
                    <small class="text-muted">Optional: Upload the first lesson PDF</small>
                </div>
            </form>
        `,
        width: 600,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-circle"></i> Create Course',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#667eea',
        preConfirm: () => {
            const form = document.getElementById('createCourseForm');
            if (!form.title.value.trim()) {
                Swal.showValidationMessage('Please enter a course title');
                return false;
            }
            return form;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('createCourseForm');
            const data = new FormData(form);
            data.append('action', 'create_course');

            Swal.fire({
                title: 'Creating Course...',
                html: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('', { method: 'POST', body: data })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Course Created!',
                            text: 'Your course has been created successfully',
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            // Reload page to show new course
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message,
                            confirmButtonColor: '#667eea'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Something went wrong. Please try again.',
                        confirmButtonColor: '#667eea'
                    });
                });
        }
    });
}

function deleteCourse(courseID) {
    Swal.fire({
        title: 'Delete Course?',
        text: "This will permanently delete the course and all its content. This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete_course.php?id=' + courseID;
        }
    });
}
</script>
</body>
</html>