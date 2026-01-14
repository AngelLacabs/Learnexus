<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$courseID = $_GET['course_id'] ?? 0;
$teacherID = $_SESSION['user_id'];

// Verify course ownership
$stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ? AND teacherID = ?");
$stmt->execute([$courseID, $teacherID]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: enrollees.php');
    exit();
}

// Get enrollees
$stmt = $conn->prepare("
    SELECT u.userID, u.firstName, u.lastName, u.studentNumber, 
           e.enrolledAt, e.progressPercentage, e.status
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    WHERE e.courseID = ?
    ORDER BY u.lastName, u.firstName
");
$stmt->execute([$courseID]);
$enrollees = $stmt->fetchAll();

// Get instructor data including avatar
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ?");
$stmt->execute([$teacherID]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Enrollees - <?php echo htmlspecialchars($course['title']); ?> - Learnexus</title>
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

        /* Back Button */
        .btn-back {
            background: white;
            color: #666;
            border: 1px solid #e5e7eb;
            padding: 10px 20px;
            border-radius: 8px;
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
            color: #374151;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* User Avatar */
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* Course Card */
        .course-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            margin-bottom: 30px;
        }

        .course-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* Table Styles */
        .enrollees-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .enrollees-table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #eaeaea;
            padding: 16px;
            font-weight: 600;
            color: #374151;
        }

        .enrollees-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .enrollees-table tbody tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }

        .enrollees-table tbody tr:last-child td {
            border-bottom: none;
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
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="view_enrollees.php?course_id=<?php echo $courseID; ?>">
                    <i class="bi bi-people fs-5"></i><span>Enrollees</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="quizzes.php">
                    <i class="bi bi-patch-question fs-5"></i><span>Quizzes</span>
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
            <!-- Header with Profile -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <a href="enrollees.php" class="btn-back d-flex align-items-center gap-2 text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Back to Enrollees
                            </a>
                            
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

            <!-- Course Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card course-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h1 class="h2 fw-bold mb-2"><?php echo htmlspecialchars($course['title']); ?></h1>
                                    <p class="mb-0 opacity-75">List of Enrollees</p>
                                </div>
                                <span class="course-badge">
                                    <?php echo count($enrollees); ?> Students
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollees Table -->
            <div class="table-card p-4">
                <h5 class="fw-bold mb-4">Total of <?php echo count($enrollees); ?> Enrollees</h5>
                
                <?php if (count($enrollees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table enrollees-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Name of Enrollees</th>
                                    <th>Program</th>
                                    <th>Campuses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollees as $index => $student): ?>
                                    <tr onclick="window.location.href='student_status.php?user_id=<?php echo $student['userID']; ?>&course_id=<?php echo $courseID; ?>'">
                                        <td class="fw-bold"><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                        <td>BS Computer Science</td>
                                        <td>PUP Sta. Mesa, Manila</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people display-1 text-muted mb-3"></i>
                        <h3 class="h5 fw-bold mb-2">No Enrollees Yet</h3>
                        <p class="text-muted mb-0">No students have enrolled in this course yet.</p>
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
</script>
</body>
</html>