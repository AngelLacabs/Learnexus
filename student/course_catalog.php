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
    SELECT c.*, CONCAT(u.firstName, ' ', u.lastName) as instructorName
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
        
        .category-badge.design { background: #e3f2fd; color: #1976d2; }
        .category-badge.development { background: #e8f5e9; color: #388e3c; }
        .category-badge.business { background: #fce4ec; color: #c2185b; }
        .category-badge.marketing { background: #fff3e0; color: #f57c00; }
        
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
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 40px;
        }
        
        .pagination button {
            width: 36px;
            height: 36px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .pagination button.active {
            background: #1e88e5;
            color: white;
            border-color: #1e88e5;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="brand">LEARNEXUS</div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="course_catalog.php" class="nav-link active">Course Catalog</a>
            <a href="my_courses.php" class="nav-link">My Courses</a>
            <a href="ai_tutor.php" class="nav-link">AI Tutor</a>
        </div>
        
        <div class="user-section">
            <i class="bi bi-bell" style="font-size: 22px; color: #666; cursor: pointer;"></i>
            <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-main">
        <div class="page-header">
            <h1>Find your next skill</h1>
            <p class="text-muted">Explore catalog courses designed for you.</p>
        </div>

        <!-- Search Box -->
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="What do you want to learn today?" value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit">Search</button>
        </form>

        <!-- Course Grid by Category -->
        <?php foreach ($coursesByCategory as $category => $categoryCourses): ?>
            <h3 style="margin-bottom: 20px; color: #333;"><?php echo htmlspecialchars($category); ?></h3>
            
            <div class="course-grid">
                <?php foreach ($categoryCourses as $course): ?>
                    <div class="course-card" onclick="window.location.href='course_details.php?id=<?php echo $course['courseID']; ?>'">
                        <div class="course-image">
                            // photo
                        </div>
                        <div class="course-body">
                            <span class="category-badge <?php echo strtolower($category); ?>">
                                <?php echo strtoupper($category); ?>
                            </span>
                            
                            <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                            <div class="course-description"><?php echo htmlspecialchars($course['description']); ?></div>
                            
                            <div class="course-footer">
                                <div class="instructor-info">
                                    <div class="instructor-avatar"></div>
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

        <?php if (empty($courses)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i>
                <?php if (!empty($searchQuery)): ?>
                    No courses found matching "<?php echo htmlspecialchars($searchQuery); ?>". Try a different search term.
                <?php else: ?>
                    No courses available at the moment. Check back later!
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <div class="pagination">
            <button>«</button>
            <button class="active">1</button>
            <button>2</button>
            <button>3</button>
            <button>4</button>
            <button>»</button>
        </div>
    </div>
</body>
</html>