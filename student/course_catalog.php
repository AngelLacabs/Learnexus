<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userAvatar = $stmt->fetchColumn();

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

// Get total published courses count
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
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation */
        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: #1a73e8;
            border-radius: 0 4px 4px 0;
            transition: height 0.25s ease;
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active::before {
            display: none;
        }

        /* Hamburger */
        .hamburger-btn {
            width: 50px;
            height: 50px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .hamburger-icon span {
            display: block;
            width: 24px;
            height: 3px;
            background: #1a73e8;
            border-radius: 3px;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        /* Main Content Margin */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Course Cards */
        .course-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .course-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15) !important;
        }

        .course-image {
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .category-badge {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .category-badge.programming { background: #e3f2fd; color: #1976d2; }
        .category-badge.design { background: #e8f5e9; color: #388e3c; }
        .category-badge.business { background: #fce4ec; color: #c2185b; }
        .category-badge.marketing { background: #fff3e0; color: #f57c00; }
        .category-badge.other { background: #f3e5f5; color: #7b1fa2; }
    </style>
</head>
<body>
    <!-- Hamburger Button (Mobile) -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100" style="width: var(--sidebar-width);" id="sidebar">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>
            
            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="my_courses.php">
                    <i class="bi bi-journal-bookmark fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="certificates.php">
                    <i class="bi bi-award fs-5"></i><span>Certificates</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="vouchers.php">
                    <i class="bi bi-ticket-perforated fs-5"></i><span>Vouchers</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="ai_tutor.php">
                    <i class="bi bi-robot fs-5"></i><span>AI Tutor</span>
                </a>
            </nav>
            
            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold" onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <!-- Search Form -->
                                <form method="GET" class="d-flex gap-2" style="flex: 1; max-width: 600px;">
                                    <input type="text" name="search" class="form-control rounded-pill" 
                                           placeholder="What do you want to learn today?" 
                                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </form>
                                
                                <!-- User Info -->
                                <div class="d-flex align-items-center gap-3" 
                                     onclick="window.location.href='settings.php'" role="button" 
                                     style="flex-shrink: 0;">
                                    <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                    </span>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                         style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
                                        <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                                            <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar" 
                                                 class="w-100 h-100 rounded-circle object-fit-cover">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <h1 class="display-5 fw-bold mb-2">Find Your Next Skill</h1>
                    <p class="text-muted">Explore <?php echo $totalPublished; ?> published courses designed for you.</p>
                </div>
            </div>

            <!-- Course Grid by Category -->
            <?php if (!empty($coursesByCategory)): ?>
                <?php foreach ($coursesByCategory as $category => $categoryCourses): ?>
                    <!-- Category Header -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="border-bottom pb-2">
                                <h3 class="h4 fw-bold mb-0">
                                    <?php echo htmlspecialchars($category); ?> 
                                    <span class="badge bg-secondary rounded-pill ms-2"><?php echo count($categoryCourses); ?></span>
                                </h3>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Course Cards -->
                    <div class="row g-4 mb-5">
                        <?php foreach ($categoryCourses as $course): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="card border-0 rounded-4 shadow-sm course-card h-100" 
                                     onclick="window.location.href='course_details.php?id=<?php echo $course['courseID']; ?>'" 
                                     role="button">
                                    <!-- Course Image -->
                                    <div class="course-image position-relative d-flex align-items-center justify-content-center">
                                        <?php if ($course['lessonCount'] > 0): ?>
                                            <span class="badge bg-white text-dark position-absolute top-0 end-0 m-2 shadow-sm">
                                                <i class="bi bi-file-earmark-text"></i> <?php echo $course['lessonCount']; ?> Lessons
                                            </span>
                                        <?php endif; ?>
                                        <i class="bi bi-book text-white" style="font-size: 3rem;"></i>
                                    </div>
                                    
                                    <!-- Card Body -->
                                    <div class="card-body p-4">
                                        <span class="badge category-badge <?php echo strtolower($category); ?> mb-2">
                                            <?php echo strtoupper($category); ?>
                                        </span>
                                        
                                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h5>
                                        
                                        <?php if (!empty($course['description'])): ?>
                                            <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?php echo htmlspecialchars($course['description']); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-muted small mb-3">No description available</p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center gap-2 text-muted small mb-3">
                                            <i class="bi bi-people"></i>
                                            <span><?php echo $course['enrollmentCount']; ?> students enrolled</span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-secondary" style="width: 24px; height: 24px; overflow: hidden;">
                                                    <?php if (!empty($course['instructorAvatar']) && file_exists($course['instructorAvatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($course['instructorAvatar']); ?>" 
                                                             alt="Avatar" class="w-100 h-100 object-fit-cover">
                                                    <?php endif; ?>
                                                </div>
                                                <span class="small text-muted"><?php echo htmlspecialchars($course['instructorName']); ?></span>
                                            </div>
                                            <div class="fw-bold <?php echo $course['price'] == 0 ? 'text-success' : 'text-primary'; ?>">
                                                <?php echo $course['price'] == 0 ? 'FREE' : '₱' . number_format($course['price'], 2); ?>
                                            </div>
                                        </div>
                                        
                                        <button class="btn btn-outline-secondary w-100 rounded-pill btn-sm">
                                            View Details <i class="bi bi-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-search display-1 text-muted mb-3"></i>
                                <?php if (!empty($searchQuery)): ?>
                                    <h3 class="h5 fw-bold mb-3">No Courses Found</h3>
                                    <p class="text-muted mb-4">
                                        No courses found matching "<?php echo htmlspecialchars($searchQuery); ?>". 
                                        Try a different search term.
                                    </p>
                                <?php else: ?>
                                    <h3 class="h5 fw-bold mb-3">No Courses Available</h3>
                                    <p class="text-muted mb-4">
                                        <?php if ($totalPublished > 0): ?>
                                            There are <?php echo $totalPublished; ?> published courses, but you may already be enrolled in all of them.
                                        <?php else: ?>
                                            No published courses available yet. Check back later!
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <a href="course_catalog.php" class="btn btn-primary rounded-pill px-4">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Clear Search
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hamburger animation
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        // Active nav state
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop();
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            }
            
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                    if (offcanvas) offcanvas.hide();
                }
            });
        });
    </script>
</body>
</html>