<?php
$conn = new PDO('mysql:host=localhost;dbname=lmslearnexus;charset=utf8mb4', 'root', '');
$stmt = $conn->query('SELECT voucherCode, isUsed FROM vouchers ORDER BY voucherID DESC LIMIT 5');
echo "Recent vouchers:\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['voucherCode'] . ' | Used: ' . $row['isUsed'] . "\n";
}
