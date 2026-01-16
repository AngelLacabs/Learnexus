<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$userAvatar = $stmt->fetchColumn();

// Get teacher's courses and whether each course already has a quiz (quizCount)
$stmt = $conn->prepare("SELECT c.courseID, c.title, (SELECT COUNT(*) FROM quizzes q WHERE q.courseID = c.courseID) as quizCount FROM courses c WHERE c.teacherID = ? ORDER BY c.title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Preselect from query string if provided
$preselectCourse = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$selectedCourseId = $preselectCourse;

// If preselectCourse already has a quiz, redirect to edit that quiz
if ($preselectCourse) {
    $stmt = $conn->prepare("SELECT quizID FROM quizzes WHERE courseID = ? LIMIT 1");
    $stmt->execute([$preselectCourse]);
    $existingQuiz = $stmt->fetchColumn();
    if ($existingQuiz) {
        header('Location: edit_quiz.php?id=' . $existingQuiz);
        exit();
    }
}

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseID = $_POST['courseID'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $passingScore = intval($_POST['passingScore'] ?? 70);
    $timeLimitMinutes = !empty($_POST['timeLimitMinutes']) ? intval($_POST['timeLimitMinutes']) : null;
    $allowRetake = isset($_POST['allowRetake']) ? 1 : 0;
    
    if ($courseID && $title) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO quizzes (courseID, title, description, passingScore, timeLimitMinutes, allowRetake, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$courseID, $title, $description, $passingScore, $timeLimitMinutes, $allowRetake]);
            $quizID = $conn->lastInsertId();
            
            // FIX: ADD SUCCESS MESSAGE HERE
            $_SESSION['success'] = "Quiz created successfully! You can now add questions to your quiz.";
            
            header('Location: edit_quiz.php?id=' . $quizID);
            exit();
        } catch (PDOException $e) {
            // FIX: Use session for error message too
            $_SESSION['error'] = "Failed to create quiz. Please try again.";
            header('Location: create_quiz.php');
            exit();
        }
    } else {
        // FIX: Use session for error message
        $_SESSION['error'] = "Please fill in all required fields.";
        header('Location: create_quiz.php');
        exit();
    }
}

// Check for success/error messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Learnexus</title>
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

        /* Sidebar - EXACTLY matching dashboard */
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

        /* Navigation - EXACTLY matching dashboard */
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

        /* Hamburger - EXACTLY matching dashboard */
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

        /* Main Content Margin - EXACTLY matching dashboard */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        /* Card Hover Effects */
        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
        }

        /* Form Styles */
        .form-control, .form-select {
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            transition: border-color 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-outline-secondary {
            border-color: #e5e7eb;
            color: #6b7280;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-outline-secondary:hover {
            background: #f8f9fa;
            border-color: #d1d5db;
            color: #374151;
            transform: translateY(-2px);
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form Labels */
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        /* Alert */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px;
            margin-bottom: 20px;
        }

        /* Course Select Option */
        .course-option {
            padding: 8px 0;
        }

        .course-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Checkbox Styling */
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        .form-check-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .form-check-label {
            cursor: pointer;
        }

        /* Back Button */
        .btn-back {
            background: white;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .btn-back:hover {
            background: #f8f9fa;
            border-color: #d1d5db;
            color: #374151;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Hamburger Button (Mobile) - EXACTLY matching dashboard -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar - EXACTLY matching dashboard -->
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
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
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
        <div class="container-fluid" style="max-width: 1200px;">
            <!-- Breadcrumb & User -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="quizzes.php" class="text-decoration-none">Quizzes</a></li>
                                    <li class="breadcrumb-item active">Create Quiz</li>
                                </ol>
                            </nav>
                            
                            <div class="d-flex align-items-center gap-3" style="flex-shrink: 0;">
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

            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 fw-bold"><i class="bi bi-plus-circle me-2"></i>Create New Quiz</h1>
                    <p class="text-muted">Design your quiz with multiple choice questions</p>
                </div>
            </div>

            <!-- Main Content Card -->
            <div class="row">
                <div class="col-12">
                    <div class="section-card card-hover">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label">Select Course *</label>
                                <select name="courseID" class="form-select" required>
                                    <option value="">Choose a course...</option>
                                    <?php foreach ($courses as $course): ?>
                                        <?php $disabled = $course['quizCount'] > 0 ? 'disabled' : ''; ?>
                                        <?php $selected = $selectedCourseId && $selectedCourseId == $course['courseID'] ? 'selected' : ''; ?>
                                        <option value="<?php echo $course['courseID']; ?>" <?php echo $disabled . ' ' . $selected; ?> class="course-option <?php echo $disabled ? 'disabled' : ''; ?>">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                            <?php if ($course['quizCount'] > 0): ?>
                                                <span class="text-muted">(Quiz exists)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-info-circle me-1"></i> Only courses without existing quizzes are available for selection.
                                </small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Quiz Title *</label>
                                <input type="text" name="title" class="form-control" required 
                                       placeholder="Enter quiz title (e.g., Midterm Exam, Chapter 1 Quiz)">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" 
                                          placeholder="Optional description about the quiz (e.g., This quiz covers chapters 1-3)"></textarea>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Passing Score (%)</label>
                                    <input type="number" name="passingScore" class="form-control" 
                                           value="70" min="0" max="100" required>
                                    <small class="text-muted mt-2 d-block">
                                        <i class="bi bi-percent me-1"></i> Minimum score required to pass
                                    </small>
                                </div>
                                
                            </div>

                        

                            <div class="d-flex gap-3 justify-content-end">
                                <a href="quizzes.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn-gradient">
                                    <i class="bi bi-plus-circle me-2"></i> Create Quiz
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hamburger animation - EXACTLY matching dashboard
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        // Active nav state - EXACTLY matching dashboard
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop();
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            }
            
            // Close sidebar
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                    if (offcanvas) offcanvas.hide();
                }
            });
        });

        // Show SweetAlert notifications if they exist
        <?php if (isset($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>

        <?php if (isset($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($error); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>
    </script>
</body>
</html>