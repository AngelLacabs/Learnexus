<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get all enrolled courses
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
        p.transactionReference
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    LEFT JOIN payments p ON e.paymentID = p.paymentID
    WHERE e.userID = ? AND e.status = 'active'
    ORDER BY e.enrolledAt DESC
");
$stmt->execute([$userID]);
$enrolledCourses = $stmt->fetchAll();
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
        
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
        
        .progress-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
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
            <a href="ai_tutor.php" class="nav-link">AI Tutor</a>
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
            <?php foreach ($enrolledCourses as $course): ?>
                <div class="course-card">
                    <div class="course-card-body">
                        <div class="course-header">
                            <div>
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
                            <span class="badge badge-primary">In Progress</span>
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
                        
                        <div class="mt-3">
                            <a href="course_learn.php?id=<?php echo $course['courseID']; ?>" class="btn-continue">
                                <?php echo $course['progressPercentage'] > 0 ? 'Continue Learning' : 'Start Course'; ?> →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
</body>
</html>