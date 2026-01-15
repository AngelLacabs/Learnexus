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

// Get all enrolled courses - we'll calculate progress dynamically
$stmt = $conn->prepare("
    SELECT 
        c.*,
        e.enrollmentID,
        e.enrolledAt,
        e.completedAt,
        e.status as enrollmentStatus,
        CONCAT(u.firstName, ' ', u.lastName) as instructorName,
        u.avatar as instructorAvatar,
        p.amount as paidAmount
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    JOIN users u ON c.teacherID = u.userID
    LEFT JOIN payments p ON e.paymentID = p.paymentID
    WHERE e.userID = ? AND e.status IN ('active', 'completed')
    ORDER BY 
        CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END ASC,
        e.enrolledAt DESC
");
$stmt->execute([$userID]);
$enrolledCourses = $stmt->fetchAll();

// Calculate progress dynamically for each course (UNIFIED LOGIC)
foreach ($enrolledCourses as &$course) {
    // Get total lessons for this course
    $stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE courseID = ?");
    $stmt->execute([$course['courseID']]);
    $course['totalLessons'] = (int) $stmt->fetchColumn();
    
    // Get completed lessons for this course
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM lessoncompletion lc 
        JOIN lessons l ON lc.lessonID = l.lessonID
        WHERE lc.userID = ? AND l.courseID = ?
    ");
    $stmt->execute([$userID, $course['courseID']]);
    $course['completedLessons'] = (int) $stmt->fetchColumn();
    
    // Get quiz info
    $stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ?");
    $stmt->execute([$course['courseID']]);
    $course['quizID'] = $stmt->fetchColumn();
    
    // Check if quiz passed
    $course['quizPassed'] = false;
    $course['quizStatus'] = 'not_taken';
    
    if ($course['quizID']) {
        $stmt = $conn->prepare("
            SELECT status 
            FROM quizresults 
            WHERE userID = ? AND quizID = ?
            ORDER BY takenAt DESC
            LIMIT 1
        ");
        $stmt->execute([$userID, $course['quizID']]);
        $quizStatus = $stmt->fetchColumn();
        
        if ($quizStatus) {
            $course['quizStatus'] = $quizStatus;
            $course['quizPassed'] = ($quizStatus === 'passed');
        }
    } else {
        $course['quizStatus'] = 'not_available';
    }
    
    // === UNIFIED PROGRESS CALCULATION ===
    $totalSteps = $course['totalLessons'] + ($course['quizID'] ? 1 : 0);
    $completedSteps = $course['completedLessons'];
    
    if ($course['quizPassed']) {
        $completedSteps++;
    }
    
    $course['progressPercentage'] = $totalSteps > 0 
        ? round(($completedSteps / $totalSteps) * 100) 
        : 0;
    
    $course['isCompleted'] = ($course['enrollmentStatus'] === 'completed');
}

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
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

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

        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
        }

        .progress-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .progress-success {
            background: linear-gradient(90deg, #43a047 0%, #66bb6a 100%) !important;
        }
    </style>
</head>
<body>
    <!-- Hamburger Button -->
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
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="my_courses.php">
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
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="input-group" style="max-width: 500px;">
                                <span class="input-group-text bg-transparent border-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" id="courseSearch" class="form-control border-0" placeholder="Search your courses...">
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar" class="w-100 h-100 rounded-circle object-fit-cover">
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
                    <h1 class="h3 fw-bold"><i class="bi bi-journal-bookmark me-2"></i>My Courses</h1>
                    <p class="text-muted">Continue learning where you left off</p>
                </div>
            </div>

            <?php if (count($enrolledCourses) > 0): ?>
                
                <!-- Active Courses -->
                <?php if (count($activeCourses) > 0): ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 border-bottom pb-2">
                                <h2 class="h5 fw-bold mb-0">In Progress</h2>
                                <span class="badge bg-primary rounded-pill"><?php echo count($activeCourses); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4 mb-5">
                        <?php foreach ($activeCourses as $course): ?>
                            <div class="col-12 col-lg-6" data-course-id="<?php echo $course['enrollmentID']; ?>">
                                <div class="card border-0 rounded-4 shadow-sm card-hover h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <div class="d-flex flex-wrap gap-3 text-muted small">
                                                    <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($course['instructorName']); ?></span>
                                                    <span><i class="bi bi-calendar3"></i> <?php echo date('M d, Y', strtotime($course['enrolledAt'])); ?></span>
                                                    <?php if ($course['paidAmount'] > 0): ?>
                                                        <span><i class="bi bi-receipt"></i> ₱<?php echo number_format($course['paidAmount'], 2); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">FREE</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="course_learn.php?id=<?php echo $course['courseID']; ?>">
                                                        <i class="bi bi-play-circle"></i> Continue Learning
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" 
                                                           onclick="confirmDelete(<?php echo $course['enrollmentID']; ?>, '<?php echo htmlspecialchars(addslashes($course['title'])); ?>'); return false;">
                                                        <i class="bi bi-trash"></i> Unenroll
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar progress-gradient" style="width: <?php echo $course['progressPercentage']; ?>%"></div>
                                        </div>
                                        <p class="text-muted small mb-3"><?php echo round($course['progressPercentage']); ?>% Complete</p>
                                        
                                        <button class="btn btn-primary w-100 rounded-pill fw-semibold" 
                                                onclick="window.location.href='course_learn.php?id=<?php echo $course['courseID']; ?>'">
                                            <i class="bi bi-play-circle me-2"></i><?php echo $course['progressPercentage'] > 0 ? 'Continue' : 'Start Course'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Completed Courses -->
                <?php if (count($completedCourses) > 0): ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 border-bottom pb-2">
                                <h2 class="h5 fw-bold mb-0 text-success"><i class="bi bi-trophy-fill"></i> Completed</h2>
                                <span class="badge bg-success rounded-pill"><?php echo count($completedCourses); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4 mb-5">
                        <?php foreach ($completedCourses as $course): ?>
                            <div class="col-12 col-lg-6" data-course-id="<?php echo $course['enrollmentID']; ?>">
                                <div class="card border-0 rounded-4 shadow-sm card-hover h-100 border-success">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Completed</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-3 text-muted small">
                                                    <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($course['instructorName']); ?></span>
                                                    <?php if ($course['completedAt']): ?>
                                                        <span><i class="bi bi-trophy"></i> <?php echo date('M d, Y', strtotime($course['completedAt'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="course_learn.php?id=<?php echo $course['courseID']; ?>">
                                                        <i class="bi bi-eye"></i> Review Course
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" 
                                                           onclick="confirmDelete(<?php echo $course['enrollmentID']; ?>, '<?php echo htmlspecialchars(addslashes($course['title'])); ?>'); return false;">
                                                        <i class="bi bi-trash"></i> Remove
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar progress-success" style="width: 100%"></div>
                                        </div>
                                        <p class="text-success small fw-semibold mb-3"><i class="bi bi-check-circle-fill"></i> 100% Complete</p>
                                        
                                        <?php if ($course['quizStatus'] === 'failed'): ?>
                                            <button class="btn btn-warning w-100 rounded-pill fw-semibold" 
                                                    onclick="window.location.href='retake_course.php?id=<?php echo $course['courseID']; ?>'">
                                                <i class="bi bi-arrow-repeat me-2"></i>Retake Course
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-success w-100 rounded-pill fw-semibold" 
                                                    onclick="window.location.href='course_learn.php?id=<?php echo $course['courseID']; ?>'">
                                                <i class="bi bi-eye me-2"></i>Review Course
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-journal-x display-1 text-muted mb-3"></i>
                                <h3 class="h5 fw-bold mb-3">No Courses Yet</h3>
                                <p class="text-muted mb-4">You haven't enrolled in any courses. Start learning today!</p>
                                <a href="course_catalog.php" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                    <i class="bi bi-search me-2"></i>Browse Courses
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
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        // Search
        document.getElementById('courseSearch').addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('[data-course-id]').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // Delete confirmation
        function confirmDelete(enrollmentID, courseTitle) {
            Swal.fire({
                title: 'Unenroll from Course?',
                html: `Are you sure you want to unenroll from <strong>${courseTitle}</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Unenroll',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('unenroll_course.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `enrollment_id=${enrollmentID}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Unenrolled!', 'Course removed successfully', 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message || 'Failed to unenroll', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>