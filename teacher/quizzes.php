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

// Check for success message from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check for error message from session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get all quizzes by teacher with course info
// NEW - querying 'quizquestions' table (the actual table name)
$stmt = $conn->prepare("
    SELECT q.*, c.title as courseTitle,
           (SELECT COUNT(*) FROM quizquestions WHERE quizID = q.quizID) as questionCount
    FROM quizzes q
    JOIN courses c ON q.courseID = c.courseID
    WHERE c.teacherID = ?
    ORDER BY q.createdAt DESC
");
$stmt->execute([$teacherID]);
$quizzes = $stmt->fetchAll();

// Get courses for dropdown filter
$stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE teacherID = ? ORDER BY title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - Learnexus</title>
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

        /* Quiz Cards - Matching student design */
        .quiz-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            height: 100%;
        }

        .quiz-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        /* Status Badges */
        .badge-draft {
            background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }

        .badge-finished {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
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

        /* Quiz Image Placeholder */
        .quiz-img-placeholder {
            height: 160px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            border-radius: 16px 16px 0 0;
            position: relative;
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

        /* Course Filter */
        .filter-dropdown {
            border: 1px solid #dee2e6;
            background: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .filter-dropdown:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        /* Add Quiz Button */
        .add-quiz-btn {
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

        .add-quiz-btn:hover {
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

        /* Quiz Stats */
        .quiz-stat-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }

        .quiz-stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #2d3436;
            display: block;
        }

        .quiz-stat-label {
            font-size: 12px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        <div class="container-fluid">
            <!-- Header with Search and Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <i class="bi bi-search search-icon position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                                <input type="text" id="quizSearch" class="form-control search-input rounded-pill ps-5" 
                                       placeholder="Search your quizzes..." autocomplete="off">
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
                    <h1 class="h3 fw-bold"><i class="bi bi-patch-question me-2"></i>Quizzes</h1>
                    <p class="text-muted">Manage and organize your quizzes</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <?php
                // Calculate quiz counts
                $totalQuizzes = count($quizzes);
                $draftQuizzes = count(array_filter($quizzes, fn($q) => $q['questionCount'] == 0));
                $finishedQuizzes = count(array_filter($quizzes, fn($q) => $q['questionCount'] > 0));
                ?>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <i class="bi bi-list-check fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Quizzes</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $totalQuizzes; ?></h3>
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
                                    <i class="bi bi-clock-history fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Draft</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $draftQuizzes; ?></h3>
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
                                    <h6 class="text-muted mb-1">Finished</h6>
                                    <h3 class="fw-bold mb-0"><?php echo $finishedQuizzes; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Tabs and Filter -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="status-tab active" data-status="all">All Quizzes</button>
                                    <button class="status-tab" data-status="draft">Draft (<?php echo $draftQuizzes; ?>)</button>
                                    <button class="status-tab" data-status="finished">Finished (<?php echo $finishedQuizzes; ?>)</button>
                                </div>
                                
                                <div>
                                    <select class="form-select filter-dropdown" id="courseFilter">
                                        <option value="">All Courses</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['courseID']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quiz Grid -->
            <div class="row g-4" id="quizzesContainer">
                <?php if (count($quizzes) > 0): ?>
                    <?php foreach ($quizzes as $quiz): ?>
                        <?php 
                            $statusClass = $quiz['questionCount'] > 0 ? 'finished' : 'draft';
                            $statusText = $quiz['questionCount'] > 0 ? 'Finished' : 'Draft';
                        ?>
                        <div class="col-12 col-md-6 col-lg-4 quiz-column" data-quiz-status="<?php echo $statusClass; ?>" data-course-id="<?php echo $quiz['courseID']; ?>">
                            <div class="card quiz-card">
                                <div class="quiz-img-placeholder">
                                    <span class="position-absolute top-0 start-0 m-3 badge badge-<?php echo $statusClass; ?> rounded-pill px-3 py-1">
                                        <?php echo $statusText; ?>
                                    </span>
                                    <i class="bi bi-patch-question"></i>
                                </div>
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-2 text-truncate"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-book"></i> <?php echo htmlspecialchars($quiz['courseTitle']); ?>
                                            </p>
                                        </div>
                                        
                                    </div>
                                    
                                    <!-- Quiz Stats -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-4">
                                            <div class="quiz-stat-card">
                                                <span class="quiz-stat-number"><?php echo $quiz['questionCount']; ?></span>
                                                <span class="quiz-stat-label">Questions</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="quiz-stat-card">
                                                <span class="quiz-stat-number">
                                                    <?php 
                                                    if (isset($quiz['timeLimitMinutes']) && $quiz['timeLimitMinutes'] > 0) {
                                                        echo $quiz['timeLimitMinutes'] . 'm';
                                                    } else {
                                                        echo '∞';
                                                    }
                                                    ?>
                                                </span>
                                                <span class="quiz-stat-label">Time</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="quiz-stat-card">
                                                <span class="quiz-stat-number">
                                                    <?php 
                                                    if (isset($quiz['passingScore']) && $quiz['passingScore'] > 0) {
                                                        echo $quiz['passingScore'] . '%';
                                                    } else {
                                                        echo '0%';
                                                    }
                                                    ?>
                                                </span>
                                                <span class="quiz-stat-label">Passing</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button class="btn btn-gradient w-100 rounded-pill fw-semibold" 
                                            onclick="window.location.href='edit_quiz.php?id=<?php echo $quiz['quizID']; ?>'">
                                        <i class="bi bi-pencil me-2"></i>
                                        <?php echo $quiz['questionCount'] > 0 ? 'Edit Quiz' : 'Continue Creating'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-patch-question empty-state-icon mb-3"></i>
                                <h3 class="h5 fw-bold mb-3">No Quizzes Yet</h3>
                                <p class="text-muted mb-4">You haven't created any quizzes yet. Create your first quiz to get started!</p>
                                <button class="btn btn-gradient rounded-pill px-4 fw-semibold" onclick="window.location.href='create_quiz.php'">
                                    <i class="bi bi-plus me-2"></i>Create Quiz
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Quiz Button -->
    <button class="add-quiz-btn" onclick="window.location.href='create_quiz.php'">
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
document.getElementById('quizSearch').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('.quiz-card').forEach(card => {
        const title = card.querySelector('.fw-bold').textContent.toLowerCase();
        const container = card.closest('.quiz-column');
        if (title.includes(term)) {
            container.style.display = '';
        } else {
            container.style.display = 'none';
        }
    });
});

// Status tab filtering
document.querySelectorAll('.status-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Update active tab
        document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        filterQuizzes();
    });
});

