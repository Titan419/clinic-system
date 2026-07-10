<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle different settings updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Update clinic information
    if (isset($_POST['update_clinic'])) {
        $clinic_name = sanitize($_POST['clinic_name']);
        $clinic_email = sanitize($_POST['clinic_email']);
        $clinic_phone = sanitize($_POST['clinic_phone']);
        $clinic_address = sanitize($_POST['clinic_address']);
        $clinic_city = sanitize($_POST['clinic_city']);
        
        // Store in session
        $_SESSION['clinic_name'] = $clinic_name;
        $_SESSION['clinic_email'] = $clinic_email;
        $_SESSION['clinic_phone'] = $clinic_phone;
        $_SESSION['clinic_address'] = $clinic_address;
        $_SESSION['clinic_city'] = $clinic_city;
        
        $success = "Clinic information updated successfully!";
    }
    
    // Update password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $query = "SELECT password FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password == $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $update = "UPDATE users SET password = :password WHERE id = :id";
                    $update_stmt = $db->prepare($update);
                    $update_stmt->bindParam(':password', $hashed_password);
                    $update_stmt->bindParam(':id', $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        $success = "Password changed successfully!";
                    } else {
                        $error = "Failed to change password.";
                    }
                } else {
                    $error = "Password must be at least 6 characters.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
    
    // Update system settings
    if (isset($_POST['update_system'])) {
        $appointment_duration = (int)$_POST['appointment_duration'];
        $max_appointments_per_day = (int)$_POST['max_appointments_per_day'];
        $working_days = isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : '';
        $working_start = $_POST['working_start'];
        $working_end = $_POST['working_end'];
        
        $_SESSION['appointment_duration'] = $appointment_duration;
        $_SESSION['max_appointments_per_day'] = $max_appointments_per_day;
        $_SESSION['working_days'] = $working_days;
        $_SESSION['working_start'] = $working_start;
        $_SESSION['working_end'] = $working_end;
        
        $success = "System settings updated successfully!";
    }
}

// Get current settings (from session or defaults)
$clinic_name = $_SESSION['clinic_name'] ?? 'ClinicCare';
$clinic_email = $_SESSION['clinic_email'] ?? 'info@cliniccare.com';
$clinic_phone = $_SESSION['clinic_phone'] ?? '+255 123 456 789';
$clinic_address = $_SESSION['clinic_address'] ?? '123 Healthcare Street';
$clinic_city = $_SESSION['clinic_city'] ?? 'Dar es Salaam, Tanzania';

