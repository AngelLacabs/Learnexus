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


// Get all quizzes by teacher with course info
$stmt = $conn->prepare("
    SELECT q.*, c.title as courseTitle,
           (SELECT COUNT(*) FROM questions WHERE quizID = q.quizID) as questionCount
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

        .filter-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }

        .course-filter {
            position: relative;
        }

        .filter-dropdown {
            padding: 12px 35px 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            min-width: 200px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .filter-dropdown:focus {
            outline: none;
            border-color: #1a73e8;
        }

        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .quiz-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .quiz-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
        }

        .quiz-image {
            width: 100%;
            height: 160px;
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

        .status-badge.finished {
            background: rgba(67, 160, 71, 0.9);
            color: white;
        }

        .status-badge.draft {
            background: rgba(251, 140, 0, 0.9);
            color: white;
        }

        .quiz-body {
            padding: 24px;
        }

        .quiz-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 48px;
        }

        .btn-quiz-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-quiz-action:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4098 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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

        .add-quiz-btn {
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
            transition: all 0.2s;
            z-index: 998;
        }

        .add-quiz-btn:hover {
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
            <a href="courses.php" class="menu-item">
                <i class="bi bi-book"></i>
                <span>Courses</span>
            </a>
            <a href="quizzes.php" class="menu-item active">
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
            <h1>Lots of Quizzes!</h1>
            <p>Manage and spread your knowledge</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="all">All Quizzes</div>
            <div class="tab" data-tab="draft">Draft</div>
            <div class="tab" data-tab="finished">Finished</div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <div class="course-filter">
                <select class="filter-dropdown" id="courseFilter">
                    <option value="">Per Courses ▼</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['courseID']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Quiz Grid -->
        <div class="quiz-grid">
            <?php if (count($quizzes) > 0): ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <div class="quiz-card" data-course="<?php echo $quiz['courseID']; ?>" data-status="<?php echo $quiz['questionCount'] > 0 ? 'finished' : 'draft'; ?>">
                        <div class="quiz-image">
                            <span class="status-badge <?php echo $quiz['questionCount'] > 0 ? 'finished' : 'draft'; ?>">
                                <?php echo $quiz['questionCount'] > 0 ? 'Finished' : 'Draft'; ?>
                            </span>
                            <i class="bi bi-patch-question" style="font-size: 48px;"></i>
                        </div>
                        <div class="quiz-body">
                            <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                            <div style="font-size: 14px; color: #666; margin-bottom: 20px;">
                                <?php echo htmlspecialchars($quiz['courseTitle']); ?>
                            </div>
                            <button class="btn-quiz-action" 
                                    onclick="window.location.href='edit_quiz.php?id=<?php echo $quiz['quizID']; ?>'">
                                <?php echo $quiz['questionCount'] > 0 ? 'Edit Quiz' : 'Continue Creating'; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-patch-question"></i>
                    <h3>No Quizzes Yet</h3>
                    <p>You haven't created any quizzes yet. Click the + button to create your first quiz!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Quiz Button -->
    <button class="add-quiz-btn" onclick="window.location.href='create_quiz.php'">
        <i class="bi bi-plus"></i>
    </button>

    <script>
        // Tab filtering
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.tab;
                filterQuizzes();
            });
        });

        // Course filter
        document.getElementById('courseFilter').addEventListener('change', filterQuizzes);

        function filterQuizzes() {
            const tabFilter = document.querySelector('.tab.active').dataset.tab;
            const courseFilter = document.getElementById('courseFilter').value;
            const cards = document.querySelectorAll('.quiz-card');
            
            cards.forEach(card => {
                let showCard = true;
                
                // Tab filter
                if (tabFilter !== 'all') {
                    showCard = card.dataset.status === tabFilter;
                }
                
                // Course filter
                if (courseFilter && showCard) {
                    showCard = card.dataset.course === courseFilter;
                }
                
                card.style.display = showCard ? 'block' : 'none';
            });
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>