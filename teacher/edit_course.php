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

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category']);
    
    $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, price = ?, category = ? WHERE courseID = ?");
    $stmt->execute([$title, $description, $price, $category, $courseID]);
    
    header('Location: courses.php');
    exit();
}
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
        <form method="POST" class="mt-4">
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
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="courses.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>