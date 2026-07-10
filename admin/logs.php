<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Define log file path
$log_file = __DIR__ . '/../logs/system.log';
$logs = [];

// Check if log file exists
if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    if (!empty($content)) {
        $logs = explode("\n", trim($content));
        $logs = array_reverse(array_slice($logs, -100)); // Get last 100 logs
    }
}

// If no logs exist, create sample logs
if (empty($logs)) {
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
    ];
    $logs = $sample_logs;
}

// Handle log clearing
if (isset($_POST['clear_logs'])) {
    file_put_contents($log_file, '');
    echo '<script>window.location.href = "logs.php?cleared=1";</script>';
    exit();
}

// Handle log download
if (isset($_GET['download'])) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.txt"');
    
    if (file_exists($log_file)) {
        readfile($log_file);
    } else {
        echo "No logs available";
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .logs-container {
            padding: 30px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .logs-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logs-title h1 {
            font-size: 2rem;
            color: #333;
        }
        
        .logs-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .logs-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logs-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .log-entry {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background 0.3s;
        }
        
        .log-entry:hover {
            background: #f8f9fa;
        }
        
        .log-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .log-icon.info {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .log-icon.success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .log-icon.warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .log-icon.error {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .log-content {
            flex: 1;
            color: #333;
        }
        
        .log-time {
            color: #6c757d;
            font-size: 12px;
            min-width: 160px;
        }
        
        .empty-logs {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        
        .empty-logs i {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .empty-logs h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-logs p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .log-search {
            margin-bottom: 20px;
        }
        
        .log-search input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .log-search input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        @media (max-width: 768px) {
            .logs-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .log-entry {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .log-time {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logs.php" class="active"><i class="fas fa-history"></i> System Logs</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="logs-container">
                <!-- Header -->
                <div class="logs-header">
                    <div class="logs-title">
                        <i class="fas fa-history" style="font-size: 2.5rem; color: #667eea;"></i>
                        <h1>System Logs</h1>
                    </div>
                    
                    <div class="logs-actions">
                        <a href="?download=1" class="btn btn-success">
                            <i class="fas fa-download"></i> Download Logs
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all logs?');">
                            <button type="submit" name="clear_logs" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Clear Logs
                            </button>
                        </form>
                        <a href="logs.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </a>
                    </div>
                </div>
                
                <?php if(isset($_GET['cleared'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Logs cleared successfully!
                    </div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="logs-stats">
                    <div>
                        <i class="fas fa-file-alt"></i>
                        <strong><?php echo count($logs); ?></strong> Log Entries
                    </div>
                    <div>
                        <i class="fas fa-calendar"></i>
                        Last Updated: <?php echo date('Y-m-d H:i:s'); ?>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="log-search">
                    <input type="text" id="searchLogs" placeholder="🔍 Search logs..." onkeyup="filterLogs()">
                </div>
                
                <!-- Logs List -->
                <div class="logs-list" id="logsList">
                    <?php if(empty($logs)): ?>
                        <div class="empty-logs">
                            <i class="fas fa-check-circle"></i>
                            <h3>No Logs Found</h3>
                            <p>The system is running smoothly with no errors to report.</p>
                            <button onclick="generateSampleLogs()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Generate Sample Logs
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach($logs as $log): ?>
                            <?php if(empty(trim($log))) continue; ?>
                            <?php
                            // Determine log type
                            $log_type = 'info';
                            $log_lower = strtolower($log);
                            if (strpos($log_lower, 'error') !== false) $log_type = 'error';
                            elseif (strpos($log_lower, 'warning') !== false) $log_type = 'warning';
                            elseif (strpos($log_lower, 'success') !== false) $log_type = 'success';
                            
                            // Extract time if exists
                            $log_time = '';
                            $log_message = $log;
                            if (preg_match('/\[(.*?)\]/', $log, $matches)) {
                                $log_time = $matches[1];
                                $log_message = trim(str_replace($matches[0], '', $log));
                            }
                            ?>
                            <div class="log-entry" data-log-type="<?php echo $log_type; ?>">
                                <div class="log-icon <?php echo $log_type; ?>">
                                    <i class="fas fa-<?php 
                                        echo $log_type == 'error' ? 'exclamation-circle' : 
                                            ($log_type == 'warning' ? 'exclamation-triangle' : 
                                            ($log_type == 'success' ? 'check-circle' : 'info-circle')); 
                                    ?>"></i>
                                </div>
                                <?php if($log_time): ?>
                                    <div class="log-time"><?php echo htmlspecialchars($log_time); ?></div>
                                <?php endif; ?>
                                <div class="log-content"><?php echo htmlspecialchars($log_message); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    function filterLogs() {
        const searchText = document.getElementById('searchLogs').value.toLowerCase();
        const logs = document.querySelectorAll('.log-entry');
        
        logs.forEach(log => {
            const text = log.textContent.toLowerCase();
            if (text.includes(searchText)) {
                log.style.display = 'flex';
            } else {
                log.style.display = 'none';
            }
        });
    }
    
    function generateSampleLogs() {
        // Redirect to generate sample logs
        window.location.href = 'generate_logs.php';
    }
    
    // Auto-refresh logs every 30 seconds
    setTimeout(() => {
        location.reload();
    }, 30000);
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>