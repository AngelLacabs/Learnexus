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
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Enrollees - Learnexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #f8f9fa;">
    <div class="container mt-5">
        <h2>List of Enrollees</h2>
        <h4><?php echo htmlspecialchars($course['title']); ?></h4>
        <p class="text-muted">Total of <?php echo count($enrollees); ?> Enrollees</p>
        
        <div class="card">
            <div class="card-body">
                <table class="table">
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
                            <tr style="cursor: pointer;" onclick="window.location.href='student_status.php?user_id=<?php echo $student['userID']; ?>'">
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                <td>BS Computer Science</td>
                                <td>PUP Sta. Mesa, Manila</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <a href="enrollees.php" class="btn btn-secondary mt-3">Back</a>
    </div>
</body>
</html>