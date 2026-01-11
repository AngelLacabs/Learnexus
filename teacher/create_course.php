<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $status = 'draft'; // Default status
    
    if (!empty($title)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO courses (teacherID, title, description, price, category, status, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$teacherID, $title, $description, $price, $category, $status]);
            $courseID = $conn->lastInsertId();
            
            $_SESSION['success'] = 'Course created successfully!';
            header('Location: edit_course.php?id=' . $courseID);
            exit();
        } catch (PDOException $e) {
            $error = 'Failed to create course: ' . $e->getMessage();
        }
    } else {
        $error = 'Course title is required';
    }
}

// Get existing courses for display
$stmt = $conn->prepare("
    SELECT * FROM courses 
    WHERE teacherID = ? 
    ORDER BY createdAt DESC
");
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
        }
        
        .brand {
            font-size: 20px;
            font-weight: 700;
            color: #1a73e8;
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
        }
        
        .nav-link {
            color: #666;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-link:hover, .nav-link.active {
            color: #1a73e8;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .container-main {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .course-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .edit-icon {
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }
        
        .course-image-box {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            margin-bottom: 15px;
            position: relative;
        }
        
        .status-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1e88e5;
        }
        
        .course-info {
            padding: 0 10px;
        }
        
        .course-title-input {
            font-size: 18px;
            font-weight: 600;
            border: none;
            border-bottom: 2px solid #e0e0e0;
            padding: 8px 0;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .course-title-input:focus {
            outline: none;
            border-bottom-color: #1a73e8;
        }
        
        .course-meta {
            font-size: 13px;
            color: #666;
        }
        
        .action-icons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 10px;
        }
        
        .action-icon {
            font-size: 20px;
            color: #666;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .action-icon:hover {
            color: #1a73e8;
        }
        
        .action-icon.delete:hover {
            color: #dc3545;
        }
        
        .add-course-card {
            background: white;
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 40px;
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
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="brand">LEARNEXUS</div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="courses.php" class="nav-link active">Courses</a>
            <a href="quizzes.php" class="nav-link">Quizzes</a>
            <a href="enrollees.php" class="nav-link">Enrollees</a>
        </div>
        
        <div class="user-section">
            <i class="bi bi-bell" style="font-size: 22px; color: #666; cursor: pointer;"></i>
            <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-main">
        <div class="page-header">
            <h1>Create Another Course</h1>
            <p>Manage and spread your knowledge</p>
        </div>

        <?php foreach ($courses as $course): ?>
            <div class="course-item">
                <div class="course-header">
                    <h5 style="margin: 0; color: #1a73e8;">
                        <i class="bi bi-pencil"></i> <?php echo htmlspecialchars($course['title']); ?>
                    </h5>
                </div>
                
                <div class="course-image-box">
                    <span class="status-badge">• Ongoing</span>
                    // photo
                </div>
                
                <div class="course-info">
                    <div style="font-size: 16px; font-weight: 600; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </div>
                    <div class="course-meta">
                        <?php echo htmlspecialchars($course['description']); ?>
                    </div>
                    
                    <div style="height: 60px;"></div>
                    
                    <div class="action-icons">
                        <i class="bi bi-pencil action-icon" onclick="window.location.href='edit_course.php?id=<?php echo $course['courseID']; ?>'" title="Edit"></i>
                        <i class="bi bi-share action-icon" title="Share"></i>
                        <i class="bi bi-printer action-icon" title="Print"></i>
                        <i class="bi bi-files action-icon" title="Duplicate"></i>
                        <i class="bi bi-trash action-icon delete" onclick="deleteCourse(<?php echo $course['courseID']; ?>)" title="Delete"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add New Course Card -->
        <div class="add-course-card" onclick="showCreateModal()">
            <i class="bi bi-plus-circle"></i>
            <h5>Create New Course</h5>
            <p style="color: #666; margin: 0;">Click to add a new course</p>
        </div>
    </div>

    <script>
        function showCreateModal() {
            Swal.fire({
                title: 'Create New Course',
                html: `
                    <form id="createCourseForm" method="POST" style="text-align: left;">
                        <div class="mb-3">
                            <label class="form-label">Course Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="Programming">Programming</option>
                                <option value="Design">Design</option>
                                <option value="Business">Business</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" name="price" class="form-control" step="0.01" value="0">
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Create Course',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const form = document.getElementById('createCourseForm');
                    if (!form.title.value) {
                        Swal.showValidationMessage('Please enter a course title');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('createCourseForm').submit();
                }
            });
        }

        function deleteCourse(courseID) {
            Swal.fire({
                title: 'Delete Course?',
                text: 'This action cannot be undone. All course data will be permanently deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete_course.php?id=' + courseID;
                }
            });
        }
    </script>
</body>
</html>