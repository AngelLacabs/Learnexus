<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get search query
$searchQuery = $_GET['search'] ?? '';

// Get all published courses that the student hasn't enrolled in
$sql = "
    SELECT c.*, 
           CONCAT(u.firstName, ' ', u.lastName) as instructorName,
           u.avatar as instructorAvatar,
           (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as enrollmentCount,
           (SELECT COUNT(*) FROM lessons WHERE courseID = c.courseID) as lessonCount
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.status = 'published'
    AND c.courseID NOT IN (
        SELECT courseID FROM enrollments WHERE userID = ?
    )
";

if (!empty($searchQuery)) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)";
    $stmt = $conn->prepare($sql);
    $searchTerm = "%$searchQuery%";
    $stmt->execute([$userID, $searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userID]);
}

$courses = $stmt->fetchAll();

// Group courses by category
$coursesByCategory = [];
foreach ($courses as $course) {
    $category = $course['category'] ?? 'Other';
    $coursesByCategory[$category][] = $course;
}

// Get total published courses count for debugging
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE status = 'published'");
$stmt->execute();
$totalPublished = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Catalog - Learnexus</title>
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
            cursor: pointer;
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
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .search-box {
            max-width: 600px;
            margin: 0 auto 40px;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .search-box button {
            padding: 12px 30px;
            background: #1e88e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .category-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        
        .category-badge.programming { background: #e3f2fd; color: #1976d2; }
        .category-badge.design { background: #e8f5e9; color: #388e3c; }
        .category-badge.business { background: #fce4ec; color: #c2185b; }
        .category-badge.marketing { background: #fff3e0; color: #f57c00; }
        .category-badge.other { background: #f3e5f5; color: #7b1fa2; }
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .course-image {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            position: relative;
        }
        
        .lesson-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.95);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #333;
        }
        
        .course-body {
            padding: 20px;
        }
        
        .course-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .course-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .instructor-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
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
        
        .course-price {
            font-size: 16px;
            font-weight: 700;
            color: #1e88e5;
        }
        
        .course-price.free {
            color: #43a047;
        }
        
        .view-details-btn {
            background: #f5f5f5;
            color: #666;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 10px;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .view-details-btn:hover {
            background: #e0e0e0;
        }
        
        .enrollment-count {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .category-header {
            margin: 40px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .category-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <a href="dashboard.php" class="brand">LEARNEXUS</a>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="course_catalog.php" class="nav-link active">Course Catalog</a>
            <a href="my_courses.php" class="nav-link">My Courses</a>
            <a href="ai_tutor.php" class="nav-link">AI Tutor</a>
        </div>
        
        <div class="user-section">
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
            <h1>Find your next skill</h1>
            <p class="text-muted">Explore <?php echo $totalPublished; ?> published courses designed for you.</p>
        </div>

        <!-- Search Box -->
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="What do you want to learn today?" value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit"><i class="bi bi-search"></i> Search</button>
        </form>

        <!-- Course Grid by Category -->
        <?php if (!empty($coursesByCategory)): ?>
            <?php foreach ($coursesByCategory as $category => $categoryCourses): ?>
                <div class="category-header">
                    <h3 class="category-title"><?php echo htmlspecialchars($category); ?> (<?php echo count($categoryCourses); ?>)</h3>
                </div>
                
                <div class="course-grid">
                    <?php foreach ($categoryCourses as $course): ?>
                        <div class="course-card" onclick="window.location.href='course_details.php?id=<?php echo $course['courseID']; ?>'">
                            <div class="course-image">
                                <?php if ($course['lessonCount'] > 0): ?>
                                    <span class="lesson-badge">
                                        <i class="bi bi-file-earmark-pdf"></i> <?php echo $course['lessonCount']; ?> Lessons
                                    </span>
                                <?php endif; ?>
                                <i class="bi bi-book" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                            <div class="course-body">
                                <span class="category-badge <?php echo strtolower($category); ?>">
                                    <?php echo strtoupper($category); ?>
                                </span>
                                
                                <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                
                                <?php if (!empty($course['description'])): ?>
                                    <div class="course-description"><?php echo htmlspecialchars($course['description']); ?></div>
                                <?php else: ?>
                                    <div class="course-description text-muted">No description available</div>
                                <?php endif; ?>
                                
                                <div class="enrollment-count">
                                    <i class="bi bi-people"></i> <?php echo $course['enrollmentCount']; ?> students enrolled
                                </div>
                                
                                <div class="course-footer">
                                    <div class="instructor-info">
                                        <div class="instructor-avatar">
                                            <?php if (!empty($course['instructorAvatar']) && file_exists($course['instructorAvatar'])): ?>
                                                <img src="<?php echo htmlspecialchars($course['instructorAvatar']); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <div style="width:100%;height:100%;background:#e0e0e0;"></div>
                                            <?php endif; ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($course['instructorName']); ?></span>
                                    </div>
                                    <div class="course-price <?php echo $course['price'] == 0 ? 'free' : ''; ?>">
                                        <?php echo $course['price'] == 0 ? 'FREE' : '₱' . number_format($course['price'], 2); ?>
                                    </div>
                                </div>
                                
                                <button class="view-details-btn">View Details →</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i>
                <?php if (!empty($searchQuery)): ?>
                    No courses found matching "<?php echo htmlspecialchars($searchQuery); ?>". Try a different search term.
                <?php else: ?>
                    <strong>No published courses available yet.</strong><br>
                    <small class="text-muted">
                        Teachers need to create courses and set them to "Published" status for them to appear here.
                        <?php if ($totalPublished > 0): ?>
                            <br>There are <?php echo $totalPublished; ?> published courses, but you may already be enrolled in all of them.
                        <?php endif; ?>
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>