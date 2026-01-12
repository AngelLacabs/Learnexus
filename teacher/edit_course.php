<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$courseID = $_GET['id'] ?? 0;

// Fetch instructor avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

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
    // Delete lesson files
    $stmt = $conn->prepare("SELECT filename FROM lessons WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $lessonFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($lessonFiles as $file) {
        $filePath = '../' . $file;
        if (file_exists($filePath)) unlink($filePath);
    }

    $stmt = $conn->prepare("DELETE FROM lessons WHERE courseID = ?");
    $stmt->execute([$courseID]);

    // Delete quiz and quiz results
    $stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
    $stmt->execute([$courseID]);
    $quizID = $stmt->fetchColumn();
    if ($quizID) {
        $stmt = $conn->prepare("DELETE FROM quiz_results WHERE quizID = ?");
        $stmt->execute([$quizID]);

        $stmt = $conn->prepare("DELETE FROM quizzes WHERE quizID = ?");
        $stmt->execute([$quizID]);
    }

    $stmt = $conn->prepare("DELETE FROM courses WHERE courseID = ? AND teacherID = ?");
    $stmt->execute([$courseID, $teacherID]);

    header('Location: courses.php');
    exit();
}

// Handle lesson deletion
if (isset($_POST['delete_lesson'])) {
    $lessonID = intval($_POST['lesson_id']);

    $stmt = $conn->prepare("SELECT filename FROM lessons WHERE lessonID = ? AND courseID = ?");
    $stmt->execute([$lessonID, $courseID]);
    $lesson = $stmt->fetch();

    if ($lesson) {
        $filePath = '../' . $lesson['filename'];
        if (file_exists($filePath)) unlink($filePath);

        $stmt = $conn->prepare("DELETE FROM lessons WHERE lessonID = ?");
        $stmt->execute([$lessonID]);
    }

    header('Location: edit_course.php?id=' . $courseID);
    exit();
}

// Handle course update and lesson upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_course']) && !isset($_POST['delete_lesson']) && !isset($_POST['create_quiz']) && !isset($_POST['delete_quiz'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    $status = trim($_POST['status']);

    // Update course with status
    $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, price = ?, category = ?, status = ? WHERE courseID = ?");
    $stmt->execute([$title, $description, $price, $category, $status, $courseID]);

    // Upload lesson if provided
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
                $stmt->execute([$courseID, $lessonTitle, '../uploads/lessons/' . $newFilename]);
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

// Handle Quiz Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $quizTitle = trim($_POST['quiz_title']);
    $quizDesc = trim($_POST['quiz_description']);

    $stmt = $conn->prepare("INSERT INTO quizzes (courseID, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$courseID, $quizTitle, $quizDesc]);

    header('Location: edit_course.php?id=' . $courseID);
    exit();
}

// Handle Quiz Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    $quizIDToDelete = intval($_POST['delete_quiz_id']);

    $stmt = $conn->prepare("DELETE FROM quiz_results WHERE quizID = ?");
    $stmt->execute([$quizIDToDelete]);

    $stmt = $conn->prepare("DELETE FROM quizzes WHERE quizID = ? AND courseID = ?");
    $stmt->execute([$quizIDToDelete, $courseID]);

    header('Location: edit_course.php?id=' . $courseID);
    exit();
}

// Fetch existing lessons
$stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY uploadedAt DESC");
$stmt->execute([$courseID]);
$lessons = $stmt->fetchAll();

// Fetch existing quiz
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Course - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .top-nav { background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%); padding: 15px 40px; }
        .brand { font-size: 20px; font-weight: 700; color: #1a73e8; }
        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="brand text-decoration-none">LEARNEXUS</a>
        <div>
            <a href="dashboard.php" class="me-3 text-decoration-none">Dashboard</a>
            <a href="courses.php" class="me-3 text-decoration-none text-primary fw-bold">Courses</a>
            <a href="quizzes.php" class="me-3 text-decoration-none">Quizzes</a>
            <a href="enrollees.php" class="me-3 text-decoration-none">Enrollees</a>
        </div>
        <a href="settings.php" class="d-flex align-items-center gap-2 text-decoration-none text-dark">
            <span class="fw-semibold"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
            <div class="user-avatar">
                <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                    <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="container mt-5">
        <h2>Edit Course</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Course Edit & Lesson Upload -->
        <form method="POST" enctype="multipart/form-data" class="mt-4">
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

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" required>
                    <option value="draft" <?php echo (isset($course['status']) && $course['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo (isset($course['status']) && $course['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                </select>
                <small class="form-text text-muted">Set to "Published" to make this course visible to students.</small>
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

        <form method="POST" class="mt-3" onsubmit="return confirm('Are you sure you want to delete this course?');">
            <button type="submit" name="delete_course" class="btn btn-danger">Delete Course</button>
        </form>

        <hr>
        <h4>Existing Lessons</h4>
        <?php if (count($lessons) > 0): ?>
            <ul class="list-group">
                <?php foreach ($lessons as $lesson): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($lesson['title']); ?>
                        <div>
                            <a href="../<?php echo $lesson['filename']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View / Download</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this lesson?');">
                                <input type="hidden" name="lesson_id" value="<?php echo $lesson['lessonID']; ?>">
                                <button type="submit" name="delete_lesson" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No lessons uploaded yet.</p>
        <?php endif; ?>

        <hr>
        <h4>Course Quiz</h4>
        <?php if ($quiz): ?>
            <div class="card mb-3 p-3">
                <h5><?php echo htmlspecialchars($quiz['title']); ?></h5>
                <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                <a href="edit_quiz.php?id=<?php echo $quiz['quizID']; ?>" class="btn btn-sm btn-primary">Edit Quiz</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quiz?');">
                    <input type="hidden" name="delete_quiz_id" value="<?php echo $quiz['quizID']; ?>">
                    <button type="submit" name="delete_quiz" class="btn btn-sm btn-danger">Delete Quiz</button>
                </form>
            </div>
        <?php else: ?>
            <form method="POST" class="mb-3">
                <div class="mb-3">
                    <label class="form-label">Quiz Title</label>
                    <input type="text" name="quiz_title" class="form-control" required placeholder="Enter quiz title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Quiz Description</label>
                    <textarea name="quiz_description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                </div>
                <button type="submit" name="create_quiz" class="btn btn-success">Create Quiz</button>
            </form>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
