<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$log_file = __DIR__ . '/../logs/system.log';
$sample_logs = [
    '[' . date('Y-m-d H:i:s') . '] System initialized successfully',
    '[' . date('Y-m-d H:i:s', strtotime('-1 minute')) . '] Admin logged in',
    '[' . date('Y-m-d H:i:s', strtotime('-5 minutes')) . '] Database connection established',
    '[' . date('Y-m-d H:i:s', strtotime('-10 minutes')) . '] New appointment #1 created',
    '[' . date('Y-m-d H:i:s', strtotime('-15 minutes')) . '] User dr.smith@clinic.com logged in',
    '[' . date('Y-m-d H:i:s', strtotime('-30 minutes')) . '] System backup completed successfully',
    '[' . date('Y-m-d H:i:s', strtotime('-1 hour')) . '] Daily report generated',
    '[' . date('Y-m-d H:i:s', strtotime('-2 hours')) . '] Patient john@example.com registered',
    '[' . date('Y-m-d H:i:s', strtotime('-3 hours')) . '] Appointment #2 status updated to confirmed',
    '[' . date('Y-m-d H:i:s', strtotime('-1 day')) . '] System maintenance completed',
    '[' . date('Y-m-d H:i:s', strtotime('-2 days')) . '] Error: Failed to send email notification',
    '[' . date('Y-m-d H:i:s', strtotime('-3 days')) . '] Warning: Database connection slow',
    '[' . date('Y-m-d H:i:s', strtotime('-1 week')) . '] User password changed for admin',
];

file_put_contents($log_file, implode("\n", $sample_logs));
header('Location: logs.php?generated=1');
exit;
?>