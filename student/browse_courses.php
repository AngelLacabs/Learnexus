<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get filters from URL
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$priceRange = $_GET['price'] ?? '';

// Build query with enrollment progress - EXCLUDE COMPLETED COURSES
$query = "
    SELECT c.*, 
           CONCAT(u.firstName, ' ', u.lastName) as instructorName,
           (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as enrollmentCount,
           EXISTS(
               SELECT 1 FROM enrollments 
               WHERE courseID = c.courseID 
               AND userID = ? 
               AND (progressPercentage < 100 OR progressPercentage IS NULL)
           ) as isEnrolled,
           (SELECT progressPercentage FROM enrollments WHERE courseID = c.courseID AND userID = ? LIMIT 1) as progressPercentage
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.status = 'published'
    AND NOT EXISTS (
        SELECT 1 FROM enrollments e 
        WHERE e.courseID = c.courseID 
        AND e.userID = ? 
        AND e.progressPercentage >= 100
    )
";

$params = [$userID, $userID, $userID];

if (!empty($search)) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $query .= " AND c.category = ?";
    $params[] = $category;
}

if (!empty($priceRange)) {
    switch($priceRange) {
        case 'free':
            $query .= " AND c.price = 0";
            break;
        case 'under100':
            $query .= " AND c.price > 0 AND c.price <= 100";
            break;
        case 'under500':
            $query .= " AND c.price > 100 AND c.price <= 500";
            break;
        case 'over500':
            $query .= " AND c.price > 500";
            break;
    }
}

