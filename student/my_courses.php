<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get all enrolled courses (including completed ones)
$stmt = $conn->prepare("
    SELECT 
        c.*,
        e.enrollmentID,
        e.progressPercentage,
        e.enrolledAt,
        e.completedAt,
        e.status as enrollmentStatus,
        CONCAT(u.firstName, ' ', u.lastName) as instructorName,
        u.avatar as instructorAvatar,
        p.amount as paidAmount,
        p.transactionReference,
        CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END as isCompleted

    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    LEFT JOIN payments p ON e.paymentID = p.paymentID
    WHERE e.userID = ? AND e.status IN ('active', 'completed')
    ORDER BY 
        CASE WHEN e.progressPercentage >= 100 THEN 1 ELSE 0 END ASC,
        e.enrolledAt DESC
");
$stmt->execute([$userID]);
$enrolledCourses = $stmt->fetchAll();

// Separate into active and completed
$activeCourses = [];
$completedCourses = [];
foreach ($enrolledCourses as $course) {
    if ($course['enrollmentStatus'] === 'completed') {
    $completedCourses[] = $course;
} else {
    $activeCourses[] = $course;
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Learnexus</title>
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
            text-decoration: none;
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
        
        .nav-link.active {
            color: #1a73e8;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .section-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        
        .section-header .badge {
            background: #1e88e5;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
        }
        
        .section-header.completed h2 {
            color: #43a047;
        }
        
        .section-header.completed .badge {
            background: #43a047;
        }
        
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .course-card.completed {
            border: 2px solid #e8f5e9;
        }
        
        .course-card-body {
            padding: 24px;
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .course-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .course-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .instructor-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .instructor-avatar {
            width: 24px;
            height: 24px;
            background: #e0e0e0;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .instructor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .progress-section {
            margin: 20px 0;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #1e88e5 0%, #42a5f5 100%);
            border-radius: 4px;
        }
        
        .progress-bar.completed {
            background: linear-gradient(90deg, #43a047 0%, #66bb6a 100%);
        }
        
        .progress-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .progress-text.completed {
            color: #43a047;
            font-weight: 600;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }
        
        .btn-continue {
            background: #1e88e5;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        
        .btn-continue:hover {
            background: #1976d2;
            color: white;
        }
        
        .btn-review {
            background: #43a047;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        
        .btn-review:hover {
            background: #388e3c;
            color: white;
        }
        
        .btn-delete {
            background: transparent;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }
        
        .btn-dropdown {
            background: transparent;
            border: 1px solid #e0e0e0;
            color: #666;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-dropdown:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }
        
        .dropdown-menu {
            min-width: 150px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }
        
        .completion-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <a href="dashboard.php" class="brand">LEARNEXUS</a>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="course_catalog.php" class="nav-link">Course Catalog</a>
            <a href="my_courses.php" class="nav-link active">My Courses</a>
        </div>
        
        <div class="user-section">
            <i class="bi bi-bell" style="font-size: 22px; color: #666; cursor: pointer;"></i>
            <a href="settings.php" style="text-decoration: none;">
                <span style="font-weight: 600; color: #333; cursor: pointer;">
                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                </span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-main">
        <div class="page-header">
            <h1>My Courses</h1>
            <p class="text-muted">Continue learning where you left off</p>
        </div>

        <?php if (count($enrolledCourses) > 0): ?>
            
            <!-- Active Courses Section -->
            <?php if (count($activeCourses) > 0): ?>
                <div class="section-header">
                    <h2>In Progress</h2>
                    <span class="badge"><?php echo count($activeCourses); ?></span>
                </div>
                
                <?php foreach ($activeCourses as $course): ?>
                    <div class="course-card" id="course-<?php echo $course['enrollmentID']; ?>">
                        <div class="course-card-body">
                            <div class="course-header">
                                <div style="flex: 1;">
                                    <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                    <div class="course-meta">
                                        <div class="instructor-info">
                                            <div class="instructor-avatar">
                                                <?php if (!empty($course['instructorAvatar']) && file_exists($course['instructorAvatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($course['instructorAvatar']); ?>" alt="Instructor">
                                                <?php endif; ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($course['instructorName']); ?></span>
                                        </div>
                                        <span><i class="bi bi-calendar3"></i> Enrolled: <?php echo date('M d, Y', strtotime($course['enrolledAt'])); ?></span>
                                        <?php if ($course['paidAmount'] > 0): ?>
                                            <span><i class="bi bi-receipt"></i> ₱<?php echo number_format($course['paidAmount'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">FREE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn-dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="course_content.php?id=<?php echo $course['courseID']; ?>">
                                                <i class="bi bi-play-circle"></i> Continue Learning
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $course['enrollmentID']; ?>, '<?php echo htmlspecialchars(addslashes($course['title'])); ?>'); return false;">
                                                <i class="bi bi-trash"></i> Unenroll from Course
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if (!empty($course['description'])): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($course['description'], 0, 150)); ?><?php echo strlen($course['description']) > 150 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            
                            <div class="progress-section">
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $course['progressPercentage']; ?>%" 
                                         aria-valuenow="<?php echo $course['progressPercentage']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="progress-text">
                                    <?php echo number_format($course['progressPercentage'], 0); ?>% Complete
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <a href="course_learn.php?id=<?php echo $course['courseID']; ?>" class="btn-continue">
                                    <?php echo $course['progressPercentage'] > 0 ? 'Continue Learning' : 'Start Course'; ?> →
                                </a>
                                <button class="btn-delete" onclick="confirmDelete(<?php echo $course['enrollmentID']; ?>, '<?php echo htmlspecialchars(addslashes($course['title'])); ?>')">
                                    <i class="bi bi-trash"></i> Unenroll
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Completed Courses Section -->
            <?php if (count($completedCourses) > 0): ?>
                <div class="section-header completed">
                    <h2><i class="bi bi-trophy-fill"></i> Completed Courses</h2>
                    <span class="badge"><?php echo count($completedCourses); ?></span>
                </div>
                
                <?php foreach ($completedCourses as $course): ?>
                    <div class="course-card completed" id="course-<?php echo $course['enrollmentID']; ?>">
                        <div class="course-card-body">
                            <div class="course-header">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                        <div class="course-title" style="margin-bottom: 0;"><?php echo htmlspecialchars($course['title']); ?></div>
                                        <span class="completion-badge">
                                            <i class="bi bi-check-circle-fill"></i> Completed
                                        </span>
                                    </div>
                                    <div class="course-meta">
                                        <div class="instructor-info">
                                            <div class="instructor-avatar">
                                                <?php if (!empty($course['instructorAvatar']) && file_exists($course['instructorAvatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($course['instructorAvatar']); ?>" alt="Instructor">
                                                <?php endif; ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($course['instructorName']); ?></span>
                                        </div>
                                        <span><i class="bi bi-calendar3"></i> Enrolled: <?php echo date('M d, Y', strtotime($course['enrolledAt'])); ?></span>
                                        <?php if ($course['completedAt']): ?>
                                            <span><i class="bi bi-trophy"></i> Completed: <?php echo date('M d, Y', strtotime($course['completedAt'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn-dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="course_content.php?id=<?php echo $course['courseID']; ?>">
                                                <i class="bi bi-eye"></i> Review Course
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $course['enrollmentID']; ?>, '<?php echo htmlspecialchars(addslashes($course['title'])); ?>'); return false;">
                                                <i class="bi bi-trash"></i> Remove from List
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if (!empty($course['description'])): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($course['description'], 0, 150)); ?><?php echo strlen($course['description']) > 150 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            
                            <div class="progress-section">
                                <div class="progress">
                                    <div class="progress-bar completed" role="progressbar" 
                                         style="width: 100%" 
                                         aria-valuenow="100" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="progress-text completed">
                                    <i class="bi bi-check-circle-fill"></i> 100% Complete
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <a href="course_content.php?id=<?php echo $course['courseID']; ?>" class="btn-review">
                                    <i class="bi bi-eye"></i> Review Course
                                </a>
                                <button class="btn-delete" onclick="confirmDelete(<?php echo $course['enrollmentID']; ?>, '<?php echo htmlspecialchars(addslashes($course['title'])); ?>')">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-journal-x"></i>
                <h3>No Courses Yet</h3>
                <p>You haven't enrolled in any courses. Start learning today!</p>
                <a href="course_catalog.php" class="btn-continue">Browse Course Catalog</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(enrollmentID, courseTitle) {
            Swal.fire({
                title: 'Unenroll from Course?',
                html: `Are you sure you want to unenroll from <strong>${courseTitle}</strong>?<br><br><span style="color: #666; font-size: 14px;">Your progress will be lost and you'll need to re-enroll to access this course again.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Unenroll',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteCourse(enrollmentID);
                }
            });
        }

        function deleteCourse(enrollmentID) {
            // Show loading
            Swal.fire({
                title: 'Processing...',
                text: 'Removing course enrollment',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send delete request
            fetch('unenroll_course.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `enrollment_id=${enrollmentID}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Unenrolled Successfully',
                        text: 'You have been removed from this course.',
                        confirmButtonColor: '#1e88e5',
                        timer: 2000
                    }).then(() => {
                        // Remove card from DOM with animation
                        const card = document.getElementById(`course-${enrollmentID}`);
                        if (card) {
                            card.style.transition = 'all 0.3s';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(-20px)';
                            setTimeout(() => {
                                card.remove();
                                
                                // Check if no courses left
                                const remainingCourses = document.querySelectorAll('.course-card');
                                if (remainingCourses.length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to unenroll from course',
                        confirmButtonColor: '#1e88e5'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#1e88e5'
                });
            });
        }
    </script>
</body>
</html>