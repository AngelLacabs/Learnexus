<?php
session_start();
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../index.php');
    exit();
}

$teacherID = $_SESSION['user_id'];

// Get courses by teacher
$stmt = $conn->prepare("SELECT courseID, title FROM courses WHERE teacherID = ? ORDER BY title");
$stmt->execute([$teacherID]);
$courses = $stmt->fetchAll();

// Get enrollees per course
$enrolleesByCourse = [];
foreach ($courses as $course) {
    $stmt = $conn->prepare("
        SELECT u.userID, u.firstName, u.lastName, u.studentNumber, e.enrolledAt, e.progressPercentage
        FROM enrollments e
        JOIN users u ON e.userID = u.userID
        WHERE e.courseID = ?
        ORDER BY e.enrolledAt DESC
    ");
    $stmt->execute([$course['courseID']]);
    $enrolleesByCourse[$course['courseID']] = [
        'course' => $course,
        'enrollees' => $stmt->fetchAll()
    ];
}

// Get all enrollees
$stmt = $conn->prepare("
    SELECT u.userID, u.firstName, u.lastName, u.studentNumber, c.title as courseTitle
    FROM enrollments e
    JOIN users u ON e.userID = u.userID
    JOIN courses c ON e.courseID = c.courseID
    WHERE c.teacherID = ?
    ORDER BY u.lastName, u.firstName
");
$stmt->execute([$teacherID]);
$allEnrollees = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Enrollees - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .top-nav { background: linear-gradient(180deg, #e8f0fe 0%, #f8f9fa 100%); padding: 15px 40px; }
        .brand { font-size: 20px; font-weight: 700; color: #1a73e8; }
        .course-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="top-nav d-flex justify-content-between align-items-center">
        <div class="brand">LEARNEXUS</div>
        <div>
            <a href="dashboard.php" class="me-3 text-decoration-none">Dashboard</a>
            <a href="courses.php" class="me-3 text-decoration-none">Courses</a>
            <a href="quizzes.php" class="me-3 text-decoration-none">Quizzes</a>
            <a href="enrollees.php" class="me-3 text-decoration-none text-primary fw-bold">Enrollees</a>
        </div>
        <span><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
    </div>

    <div class="container mt-4">
        <h2>List of Enrollees</h2>
        <p class="text-muted">Manage and spread your knowledge</p>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#per-courses">Per Courses</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#all-enrollees">All Enrollees</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="per-courses">
                <div class="row">
                    <?php foreach ($enrolleesByCourse as $data): ?>
                        <?php if (count($data['enrollees']) > 0): ?>
                            <div class="col-md-4 mb-3">
                                <div class="course-card">
                                    <div style="height: 120px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999; margin-bottom: 15px;">
                                        // photo
                                    </div>
                                    <p class="text-muted small mb-1">Total of <?php echo count($data['enrollees']); ?> Enrollees</p>
                                    <h6><?php echo htmlspecialchars($data['course']['title']); ?></h6>
                                    <button class="btn btn-primary btn-sm w-100 mt-3" onclick="window.location.href='view_enrollees.php?course_id=<?php echo $data['course']['courseID']; ?>'">View</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="all-enrollees">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted">Total of <?php echo count($allEnrollees); ?> Enrollees</p>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Name of Enrollees</th>
                                    <th>Student Number</th>
                                    <th>Course</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allEnrollees as $index => $student): ?>
                                    <tr style="cursor: pointer;" onclick="window.location.href='student_status.php?user_id=<?php echo $student['userID']; ?>'">
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                        <td><?php echo htmlspecialchars($student['studentNumber']); ?></td>
                                        <td><?php echo htmlspecialchars($student['courseTitle']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>