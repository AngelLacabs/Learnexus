<?php
session_start();
require_once '../database/db_connect.php';

$courseID = $_GET['id'] ?? 3;
$userID = $_SESSION['user_id'] ?? 0;

echo "<h2>Course Lessons Debug</h2>";
echo "<p>Course ID: {$courseID}</p>";
echo "<p>User ID: {$userID}</p>";
echo "<hr>";

// Check if course exists
echo "<h3>1. Course Information</h3>";
$stmt = $conn->prepare("SELECT * FROM courses WHERE courseID = ?");
$stmt->execute([$courseID]);
$course = $stmt->fetch();
if ($course) {
    echo "<pre>";
    print_r($course);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Course not found!</p>";
}

// Check lessons in database
echo "<h3>2. All Lessons for This Course</h3>";
$stmt = $conn->prepare("SELECT * FROM lessons WHERE courseID = ? ORDER BY lessonID ASC");
$stmt->execute([$courseID]);
$lessons = $stmt->fetchAll();
echo "<p>Total lessons found: " . count($lessons) . "</p>";
if (count($lessons) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Filename</th><th>Uploaded At</th><th>File Exists?</th></tr>";
    foreach ($lessons as $lesson) {
        $fileExists = file_exists('../' . $lesson['filename']) ? '✓ Yes' : '✗ No';
        echo "<tr>";
        echo "<td>{$lesson['lessonID']}</td>";
        echo "<td>{$lesson['title']}</td>";
        echo "<td>{$lesson['filename']}</td>";
        echo "<td>{$lesson['uploadedAt']}</td>";
        echo "<td>{$fileExists}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No lessons found for this course!</p>";
}

// Check enrollment
echo "<h3>3. Enrollment Status</h3>";
$stmt = $conn->prepare("SELECT * FROM enrollments WHERE courseID = ? AND userID = ?");
$stmt->execute([$courseID, $userID]);
$enrollment = $stmt->fetch();
if ($enrollment) {
    echo "<pre>";
    print_r($enrollment);
    echo "</pre>";
    
    // Check what lessons would be shown based on completion status
    echo "<h3>4. Lessons That Would Be Displayed</h3>";
    if ($enrollment['status'] === 'completed' && !empty($enrollment['completedAt'])) {
        echo "<p style='color: orange;'>⚠️ Course is COMPLETED - Showing locked view (lessons at completion time)</p>";
        $stmt = $conn->prepare("
            SELECT l.*, 
                   EXISTS(SELECT 1 FROM lesson_completions WHERE lessonID = l.lessonID AND userID = ?) as isCompleted
            FROM lessons l
            WHERE l.courseID = ?
              AND l.uploadedAt <= ?
            ORDER BY l.lessonID ASC
        ");
        $stmt->execute([$userID, $courseID, $enrollment['completedAt']]);
    } else {
        echo "<p style='color: green;'>✓ Course is ACTIVE - Showing all current lessons</p>";
        $stmt = $conn->prepare("
            SELECT l.*, 
                   EXISTS(SELECT 1 FROM lesson_completions WHERE lessonID = l.lessonID AND userID = ?) as isCompleted
            FROM lessons l
            WHERE l.courseID = ?
            ORDER BY l.lessonID ASC
        ");
        $stmt->execute([$userID, $courseID]);
    }
    $displayedLessons = $stmt->fetchAll();
    echo "<p>Lessons to display: " . count($displayedLessons) . "</p>";
    if (count($displayedLessons) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Title</th><th>Completed?</th></tr>";
        foreach ($displayedLessons as $lesson) {
            echo "<tr>";
            echo "<td>{$lesson['lessonID']}</td>";
            echo "<td>{$lesson['title']}</td>";
            echo "<td>" . ($lesson['isCompleted'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>User is NOT enrolled in this course!</p>";
    echo "<p>Please enroll first at: <a href='../courses.php'>Browse Courses</a></p>";
}

// Check lesson completions
echo "<h3>5. Lesson Completion Records</h3>";
$stmt = $conn->prepare("
    SELECT lc.*, l.title 
    FROM lesson_completions lc
    JOIN lessons l ON lc.lessonID = l.lessonID
    WHERE lc.userID = ? AND l.courseID = ?
");
$stmt->execute([$userID, $courseID]);
$completions = $stmt->fetchAll();
echo "<p>Total completions: " . count($completions) . "</p>";
if (count($completions) > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Lesson ID</th><th>Title</th><th>Completed At</th></tr>";
    foreach ($completions as $completion) {
        echo "<tr>";
        echo "<td>{$completion['lessonID']}</td>";
        echo "<td>{$completion['title']}</td>";
        echo "<td>{$completion['completedAt']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>Recommendations:</h3>";
echo "<ul>";
if (!$enrollment) {
    echo "<li style='color: red;'>❌ You need to enroll in this course first</li>";
}
if (count($lessons) == 0) {
    echo "<li style='color: red;'>❌ Instructor needs to upload lessons</li>";
}
if (count($lessons) > 0 && count($displayedLessons) == 0 && $enrollment) {
    echo "<li style='color: orange;'>⚠️ Course is completed - lessons uploaded after completion won't show</li>";
    echo "<li>Solution: Instructor can't add new content to completed courses (by design)</li>";
}
if (count($lessons) > 0) {
    foreach ($lessons as $lesson) {
        if (!file_exists('../' . $lesson['filename'])) {
            echo "<li style='color: red;'>❌ File missing: {$lesson['filename']}</li>";
        }
    }
}
echo "</ul>";
?>