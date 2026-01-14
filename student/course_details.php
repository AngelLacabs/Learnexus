<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['id'] ?? 0;
$userID = $_SESSION['user_id'];

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userAvatar = $stmt->fetchColumn();

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, CONCAT(u.firstName, ' ', u.lastName) as instructorName
    FROM courses c
    JOIN users u ON c.teacherID = u.userID
    WHERE c.courseID = ?
");
$stmt->execute([$courseID]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: course_catalog.php');
    exit();
}

// Check if already enrolled
$stmt = $conn->prepare("SELECT enrollmentID FROM enrollments WHERE userID = ? AND courseID = ?");
$stmt->execute([$userID, $courseID]);
$alreadyEnrolled = $stmt->fetch();

// Get lessons count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE courseID = ?");
$stmt->execute([$courseID]);
$lessonsCount = $stmt->fetch()['count'];

// Get quizzes count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE courseID = ?");
$stmt->execute([$courseID]);
$quizzesCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-accent: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            width: var(--sidebar-width);
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
            color: #444;
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

        .nav-link:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #1a73e8;
            transform: translateX(4px);
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: var(--gradient-primary);
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

        @media (min-width: 993px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }

        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
        }

        .course-hero {
            height: 250px;
            background: var(--gradient-primary);
        }

        .price-display {
            font-size: 3rem;
            font-weight: 800;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100" id="sidebar" tabindex="-1">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>
            
            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2" href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2" href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2" href="my_courses.php">
                    <i class="bi bi-journal-bookmark fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2" href="certificates.php">
                    <i class="bi bi-award fs-5"></i><span>Certificates</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2" href="vouchers.php">
                    <i class="bi bi-ticket-perforated fs-5"></i><span>Vouchers</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2" href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2" href="ai_tutor.php">
                    <i class="bi bi-robot fs-5"></i><span>AI Tutor</span>
                </a>
            </nav>
            
            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill py-2 fw-semibold" onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid" style="max-width: 1200px;">
            <!-- Breadcrumb & User -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="course_catalog.php" class="text-decoration-none">Catalog</a></li>
                                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($course['title']); ?></li>
                                </ol>
                            </nav>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button">
                                <span class="fw-semibold d-none d-sm-inline">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 45px; height: 45px; background: var(--gradient-primary);">
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

            <div class="row g-4">
                <!-- Left Column: Course Info -->
                <div class="col-12 col-lg-8">
                    <!-- Course Header -->
                    <div class="card border-0 rounded-4 shadow-sm card-hover mb-4">
                        <div class="card-body p-4">
                            <h1 class="h2 fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                            <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                            
                            <!-- Stats Badges -->
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                    <i class="bi bi-book me-1"></i> <?php echo $lessonsCount; ?> Lessons
                                </span>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                    <i class="bi bi-clipboard-check me-1"></i> <?php echo $quizzesCount; ?> Quizzes
                                </span>
                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2">
                                    <i class="bi bi-award me-1"></i> <?php echo $course['passingScore']; ?>% Passing
                                </span>
                            </div>
                            
                            <!-- Instructor -->
                            <div class="d-flex align-items-center gap-3 pt-3 border-top">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" 
                                     style="width: 48px; height: 48px; font-size: 1.2rem;">
                                    <?php echo strtoupper(substr($course['instructorName'], 0, 1)); ?>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Instructor</small>
                                    <strong><?php echo htmlspecialchars($course['instructorName']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- What You'll Learn -->
                    <div class="card border-0 rounded-4 shadow-sm card-hover">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">What you'll learn</h5>
                            <p class="text-muted mb-4">
                                This course includes <?php echo $lessonsCount; ?> comprehensive lessons and <?php echo $quizzesCount; ?> assessments. 
                                Achieve <?php echo $course['passingScore']; ?>% to earn your certificate.
                            </p>
                            
                            <div class="list-group list-group-flush">
                                <?php if ($lessonsCount > 0): ?>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-file-text text-primary me-2"></i>
                                    <?php echo $lessonsCount; ?> Reading Materials (PDF Documents)
                                </div>
                                <?php endif; ?>
                                <?php if ($quizzesCount > 0): ?>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-clipboard-check text-success me-2"></i>
                                    <?php echo $quizzesCount; ?> Assessments to Test Your Knowledge
                                </div>
                                <?php endif; ?>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-trophy text-warning me-2"></i>
                                    Certificate of Completion
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Enrollment Card -->
                <div class="col-12 col-lg-4">
                    <!-- Course Image -->
                    <div class="course-hero rounded-4 d-flex align-items-center justify-content-center mb-4 shadow-sm">
                        <i class="bi bi-book text-white" style="font-size: 4rem;"></i>
                    </div>
                    
                    <!-- Price Card -->
                    <div class="card border-0 rounded-4 shadow-sm card-hover sticky-top" style="top: 20px;">
                        <div class="card-body p-4">
                            <div class="price-display mb-4">₱<?php echo number_format($course['price'], 2); ?></div>
                            
                            <?php if ($alreadyEnrolled): ?>
                                <button class="btn btn-success w-100 rounded-pill py-3 fw-semibold mb-3" 
                                        onclick="window.location.href='view_course.php?id=<?php echo $courseID; ?>'">
                                    <i class="bi bi-check-circle me-2"></i>Go to Course
                                </button>
                                <div class="alert alert-success mb-0">
                                    <i class="bi bi-info-circle me-2"></i>You're already enrolled
                                </div>
                            <?php else: ?>
                                <button class="btn btn-primary w-100 rounded-pill py-3 fw-semibold mb-4" 
                                        style="background: var(--gradient-primary); border: none;"
                                        onclick="window.location.href='checkout.php?course_id=<?php echo $courseID; ?>'">
                                    <i class="bi bi-cart me-2"></i>Enroll Now
                                </button>
                            <?php endif; ?>
                            
                            <!-- Includes -->
                            <div class="pt-3 border-top">
                                <h6 class="fw-bold mb-3">This course includes:</h6>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item border-0 px-0 d-flex align-items-center gap-2">
                                        <i class="bi bi-book text-primary"></i>
                                        <span><?php echo $lessonsCount; ?> Lessons</span>
                                    </div>
                                    <div class="list-group-item border-0 px-0 d-flex align-items-center gap-2">
                                        <i class="bi bi-clipboard-check text-success"></i>
                                        <span><?php echo $quizzesCount; ?> Quizzes</span>
                                    </div>
                                    <div class="list-group-item border-0 px-0 d-flex align-items-center gap-2">
                                        <i class="bi bi-award text-warning"></i>
                                        <span>Certificate</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
    </script>
</body>
</html>