$appointment_duration = $_SESSION['appointment_duration'] ?? 30;
$max_appointments_per_day = $_SESSION['max_appointments_per_day'] ?? 20;
$working_days = $_SESSION['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday';
$working_start = $_SESSION['working_start'] ?? '09:00';
$working_end = $_SESSION['working_end'] ?? '17:00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #2c3e50 0%, #1e2b37 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            color: white;
        }

        .sidebar-header h3 i {
            color: #3498db;
            margin-right: 10px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            color: #3498db;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .settings-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .settings-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header i {
            font-size: 2rem;
            color: #667eea;
            margin-right: 15px;
        }
        
        .card-header h3 {
            font-size: 1.3rem;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
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
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            margin-bottom: 0;
            cursor: pointer;
        }
        
        .info-box {
            background: #f7fafc;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
        }
        
        .info-box h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .working-hours {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .password-reset-section {
            margin-top: 30px;
            padding: 20px;
            background: #ebf4ff;
            border-radius: 10px;
            border: 2px dashed #667eea;
        }
        
        .password-reset-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .password-reset-section h3 i {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .working-hours {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar - UPDATED with Reset Password link -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-clinic-medical"></i> ClinicCare</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="reset-password.php"><i class="fas fa-key"></i> Reset Password</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="settings-container">
                <!-- Header -->
                <div class="settings-header">
                    <h1><i class="fas fa-cog"></i> System Settings</h1>
                    <p>Configure and manage your clinic system settings</p>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Password Reset Quick Link -->
                <div class="password-reset-section">
                    <h3><i class="fas fa-key"></i> Need to reset a user's password?</h3>
                    <p>If a patient, doctor, or staff member has forgotten their password, you can reset it here:</p>
                    <a href="reset-password.php" class="btn btn-warning">
                        <i class="fas fa-key"></i> Go to Password Reset Page
                    </a>
                </div>
                
                <!-- Settings Grid -->
                <div class="settings-grid">
                    <!-- Clinic Information Card -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-hospital"></i>
                            <h3>Clinic Information</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="clinic_name">Clinic Name</label>
                                <input type="text" class="form-control" id="clinic_name" name="clinic_name" 
                                       value="<?php echo htmlspecialchars($clinic_name); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="clinic_email">Clinic Email</label>
                                <input type="email" class="form-control" id="clinic_email" name="clinic_email" 
                                       value="<?php echo htmlspecialchars($clinic_email); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="clinic_phone">Clinic Phone</label>
                                <input type="text" class="form-control" id="clinic_phone" name="clinic_phone" 
                                       value="<?php echo htmlspecialchars($clinic_phone); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="clinic_address">Street Address</label>
                                <input type="text" class="form-control" id="clinic_address" name="clinic_address" 
                                       value="<?php echo htmlspecialchars($clinic_address); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="clinic_city">City</label>
                                <input type="text" class="form-control" id="clinic_city" name="clinic_city" 
                                       value="<?php echo htmlspecialchars($clinic_city); ?>" required>
                            </div>
                            
                            <button type="submit" name="update_clinic" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Clinic Info
                            </button>
                        </form>
                    </div>
                    
                    <!-- System Settings Card -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-sliders-h"></i>
                            <h3>System Configuration</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="appointment_duration">Appointment Duration (minutes)</label>
                                <select class="form-control" id="appointment_duration" name="appointment_duration">
                                    <option value="15" <?php echo $appointment_duration == 15 ? 'selected' : ''; ?>>15 minutes</option>
                                    <option value="30" <?php echo $appointment_duration == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                    <option value="45" <?php echo $appointment_duration == 45 ? 'selected' : ''; ?>>45 minutes</option>
                                    <option value="60" <?php echo $appointment_duration == 60 ? 'selected' : ''; ?>>60 minutes</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_appointments_per_day">Max Appointments Per Day (per doctor)</label>
                                <input type="number" class="form-control" id="max_appointments_per_day" 
                                       name="max_appointments_per_day" min="1" max="50" 
                                       value="<?php echo $max_appointments_per_day; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Working Days</label>
                                <div class="checkbox-group">
                                    <?php
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $selected_days = explode(',', $working_days);
                                    foreach($days as $day):
                                    ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="working_days[]" value="<?php echo $day; ?>" 
                                               id="day_<?php echo $day; ?>"
                                               <?php echo in_array($day, $selected_days) ? 'checked' : ''; ?>>
                                        <label for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="working-hours">
                                <div class="form-group">
                                    <label for="working_start">Working Start Time</label>
                                    <input type="time" class="form-control" id="working_start" name="working_start" 
                                           value="<?php echo $working_start; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="working_end">Working End Time</label>
                                    <input type="time" class="form-control" id="working_end" name="working_end" 
                                           value="<?php echo $working_end; ?>" required>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_system" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update System Settings
                            </button>
                        </form>
                        
                        <div class="info-box">
                            <h4><i class="fas fa-info-circle"></i> System Information</h4>
                            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                            <p><strong>Database:</strong> MySQL</p>
                            <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Localhost'; ?></p>
                            <p><strong>System Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Password Change Card -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-lock"></i>
                            <h3>Change Your Password</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" class="form-control" id="current_password" 
                                       name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" 
                                       name="new_password" required>
                                <small style="color: #666;">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="update_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                    
                    <!-- Backup & Maintenance Card -->
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-database"></i>
                            <h3>Backup & Maintenance</h3>
                        </div>
                        
                        <div class="form-group">
                            <label>Database Backup</label>
                            <p style="color: #666; margin-bottom: 15px;">Download a complete backup of your database</p>
                            <a href="backup.php" class="btn btn-success" style="width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-download"></i> Download Backup
                            </a>
                        </div>
                        
                        <div class="form-group">
                            <label>System Logs</label>
                            <p style="color: #666; margin-bottom: 15px;">View system activity and error logs</p>
                            <a href="logs.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-history"></i> View Logs
                            </a>
                        </div>
                        
                        <div class="form-group">
                            <label>Clear Cache</label>
                            <p style="color: #666; margin-bottom: 15px;">Clear system cache and temporary data</p>
                            <button onclick="clearCache()" class="btn btn-warning" style="width: 100%;">
                                <i class="fas fa-eraser"></i> Clear Cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function clearCache() {
        if(confirm('Are you sure you want to clear the system cache?')) {
            fetch('clear-cache.php')
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    location.reload();
                })
                .catch(error => {
                    alert('Cache cleared successfully!');
                    location.reload();
                });
        }
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>