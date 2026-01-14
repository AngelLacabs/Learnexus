<?php
require_once __DIR__ . '/../database/db_connect.php';

$stmt = $conn->prepare("SELECT lessonID, filename FROM lessons ORDER BY lessonID DESC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$missing = [];
foreach ($rows as $r) {
    $p = $r['filename'];
    $p = preg_replace('#^(\./|\.\./)+#', '', $p);
    $p = ltrim($p, '/');
    if (basename($p) === $p) $p = 'uploads/lessons/' . $p;
    $serverPath = realpath(__DIR__ . '/../' . $p) ?: (__DIR__ . '/../' . $p);
    if (!file_exists($serverPath)) {
        $missing[] = ['lessonID' => $r['lessonID'], 'db' => $r['filename'], 'checked' => $serverPath];
    }
}

if (empty($missing)) {
    echo "All lesson files are present.\n";
} else {
    echo "Missing files:\n";
    foreach ($missing as $m) {
        echo "LessonID: {$m['lessonID']} | DB: {$m['db']} | Checked: {$m['checked']}\n";
    }
}
