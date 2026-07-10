<?php
// Create logs directory
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
    echo "✅ Logs directory created<br>";
}

// Create log file with sample content
$log_file = $log_dir . '/system.log';
$sample_logs = [
    '[' . date('Y-m-d H:i:s') . '] 🚀 System started successfully',
    '[' . date('Y-m-d H:i:s', strtotime('-2 minutes')) . '] 🔐 Admin logged in',
    '[' . date('Y-m-d H:i:s', strtotime('-5 minutes')) . '] 📅 New appointment created',
    '[' . date('Y-m-d H:i:s', strtotime('-10 minutes')) . '] ✅ Appointment confirmed',
    '[' . date('Y-m-d H:i:s', strtotime('-30 minutes')) . '] 👨‍⚕️ Doctor logged in',
    '[' . date('Y-m-d H:i:s', strtotime('-1 hour')) . '] 📊 Report generated',
    '[' . date('Y-m-d H:i:s', strtotime('-2 hours')) . '] 💾 Database backup completed',
];

file_put_contents($log_file, implode("\n", $sample_logs));
echo "✅ Sample logs created<br><br>";

echo "<a href='admin/logs.php' style='display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>View System Logs →</a>";
?>