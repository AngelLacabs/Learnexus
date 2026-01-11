<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

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
        
        .brand {
            font-size: 20px;
            font-weight: 700;
            color: #1a73e8;
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
        }
        
        .nav-link {
            color: #666;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-link:hover, .nav-link.active {
            color: #1a73e8;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
        }
        
        .tab {
            padding: 10px 20px;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab.active {
            color: #1a73e8;
            border-bottom-color: #1a73e8;
        }
        
        .filter-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .course-filter {
            position: relative;
        }
        
        .filter-dropdown {
            padding: 8px 35px 8px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            min-width: 200px;
        }
        
        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
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
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
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
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1e88e5;
        }
        
        .status-badge.finished {
            background: #e8f5e9;
            color: #43a047;
        }
        
        .quiz-body {
            padding: 20px;
        }
        
        .quiz-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .add-quiz-btn {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1e88e5;
            color: white;
            border: none;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.4);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .add-quiz-btn:hover {
            background: #1565c0;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="brand">LEARNEXUS</div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="courses.php" class="nav-link">Courses</a>
            <a href="quizzes.php" class="nav-link active">Quizzes</a>
            <a href="enrollees.php" class="nav-link">Enrollees</a>
        </div>
        
        <div class="user-section">
            <i class="bi bi-bell" style="font-size: 22px; color: #666; cursor: pointer;"></i>
            <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-main">
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
                            <span class="status-badge <?php echo $quiz['questionCount'] > 0 ? 'finished' : ''; ?>">
                                • <?php echo $quiz['questionCount'] > 0 ? 'Finished' : 'Draft'; ?>
                            </span>
                            // photo
                        </div>
                        <div class="quiz-body">
                            <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                            <div style="font-size: 13px; color: #666; margin-bottom: 15px;">
                                <?php echo htmlspecialchars($quiz['courseTitle']); ?>
                            </div>
                            <button class="btn btn-primary btn-sm w-100" 
                                    onclick="window.location.href='edit_quiz.php?id=<?php echo $quiz['quizID']; ?>'">
                                <?php echo $quiz['questionCount'] > 0 ? 'Edit Quiz' : 'Continue Creating'; ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No quizzes created yet. Click the + button to create your first quiz!
                    </div>
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
</body>
</html>