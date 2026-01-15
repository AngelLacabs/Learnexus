<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
// Fetch instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

// Get courses by teacher
$stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE teacherID = ? ORDER BY title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Get enrollees per course
$enrolleesByCourse = [];
foreach ($courses as $course) {
    $stmt = $conn->prepare("
        SELECT u.userID, u.firstName, u.lastName, u.studentNumber, e.enrolledAt, e.progressPercentage
        FROM enrollments e
        JOIN users u ON e.userID = u.userID
        WHERE e.courseID = ?
        ORDER BY e.enrolledAt DESC
    ");
    $stmt->execute([$course['courseID']]);
    $enrolleesByCourse[$course['courseID']] = [
        'course' => $course,
        'enrollees' => $stmt->fetchAll()
    ];
}

// Get all enrollees
$stmt = $conn->prepare("
    SELECT u.userID, u.firstName, u.lastName, u.studentNumber, c.title as courseTitle
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    JOIN courses c ON e.courseID = c.courseID
    WHERE c.teacherID = ?
    ORDER BY u.lastName, u.firstName
");
$stmt->execute([$teacherID]);
$allEnrollees = $stmt->fetchAll();

// Calculate total students
$totalStudents = count($allEnrollees);

// Calculate stats
$totalCourses = count($courses);
$coursesWithEnrollees = count(array_filter($enrolleesByCourse, fn($data) => count($data['enrollees']) > 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollees - Learnexus</title>
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

        /* Sidebar - Matching student design */
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

        /* Navigation - Matching student design */
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

        /* Hamburger - Matching student design */
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

        /* Stats Cards - Updated to match student design */
        .stat-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Course Cards - Matching student design */
        .course-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            height: 100%;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Search Input */
        .search-input {
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.9);
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .search-icon {
            color: #6c757d;
        }

        /* User Avatar */
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* Status Tabs */
        .status-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
        }

        .status-tab:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .status-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Course Image Placeholder */
        .course-img-placeholder {
            height: 140px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            border-radius: 16px 16px 0 0;
        }

        /* Action Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            color: white;
        }

        /* Empty State */
        .empty-state-icon {
            font-size: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Table Styling */
        .enrollee-table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .enrollee-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            color: #666;
            font-weight: 600;
            padding: 16px;
        }

        .enrollee-table tbody tr {
            transition: background 0.2s;
            cursor: pointer;
        }

        .enrollee-table tbody tr:hover {
            background: #f8f9fa;
        }

        .enrollee-table tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #eaeaea;
        }

        /* Badge for enrollee count */
        .enrollee-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.75em;
            font-weight: 600;
            padding: 0.35em 0.65em;
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
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="courses.php">
                    <i class="bi bi-book fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
                    <i class="bi bi-patch-question fs-5"></i><span>Quizzes</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="enrollees.php">
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
            <!-- Header with Search and Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <i class="bi bi-search search-icon position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                                <input type="text" id="enrolleeSearch" class="form-control search-input rounded-pill ps-5" 
                                       placeholder="Search enrollees or courses..." autocomplete="off">
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold user-avatar">
                                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" 
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

            <!-- Page Title -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 fw-bold"><i class="bi bi-people me-2"></i>Enrollees</h1>
                    <p class="text-muted">Manage and monitor your students</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <i class="bi bi-people-fill fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Students</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalStudents; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                                    <i class="bi bi-book fs-4 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Courses</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalCourses; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                                    <i class="bi bi-check-circle fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Active Courses</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $coursesWithEnrollees; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Tabs - UPDATED: All Enrollees first, then Per Courses -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="status-tab active" onclick="showTab('all-enrollees')">
                                    <i class="bi bi-list-ul me-1"></i> All Enrollees
                                </button>
                                <button class="status-tab" onclick="showTab('per-courses')">
                                    <i class="bi bi-grid me-1"></i> Per Courses
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Enrollees Tab - UPDATED: Now shown by default -->
            <div class="row" id="all-enrollees">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-0">
                            <div class="p-4 border-bottom">
                                <h5 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i> All Enrollees</h5>
                                <p class="text-muted mb-0 small">Total: <?php echo $totalStudents; ?> students</p>
                            </div>
                            
                            <?php if (count($allEnrollees) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover enrollee-table mb-0">
                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Student Name</th>
                                                <th>Student Number</th>
                                                <th>Course</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allEnrollees as $index => $student): ?>
                                                <tr>
                                                    <td class="text-muted"><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <div class="fw-medium"><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></div>
                                                    </td>
                                                    <td class="text-muted"><?php echo htmlspecialchars($student['studentNumber']); ?></td>
                                                    <td>
                                                        <div class="fw-medium"><?php echo htmlspecialchars($student['courseTitle']); ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-people empty-state-icon mb-3"></i>
                                    <h3 class="h5 fw-bold mb-3">No Enrollees Yet</h3>
                                    <p class="text-muted mb-4">No students have enrolled in your courses yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per Courses Tab - UPDATED: Now hidden by default -->
            <div class="row g-4 d-none" id="per-courses">
                <?php if (count($enrolleesByCourse) > 0): ?>
                    <?php 
                    $hasEnrollees = false;
                    foreach ($enrolleesByCourse as $data): 
                        if (count($data['enrollees']) > 0): 
                            $hasEnrollees = true;
                    ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card course-card">
                                <div class="course-img-placeholder">
                                    <i class="bi bi-book"></i>
                                </div>
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-2 text-truncate"><?php echo htmlspecialchars($data['course']['title']); ?></h5>
                                            <span class="enrollee-badge rounded-pill">
                                                <i class="bi bi-person me-1"></i> <?php echo count($data['enrollees']); ?> enrollees
                                            </span>
                                        </div>
                                        
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            Last enrolled: 
                                            <?php 
                                            if (count($data['enrollees']) > 0) {
                                                $latest = $data['enrollees'][0];
                                                echo date('M d, Y', strtotime($latest['enrolledAt']));
                                            } else {
                                                echo 'No enrollees yet';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    
                                    <button class="btn btn-gradient w-100 rounded-pill fw-semibold" 
                                            onclick="window.location.href='view_enrollees.php?course_id=<?php echo $data['course']['courseID']; ?>'">
                                        <i class="bi bi-people me-2"></i> Manage Enrollees
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    
                    if (!$hasEnrollees): ?>
                        <div class="col-12">
                            <div class="card border-0 rounded-4 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-people empty-state-icon mb-3"></i>
                                    <h3 class="h5 fw-bold mb-3">No Enrollees Yet</h3>
                                    <p class="text-muted mb-4">No students have enrolled in your courses yet.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-people empty-state-icon mb-3"></i>
                                <h3 class="h5 fw-bold mb-3">No Courses Yet</h3>
                                <p class="text-muted mb-4">You haven't created any courses yet. Create courses first to get enrollees.</p>
                                <a href="courses.php" class="btn btn-gradient rounded-pill px-4 fw-semibold">
                                    <i class="bi bi-plus me-2"></i>Create Course
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
    
    // Close sidebar on mobile after click
    link.addEventListener('click', () => {
        if (window.innerWidth <= 992) {
            const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
            if (offcanvas) offcanvas.hide();
        }
    });
});

// Search functionality
document.getElementById('enrolleeSearch').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    const activeTab = document.querySelector('.status-tab.active').textContent.toLowerCase();
    
    if (activeTab.includes('courses')) {
        // Search in courses tab
        document.querySelectorAll('.course-card').forEach(card => {
            const title = card.querySelector('.fw-bold').textContent.toLowerCase();
            const container = card.closest('.col-12.col-md-6.col-lg-4');
            if (title.includes(term)) {
                container.style.display = '';
            } else {
                container.style.display = 'none';
            }
        });
    } else {
        // Search in all enrollees tab
        document.querySelectorAll('.enrollee-table tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }
});

// Tab switching function
function showTab(tabId) {
    // Update active tab button
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Show/hide tab contents
    const perCoursesTab = document.getElementById('per-courses');
    const allEnrolleesTab = document.getElementById('all-enrollees');
    
    if (tabId === 'all-enrollees') {
        allEnrolleesTab.classList.remove('d-none');
        perCoursesTab.classList.add('d-none');
    } else {
        allEnrolleesTab.classList.add('d-none');
        perCoursesTab.classList.remove('d-none');
    }
}

// Initialize search when tab changes
document.querySelectorAll('.status-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Trigger search to update results for active tab
        const searchInput = document.getElementById('enrolleeSearch');
        if (searchInput.value) {
            searchInput.dispatchEvent(new Event('input'));
        }
    });
});
</script>
</body>
</html>