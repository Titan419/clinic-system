<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$email = '';
$error = '';
$success = '';
$security_question = '';
$user_id = 0;

// Handle email submission (Step 1)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_email'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email exists
        $query = "SELECT id, full_name, email FROM users WHERE email = :email AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $user['id'];
            
            // In a real system, you would send an email here
            // For demo, we'll use security questions or direct reset
            
            // For simplicity, we'll go directly to password reset
            // In production, you'd send an email with a reset link
            $_SESSION['reset_user_id'] = $user_id;
            $_SESSION['reset_email'] = $email;
            
            // Redirect to reset password page
            header('Location: reset-password.php?email=' . urlencode($email));
            exit();
            
        } else {
            $error = "Email not found in our system";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ClinicCare</title>
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

        .forgot-container {
            width: 100%;
            max-width: 450px;
        }

        .forgot-card {
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

        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .forgot-header i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .forgot-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .forgot-header p {
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

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }

        .info-box i {
            color: #667eea;
            margin-right: 5px;
        }

        .demo-accounts {
            margin-top: 20px;
            padding: 15px;
            background: #e8f4fd;
            border-radius: 8px;
        }

        .demo-accounts h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .demo-accounts p {
            margin: 5px 0;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <i class="fas fa-key"></i>
                <h2>Forgot Password?</h2>
                <p>Enter your email to reset your password</p>
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
            <?php endif; ?>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Enter your Email Address for your account.
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="Enter your registered email" 
                           value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <button type="submit" name="check_email" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
                
                <div class="login-link">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            </form>
            
            
        </div>
    </div>
</body>
</html>
