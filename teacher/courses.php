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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }

        /* Main Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Left side like the image */
        .sidebar {
            width: 250px;
            background: white;
            padding: 30px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 25px 30px;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 30px;
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3436;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 0 20px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: #636e72;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
        }

        .menu-item:hover {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.2);
        }

        .menu-item i {
            font-size: 18px;
            width: 24px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            padding: 0 25px;
        }

        /* UPDATED: Sidebar Logout Button - Simple Red Hover */
        .menu-item.logout-item {
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
            margin: 10px 16px;
            border-radius: 20px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item.logout-item:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        /* Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 8px;
        }

        .header-left p {
            color: #636e72;
            font-size: 16px;
        }

        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: transform 0.2s;
            border: 1px solid #f0f0f0;
        }

        .user-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .user-info p {
            font-size: 12px;
            color: #666;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #388e3c;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #f57c00;
        }

        .stat-content h3 {
            font-size: 12px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .stat-content .number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3436;
        }

        /* Tabs */
        .tabs-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            padding: 0 0 15px 0;
        }

        .tab {
            padding: 12px 24px;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            border-radius: 6px 6px 0 0;
        }

        .tab.active {
            color: #7d4fab;
            border-bottom-color: #7d4fab;
            background: rgba(125, 79, 171, 0.05);
        }

        .tab:hover:not(.active) {
            color: #7d4fab;
            background: rgba(125, 79, 171, 0.05);
        }

        /* Course Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-5px);
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
            color: #2d3436;
            margin-bottom: 12px;
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
            display: flex;
            align-items: center;
            gap: 8px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
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

        .btn-manage {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-manage:hover {
            background: linear-gradient(135deg, #0da271 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
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
            color: #636e72;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #9ca3af;
            margin: 0;
        }

        /* Add Course Button */
        .add-course-btn {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            border: none;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 998;
        }

        .add-course-btn:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 8px 20px rgba(125, 79, 171, 0.5);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }
            
            .sidebar-title, .menu-item span, .user-info h4, .user-info p {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-profile {
                align-self: flex-start;
            }
            
            .tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">LEARNEXUS</div>
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
                <!-- UPDATED: Simple Red Hover Logout Button -->
                <a href="../logout.php" class="menu-item logout-item">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <h1>My Courses</h1>
                    <p>Manage and spread your knowledge</p>
                </div>
                
                <!-- User Profile -->
                <div class="user-profile" onclick="window.location.href='settings.php'">
                    <div class="user-avatar">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h4>
                        <p>Teacher</p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Courses in Progress</h3>
                        <div class="number"><?php echo count($inProgress); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Completed Courses</h3>
                        <div class="number"><?php echo count($completed); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-bookmark-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Approval</h3>
                        <div class="number"><?php echo count($approved); ?></div>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="tabs-section">
                <div class="tabs">
                    <div class="tab active" data-tab="all">All Courses</div>
                    <div class="tab" data-tab="ongoing">On Going</div>
                    <div class="tab" data-tab="completed">Completed</div>
                    <div class="tab" data-tab="approved">Approved</div>
                </div>

                <!-- Course Grid -->
                <div class="courses-grid" id="courseGrid">
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
                                    <div class="course-meta">
                                        <i class="bi bi-person"></i> Own by you
                                        <i class="bi bi-people"></i> <?php echo $course['studentCount']; ?> students
                                    </div>
                                    
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
                                        <!-- Manage Course Button -->
                                        <button class="btn-action btn-manage" onclick="window.location.href='manage_course.php?id=<?php echo $course['courseID']; ?>'">
                                            <i class="bi bi-gear"></i> Manage
                                        </button>
                                        
                                        <!-- Publish/Unpublish Button -->
                                        <?php if ($course['status'] === 'published'): ?>
                                            <button class="btn-action btn-published" onclick="toggleCourseStatus(<?php echo $course['courseID']; ?>, 'unpublish')">
                                                <i class="bi bi-x-circle"></i> Unpublish
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-continue" onclick="toggleCourseStatus(<?php echo $course['courseID']; ?>, 'publish')">
                                                <i class="bi bi-check-circle"></i> Publish
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