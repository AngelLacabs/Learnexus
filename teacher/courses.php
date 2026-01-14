<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();

// Get all courses by teacher
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as studentCount,
           (SELECT COUNT(*) FROM lessons WHERE courseID = c.courseID) as moduleCount
    FROM courses c
    WHERE c.teacherID = ?
    ORDER BY c.createdAt DESC
");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Separate courses by status
$inProgress = array_filter($courses, fn($c) => $c['status'] == 'draft');
$completed = array_filter($courses, fn($c) => $c['status'] == 'published');
$approved = array_filter($courses, fn($c) => $c['status'] == 'archived');

function calculateCompletion($moduleCount) {
    if ($moduleCount == 0) return 0;
    if ($moduleCount >= 5) return 100;
    return round(($moduleCount / 5) * 100);
}

$page_title = "My Courses - Learnexus";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Progress Bar */
        .progress-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        /* Status Badges */
        .badge-draft {
            background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
            color: white;
        }

        .badge-published {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
        }

        .badge-archived {
            background: linear-gradient(135deg, #757575 0%, #9e9e9e 100%);
            color: white;
        }

        /* Search Input */
        .search-input {
            border: 1px solid #dee2e6;
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
            height: 160px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
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

        /* Add Course Button */
        .add-course-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 1000;
        }

        .add-course-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        /* Empty State */
        .empty-state-icon {
            font-size: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="courses.php">
                    <i class="bi bi-book fs-5"></i><span>My Courses</span>
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
            <!-- Header with Search and Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <i class="bi bi-search search-icon position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                                <input type="text" id="courseSearch" class="form-control search-input rounded-pill ps-5" 
                                       placeholder="Search your courses..." autocomplete="off">
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
                    <h1 class="h3 fw-bold"><i class="bi bi-book me-2"></i>My Courses</h1>
                    <p class="text-muted">Manage and spread your knowledge</p>
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
                                    <i class="bi bi-clock-history fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">In Progress</h6>
                                    <h3 class="fw-bold mb-0"><?php echo count($inProgress); ?></h3>
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
                                    <i class="bi bi-check-circle fs-4 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Published</h6>
                                    <h3 class="fw-bold mb-0"><?php echo count($completed); ?></h3>
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
                                    <i class="bi bi-archive fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Archived</h6>
                                    <h3 class="fw-bold mb-0"><?php echo count($approved); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="status-tab active" data-status="all">All Courses</button>
                                <button class="status-tab" data-status="draft">In Progress (<?php echo count($inProgress); ?>)</button>
                                <button class="status-tab" data-status="published">Published (<?php echo count($completed); ?>)</button>
                                <button class="status-tab" data-status="archived">Archived (<?php echo count($approved); ?>)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Grid -->
            <div class="row g-4" id="coursesContainer">
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): ?>
                        <?php 
                            $completion = calculateCompletion($course['moduleCount']);
                            $statusClass = $course['status'] == 'published' ? 'published' : ($course['status'] == 'archived' ? 'archived' : 'draft');
                            $statusText = $course['status'] == 'published' ? 'Published' : ($course['status'] == 'archived' ? 'Archived' : 'Draft');
                        ?>
                        <div class="col-12 col-md-6 col-lg-4 course-column" data-course-status="<?php echo $statusClass; ?>">
                            <div class="card course-card">
                                <div class="course-img-placeholder">
                                    <i class="bi bi-book"></i>
                                </div>
                                <div class="card-body p-4">
                                    <!-- UPDATED: Removed the three-dot dropdown menu -->
                                    <div class="mb-3">
                                        <h5 class="fw-bold mb-2 text-truncate"><?php echo htmlspecialchars($course['title']); ?></h5>
                                        <span class="badge badge-<?php echo $statusClass; ?> rounded-pill px-3 py-1">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between text-muted small mb-3">
                                        <span><i class="bi bi-people"></i> <?php echo $course['studentCount']; ?> students</span>
                                        <span><i class="bi bi-journal-text"></i> <?php echo $course['moduleCount']; ?> modules</span>
                                    </div>
                                    
                                    <?php if ($course['status'] === 'draft'): ?>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar progress-gradient" style="width: <?php echo $completion; ?>%"></div>
                                        </div>
                                        <p class="text-muted small mb-3"><?php echo $completion; ?>% Complete</p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-gradient btn-sm rounded-pill flex-grow-1" 
                                                onclick="window.location.href='manage_course.php?id=<?php echo $course['courseID']; ?>'">
                                            <i class="bi bi-gear me-1"></i> Manage
                                        </button>
                                        <?php if ($course['status'] === 'published'): ?>
                                            <button class="btn btn-outline-secondary btn-sm rounded-pill" 
                                                    onclick="toggleCourseStatus(<?php echo $course['courseID']; ?>, 'unpublish')">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-success btn-sm rounded-pill" 
                                                    onclick="toggleCourseStatus(<?php echo $course['courseID']; ?>, 'publish')">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-journal-x empty-state-icon mb-3"></i>
                                <h3 class="h5 fw-bold mb-3">No Courses Yet</h3>
                                <p class="text-muted mb-4">You haven't created any courses yet. Create your first course to get started!</p>
                                <button class="btn btn-gradient rounded-pill px-4 fw-semibold" onclick="showCreateCourseModal()">
                                    <i class="bi bi-plus me-2"></i>Create Course
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Course Button -->
    <button class="add-course-btn" onclick="showCreateCourseModal()">
        <i class="bi bi-plus"></i>
    </button>

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
document.getElementById('courseSearch').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('.course-card').forEach(card => {
        const title = card.querySelector('.fw-bold').textContent.toLowerCase();
        const container = card.closest('.course-column');
        if (title.includes(term)) {
            container.style.display = '';
        } else {
            container.style.display = 'none';
        }
    });
});

