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
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .top-nav {
            background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .brand { font-size: 20px; font-weight: 700; color: #1a73e8; }
        .nav-menu { display: flex; gap: 30px; align-items: center; }
        .nav-link { color: #666; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .nav-link:hover, .nav-link.active { color: #1a73e8; }
        .user-section { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .container-main { max-width: 1200px; margin: 40px auto; padding: 0 40px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
        .page-header p { color: #666; margin: 0; }
        .stats-row { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-card { flex: 1; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-card .icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; font-size: 20px; }
        .stat-card.blue .icon { background: #e3f2fd; color: #1e88e5; }
        .stat-card.green .icon { background: #e8f5e9; color: #43a047; }
        .stat-card.orange .icon { background: #fff3e0; color: #fb8c00; }
        .stat-card h3 { font-size: 13px; color: #666; margin: 0 0 5px 0; }
        .stat-card .number { font-size: 28px; font-weight: 700; color: #333; }
        .tabs { display: flex; gap: 20px; border-bottom: 2px solid #e0e0e0; margin-bottom: 30px; }
        .tab { padding: 10px 20px; color: #666; font-weight: 500; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab.active { color: #1a73e8; border-bottom-color: #1a73e8; }
        .course-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .course-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; position: relative; }
        .course-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .course-image { width: 100%; height: 180px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); display: flex; align-items: center; justify-content: center; color: #999; font-size: 14px; position: relative; }
        .status-badge { position: absolute; top: 12px; left: 12px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-badge.completed { background: #e8f5e9; color: #43a047; }
        .status-badge.ongoing { background: #e3f2fd; color: #1e88e5; }
        .status-badge.approved { background: #fff3e0; color: #fb8c00; }
        .course-body { padding: 20px; }
        .course-title { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .course-meta { font-size: 13px; color: #666; margin-bottom: 15px; }
        .progress-section { margin-bottom: 15px; }
        .progress-label { font-size: 12px; color: #666; margin-bottom: 5px; display: flex; justify-content: space-between; }
        .progress { height: 8px; border-radius: 10px; background: #f0f0f0; }
        .progress-bar { border-radius: 10px; }
        .progress-bar.finished { background: linear-gradient(90deg, #43a047 0%, #66bb6a 100%); }
        .progress-bar.progress { background: linear-gradient(90deg, #1e88e5 0%, #42a5f5 100%); }
        .course-actions { display: flex; gap: 10px; }
        .btn-action { flex: 1; padding: 10px; border-radius: 8px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-published { background: #e0e0e0; color: #666; }
        .btn-continue { background: #1e88e5; color: white; }
        .btn-continue:hover { background: #1565c0; }
        .btn-ready { background: #ffc107; color: #333; }
        .add-course-btn { position: fixed; bottom: 40px; right: 40px; width: 60px; height: 60px; border-radius: 50%; background: #1e88e5; color: white; border: none; font-size: 28px; box-shadow: 0 4px 12px rgba(30, 136, 229, 0.4); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .add-course-btn:hover { background: #1565c0; transform: scale(1.05); }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <a href="dashboard.php" class="brand text-decoration-none">LEARNEXUS</a>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="courses.php" class="nav-link active">Courses</a>
            <a href="quizzes.php" class="nav-link">Quizzes</a>
            <a href="enrollees.php" class="nav-link">Enrollees</a>
        </div>
        <div class="user-section">
            <i class="bi bi-bell" style="font-size: 22px; color: #666; cursor: pointer;"></i>
            <a href="settings.php" style="font-weight: 600; color: #333; text-decoration: none;">
                <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
            </a>
            <a href="settings.php" style="display: inline-block; text-decoration: none;">
                <div class="user-avatar">
                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-main">
        <div class="page-header">
            <h1>My Courses</h1>
            <p>Manage and spread your knowledge</p>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card blue">
                <div class="icon"><i class="bi bi-clock-history"></i></div>
                <h3>Courses in Progress</h3>
                <div class="number"><?php echo count($inProgress); ?></div>
            </div>
            <div class="stat-card green">
                <div class="icon"><i class="bi bi-check-circle"></i></div>
                <h3>Completed Courses</h3>
                <div class="number"><?php echo count($completed); ?></div>
            </div>
            <div class="stat-card orange">
                <div class="icon"><i class="bi bi-bookmark-check"></i></div>
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
            <?php foreach ($courses as $course): ?>
                <?php 
                    $completion = calculateCompletion($course['moduleCount']);
                    $statusClass = $course['status'] == 'published' ? 'completed' : ($course['status'] == 'archived' ? 'approved' : 'ongoing');
                    $statusText = $course['status'] == 'published' ? 'Completed' : ($course['status'] == 'archived' ? 'Approved' : 'Ongoing');
                ?>
                <div class="course-card" data-status="<?php echo $statusClass; ?>">
                    <div class="course-image">
                        <span class="status-badge <?php echo $statusClass; ?>">• <?php echo $statusText; ?></span>
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
                            <?php if ($course['status'] == 'published'): ?>
                                <button class="btn-action btn-published">Already Published</button>
                            <?php elseif ($course['status'] == 'archived'): ?>
                                <button class="btn-action btn-ready" onclick="window.location.href='edit_course.php?id=<?php echo $course['courseID']; ?>'">
                                    <i class="bi bi-download"></i> Ready to Publish
                                </button>
                            <?php else: ?>
                                <button class="btn-action btn-continue" onclick="window.location.href='edit_course.php?id=<?php echo $course['courseID']; ?>'">
                                    Continue Editing →
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Course Button -->
    <button class="add-course-btn" onclick="showCreateCourseModal()">
        <i class="bi bi-plus"></i>
    </button>

<script>
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
</body>
</html>
