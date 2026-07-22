<?php
require_once '../includes/database.php';

$db = Database::getInstance();

echo "Starting cleanup process...\n";

// এক্সপায়ার্ড লাইসেন্স আপডেট
$query = "UPDATE licenses SET status = 'expired' WHERE expires_at <= NOW() AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$expired = $stmt->rowCount();
echo "Updated {$expired} expired licenses\n";

// ইনঅ্যাকটিভ ডিভাইস আপডেট (৫ মিনিটের বেশি)
$query = "UPDATE devices SET is_active = FALSE WHERE is_active = TRUE AND last_active < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$stmt = $db->prepare($query);
$stmt->execute();
$inactive = $stmt->rowCount();
echo "Updated {$inactive} inactive devices\n";

// পুরাতন ডিভাইস ডিলিট (৩০ দিনের বেশি পুরাতন)
$query = "DELETE FROM devices WHERE is_active = FALSE AND last_active < DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $db->prepare($query);
$stmt->execute();
$deleted = $stmt->rowCount();
echo "Deleted {$deleted} old inactive devices\n";

// পুরাতন লগ ডিলিট (সেটিংস থেকে ডেস নিয়ে)
$settings = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'log_retention_days'")->fetch();
$days = $settings['setting_value'] ?? 90;

$query = "DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
$stmt = $db->prepare($query);
$stmt->execute([':days' => $days]);
$logs = $stmt->rowCount();
echo "Deleted {$logs} old logs (older than {$days} days)\n";

// পুরাতন রেট লিমিট ডিলিট
$query = "DELETE FROM rate_limits WHERE last_request < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
$stmt = $db->prepare($query);
$stmt->execute();
$rate = $stmt->rowCount();
echo "Deleted {$rate} old rate limit records\n";

// পুরাতন ব্যর্থ লগইন ডিলিট
$query = "DELETE FROM failed_logins WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
$stmt = $db->prepare($query);
$stmt->execute();
$failed = $stmt->rowCount();
echo "Deleted {$failed} old failed login records\n";

echo "Cleanup process completed successfully!\n";
?>