// Status tab filtering - FIXED VERSION
document.querySelectorAll('.status-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Update active tab
        document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const status = this.dataset.status;
        const courseColumns = document.querySelectorAll('.course-column');
        
        courseColumns.forEach(column => {
            const courseStatus = column.dataset.courseStatus;
            
            if (status === 'all') {
                column.style.display = '';
            } else {
                column.style.display = courseStatus === status ? '' : 'none';
            }
        });
        
        // Show empty state if no courses match the filter
        const visibleCourses = document.querySelectorAll('.course-column[style=""]').length;
        const noCoursesElement = document.querySelector('.col-12 .card.text-center');
        
        if (visibleCourses === 0 && noCoursesElement) {
            noCoursesElement.closest('.col-12').style.display = '';
        }
    });
});

// Course status toggle function (from original)
function toggleCourseStatus(courseID, action) {
    fetch('ajax_toggle_course_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            courseID: courseID,
            action: action
        })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            Swal.fire('Success', res.message, 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Server error', 'error'));
}

// Delete confirmation
function confirmDelete(courseID, courseTitle) {
    Swal.fire({
        title: 'Delete Course?',
        html: `Are you sure you want to delete <strong>${courseTitle}</strong>?<br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Add your delete API call here
            fetch('ajax_delete_course.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    courseID: courseID
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Deleted!', 'Course has been deleted.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to delete course', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Server error', 'error'));
        }
    });
}

// Show create course modal (from original)
function showCreateCourseModal() {
    Swal.fire({
        title: 'Create New Course',
        html: `
            <form id="createCourseForm" enctype="multipart/form-data" style="text-align:left;">
                <div class="mb-3">
                    <label>Course Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="Programming">Programming</option>
                        <option value="Design">Design</option>
                        <option value="Business">Business</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Price (₱)</label>
                    <input type="number" name="price" class="form-control" step="0.01" value="0">
                </div>
                <div class="mb-3"><small class="text-muted">Note: Upload lesson files later in <strong>Manage Course</strong> after creating the course.</small></div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Create Course',
        preConfirm: () => {
            const form = document.getElementById('createCourseForm');
            if (!form.title.value) Swal.showValidationMessage('Please enter a course title');
            return new FormData(form);
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            data.append('action','create_course');
            fetch('ajax_create_course.php',{
                method:'POST',
                body:data
            }).then(res=>res.json())
              .then(res=>{
                  if(res.success) Swal.fire('Success','Course created!','success').then(()=>location.reload());
                  else Swal.fire('Error',res.message,'error');
              }).catch(()=>Swal.fire('Error','Something went wrong','error'));
        }
    });
}
</script>
</body>
</html>