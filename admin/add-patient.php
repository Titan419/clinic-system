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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Get form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    $blood_group = $_POST['blood_group'];
    $emergency_contact = trim($_POST['emergency_contact']);
    $medical_history = trim($_POST['medical_history']);
    $allergies = trim($_POST['allergies']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password != $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Email already exists in the system";
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $user_query = "INSERT INTO users (email, password, full_name, phone, address, user_type, is_active) 
                          VALUES (:email, :password, :full_name, :phone, :address, 'patient', 1)";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':email', $email);
            $user_stmt->bindParam(':password', $hashed_password);
            $user_stmt->bindParam(':full_name', $full_name);
            $user_stmt->bindParam(':phone', $phone);
            $user_stmt->bindParam(':address', $address);
            $user_stmt->execute();
            
            $user_id = $db->lastInsertId();
            
            // Insert into patients table
            $patient_query = "INSERT INTO patients (user_id, date_of_birth, blood_group, emergency_contact, medical_history, allergies) 
                             VALUES (:user_id, :date_of_birth, :blood_group, :emergency_contact, :medical_history, :allergies)";
            $patient_stmt = $db->prepare($patient_query);
            $patient_stmt->bindParam(':user_id', $user_id);
            $patient_stmt->bindParam(':date_of_birth', $date_of_birth);
            $patient_stmt->bindParam(':blood_group', $blood_group);
            $patient_stmt->bindParam(':emergency_contact', $emergency_contact);
            $patient_stmt->bindParam(':medical_history', $medical_history);
            $patient_stmt->bindParam(':allergies', $allergies);
            $patient_stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            $success = "Patient added successfully!";
            
            // Clear form data after success
            $_POST = array();
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->rollBack();
            $error = "Database error: " . $e->getMessage();
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
    <title>Add New Patient - ClinicCare</title>
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
        }

        .sidebar-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
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
            font-size: 1.1rem;
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

        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            font-size: 2rem;
            color: #3498db;
            background: rgba(52,152,219,0.1);
            padding: 12px;
            border-radius: 12px;
        }

        .page-title h1 {
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .page-title p {
            color: #7f8c8d;
            margin-top: 5px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.3);
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label i {
            color: #3498db;
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
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

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .password-hint {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .blood-group-option {
            text-align: center;
        }

        .blood-group-option input[type="radio"] {
            display: none;
        }

        .blood-group-option label {
            display: block;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0;
            font-weight: normal;
        }

        .blood-group-option input[type="radio"]:checked + label {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .blood-group-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logs.php"><i class="fas fa-history"></i> System Logs</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <i class="fas fa-user-plus"></i>
                    <div>
                        <h1>Add New Patient</h1>
                        <p>Register a new patient in the system</p>
                    </div>
                </div>
                <a href="patients.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Patients
                </a>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" action="" onsubmit="return validateForm()">
                    <div class="form-grid">
                        <!-- Personal Information -->
                        <div class="form-group full-width">
                            <h3 style="color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                                <i class="fas fa-user" style="color: #3498db;"></i> Personal Information
                            </h3>
                        </div>
                        
                        <div class="form-group">
                            <label class="required"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                                   placeholder="Enter full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   placeholder="Enter email address" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                   placeholder="Enter phone number" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea name="address" class="form-control" placeholder="Enter full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Medical Information -->
                        <div class="form-group full-width">
                            <h3 style="color: #2c3e50; margin: 20px 0 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                                <i class="fas fa-notes-medical" style="color: #3498db;"></i> Medical Information
                            </h3>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tint"></i> Blood Group</label>
                            <div class="blood-group-grid">
                                <?php 
                                $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                $selected_blood = isset($_POST['blood_group']) ? $_POST['blood_group'] : '';
                                foreach($blood_groups as $bg): 
                                ?>
                                <div class="blood-group-option">
                                    <input type="radio" name="blood_group" value="<?php echo $bg; ?>" 
                                           id="bg_<?php echo $bg; ?>" 
                                           <?php echo ($selected_blood == $bg) ? 'checked' : ''; ?>>
                                    <label for="bg_<?php echo $bg; ?>"><?php echo $bg; ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone-alt"></i> Emergency Contact</label>
                            <input type="tel" name="emergency_contact" class="form-control" 
                                   value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>" 
                                   placeholder="Emergency contact number">
                        </div>
                        
                        <div class="form-group full-width">
                            <label><i class="fas fa-notes-medical"></i> Medical History</label>
                            <textarea name="medical_history" class="form-control" placeholder="Any previous medical conditions, surgeries, etc."><?php echo isset($_POST['medical_history']) ? htmlspecialchars($_POST['medical_history']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label><i class="fas fa-allergies"></i> Allergies</label>
                            <textarea name="allergies" class="form-control" placeholder="Any known allergies (medications, foods, etc.)"><?php echo isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Account Security -->
                        <div class="form-group full-width">
                            <h3 style="color: #2c3e50; margin: 20px 0 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                                <i class="fas fa-lock" style="color: #3498db;"></i> Account Security
                            </h3>
                        </div>
                        
                        <div class="form-group">
                            <label class="required"><i class="fas fa-key"></i> Password</label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter password" required>
                            <div class="password-hint">Minimum 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required"><i class="fas fa-check-circle"></i> Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm password" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary" onclick="return confirm('Reset all fields?')">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Patient
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    function validateForm() {
        const password = document.querySelector('input[name="password"]').value;
        const confirm = document.querySelector('input[name="confirm_password"]').value;
        
        if (password.length < 6) {
            alert('Password must be at least 6 characters long');
            return false;
        }
        
        if (password !== confirm) {
            alert('Passwords do not match');
            return false;
        }
        
        return true;
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