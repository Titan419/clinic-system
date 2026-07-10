<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is doctor
if (!isLoggedIn() || !isDoctor()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get doctor ID
$query = "SELECT id FROM doctors WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    // If no doctor record found, redirect to dashboard
    header('Location: dashboard.php');
    exit();
}

$doctor_id = $doctor['id'];

// Get doctor details with user information
$query = "SELECT u.*, d.specialization, d.qualification, d.experience_years, 
          d.consultation_fee, d.available_days, d.start_time, d.end_time
          FROM users u
          LEFT JOIN doctors d ON u.id = d.user_id
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Create default array with null checks
$doctor_info = [
    'full_name' => $result['full_name'] ?? $_SESSION['full_name'] ?? '',
    'email' => $result['email'] ?? $_SESSION['email'] ?? '',
    'phone' => $result['phone'] ?? '',
    'address' => $result['address'] ?? '',
    'specialization' => $result['specialization'] ?? '',
    'qualification' => $result['qualification'] ?? '',
    'experience_years' => $result['experience_years'] ?? '',
    'consultation_fee' => $result['consultation_fee'] ?? '',
    'available_days' => $result['available_days'] ?? '',
    'start_time' => $result['start_time'] ?? '09:00',
    'end_time' => $result['end_time'] ?? '17:00'
];

// Get statistics with null checks
$stats = [];

// Total patients treated
$query = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_patients'] = isset($result['count']) ? $result['count'] : 0;

// Total appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_appointments'] = isset($result['count']) ? $result['count'] : 0;

// Completed appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['completed'] = isset($result['count']) ? $result['count'] : 0;

// Pending appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['pending'] = isset($result['count']) ? $result['count'] : 0;

