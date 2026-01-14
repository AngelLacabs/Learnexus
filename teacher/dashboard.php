<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get teacher avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

// Get total courses created by teacher
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE teacherID = ?");
$stmt->execute([$teacherID]);
$totalCourses = $stmt->fetch()['count'];

// Get total students enrolled in teacher's courses
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.userID) as count
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    WHERE c.teacherID = ?
");
$stmt->execute([$teacherID]);
$totalStudents = $stmt->fetch()['count'];

// Get total revenue from teacher's courses
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(p.amount), 0) as total
    FROM payments p
    JOIN courses c ON p.courseID = c.courseID
    WHERE c.teacherID = ? AND p.status = 'completed'
");
$stmt->execute([$teacherID]);
$totalRevenue = $stmt->fetch()['total'];

// Get recent courses (last 6)
$stmt = $conn->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as enrollmentCount,
           (SELECT COUNT(*) FROM lessons WHERE courseID = c.courseID) as lessonCount,
           (SELECT COUNT(*) FROM quizzes WHERE courseID = c.courseID) as quizCount
    FROM courses c
    WHERE c.teacherID = ?
    ORDER BY c.createdAt DESC
    LIMIT 6
");
$stmt->execute([$teacherID]);
$recentCourses = $stmt->fetchAll();

// Motivational phrases for teachers
$teacherMotivations = [
    "Inspire minds, shape futures—your impact is immeasurable!",
    "Great teachers empower students to discover their potential.",
    "Every lesson you create changes lives. Keep inspiring!",
    "Your dedication to education makes the world brighter.",
    "Teaching is the art of awakening curiosity and joy in learning.",
    "You're not just teaching—you're building tomorrow's leaders.",
    "Knowledge shared is knowledge multiplied. Keep sharing!",
    "Your passion for teaching ignites the spark in your students.",
    "Excellence in education starts with educators like you.",
    "Thank you for shaping minds and transforming lives!"
];

