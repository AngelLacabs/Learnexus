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
                    $stmt->execute([$courseID, $lessonTitle, 'uploads/lessons/' . $newFilename]);
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
<title>Courses - Learnexus</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.add-course-card {
    background: white;
    border: 2px dashed #ccc;
    border-radius: 12px;
    padding: 60px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.add-course-card:hover {
    border-color: #1a73e8;
    background: #f8f9fa;
}
.add-course-card i {
    font-size: 48px;
    color: #1a73e8;
    margin-bottom: 10px;
}
</style>
</head>
<body>
<div class="container mt-5">
    <h1>Manage Your Courses</h1>
    <p>Click the "+" below to create a new course.</p>

    <div id="course-list" class="mb-4">
        <?php foreach ($courses as $course): ?>
            <div class="card mb-3 p-3" id="course-<?= $course['courseID'] ?>">
                <div class="d-flex justify-content-between">
                    <h5><?= htmlspecialchars($course['title']) ?></h5>
                    <div>
                        <i class="bi bi-pencil" onclick="window.location.href='edit_course.php?id=<?= $course['courseID'] ?>'"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add New Course Card -->
    <div class="add-course-card" onclick="showCreateModal()">
        <i class="bi bi-plus-circle"></i>
        <h5>Create New Course</h5>
        <p>Click to add a new course</p>
    </div>
</div>

<script>
function showCreateModal() {
    Swal.fire({
        title: 'Create New Course',
        html: `
            <form id="createCourseForm" enctype="multipart/form-data" style="text-align:left;">
                <div class="mb-3">
                    <label>Course Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="Programming">Programming</option>
                        <option value="Design">Design</option>
                        <option value="Business">Business</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Price (₱)</label>
                    <input type="number" name="price" class="form-control" step="0.01" value="0">
                </div>
                <div class="mb-3">
                    <label>Lesson File (PDF only)</label>
                    <input type="file" name="lesson_file" class="form-control" accept=".pdf">
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Create Course',
        preConfirm: () => {
            const form = document.getElementById('createCourseForm');
            if (!form.title.value) Swal.showValidationMessage('Please enter a course title');
            return form;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('createCourseForm');
            const data = new FormData(form);
            data.append('action', 'create_course');

            fetch('', { method: 'POST', body: data })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Success', 'Course created!', 'success');

                        // Append new course to course list
                        const courseList = document.getElementById('course-list');
                        const div = document.createElement('div');
                        div.className = 'card mb-3 p-3';
                        div.id = 'course-' + res.course.courseID;
                        div.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <h5>${res.course.title}</h5>
                                <div>
                                    <i class="bi bi-pencil" onclick="window.location.href='edit_course.php?id=${res.course.courseID}'"></i>
                                </div>
                            </div>
                        `;
                        courseList.prepend(div);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Something went wrong', 'error'));
        }
    });
}
</script>
</body>
</html>