$success = '';
$error = '';
$validation_errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Get form data
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience_years = isset($_POST['experience_years']) ? (int)$_POST['experience_years'] : '';
        $consultation_fee = isset($_POST['consultation_fee']) ? (float)$_POST['consultation_fee'] : '';
        $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        
        // Validate all fields
        $validation_errors = [];
        
        if (empty($full_name)) {
            $validation_errors['full_name'] = "Full name is required";
        }
        
        if (empty($phone)) {
            $validation_errors['phone'] = "Phone number is required";
        }
        
        if (empty($email)) {
            $validation_errors['email'] = "Email address is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors['email'] = "Please enter a valid email address (e.g., name@example.com)";
        }
        
        if (empty($specialization)) {
            $validation_errors['specialization'] = "Specialization is required";
        }
        
        if (empty($qualification)) {
            $validation_errors['qualification'] = "Qualification is required";
        }
        
        if ($experience_years === '' || $experience_years < 0) {
            $validation_errors['experience_years'] = "Please enter valid years of experience";
        }
        
        if ($consultation_fee === '' || $consultation_fee < 0) {
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
        
        // If no validation errors, proceed with update
        if (empty($validation_errors)) {
            try {
                $db->beginTransaction();
                
                // Update users table
                $user_query = "UPDATE users SET full_name = :full_name, phone = :phone, 
                              email = :email, address = :address WHERE id = :id";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindParam(':full_name', $full_name);
                $user_stmt->bindParam(':phone', $phone);
                $user_stmt->bindParam(':email', $email);
                $user_stmt->bindParam(':address', $address);
                $user_stmt->bindParam(':id', $_SESSION['user_id']);
                $user_stmt->execute();
                
                // Check if doctor record exists
                $check = "SELECT id FROM doctors WHERE user_id = :user_id";
                $check_stmt = $db->prepare($check);
                $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing doctor record
                    $doctor_query = "UPDATE doctors SET specialization = :specialization, 
                                    qualification = :qualification, experience_years = :experience_years,
                                    consultation_fee = :consultation_fee, available_days = :available_days,
                                    start_time = :start_time, end_time = :end_time
                                    WHERE user_id = :user_id";
                } else {
                    // Insert new doctor record
                    $doctor_query = "INSERT INTO doctors (user_id, specialization, qualification, experience_years, 
                                    consultation_fee, available_days, start_time, end_time) 
                                    VALUES (:user_id, :specialization, :qualification, :experience_years, 
                                    :consultation_fee, :available_days, :start_time, :end_time)";
                }
                
                $doctor_stmt = $db->prepare($doctor_query);
                $doctor_stmt->bindParam(':specialization', $specialization);
                $doctor_stmt->bindParam(':qualification', $qualification);
                $doctor_stmt->bindParam(':experience_years', $experience_years);
                $doctor_stmt->bindParam(':consultation_fee', $consultation_fee);
                $doctor_stmt->bindParam(':available_days', $available_days);
                $doctor_stmt->bindParam(':start_time', $start_time);
                $doctor_stmt->bindParam(':end_time', $end_time);
                $doctor_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $doctor_stmt->execute();
                
                $db->commit();
                $success = "✅ Profile updated successfully!";
                
                // Refresh doctor info
                $doctor_info['full_name'] = $full_name;
                $doctor_info['phone'] = $phone;
                $doctor_info['email'] = $email;
                $doctor_info['address'] = $address;
                $doctor_info['specialization'] = $specialization;
                $doctor_info['qualification'] = $qualification;
                $doctor_info['experience_years'] = $experience_years;
                $doctor_info['consultation_fee'] = $consultation_fee;
                $doctor_info['available_days'] = $available_days;
                $doctor_info['start_time'] = $start_time;
                $doctor_info['end_time'] = $end_time;
                
            } catch (PDOException $e) {
                $db->rollBack();
                $error = "❌ Error updating profile: " . $e->getMessage();
            }
        } else {
            $error = "⚠️ Please fix the errors below and try again.";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $validation_errors = [];
        
        if (empty($current_password)) {
            $validation_errors['current_password'] = "Current password is required";
        }
        
        if (empty($new_password)) {
            $validation_errors['new_password'] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $validation_errors['new_password'] = "Password must be at least 6 characters (e.g., Doc@123)";
        }
        
        if (empty($confirm_password)) {
            $validation_errors['confirm_password'] = "Please confirm your new password";
        } elseif ($new_password != $confirm_password) {
            $validation_errors['confirm_password'] = "Passwords do not match";
        }
        
        if (empty($validation_errors)) {
            // Verify current password
            $query = "SELECT password FROM users WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $db->prepare($update);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':id', $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $success = "✅ Password changed successfully!";
                } else {
                    $error = "❌ Failed to change password.";
                }
            } else {
                $validation_errors['current_password'] = "Current password is incorrect";
                $error = "⚠️ Current password is incorrect";
            }
        } else {
            $error = "⚠️ Please fix the password errors below.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - ClinicCare</title>
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

        .user-welcome {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 10px;
            border: 3px solid rgba(255,255,255,0.2);
        }

        .user-welcome h4 {
            color: white;
            margin-bottom: 5px;
        }

        .user-welcome p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
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

        .profile-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .profile-info h1 {
            font-size: 2.2rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .profile-info p {
            color: #7f8c8d;
            margin: 5px 0;
            font-size: 1rem;
        }

        .profile-info i {
            width: 25px;
            color: #3498db;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 25px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            color: #7f8c8d;
        }

        .tab-btn:hover {
            background: #f0f0f0;
        }

        .tab-btn.active {
            background: #3498db;
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .form-control::placeholder {
            color: #bdc3c7;
            font-style: italic;
            font-size: 13px;
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .error-message i {
            font-size: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 12px 25px;
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .days-checkbox {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
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

        .hint-text {
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 5px;
            font-style: italic;
        }

        .hint-text i {
            color: #3498db;
            margin-right: 3px;
        }

        .field-requirements {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 12px;
            color: #2c3e50;
        }

        .field-requirements i {
            color: #27ae60;
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .working-hours {
                grid-template-columns: 1fr;
            }
            
            .tab-buttons {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
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
            
            <div class="user-welcome">
                <div class="user-avatar-large">
                    <?php 
                    $initial = !empty($doctor_info['full_name']) ? substr($doctor_info['full_name'], 0, 1) : 'D';
                    echo htmlspecialchars($initial);
                    ?>
                </div>
                <h4>Dr. <?php echo htmlspecialchars($doctor_info['full_name'] ?: 'Doctor'); ?></h4>
                <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor_info['specialization'] ?: 'General Physician'); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?php 
                    $initial = !empty($doctor_info['full_name']) ? substr($doctor_info['full_name'], 0, 1) : 'D';
                    echo htmlspecialchars($initial);
                    ?>
                </div>
                <div class="profile-info">
                    <h1>Dr. <?php echo htmlspecialchars($doctor_info['full_name'] ?: 'Doctor'); ?></h1>
                    <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor_info['specialization'] ?: 'General Physician'); ?></p>
                    <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($doctor_info['qualification'] ?: 'Not specified'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor_info['email'] ?: 'Not provided'); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor_info['phone'] ?: 'Not provided'); ?></p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $stats['total_patients']; ?></h3>
                    <p>Total Patients</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $stats['total_appointments']; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>

            <!-- Tab Buttons -->
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('personal')">
                    <i class="fas fa-user"></i> Personal Information
                </button>
                <button class="tab-btn" onclick="showTab('professional')">
                    <i class="fas fa-briefcase"></i> Professional Details
                </button>
                <button class="tab-btn" onclick="showTab('schedule')">
                    <i class="fas fa-clock"></i> Working Schedule
                </button>
                <button class="tab-btn" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>

            <!-- Personal Information Tab -->
            <div id="personal-tab" class="tab-content active">
                <h2 style="margin-bottom: 25px;">Personal Information</h2>
                <form method="POST" id="personalForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control <?php echo isset($validation_errors['full_name']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($doctor_info['full_name']); ?>" 
                                   placeholder="e.g., John Smith" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Enter your full name as per official records</div>
                            <?php if(isset($validation_errors['full_name'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['full_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" class="form-control <?php echo isset($validation_errors['phone']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($doctor_info['phone']); ?>" 
                                   placeholder="e.g., +255 712 345 678" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Include country code for international numbers</div>
                            <?php if(isset($validation_errors['phone'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['phone']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($doctor_info['email']); ?>" 
                                   placeholder="e.g., doctor@example.com" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> We'll never share your email</div>
                            <?php if(isset($validation_errors['email'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?php echo htmlspecialchars($doctor_info['address']); ?>" 
                                   placeholder="e.g., 123 Healthcare St, Dar es Salaam">
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Optional: Your clinic or home address</div>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-check-circle"></i> All fields marked with <span class="required">*</span> are required
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Personal Information
                    </button>
                </form>
            </div>

            <!-- Professional Details Tab -->
            <div id="professional-tab" class="tab-content">
                <h2 style="margin-bottom: 25px;">Professional Details</h2>
                <form method="POST" id="professionalForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-stethoscope"></i> Specialization <span class="required">*</span></label>
                            <input type="text" name="specialization" class="form-control <?php echo isset($validation_errors['specialization']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($doctor_info['specialization']); ?>" 
                                   placeholder="e.g., Cardiology, Pediatrics, General Medicine" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Your medical specialty area</div>
                            <?php if(isset($validation_errors['specialization'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['specialization']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-graduation-cap"></i> Qualification <span class="required">*</span></label>
                            <input type="text" name="qualification" class="form-control <?php echo isset($validation_errors['qualification']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($doctor_info['qualification']); ?>" 
                                   placeholder="e.g., MD, MBBS, PhD" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Your highest medical qualifications</div>
                            <?php if(isset($validation_errors['qualification'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['qualification']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Experience (Years) <span class="required">*</span></label>
                            <input type="number" name="experience_years" class="form-control <?php echo isset($validation_errors['experience_years']) ? 'error' : ''; ?>" 
                                   value="<?php echo $doctor_info['experience_years']; ?>" 
                                   placeholder="e.g., 10" 
                                   min="0" max="70" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Total years of medical practice</div>
                            <?php if(isset($validation_errors['experience_years'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['experience_years']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-money-bill"></i> Consultation Fee (TSh) <span class="required">*</span></label>
                            <input type="number" name="consultation_fee" class="form-control <?php echo isset($validation_errors['consultation_fee']) ? 'error' : ''; ?>" 
                                   value="<?php echo $doctor_info['consultation_fee']; ?>" 
                                   placeholder="e.g., 50000" 
                                   min="0" step="1000" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Standard consultation fee in Tanzanian Shillings</div>
                            <?php if(isset($validation_errors['consultation_fee'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['consultation_fee']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-check-circle"></i> All fields marked with <span class="required">*</span> are required
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Professional Details
                    </button>
                </form>
            </div>

            <!-- Working Schedule Tab -->
            <div id="schedule-tab" class="tab-content">
                <h2 style="margin-bottom: 25px;">Working Schedule</h2>
                <form method="POST" id="scheduleForm" novalidate>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Available Days <span class="required">*</span></label>
                        <div class="days-checkbox <?php echo isset($validation_errors['available_days']) ? 'error' : ''; ?>">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $available_days = !empty($doctor_info['available_days']) ? explode(',', $doctor_info['available_days']) : [];
                            foreach($days as $day):
                            ?>
                            <div class="day-item">
                                <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" 
                                       id="day_<?php echo $day; ?>"
                                       <?php echo in_array($day, $available_days) ? 'checked' : ''; ?>>
                                <label for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hint-text"><i class="fas fa-info-circle"></i> Select the days you are available for consultations</div>
                        <?php if(isset($validation_errors['available_days'])): ?>
                            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['available_days']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="working-hours">
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Start Time <span class="required">*</span></label>
                            <input type="time" name="start_time" class="form-control <?php echo isset($validation_errors['start_time']) ? 'error' : ''; ?>" 
                                   value="<?php echo $doctor_info['start_time']; ?>" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> When your work day begins (e.g., 09:00)</div>
                            <?php if(isset($validation_errors['start_time'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['start_time']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> End Time <span class="required">*</span></label>
                            <input type="time" name="end_time" class="form-control <?php echo isset($validation_errors['end_time']) ? 'error' : ''; ?>" 
                                   value="<?php echo $doctor_info['end_time']; ?>" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> When your work day ends (e.g., 17:00)</div>
                            <?php if(isset($validation_errors['end_time'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['end_time']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-check-circle"></i> All fields marked with <span class="required">*</span> are required
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Schedule
                    </button>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-content">
                <h2 style="margin-bottom: 25px;">Change Password</h2>
                <form method="POST" id="passwordForm" novalidate>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" class="form-control <?php echo isset($validation_errors['current_password']) ? 'error' : ''; ?>" 
                               placeholder="Enter your current password" 
                               required>
                        <div class="hint-text"><i class="fas fa-info-circle"></i> Your existing password for verification</div>
                        <?php if(isset($validation_errors['current_password'])): ?>
                            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['current_password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password <span class="required">*</span></label>
                            <input type="password" name="new_password" class="form-control <?php echo isset($validation_errors['new_password']) ? 'error' : ''; ?>" 
                                   placeholder="e.g., Doc@123 (min 6 characters)" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Use a mix of letters, numbers, and symbols for security</div>
                            <?php if(isset($validation_errors['new_password'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['new_password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>" 
                                   placeholder="Re-enter your new password" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Both passwords must match exactly</div>
                            <?php if(isset($validation_errors['confirm_password'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-check-circle"></i> Password Requirements:
                        <ul style="margin-left: 20px; margin-top: 5px;">
                            <li>Minimum 6 characters</li>
                            <li>Use a mix of letters and numbers for better security</li>
                            <li>Don't use easily guessable passwords</li>
                        </ul>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }
    
    // Form validation before submit
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    hasError = true;
                    
                    // Check if error message exists, if not create one
                    let errorDiv = field.parentElement.querySelector('.error-message');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> This field is required';
                        field.parentElement.appendChild(errorDiv);
                    }
                } else {
                    field.classList.remove('error');
                    let errorDiv = field.parentElement.querySelector('.error-message');
                    if (errorDiv && errorDiv.innerText.includes('This field is required')) {
                        errorDiv.remove();
                    }
                }
            });
            
            // Email validation
            const emailField = this.querySelector('input[type="email"]');
            if (emailField && emailField.value) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailField.value)) {
                    emailField.classList.add('error');
                    hasError = true;
                    
                    let errorDiv = emailField.parentElement.querySelector('.error-message');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid email address';
                        emailField.parentElement.appendChild(errorDiv);
                    }
                }
            }
            
            // Password match validation
            const passwordField = this.querySelector('input[name="new_password"]');
            const confirmField = this.querySelector('input[name="confirm_password"]');
            if (passwordField && confirmField && passwordField.value && confirmField.value) {
                if (passwordField.value !== confirmField.value) {
                    passwordField.classList.add('error');
                    confirmField.classList.add('error');
                    hasError = true;
                    
                    let errorDiv = confirmField.parentElement.querySelector('.error-message');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match';
                        confirmField.parentElement.appendChild(errorDiv);
                    }
                }
            }
            
            if (hasError) {
                e.preventDefault();
                alert('⚠️ Please fill in all required fields correctly.');
            }
        });
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