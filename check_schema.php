<?php
$conn = new PDO('mysql:host=localhost;dbname=lmslearnexus;charset=utf8mb4', 'root', '');
$stmt = $conn->query('DESCRIBE vouchers');
echo "Vouchers table columns:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}
