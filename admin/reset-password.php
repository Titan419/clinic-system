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
$validation_errors = [];
$reset_logs = [];

// Get all users for dropdown
$query = "SELECT id, email, full_name, user_type FROM users WHERE is_active = 1 ORDER BY user_type, full_name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Try to get password reset log, but handle if table doesn't exist
try {
    $log_query = "SELECT prl.*, 
                  a.full_name as admin_name, 
                  u.full_name as user_name,
                  u.email as user_email
                  FROM password_reset_log prl
                  JOIN users a ON prl.admin_id = a.id
                  JOIN users u ON prl.user_id = u.id
                  ORDER BY prl.reset_time DESC
                  LIMIT 20";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->execute();
    $reset_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist, just set empty logs
    $reset_logs = [];
    // Optionally log the error
    error_log("Password reset log table not found: " . $e->getMessage());
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    $validation_errors = [];
    
    if ($user_id <= 0) {
        $validation_errors['user_id'] = "Please select a user";
    }
    
    if (empty($new_password)) {
        $validation_errors['new_password'] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $validation_errors['new_password'] = "Password must be at least 6 characters";
    }
    
    if ($new_password != $confirm_password) {
        $validation_errors['confirm_password'] = "Passwords do not match";
    }
    
    if (empty($validation_errors)) {
        try {
            // Get user details before update
            $user_query = "SELECT email, full_name FROM users WHERE id = :id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':id', $user_id);
            $user_stmt->execute();
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Start transaction
                $db->beginTransaction();
                
                // Update password (plain text for demo)
                $update = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $db->prepare($update);
                $update_stmt->bindParam(':password', $new_password);
                $update_stmt->bindParam(':id', $user_id);
                
                if ($update_stmt->execute()) {
                    // Try to log the password reset, but don't fail if table doesn't exist
                    try {
                        $log_query = "INSERT INTO password_reset_log (admin_id, user_id, reset_time) VALUES (:admin_id, :user_id, NOW())";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                        $log_stmt->bindParam(':user_id', $user_id);
                        $log_stmt->execute();
                    } catch (PDOException $logError) {
                        // Log table doesn't exist - just continue
                        error_log("Could not log password reset: " . $logError->getMessage());
                    }
                    
                    $db->commit();
                    $success = "✅ Password reset successfully for " . $user['full_name'];
                    
                    // Clear form
                    $_POST = array();
                } else {
                    $db->rollBack();
                    $error = "❌ Failed to reset password";
                }
            } else {
                $error = "❌ User not found";
            }
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            $error = "❌ Database error: " . $e->getMessage();
        }
    } else {
        $error = "⚠️ Please fix the errors below";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset User Password - ClinicCare</title>
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

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #3498db;
        }

        .reset-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .reset-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header i {
            font-size: 1.5rem;
            color: #3498db;
        }

        .card-header h2 {
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group label i {
            color: #3498db;
            margin-right: 5px;
        }

        .form-group label .required {
            color: #e74c3c;
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }

        .form-control.error {
            border-color: #e74c3c;
            background: #fdf3f2;
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 13px;
        }

        .password-requirements i {
            color: #27ae60;
            margin-right: 5px;
        }

        .password-requirements ul {
            margin-left: 25px;
            margin-top: 8px;
        }

        .user-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-admin {
            background: #e74c3c;
            color: white;
        }

        .badge-doctor {
            background: #3498db;
            color: white;
        }

        .badge-patient {
            background: #27ae60;
            color: white;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .log-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
        }

        .log-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .log-table tr:hover {
            background: #f8f9fa;
        }

        .setup-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .setup-note i {
            color: #856404;
            margin-right: 10px;
        }

        .setup-note code {
            background: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .reset-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
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
                <li><a href="reset-password.php" class="active"><i class="fas fa-key"></i> Reset Password</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-key"></i> Reset User Password</h1>
                <span class="badge badge-primary">Admin Only</span>
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
            
            <!-- Setup note if log table doesn't exist -->
            <?php if(empty($reset_logs) && !isset($log_error)): ?>
            <div class="setup-note">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> The password reset log table doesn't exist yet. 
                Password resets will still work, but they won't be logged. 
                To enable logging, run this SQL in phpMyAdmin:
                <br><br>
                <code>CREATE TABLE IF NOT EXISTS password_reset_log ( id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, user_id INT NOT NULL, reset_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (admin_id) REFERENCES users(id), FOREIGN KEY (user_id) REFERENCES users(id) );</code>
            </div>
            <?php endif; ?>
            
            <div class="reset-container">
                <!-- Reset Password Form -->
                <div class="reset-card">
                    <div class="card-header">
                        <i class="fas fa-user-lock"></i>
                        <h2>Reset User Password</h2>
                    </div>
                    
                    <form method="POST" action="" id="resetForm">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Select User <span class="required">*</span></label>
                            <select name="user_id" class="form-control <?php echo isset($validation_errors['user_id']) ? 'error' : ''; ?>" required>
                                <option value="">-- Select a user --</option>
                                <?php 
                                $current_type = '';
                                foreach($users as $user): 
                                    if($current_type != $user['user_type']):
                                        if($current_type != '') echo "</optgroup>";
                                        $current_type = $user['user_type'];
                                        echo "<optgroup label='" . ucfirst($current_type) . "s'>";
                                    endif;
                                ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <?php if(isset($validation_errors['user_id'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['user_id']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> New Password <span class="required">*</span></label>
                            <input type="password" name="new_password" class="form-control <?php echo isset($validation_errors['new_password']) ? 'error' : ''; ?>" 
                                   placeholder="Enter new password" required>
                            <?php if(isset($validation_errors['new_password'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['new_password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>" 
                                   placeholder="Confirm new password" required>
                            <?php if(isset($validation_errors['confirm_password'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="password-requirements">
                            <i class="fas fa-info-circle"></i> <strong>Password Requirements:</strong>
                            <ul>
                                <li>Minimum 6 characters</li>
                                <li>Use a mix of letters and numbers</li>
                                <li>Don't use easily guessable passwords</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </form>
                </div>
                
                <!-- Password Reset Log -->
                <div class="reset-card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h2>Recent Password Resets</h2>
                    </div>
                    
                    <?php if(empty($reset_logs)): ?>
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <i class="fas fa-history" style="font-size: 48px; margin-bottom: 10px;"></i>
                            <p>No password resets logged yet</p>
                            <p style="font-size: 13px; margin-top: 10px;">Run the SQL setup to enable logging</p>
                        </div>
                    <?php else: ?>
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Admin</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($reset_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M d, H:i', strtotime($log['reset_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($log['user_name']); ?>
                                        <small>(<?php echo htmlspecialchars($log['user_email']); ?>)</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick User List -->
            <div class="reset-card" style="margin-top: 25px;">
                <div class="card-header">
                    <i class="fas fa-users"></i>
                    <h2>System Users</h2>
                </div>
                
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['user_type']; ?>">
                                    <?php echo ucfirst($user['user_type']); ?>
                                </span>
                            </td>
                            <td><span class="badge badge-success">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
    // Form validation
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="new_password"]').value;
        const confirm = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirm) {
            e.preventDefault();
            alert('❌ Passwords do not match!');
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>