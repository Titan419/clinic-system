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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
    $start_time = $_POST['start_time'] ?? '09:00';
    $end_time = $_POST['end_time'] ?? '17:00';
    
    // Validate inputs
    $validation_errors = [];
    
    if (empty($full_name)) {
        $validation_errors['full_name'] = "Doctor's full name is required";
    }
    
    if (empty($email)) {
        $validation_errors['email'] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = "Please enter a valid email address";
    }
    
    if (empty($phone)) {
        $validation_errors['phone'] = "Phone number is required";
    }
    
    if (empty($password)) {
        $validation_errors['password'] = "Password is required";
    } elseif (strlen($password) < 6) {
        $validation_errors['password'] = "Password must be at least 6 characters";
    }
    
    if ($password != $confirm_password) {
        $validation_errors['confirm_password'] = "Passwords do not match";
    }
    
    if (empty($specialization)) {
        $validation_errors['specialization'] = "Specialization is required";
    }
    
    if (empty($qualification)) {
        $validation_errors['qualification'] = "Qualification is required";
    }
    
    if ($experience_years <= 0) {
        $validation_errors['experience_years'] = "Please enter valid years of experience";
    }
    
    if ($consultation_fee <= 0) {
        $validation_errors['consultation_fee'] = "Please enter a valid consultation fee";
    }
    
    if (empty($available_days)) {
        $validation_errors['available_days'] = "Please select at least one working day";
    }
    
    if (empty($start_time)) {
        $validation_errors['start_time'] = "Start time is required";
    }
    
    if (empty($end_time)) {
        $validation_errors['end_time'] = "End time is required";
    }
    
    // Check if email already exists
    if (empty($validation_errors)) {
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $validation_errors['email'] = "Email already exists in the system";
        }
    }
    
    // If no errors, insert into database
    if (empty($validation_errors)) {
        try {
            $db->beginTransaction();
            
            // Insert into users table
            $user_query = "INSERT INTO users (email, password, full_name, phone, address, user_type, is_active) 
                          VALUES (:email, :password, :full_name, :phone, :address, 'doctor', 1)";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':email', $email);
            $user_stmt->bindParam(':password', $password); // Store as plain text for demo
            $user_stmt->bindParam(':full_name', $full_name);
            $user_stmt->bindParam(':phone', $phone);
            $user_stmt->bindParam(':address', $address);
            $user_stmt->execute();
            
            $user_id = $db->lastInsertId();
            
            // Insert into doctors table
            $doctor_query = "INSERT INTO doctors (user_id, specialization, qualification, experience_years, 
                            consultation_fee, available_days, start_time, end_time) 
                            VALUES (:user_id, :specialization, :qualification, :experience_years, 
                            :consultation_fee, :available_days, :start_time, :end_time)";
            $doctor_stmt = $db->prepare($doctor_query);
            $doctor_stmt->bindParam(':user_id', $user_id);
            $doctor_stmt->bindParam(':specialization', $specialization);
            $doctor_stmt->bindParam(':qualification', $qualification);
            $doctor_stmt->bindParam(':experience_years', $experience_years);
            $doctor_stmt->bindParam(':consultation_fee', $consultation_fee);
            $doctor_stmt->bindParam(':available_days', $available_days);
            $doctor_stmt->bindParam(':start_time', $start_time);
            $doctor_stmt->bindParam(':end_time', $end_time);
            $doctor_stmt->execute();
            
            $db->commit();
            $success = "✅ Dr. " . $full_name . " has been added successfully!";
            
            // Clear form data
            $_POST = array();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "❌ Database error: " . $e->getMessage();
        }
    } else {
        $error = "⚠️ Please fix the errors below and try again.";
    }
}