// Course filter
document.getElementById('courseFilter').addEventListener('change', filterQuizzes);

function filterQuizzes() {
    const statusFilter = document.querySelector('.status-tab.active').dataset.status;
    const courseFilter = document.getElementById('courseFilter').value;
    const quizColumns = document.querySelectorAll('.quiz-column');
    
    quizColumns.forEach(column => {
        const quizStatus = column.dataset.quizStatus;
        const courseId = column.dataset.courseId;
        
        let showColumn = true;
        
        // Status filter
        if (statusFilter !== 'all') {
            showColumn = quizStatus === statusFilter;
        }
        
        // Course filter
        if (courseFilter && showColumn) {
            showColumn = courseId === courseFilter;
        }
        
        column.style.display = showColumn ? '' : 'none';
    });
    
    // Show empty state if no quizzes match the filter
    const visibleQuizzes = document.querySelectorAll('.quiz-column[style=""]').length;
    const noQuizzesElement = document.querySelector('.col-12 .card.text-center');
    
    if (visibleQuizzes === 0 && noQuizzesElement) {
        noQuizzesElement.closest('.col-12').style.display = '';
    } else if (noQuizzesElement) {
        noQuizzesElement.closest('.col-12').style.display = 'none';
    }
}

// Delete confirmation
function confirmDelete(quizID, quizTitle) {
    if (confirm(`Are you sure you want to delete "${quizTitle}"? This action cannot be undone.`)) {
        // Add your delete API call here
        fetch('ajax_delete_quiz.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                quizID: quizID
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert('Quiz deleted successfully!');
                location.reload();
            } else {
                alert(res.message || 'Failed to delete quiz');
            }
        })
        .catch(() => alert('Server error'));
    }
}

<?php if (isset($success)): ?>
// Show success toast
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
// Show error toast
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