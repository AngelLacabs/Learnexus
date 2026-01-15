<?php
/**
 * Test script to check if SMS Feedback database table exists
 */

require_once __DIR__ . '/database/db_connect.php';

header('Content-Type: text/plain');

echo "=== SMS Feedback Database Test ===\n\n";

try {
    // Check if table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'sms_feedback'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "✅ Table 'sms_feedback' EXISTS\n\n";
        
        // Check table structure
        $stmt = $conn->query("DESCRIBE sms_feedback");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Table Structure:\n";
        echo str_repeat("-", 50) . "\n";
        foreach ($columns as $col) {
            echo sprintf("%-20s %-20s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
        }
        
        // Check record count
        $stmt = $conn->query("SELECT COUNT(*) as count FROM sms_feedback");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\n✅ Total records: " . $count['count'] . "\n";
        
        // Show recent records
        $stmt = $conn->query("SELECT * FROM sms_feedback ORDER BY createdAt DESC LIMIT 5");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent)) {
            echo "\nRecent Records:\n";
            echo str_repeat("-", 50) . "\n";
            foreach ($recent as $record) {
                echo sprintf("ID: %d | From: %s | Message: %s | Status: %s | Created: %s\n",
                    $record['feedbackID'],
                    $record['from_number'],
                    substr($record['message'], 0, 30),
                    $record['status'],
                    $record['createdAt']
                );
            }
        }
        
    } else {
        echo "❌ Table 'sms_feedback' DOES NOT EXIST\n";
        echo "\nPlease run the migration:\n";
        echo "database/migrations/2026-01-15-create-sms-feedback.sql\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
