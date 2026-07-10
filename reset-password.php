<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
$error = '';
$success = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($new_password != $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        // Check if user exists
        $query = "SELECT id, full_name FROM users WHERE email = :email AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update password (plain text for demo)
            $update = "UPDATE users SET password = :password WHERE id = :id";
            $update_stmt = $db->prepare($update);
            $update_stmt->bindParam(':password', $new_password);
            $update_stmt->bindParam(':id', $user['id']);
            
            if ($update_stmt->execute()) {
                $success = "✅ Password reset successfully! You can now login with your new password.";
                
                // Clear email
                $email = '';
            } else {
                $error = "❌ Failed to reset password. Please try again.";
            }
        } else {
            $error = "❌ Email not found in our system";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ClinicCare</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-container {
            width: 100%;
            max-width: 450px;
        }

        .reset-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-header i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .reset-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .reset-header p {
            color: #666;
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

        .form-group label i {
            color: #667eea;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-control[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
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

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
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

        .email-display {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .email-display i {
            color: #1976d2;
            margin-right: 5px;
        }

        .email-display strong {
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <i class="fas fa-key"></i>
                <h2>Reset Password</h2>
                <p>Create a new password for your account</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <div class="login-link">
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
                </div>
            <?php else: ?>
                
                <?php if($email): ?>
                <div class="email-display">
                    <i class="fas fa-envelope"></i> Resetting password for: <strong><?php echo htmlspecialchars($email); ?></strong>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" class="form-control" 
                               placeholder="Enter new password" required>
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Confirm new password" required>
                    </div>
                    
                    <div class="password-requirements">
                        <i class="fas fa-info-circle"></i> <strong>Password Requirements:</strong>
                        <ul>
                            <li>Minimum 6 characters</li>
                            <li>Use a mix of letters and numbers</li>
                            <li>Don't use easily guessable passwords</li>
                        </ul>
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                    
                    <div class="login-link">
                        <a href="forgot-password.php"><i class="fas fa-arrow-left"></i> Back</a> | 
                        <a href="login.php">Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>