// Days of week for checkbox
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Doctor - ClinicCare</title>
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

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #3498db;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
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

        .form-control::placeholder {
            color: #bdc3c7;
            font-style: italic;
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .days-checkbox {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .day-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .day-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .day-item label {
            margin-bottom: 0;
            cursor: pointer;
            color: #2c3e50;
        }

        .working-hours {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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

        .hint-text {
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 5px;
        }

        .field-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-row {
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-clinic-medical"></i> ClinicCare</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-user-md"></i> Add New Doctor</h1>
                <a href="doctors.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Doctors
                </a>
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
            
            <div class="form-container">
                <form method="POST" action="" id="addDoctorForm">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" class="form-control <?php echo isset($validation_errors['full_name']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., John Smith" 
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['full_name'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['full_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                                <input type="tel" name="phone" class="form-control <?php echo isset($validation_errors['phone']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., +255712345678" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['phone'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., doctor@clinic.com" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['email'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Address</label>
                                <input type="text" name="address" class="form-control" 
                                       placeholder="e.g., 123 Medical Street" 
                                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                                <input type="password" name="password" class="form-control <?php echo isset($validation_errors['password']) ? 'error' : ''; ?>" 
                                       placeholder="Min 6 characters" 
                                       required>
                                <?php if(isset($validation_errors['password'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-check-circle"></i> Confirm Password <span class="required">*</span></label>
                                <input type="password" name="confirm_password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>" 
                                       placeholder="Re-enter password" 
                                       required>
                                <?php if(isset($validation_errors['confirm_password'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-stethoscope"></i> Specialization <span class="required">*</span></label>
                                <input type="text" name="specialization" class="form-control <?php echo isset($validation_errors['specialization']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., Cardiology, Pediatrics" 
                                       value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['specialization'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['specialization']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Qualification <span class="required">*</span></label>
                                <input type="text" name="qualification" class="form-control <?php echo isset($validation_errors['qualification']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., MD, MBBS, PhD" 
                                       value="<?php echo isset($_POST['qualification']) ? htmlspecialchars($_POST['qualification']) : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['qualification'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['qualification']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Experience (Years) <span class="required">*</span></label>
                                <input type="number" name="experience_years" class="form-control <?php echo isset($validation_errors['experience_years']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., 10" min="0" max="70"
                                       value="<?php echo isset($_POST['experience_years']) ? $_POST['experience_years'] : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['experience_years'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['experience_years']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-money-bill"></i> Consultation Fee (TSh) <span class="required">*</span></label>
                                <input type="number" name="consultation_fee" class="form-control <?php echo isset($validation_errors['consultation_fee']) ? 'error' : ''; ?>" 
                                       placeholder="e.g., 50000" min="0" step="1000"
                                       value="<?php echo isset($_POST['consultation_fee']) ? $_POST['consultation_fee'] : ''; ?>"
                                       required>
                                <?php if(isset($validation_errors['consultation_fee'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['consultation_fee']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Working Schedule -->
                    <div class="form-section">
                        <h3><i class="fas fa-clock"></i> Working Schedule</h3>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Available Days <span class="required">*</span></label>
                            <div class="days-checkbox">
                                <?php foreach($days_of_week as $day): ?>
                                <div class="day-item">
                                    <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" 
                                           id="day_<?php echo $day; ?>"
                                           <?php echo (isset($_POST['available_days']) && in_array($day, $_POST['available_days'])) ? 'checked' : ''; ?>>
                                    <label for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if(isset($validation_errors['available_days'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['available_days']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="working-hours">
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Start Time <span class="required">*</span></label>
                                <input type="time" name="start_time" class="form-control <?php echo isset($validation_errors['start_time']) ? 'error' : ''; ?>" 
                                       value="<?php echo isset($_POST['start_time']) ? $_POST['start_time'] : '09:00'; ?>"
                                       required>
                                <?php if(isset($validation_errors['start_time'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['start_time']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> End Time <span class="required">*</span></label>
                                <input type="time" name="end_time" class="form-control <?php echo isset($validation_errors['end_time']) ? 'error' : ''; ?>" 
                                       value="<?php echo isset($_POST['end_time']) ? $_POST['end_time'] : '17:00'; ?>"
                                       required>
                                <?php if(isset($validation_errors['end_time'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['end_time']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-info-circle"></i> All fields marked with <span class="required">*</span> are required
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Doctor
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
    // Form validation
    document.getElementById('addDoctorForm').addEventListener('submit', function(e) {
        const password = document.querySelector('input[name="password"]').value;
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