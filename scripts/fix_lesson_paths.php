<?php
/** Run this once to normalize lesson filenames in DB to the format: 'uploads/lessons/filename.pdf' */
require_once __DIR__ . '/../database/db_connect.php';

$updates = 0;

// 1) Replace filenames starting with '../uploads/' or './uploads/' or '/uploads/' -> 'uploads/'
$stmt = $conn->prepare("UPDATE lessons SET filename = REPLACE(filename, '../', '') WHERE filename LIKE '../%'");
$stmt->execute();
$updates += $stmt->rowCount();

$stmt = $conn->prepare("UPDATE lessons SET filename = REPLACE(filename, './', '') WHERE filename LIKE './%'");
$stmt->execute();
$updates += $stmt->rowCount();

$stmt = $conn->prepare("UPDATE lessons SET filename = TRIM(LEADING '/' FROM filename) WHERE filename LIKE '/%'");
$stmt->execute();
$updates += $stmt->rowCount();

// 2) For filenames that have no slash (just basename), prefix with uploads/lessons/
$stmt = $conn->prepare("SELECT lessonID, filename FROM lessons");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    if (strpos($r['filename'], '/') === false) {
        $new = 'uploads/lessons/' . $r['filename'];
        $u = $conn->prepare("UPDATE lessons SET filename = ? WHERE lessonID = ?");
        $u->execute([$new, $r['lessonID']]);
        $updates += $u->rowCount();
    }
}

// 3) Ensure all uploads/lessons paths are normalized (remove duplicate slashes)
$stmt = $conn->prepare("UPDATE lessons SET filename = REPLACE(filename, 'uploads//lessons/', 'uploads/lessons/') WHERE filename LIKE '%uploads//lessons/%'");
$stmt->execute();
$updates += $stmt->rowCount();

echo "Done. Rows updated (approx): " . $updates . "\n";
echo "Please verify files exist in uploads/lessons/ and adjust permissions if needed.\n";
