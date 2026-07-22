<?php
require_once '../includes/database.php';

$db = Database::getInstance();

echo "Checking for expiring licenses...\n";

// ৭ দিনের মধ্যে এক্সপায়ার হবে এমন লাইসেন্স
$query = "
    SELECT license_key, expires_at, created_by 
    FROM licenses 
    WHERE status = 'active' 
    AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
";
$stmt = $db->query($query);
$expiring = $stmt->fetchAll();

if (count($expiring) > 0) {
    echo "Found " . count($expiring) . " licenses expiring within 7 days\n";
    
    foreach ($expiring as $license) {
        $days_left = floor((strtotime($license['expires_at']) - time()) / 86400);
        echo "License {$license['license_key']} expires in {$days_left} days\n";
        
        // এখানে আপনি এডমিনকে ইমেইল নোটিফিকেশন পাঠাতে পারেন
        // বা অন্য কোনো অ্যাকশন নিতে পারেন
    }
} else {
    echo "No licenses expiring within 7 days\n";
}

echo "Expiration check completed!\n";
?>