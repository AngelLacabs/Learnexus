<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;
$teacherID = $_SESSION['user_id'];

// Get course
$stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ? AND teacherID = ?");
$stmt->execute([$courseID, $teacherID]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Handle course deletion
if (isset($_POST['delete_course'])) {
    // Delete lesson files from server
    $stmt = $conn->prepare("SELECT filename FROM lessons WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $lessonFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($lessonFiles as $file) {
        $filePath = '../' . $file;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Delete lessons from DB
    $stmt = $conn->prepare("DELETE FROM lessons WHERE courseID = ?");
    $stmt->execute([$courseID]);

    // Delete course
    $stmt = $conn->prepare("DELETE FROM courses WHERE courseID = ? AND teacherID = ?");
    $stmt->execute([$courseID, $teacherID]);

    header('Location: courses.php');
    exit();
}

// Handle updates and lesson upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_course'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    
    $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, price = ?, category = ? WHERE courseID = ?");
    $stmt->execute([$title, $description, $price, $category, $courseID]);

    // Handle lesson file upload (PDF only)
    if (!empty($_FILES['lesson_file']['name'])) {
        $lessonTitle = trim($_POST['lesson_title']) ?: "Untitled Lesson";
        $file = $_FILES['lesson_file'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext === 'pdf' && $file['error'] === 0) {
            $uploadDir = '../uploads/lessons/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $newFilename = uniqid() . "_" . basename($file['name']);
            $destination = $uploadDir . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $stmt = $conn->prepare("INSERT INTO lessons (courseID, title, filename) VALUES (?, ?, ?)");
                $stmt->execute([$courseID, $lessonTitle, 'uploads/lessons/' . $newFilename]);
            } else {
                $error = "Failed to upload the file.";
            }
        } else {
            $error = "Invalid file type. Only PDF files are allowed.";
        }
    }

    header('Location: edit_course.php?id=' . $courseID);
    exit();
}

// Get existing lessons
$stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
$stmt->execute([$courseID]);
$lessons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Course - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Course</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Edit Course & Upload Lesson Form -->
    <form method="POST" enctype="multipart/form-data" class="mt-4">
        <!-- Course fields -->
        <div class="mb-3">
            <label class="form-label">Course Title</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
                <option value="Programming" <?php echo $course['category'] == 'Programming' ? 'selected' : ''; ?>>Programming</option>
                <option value="Design" <?php echo $course['category'] == 'Design' ? 'selected' : ''; ?>>Design</option>
                <option value="Business" <?php echo $course['category'] == 'Business' ? 'selected' : ''; ?>>Business</option>
                <option value="Marketing" <?php echo $course['category'] == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Price (₱)</label>
            <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $course['price']; ?>">
        </div>

        <hr>
        <h4>Upload Lesson (PDF Only)</h4>
        <div class="mb-3">
            <label class="form-label">Lesson Title</label>
            <input type="text" name="lesson_title" class="form-control" placeholder="Lesson Title">
        </div>

        <div class="mb-3">
            <label class="form-label">Lesson File (PDF)</label>
            <input type="file" name="lesson_file" class="form-control" accept=".pdf">
        </div>

        <button type="submit" class="btn btn-primary">Save Changes / Upload Lesson</button>
        <a href="courses.php" class="btn btn-secondary">Cancel</a>
    </form>

    <!-- Delete Course Button -->
    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this course? This action cannot be undone.');">
        <button type="submit" name="delete_course" class="btn btn-danger mt-3">Delete Course</button>
    </form>

    <hr>
    <h4>Existing Lessons</h4>
    <?php if (count($lessons) > 0): ?>
        <ul class="list-group">
            <?php foreach ($lessons as $lesson): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($lesson['title']); ?>
                    <a href="../<?php echo $lesson['filename']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View / Download</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No lessons uploaded yet.</p>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
