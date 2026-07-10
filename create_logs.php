<?php
// Create logs directory if it doesn't exist
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Create log file with sample content
$log_file = $log_dir . '/system.log';
$sample_logs = [
    '[' . date('Y-m-d H:i:s') . '] System started successfully',
    '[' . date('Y-m-d H:i:s', strtotime('-1 minute')) . '] Database connection established',
    '[' . date('Y-m-d H:i:s', strtotime('-2 minutes')) . '] Admin logged in (admin@clinic.com)',
    '[' . date('Y-m-d H:i:s', strtotime('-5 minutes')) . '] New appointment #1 created for patient John Doe',
    '[' . date('Y-m-d H:i:s', strtotime('-10 minutes')) . '] Doctor dr.smith@clinic.com logged in',
    '[' . date('Y-m-d H:i:s', strtotime('-15 minutes')) . '] Appointment #1 status updated to confirmed',
    '[' . date('Y-m-d H:i:s', strtotime('-30 minutes')) . '] Daily report generated successfully',
    '[' . date('Y-m-d H:i:s', strtotime('-1 hour')) . '] Patient john@example.com logged in',
    '[' . date('Y-m-d H:i:s', strtotime('-2 hours')) . '] New appointment #2 created for patient Jane Doe',
    '[' . date('Y-m-d H:i:s', strtotime('-3 hours')) . '] System backup completed successfully',
    '[' . date('Y-m-d H:i:s', strtotime('-1 day')) . '] Error: Failed to send email notification',
    '[' . date('Y-m-d H:i:s', strtotime('-2 days')) . '] Warning: Database query slow (2.3 seconds)',
];

file_put_contents($log_file, implode(PHP_EOL, $sample_logs));

echo "✅ Logs created successfully!";
echo "<br><br>";
echo "<a href='admin/logs.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to System Logs</a>";
?>