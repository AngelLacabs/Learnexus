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
// NEW - querying 'quiz_questions' table (the actual table name)
$stmt = $conn->prepare("
    SELECT q.*, c.title as courseTitle,
           (SELECT COUNT(*) FROM quiz_questions WHERE quizID = q.quizID) as questionCount
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

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            width: 220px;
        }

        .course-filter {
            position: relative;
            width: 100%;
        }

        .filter-dropdown {
            width: 100%;
            padding: 10px 35px 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: #f9fafb;
            color: #374151;
            font-size: 14px;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            font-weight: 500;
        }

        .course-filter::after {
            content: "▾";
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7d4fab;
            font-size: 18px;
            pointer-events: none;
            font-weight: bold;
        }

        /* Tabs Section */
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

        /* Quiz Grid */
        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .quiz-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .quiz-card:hover {
            transform: translateY(-5px);
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
            color: #2d3436;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 48px;
        }

        .quiz-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quiz-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .quiz-stat {
            text-align: center;
        }

        .quiz-stat .number {
            font-size: 20px;
            font-weight: 700;
            color: #2d3436;
            display: block;
        }

        .quiz-stat .label {
            font-size: 11px;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-quiz-action {
            background: linear-gradient(135deg, #7fb3cd 0%, #7d4fab 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-quiz-action:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(125, 79, 171, 0.3);
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

        /* Add Quiz Button */
        .add-quiz-btn {
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

        .add-quiz-btn:hover {
            background: linear-gradient(135deg, #6fa3bd 0%, #6d3f9b 100%);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 8px 20px rgba(125, 79, 171, 0.5);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .quiz-grid {
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
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-profile {
                align-self: flex-start;
            }
            
            .filter-section {
                width: 100%;
                margin-top: 15px;
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
                    <h1>Quizzes</h1>
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

            <!-- Tabs Section -->
            <div class="tabs-section">
                <!-- Filter -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <div class="tabs">
                        <div class="tab active" data-tab="all">All Quizzes</div>
                        <div class="tab" data-tab="draft">Draft</div>
                        <div class="tab" data-tab="finished">Finished</div>
                    </div>
                    
                    <div class="filter-section">
                        <div class="course-filter">
                            <select class="filter-dropdown" id="courseFilter">
                                <option value="">Per Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['courseID']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Quiz Grid -->
                <div class="quiz-grid" id="quizGrid">
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
                                    <div class="quiz-meta">
                                        <i class="bi bi-book"></i> <?php echo htmlspecialchars($quiz['courseTitle']); ?>
                                    </div>
                                    
                                    <div class="quiz-info">
                                        <div class="quiz-stat">
                                            <span class="number"><?php echo $quiz['questionCount']; ?></span>
                                            <span class="label">Questions</span>
                                        </div>
                                        <div class="quiz-stat">
                                            <span class="number">
                                                <?php 
                                                // Safe check for timeLimitMinutes
                                                if (isset($quiz['timeLimitMinutes']) && $quiz['timeLimitMinutes'] > 0) {
                                                    echo $quiz['timeLimitMinutes'] . 'm';
                                                } else {
                                                    echo '∞';
                                                }
                                                ?>
                                            </span>
                                            <span class="label">Time Limit</span>
                                        </div>
                                        <div class="quiz-stat">
                                            <span class="number">
                                                <?php 
                                                // Safe check for passingScore
                                                if (isset($quiz['passingScore']) && $quiz['passingScore'] > 0) {
                                                    echo $quiz['passingScore'] . '%';
                                                } else {
                                                    echo '0%';
                                                }
                                                ?>
                                            </span>
                                            <span class="label">Passing Score</span>
                                        </div>
                                    </div>
                                    
                                    <button class="btn-quiz-action" 
                                            onclick="window.location.href='edit_quiz.php?id=<?php echo $quiz['quizID']; ?>'">
                                        <i class="bi bi-pencil"></i>
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