// Sorting
switch($sort) {
    case 'popular':
        $query .= " ORDER BY enrollmentCount DESC";
        break;
    case 'price_low':
        $query .= " ORDER BY c.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY c.price DESC";
        break;
    default:
        $query .= " ORDER BY c.createdAt DESC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get all categories
$categoriesStmt = $conn->query("SELECT DISTINCT category FROM courses WHERE status = 'published' AND category IS NOT NULL ORDER BY category");
$categories = $categoriesStmt->fetchAll();

// Get statistics
$statsStmt = $conn->query("SELECT COUNT(*) as total, COUNT(CASE WHEN price = 0 THEN 1 END) as free FROM courses WHERE status = 'published'");
$stats = $statsStmt->fetch();
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
            background: #f8f9fa; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .top-nav {
            background: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-bottom: 1px solid #e0e0e0;
        }
        .brand {
            font-size: 24px;
            font-weight: 700;
            color: #1e88e5;
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
            transition: color 0.2s;
        }
        .nav-link:hover {
            color: #1e88e5;
        }
        .nav-link.active {
            color: #1e88e5;
        }
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .container-main { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 40px 40px;
        }
        .header-section {
            margin-bottom: 40px;
        }
        .header-section h1 {
            font-size: 36px;
            font-weight: 700;
            color: #212121;
            margin-bottom: 8px;
        }
        .search-bar {
            max-width: 600px;
            margin: 24px 0;
        }
        .search-bar input {
            padding: 14px 20px;
            border-radius: 50px;
            border: 1px solid #e0e0e0;
            font-size: 15px;
        }
        .search-bar .btn {
            border-radius: 50px;
            padding: 14px 28px;
        }
        .filters-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 16px 0;
        }
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
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
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .course-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            position: relative;
        }
        .course-status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .badge-enrolled {
            background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
            color: white;
        }
        .course-body {
            padding: 20px;
        }
        .course-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #212121;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .course-instructor {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }
        .course-price {
            font-size: 20px;
            font-weight: 700;
            color: #1e88e5;
        }
        .course-price.free {
            color: #43a047;
        }
        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #f5f5f5;
            border-radius: 12px;
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }
        .enrollment-count {
            font-size: 13px;
            color: #666;
        }
        .btn-enroll {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 12px;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #1e88e5;
            color: white;
        }
        .btn-primary:hover {
            background: #1565c0;
        }
        .btn-success {
            background: #43a047;
            color: white;
        }
        .btn-success:hover {
            background: #388e3c;
        }
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .no-results i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 16px;
        }
        .clear-filters {
            color: #1e88e5;
            text-decoration: none;
            font-weight: 600;
        }
        .clear-filters:hover {
            text-decoration: underline;
        }
        .progress-mini {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .progress-mini-fill {
            height: 100%;
            background: linear-gradient(90deg, #1e88e5 0%, #42a5f5 100%);
            border-radius: 2px;
            transition: width 0.3s;
        }
        .progress-text-mini {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <div class="top-nav">
        <a href="dashboard.php" class="brand">LEARNEXUS</a>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="course_catalog.php" class="nav-link active">Course Catalog</a>
            <a href="my_courses.php" class="nav-link">My Courses</a>
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

    <div class="container-main">
        <!-- Header Section -->
        <div class="header-section">
            <h1>Explore Courses</h1>
            <p class="text-muted">Discover new skills and advance your career with <?php echo $stats['total']; ?> courses available</p>
            
            <!-- Search Bar -->
            <form method="GET" action="" class="search-bar">
                <div class="input-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search for courses..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                
                <div class="row g-4">
                    <!-- Category Filter -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price Filter -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Price</label>
                        <select name="price" class="form-select" onchange="this.form.submit()">
                            <option value="">All Prices</option>
                            <option value="free" <?php echo $priceRange === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="under100" <?php echo $priceRange === 'under100' ? 'selected' : ''; ?>>Under ₱100</option>
                            <option value="under500" <?php echo $priceRange === 'under500' ? 'selected' : ''; ?>>₱100 - ₱500</option>
                            <option value="over500" <?php echo $priceRange === 'over500' ? 'selected' : ''; ?>>Over ₱500</option>
                        </select>
                    </div>

                    <!-- Sort Filter -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sort by</label>
                        <select name="sort" class="form-select" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                </div>

                <?php if ($category || $search || $priceRange || $sort !== 'newest'): ?>
                    <div class="mt-3">
                        <a href="course_catalog.php" class="clear-filters">
                            <i class="bi bi-x-circle"></i> Clear all filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div>
                <strong><?php echo count($courses); ?></strong> course<?php echo count($courses) !== 1 ? 's' : ''; ?> found
            </div>
        </div>

        <!-- Course Grid -->
        <?php if (empty($courses)): ?>
            <div class="no-results">
                <i class="bi bi-search"></i>
                <h4>No courses found</h4>
                <p class="text-muted">Try adjusting your filters or search terms</p>
                <a href="course_catalog.php" class="clear-filters">Clear all filters</a>
            </div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($courses as $course): 
                    $gradients = [
                        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                        'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
                    ];
                    $gradient = $gradients[$course['courseID'] % count($gradients)];
                    $progress = $course['progressPercentage'] ?? 0;
                ?>
                    <div class="course-card" onclick="window.location.href='course_details.php?id=<?php echo $course['courseID']; ?>'">
                        <div class="course-image" style="background: <?php echo $gradient; ?>">
                            <i class="bi bi-book"></i>
                            <?php if ($course['isEnrolled'] && $progress > 0 && $progress < 100): ?>
                                <span class="course-status-badge badge-enrolled">
                                    <i class="bi bi-play-circle-fill"></i> IN PROGRESS
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="course-body">
                            <span class="category-badge">
                                <i class="bi bi-folder"></i> <?php echo htmlspecialchars($course['category'] ?? 'General'); ?>
                            </span>
                            <h5 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="course-instructor">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($course['instructorName']); ?>
                            </p>
                            
                            <?php if (!empty($course['description'])): ?>
                                <p class="text-muted small" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($course['description']); ?>
                                </p>
                            <?php endif; ?>

                            <div class="enrollment-count">
                                <i class="bi bi-people"></i> <?php echo $course['enrollmentCount']; ?> student<?php echo $course['enrollmentCount'] !== 1 ? 's' : ''; ?> enrolled
                            </div>

                            <?php if ($course['isEnrolled'] && $progress > 0 && $progress < 100): ?>
                                <div class="progress-mini">
                                    <div class="progress-mini-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <div class="progress-text-mini">
                                    <?php echo number_format($progress, 0); ?>% Complete
                                </div>
                            <?php endif; ?>

                            <div class="course-meta">
                                <div class="course-price <?php echo $course['price'] == 0 ? 'free' : ''; ?>">
                                    <?php if ($course['price'] == 0): ?>
                                        FREE
                                    <?php else: ?>
                                        ₱<?php echo number_format($course['price'], 2); ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($course['isEnrolled'] && $progress > 0): ?>
                                <button class="btn btn-success btn-enroll" onclick="event.stopPropagation(); window.location.href='view_course.php?id=<?php echo $course['courseID']; ?>'">
                                    <i class="bi bi-play-circle"></i> Continue Learning
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary btn-enroll" onclick="event.stopPropagation(); window.location.href='course_details.php?id=<?php echo $course['courseID']; ?>'">
                                    View Details <i class="bi bi-arrow-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>