<?php
// ========================================
// FILE: teacher/student_status.php
// View individual student's quiz results and course progress
// ========================================
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];
$userID = $_GET['user_id'] ?? 0;

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE userID = ? AND role = 'student'");
$stmt->execute([$userID]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: enrollees.php');
    exit();
}

// Get student's enrollments in teacher's courses
$stmt = $conn->prepare("
    SELECT c.*, e.enrollmentID, e.progressPercentage, e.status
    FROM enrollments e
    JOIN courses c ON e.courseID = c.courseID
    WHERE e.userID = ? AND c.teacherID = ?
");
$stmt->execute([$userID, $teacherID]);
$enrolledCourses = $stmt->fetchAll();

// Get quiz results
$stmt = $conn->prepare("
    SELECT qr.*, q.title as quizTitle, c.title as courseTitle
    FROM quiz_results qr
    JOIN quizzes q ON qr.quizID = q.quizID
    JOIN courses c ON q.courseID = c.courseID
    WHERE qr.userID = ? AND c.teacherID = ?
    ORDER BY qr.submittedAt DESC
");
$stmt->execute([$userID, $teacherID]);
$quizResults = $stmt->fetchAll();

// Calculate overall passing/failing percentage
$totalQuizzes = count($quizResults);
$passedQuizzes = count(array_filter($quizResults, fn($q) => $q['status'] == 'passed'));
$passingPercentage = $totalQuizzes > 0 ? round(($passedQuizzes / $totalQuizzes) * 100) : 0;
$failingPercentage = 100 - $passingPercentage;

$activeTab = $_GET['tab'] ?? 'quizzes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollees' Status - Learnexus</title>
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
        
        .container-main {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
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
            text-decoration: none;
        }
        
        .tab.active {
            color: #1a73e8;
            border-bottom-color: #1a73e8;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .course-image {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.completed { background: #e8f5e9; color: #43a047; }
        .status-badge.ongoing { background: #e3f2fd; color: #1e88e5; }
        .brand {
    font-size: 22px;
    font-weight: 700;
    color: #1e88e5;
    text-decoration: none;
}

.brand:hover {
    text-decoration: none;
    color: #1565c0;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 8px;
    transition: background 0.2s;
}

.user-info:hover {
    background: #f5f5f5;
}

.user-name {
    font-weight: 600;
    color: #333;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}


    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <a href="dashboard.php" class="brand">LEARNEXUS</a>

        <div>
            <a href="dashboard.php" class="me-3 text-decoration-none text-muted">Dashboard</a>
            <a href="courses.php" class="me-3 text-decoration-none text-muted">Courses</a>
            <a href="quizzes.php" class="me-3 text-decoration-none text-muted">Quizzes</a>
            <a href="enrollees.php" class="me-3 text-decoration-none fw-bold" style="color: #1a73e8;">Enrollees</a>
        </div>
        <div class="user-info" onclick="window.location.href='settings.php'">
    <span class="user-name">
        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
    </span>
    <div class="user-avatar">
        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
    </div>
</div>

    </div>

    <!-- Main Content -->
    <div class="container-main">
        <h2>Enrollees' Status</h2>
        <p class="text-muted">Manage and spread your knowledge</p>

        <!-- Student Header -->
        <div class="student-header">
            <h3><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?>'s Results</h3>
            <p class="mb-0">
                <i class="bi bi-arrow-left"></i> 
                <?php echo count($enrolledCourses) > 0 ? htmlspecialchars($enrolledCourses[0]['title']) : 'Courses'; ?>
            </p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?user_id=<?php echo $userID; ?>&tab=quizzes" class="tab <?php echo $activeTab == 'quizzes' ? 'active' : ''; ?>">
                Quizzes' Result
            </a>
            <a href="?user_id=<?php echo $userID; ?>&tab=progress" class="tab <?php echo $activeTab == 'progress' ? 'active' : ''; ?>">
                Course Progress
            </a>
        </div>

        <?php if ($activeTab == 'quizzes'): ?>
            <!-- Quiz Results Tab -->
            <div class="row">
                <div class="col-md-8">
                    <div class="stats-card mb-4">
                        <h5>Total Results</h5>
                        <ul class="list-unstyled">
                            <?php foreach ($enrolledCourses as $course): ?>
                                <li>Module: <?php echo htmlspecialchars($course['title']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="stats-card">
                        <canvas id="resultsChart" width="200" height="200"></canvas>
                        <div class="mt-3 text-center">
                            <p class="mb-1"><strong>Total Results' Percentage:</strong></p>
                            <p class="mb-0 text-primary">■ Passing - - - - <?php echo $passingPercentage; ?>%</p>
                            <p class="mb-0 text-danger">■ Failing - - - - <?php echo $failingPercentage; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Course Progress Tab -->
            <div class="mb-3">
                <p class="mb-1"><strong>Total Results' Percentage:</strong></p>
                <p class="mb-0 text-primary">■ Passing - - - - <?php echo $passingPercentage; ?>%</p>
                <p class="mb-0 text-danger">■ Failing - - - - <?php echo $failingPercentage; ?>%</p>
            </div>

            <div class="row">
                <?php foreach ($enrolledCourses as $course): ?>
                    <div class="col-md-4">
                        <div class="course-card">
                            <div class="course-image">
                                <span class="status-badge <?php echo $course['status'] == 'completed' ? 'completed' : 'ongoing'; ?>">
                                    • <?php echo ucfirst($course['status']); ?>
                                </span>
                                // photo
                            </div>
                            <div class="p-3">
                                <h6><?php echo htmlspecialchars($course['title']); ?></h6>
                                <p class="text-muted small">Own by you</p>
                                
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small"><?php echo $course['status'] == 'completed' ? 'Finished' : 'Progress'; ?></span>
                                        <span class="small"><?php echo round($course['progressPercentage']); ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" style="width: <?php echo $course['progressPercentage']; ?>%; background: <?php echo $course['status'] == 'completed' ? '#43a047' : '#1e88e5'; ?>;"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small">Passing</span>
                                        <span class="small"><?php echo $passingPercentage; ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo $passingPercentage; ?>%;"></div>
                                    </div>
                                </div>
                                
                                <button class="btn btn-primary btn-sm w-100">View</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Pie chart for quiz results
        <?php if ($activeTab == 'quizzes'): ?>
        const ctx = document.getElementById('resultsChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Passing', 'Failing'],
                datasets: [{
                    data: [<?php echo $passingPercentage; ?>, <?php echo $failingPercentage; ?>],
                    backgroundColor: ['#1e88e5', '#ef5350']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>