$dayOfYear = date('z');
$dailyMotivationTeacher = $teacherMotivations[$dayOfYear % count($teacherMotivations)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Learnexus</title>
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

        /* Search */
        .search-input {
            padding-left: 2.5rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-input:focus ~ .search-icon {
            color: #667eea;
        }

        .clear-search {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            display: none;
        }

        .clear-search.show {
            display: block;
        }

        /* Course Cards */
        .course-header {
            height: 160px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15) !important;
        }

        /* Hide/Show Animations */
        .course-card-wrapper {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .course-card-wrapper.hidden {
            opacity: 0;
            transform: scale(0.95);
            height: 0;
            overflow: hidden;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Status badges */
        .status-active {
            background: #d4edda !important;
            color: #155724 !important;
        }

        .status-draft {
            background: #fff3cd !important;
            color: #856404 !important;
        }

        .status-archived {
            background: #f8d7da !important;
            color: #721c24 !important;
        }
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
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="dashboard.php">
                    <i class="bi bi-speedometer2 fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="courses.php">
                    <i class="bi bi-book fs-5"></i><span>Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
                    <i class="bi bi-patch-question fs-5"></i><span>Quizzes</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="enrollees.php">
                    <i class="bi bi-people fs-5"></i><span>Enrollees</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
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
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <i class="bi bi-search search-icon position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                                <input type="text" id="courseSearch" class="form-control search-input rounded-pill ps-5" 
                                       placeholder="Search your courses..." autocomplete="off">
                                <button class="clear-search" id="clearSearch">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
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

            <!-- Welcome Banner -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow text-white" 
                         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body p-4 p-lg-5">
                            <h2 class="h3 fw-bold mb-0"><?php echo htmlspecialchars($dailyMotivationTeacher); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card border-0 rounded-4 shadow-sm card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 56px; height: 56px; font-size: 1.5rem;">
                                <i class="bi bi-book-fill"></i>
                            </div>
                            <p class="text-muted small mb-2">Total Courses</p>
                            <h3 class="display-5 fw-bold mb-0"><?php echo $totalCourses; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border-0 rounded-4 shadow-sm card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 56px; height: 56px; font-size: 1.5rem;">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <p class="text-muted small mb-2">Total Students</p>
                            <h3 class="display-5 fw-bold mb-0"><?php echo $totalStudents; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border-0 rounded-4 shadow-sm card-hover h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 56px; height: 56px; font-size: 1.5rem;">
                                <i class="bi bi-currency-dollar"></i>
                            </div>
                            <p class="text-muted small mb-2">Total Revenue</p>
                            <h3 class="display-5 fw-bold mb-0">₱<?php echo number_format($totalRevenue, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Results Info -->
            <div class="row mb-3 d-none" id="searchResultsInfo">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-search me-2"></i>
                                Found <strong id="resultCount">0</strong> course(s) matching 
                                "<span id="searchTerm" class="badge bg-warning text-dark"></span>"
                            </div>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="clearSearch()">
                                <i class="bi bi-x"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Title -->
            <div class="row mb-3">
                <div class="col-12">
                    <h2 class="h4 fw-bold">Recent Courses</h2>
                </div>
            </div>

            <!-- Course Cards -->
            <div class="row g-4 mb-5" id="coursesContainer">
                <?php if (count($recentCourses) > 0): ?>
                    <?php foreach ($recentCourses as $course): ?>
                        <div class="col-12 col-md-6 col-lg-4 course-card-wrapper" 
                             data-course-title="<?php echo strtolower(htmlspecialchars($course['title'])); ?>"
                             data-course-status="<?php echo strtolower(htmlspecialchars($course['status'])); ?>">
                            <div class="card border-0 rounded-4 shadow-sm card-hover h-100">
                                <!-- Course Header -->
                                <div class="course-header position-relative d-flex align-items-center justify-content-center">
                                    <span class="badge status-<?php echo strtolower($course['status']); ?> position-absolute top-0 end-0 m-2 shadow-sm fw-bold">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                    <span class="fs-1">📚</span>
                                </div>
                                
                                <div class="card-body p-4">
                                    <p class="text-primary small text-uppercase fw-bold mb-2">
                                        <?php echo htmlspecialchars($course['category'] ?? 'General'); ?>
                                    </p>
                                    <h5 class="fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h5>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-4">
                                            <div class="text-center p-2 bg-light rounded-3">
                                                <div class="fw-bold text-primary"><?php echo $course['enrollmentCount']; ?></div>
                                                <small class="text-muted">Students</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-center p-2 bg-light rounded-3">
                                                <div class="fw-bold text-success"><?php echo $course['lessonCount']; ?></div>
                                                <small class="text-muted">Lessons</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-center p-2 bg-light rounded-3">
                                                <div class="fw-bold text-warning"><?php echo $course['quizCount']; ?></div>
                                                <small class="text-muted">Quizzes</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="text-muted small mb-3">
                                        <i class="bi bi-calendar me-1"></i>
                                        Created <?php echo date('M d, Y', strtotime($course['createdAt'])); ?>
                                    </p>
                                    
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12" id="emptyState">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-book display-1 text-muted mb-3"></i>
                                <h3 class="h5 fw-bold mb-3">No Courses Yet</h3>
                                <p class="text-muted mb-4">You haven't created any courses yet. Start creating to inspire your students!</p>
                                <a href="courses.php" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                    <i class="bi bi-plus-circle me-2"></i>Create First Course
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- No Results -->
            <div class="row d-none" id="noResults">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-search display-1 text-muted mb-3"></i>
                            <h3 class="h5 fw-bold mb-3">No Courses Found</h3>
                            <p class="text-muted mb-4">We couldn't find any courses matching your search.</p>
                            <button class="btn btn-primary rounded-pill px-4 fw-semibold" onclick="clearSearch()">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Clear Search
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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

        // Search Functionality
        const searchInput = document.getElementById('courseSearch');
        const clearSearchBtn = document.getElementById('clearSearch');
        const searchResultsInfo = document.getElementById('searchResultsInfo');
        const resultCount = document.getElementById('resultCount');
        const searchTermSpan = document.getElementById('searchTerm');
        const noResultsEl = document.getElementById('noResults');
        const emptyState = document.getElementById('emptyState');
        const courseCards = document.querySelectorAll('.course-card-wrapper');
        const totalCourses = courseCards.length;

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            clearSearchBtn.classList.toggle('show', searchTerm.length > 0);
            filterCourses(searchTerm);
        });

        clearSearchBtn.addEventListener('click', clearSearch);
        searchInput.addEventListener('keydown', (e) => e.key === 'Escape' && clearSearch());

        function filterCourses(searchTerm) {
            let visibleCount = 0;
            
            if (!searchTerm) {
                courseCards.forEach(card => card.classList.remove('hidden'));
                searchResultsInfo.classList.add('d-none');
                noResultsEl.classList.add('d-none');
                if (emptyState) emptyState.style.display = totalCourses === 0 ? 'block' : 'none';
                return;
            }
            
            if (emptyState) emptyState.style.display = 'none';
            
            courseCards.forEach(card => {
                const title = card.getAttribute('data-course-title') || '';
                const status = card.getAttribute('data-course-status') || '';
                const matches = title.includes(searchTerm) || status.includes(searchTerm);
                
                card.classList.toggle('hidden', !matches);
                if (matches) visibleCount++;
            });
            
            if (visibleCount > 0) {
                searchResultsInfo.classList.remove('d-none');
                noResultsEl.classList.add('d-none');
                resultCount.textContent = visibleCount;
                searchTermSpan.textContent = searchTerm;
            } else {
                searchResultsInfo.classList.add('d-none');
                noResultsEl.classList.remove('d-none');
            }
        }

        function clearSearch() {
            searchInput.value = '';
            clearSearchBtn.classList.remove('show');
            searchResultsInfo.classList.add('d-none');
            noResultsEl.classList.add('d-none');
            courseCards.forEach(card => card.classList.remove('hidden'));
            if (emptyState) emptyState.style.display = totalCourses === 0 ? 'block' : 'none';
            searchInput.focus();
        }
    </script>
</body>
</html>