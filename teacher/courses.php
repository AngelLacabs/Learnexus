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
           (SELECT COUNT(*) FROM modules WHERE courseID = c.courseID) as moduleCount
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f6fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 22px;
            font-weight: 700;
            color: white;
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .navbar-user:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .navbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: 700;
            overflow: hidden;
        }

        .navbar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            height: calc(100vh - 68px);
            background: white;
            position: fixed;
            left: 0;
            top: 68px;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            color: #374151;
        }
        
        .sidebar-menu {
            padding: 16px 0;
        }

        .menu-item {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 15px;
        }

        .menu-item:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .menu-item.active {
            background: linear-gradient(135deg,  #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
        }

        .menu-item i {
            font-size: 20px;
            width: 24px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #e5e7eb;
            margin-top: auto;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 68px;
            padding: 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 15px;
        }
        
        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .stat-card .icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 28px;
        }
        
        .stat-card.blue .icon {
            background: #e3f2fd;
            color: #1e88e5;
        }
        
        .stat-card.green .icon {
            background: #e8f5e9;
            color: #43a047;
        }
        
        .stat-card.orange .icon {
            background: #fff3e0;
            color: #fb8c00;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            background: white;
            padding: 0 20px;
            border-radius: 12px 12px 0 0;
        }

        .tab {
            padding: 16px 20px;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab.active {
            color: #1a73e8;
            border-bottom-color: #1a73e8;
        }

        .tab:hover:not(.active) {
            color: #1a73e8;
        }

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
        }

        .course-image {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            position: relative;
        }

        .status-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            backdrop-filter: blur(10px);
        }

        .status-badge.completed {
            background: rgba(67, 160, 71, 0.9);
            color: white;
        }

        .status-badge.ongoing {
            background: rgba(251, 140, 0, 0.9);
            color: white;
        }

        .status-badge.approved {
            background: rgba(30, 136, 229, 0.9);
            color: white;
        }

        .course-body {
            padding: 24px;
        }

        .course-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 48px;
        }

        .course-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }

        .progress-section {
            margin-bottom: 20px;
        }

        .progress-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
            background: #f0f0f0;
        }

        .progress-bar {
            border-radius: 10px;
        }

        .progress-bar.finished {
            background: linear-gradient(90deg, #43a047 0%, #66bb6a 100%);
        }

        .progress-bar.progress {
            background: linear-gradient(90deg, #1e88e5 0%, #42a5f5 100%);
        }

        .course-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }

        .btn-published {
            background: #f8f9fa;
            color: #666;
        }

        .btn-published:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .btn-continue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-continue:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-ready {
            background: #ffc107;
            color: #333;
        }

        .btn-ready:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 12px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #9ca3af;
            margin: 0;
        }

        .add-course-btn {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 998;
        }

        .add-course-btn:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-navbar">
        <a href="dashboard.php" class="navbar-brand">LEARNEXUS</a>
        <div class="navbar-user" onclick="window.location.href='settings.php'">
            <span style="font-weight: 600;">
                <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
            </span>
            <div class="navbar-avatar">
                <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Teacher Panel</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="courses.php" class="menu-item active">
                <i class="bi bi-book"></i>
                <span>Courses</span>
            </a>
            <a href="quizzes.php" class="menu-item">
                <i class="bi bi-patch-question"></i>
                <span>Quizzes</span>
            </a>
            <a href="enrollees.php" class="menu-item">
                <i class="bi bi-people"></i>
                <span>Enrollees</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-item">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>My Courses</h1>
            <p>Manage and spread your knowledge</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card blue">
                <div class="icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3>Courses in Progress</h3>
                <div class="number"><?php echo count($inProgress); ?></div>
            </div>
            
            <div class="stat-card green">
                <div class="icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3>Completed Courses</h3>
                <div class="number"><?php echo count($completed); ?></div>
            </div>
            
            <div class="stat-card orange">
                <div class="icon">
                    <i class="bi bi-bookmark-check"></i>
                </div>
                <h3>Pending Approval</h3>
                <div class="number"><?php echo count($approved); ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="all">All Courses</div>
            <div class="tab" data-tab="ongoing">On Going</div>
            <div class="tab" data-tab="completed">Completed</div>
            <div class="tab" data-tab="approved">Approved</div>
        </div>

        <!-- Course Grid -->
        <div class="course-grid" id="courseGrid">
            <?php if (count($courses) > 0): ?>
                <?php foreach ($courses as $course): ?>
                    <?php 
                        $completion = calculateCompletion($course['moduleCount']);
                        $statusClass = $course['status'] == 'published' ? 'completed' : ($course['status'] == 'archived' ? 'approved' : 'ongoing');
                        $statusText = $course['status'] == 'published' ? 'Published' : ($course['status'] == 'archived' ? 'Approved' : 'Draft');
                    ?>
                    <div class="course-card" data-status="<?php echo $statusClass; ?>">
                        <div class="course-image">
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            <i class="bi bi-book" style="font-size: 48px;"></i>
                        </div>
                        <div class="course-body">
                            <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                            <div class="course-meta">Own by you</div>
                            
                            <div class="progress-section">
                                <div class="progress-label">
                                    <span><?php echo $course['status'] == 'published' ? 'Finished' : 'Progress'; ?></span>
                                    <span><?php echo $completion; ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar <?php echo $course['status'] == 'published' ? 'finished' : 'progress'; ?>" 
                                         style="width: <?php echo $completion; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <?php if ($course['status'] === 'published'): ?>
                                    <button class="btn-action btn-published" onclick="toggleCourseStatus(<?php echo $course['courseID']; ?>, 'unpublish')">
                                        Unpublish
                                    </button>
                                <?php else: ?>
                                    <button class="btn-action btn-continue" onclick="toggleCourseStatus(<?php echo $course['courseID']; ?>, 'publish')">
                                        Publish Course
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-book"></i>
                    <h3>No Courses Yet</h3>
                    <p>You haven't created any courses yet. Click the + button to get started!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Course Button -->
    <button class="add-course-btn" onclick="showCreateCourseModal()">
        <i class="bi bi-plus"></i>
    </button>

<script>
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

// Tab filtering
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.tab;
        const cards = document.querySelectorAll('.course-card');
        cards.forEach(card => {
            if (filter === 'all') card.style.display = 'block';
            else card.style.display = card.dataset.status === filter ? 'block' : 'none';
        });
    });
});

// Show create course modal
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
                <div class="mb-3">
                    <label>Lesson File (PDF only)</label>
                    <input type="file" name="lesson_file" class="form-control" accept=".pdf">
                </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>