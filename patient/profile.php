<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is patient
if (!isLoggedIn() || !isPatient()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get patient ID
$query = "SELECT id FROM patients WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: dashboard.php');
    exit();
}

$patient_id = $patient['id'];

// Get patient details with null checks
$query = "SELECT u.*, p.date_of_birth, p.blood_group, p.emergency_contact, 
          p.medical_history, p.allergies
          FROM users u
          LEFT JOIN patients p ON u.id = p.user_id
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Create default array with null checks
$patient_info = [
    'full_name' => $result['full_name'] ?? $_SESSION['full_name'] ?? '',
    'email' => $result['email'] ?? $_SESSION['email'] ?? '',
    'phone' => $result['phone'] ?? '',
    'address' => $result['address'] ?? '',
    'date_of_birth' => $result['date_of_birth'] ?? '',
    'blood_group' => $result['blood_group'] ?? '',
    'emergency_contact' => $result['emergency_contact'] ?? '',
    'medical_history' => $result['medical_history'] ?? '',
    'allergies' => $result['allergies'] ?? ''
];

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
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $blood_group = $_POST['blood_group'] ?? '';
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $medical_history = trim($_POST['medical_history'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        
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
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                // Check if patient record exists
                $check = "SELECT id FROM patients WHERE user_id = :user_id";
                $check_stmt = $db->prepare($check);
                $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing patient record
                    $patient_query = "UPDATE patients SET 
                                    date_of_birth = :date_of_birth,
                                    blood_group = :blood_group,
                                    emergency_contact = :emergency_contact,
                                    medical_history = :medical_history,
                                    allergies = :allergies
                                    WHERE user_id = :user_id";
                } else {
                    // Insert new patient record
                    $patient_query = "INSERT INTO patients (user_id, date_of_birth, blood_group, 
                                    emergency_contact, medical_history, allergies) 
                                    VALUES (:user_id, :date_of_birth, :blood_group, 
                                    :emergency_contact, :medical_history, :allergies)";
                }
                
                $patient_stmt = $db->prepare($patient_query);
                $patient_stmt->bindParam(':date_of_birth', $date_of_birth);
                $patient_stmt->bindParam(':blood_group', $blood_group);
                $patient_stmt->bindParam(':emergency_contact', $emergency_contact);
                $patient_stmt->bindParam(':medical_history', $medical_history);
                $patient_stmt->bindParam(':allergies', $allergies);
                $patient_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $patient_stmt->execute();
                
                $db->commit();
                $success = "✅ Profile updated successfully!";
                
                // Refresh patient info
                $patient_info['full_name'] = $full_name;
                $patient_info['phone'] = $phone;
                $patient_info['email'] = $email;
                $patient_info['address'] = $address;
                $patient_info['date_of_birth'] = $date_of_birth;
                $patient_info['blood_group'] = $blood_group;
                $patient_info['emergency_contact'] = $emergency_contact;
                $patient_info['medical_history'] = $medical_history;
                $patient_info['allergies'] = $allergies;
                
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
            $validation_errors['new_password'] = "Password must be at least 6 characters (e.g., Pass@123)";
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

// Blood group options
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ClinicCare</title>
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

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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

        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .blood-option {
            text-align: center;
        }

        .blood-option input[type="radio"] {
            display: none;
        }

        .blood-option label {
            display: block;
            padding: 10px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0;
            font-weight: 500;
        }

        .blood-option input[type="radio"]:checked + label {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .blood-option label:hover {
            border-color: #3498db;
            background: #e3f2fd;
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
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 13px;
            color: #2c3e50;
            border-left: 4px solid #3498db;
        }

        .field-requirements i {
            color: #27ae60;
            margin-right: 5px;
        }

        .field-requirements ul {
            margin-left: 25px;
            margin-top: 8px;
        }

        .field-requirements li {
            margin: 5px 0;
            color: #34495e;
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
            
            .blood-group-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    $initial = !empty($patient_info['full_name']) ? substr($patient_info['full_name'], 0, 1) : 'P';
                    echo htmlspecialchars($initial);
                    ?>
                </div>
                <h4><?php echo htmlspecialchars($patient_info['full_name'] ?: 'Patient'); ?></h4>
                <p><i class="fas fa-user"></i> Patient</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="book-appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="medical-records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
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
                    $initial = !empty($patient_info['full_name']) ? substr($patient_info['full_name'], 0, 1) : 'P';
                    echo htmlspecialchars($initial);
                    ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($patient_info['full_name'] ?: 'Patient'); ?></h1>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient_info['email'] ?: 'Not provided'); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient_info['phone'] ?: 'Not provided'); ?></p>
                    <?php if($patient_info['blood_group']): ?>
                        <p><i class="fas fa-tint"></i> Blood Group: <?php echo $patient_info['blood_group']; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Buttons -->
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('personal')">
                    <i class="fas fa-user"></i> Personal Information
                </button>
                <button class="tab-btn" onclick="showTab('medical')">
                    <i class="fas fa-notes-medical"></i> Medical Information
                </button>
                <button class="tab-btn" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>

            <!-- Personal Information Tab -->
            <div id="personal-tab" class="tab-content active">
                <h2 style="margin-bottom: 25px;"><i class="fas fa-user"></i> Personal Information</h2>
                <form method="POST" id="personalForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control <?php echo isset($validation_errors['full_name']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($patient_info['full_name']); ?>" 
                                   placeholder="e.g., John Doe" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Enter your full name as on ID</div>
                            <?php if(isset($validation_errors['full_name'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['full_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" class="form-control <?php echo isset($validation_errors['phone']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($patient_info['phone']); ?>" 
                                   placeholder="e.g., +255 712 345 678" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Include country code</div>
                            <?php if(isset($validation_errors['phone'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['phone']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($patient_info['email']); ?>" 
                                   placeholder="e.g., name@example.com" 
                                   required>
                            <div class="hint-text"><i class="fas fa-info-circle"></i> We'll never share your email</div>
                            <?php if(isset($validation_errors['email'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?php echo htmlspecialchars($patient_info['address']); ?>" 
                                   placeholder="e.g., 123 Main St, City">
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Your residential address</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo $patient_info['date_of_birth']; ?>">
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Your birth date</div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone-alt"></i> Emergency Contact</label>
                            <input type="tel" name="emergency_contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($patient_info['emergency_contact']); ?>" 
                                   placeholder="e.g., +255 712 345 679">
                            <div class="hint-text"><i class="fas fa-info-circle"></i> Contact in case of emergency</div>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-check-circle"></i> <strong>Required Fields:</strong> Fields marked with <span class="required">*</span> must be filled
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Personal Information
                    </button>
                </form>
            </div>

            <!-- Medical Information Tab -->
            <div id="medical-tab" class="tab-content">
                <h2 style="margin-bottom: 25px;"><i class="fas fa-notes-medical"></i> Medical Information</h2>
                <form method="POST" id="medicalForm">
                    <div class="form-group">
                        <label><i class="fas fa-tint"></i> Blood Group</label>
                        <div class="blood-group-grid">
                            <?php foreach($blood_groups as $bg): ?>
                            <div class="blood-option">
                                <input type="radio" name="blood_group" value="<?php echo $bg; ?>" 
                                       id="bg_<?php echo $bg; ?>"
                                       <?php echo ($patient_info['blood_group'] == $bg) ? 'checked' : ''; ?>>
                                <label for="bg_<?php echo $bg; ?>"><?php echo $bg; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hint-text"><i class="fas fa-info-circle"></i> Select your blood type</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-history"></i> Medical History</label>
                        <textarea name="medical_history" class="form-control" 
                                  placeholder="e.g., High blood pressure, Diabetes, Previous surgeries..."><?php echo htmlspecialchars($patient_info['medical_history']); ?></textarea>
                        <div class="hint-text"><i class="fas fa-info-circle"></i> List any chronic conditions or past medical events</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-allergies"></i> Allergies</label>
                        <textarea name="allergies" class="form-control" 
                                  placeholder="e.g., Penicillin, Peanuts, Pollen..."><?php echo htmlspecialchars($patient_info['allergies']); ?></textarea>
                        <div class="hint-text"><i class="fas fa-info-circle"></i> List any known allergies</div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Medical information helps doctors provide better care
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Medical Information
                    </button>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-content">
                <h2 style="margin-bottom: 25px;"><i class="fas fa-lock"></i> Change Password</h2>
                <form method="POST" id="passwordForm" novalidate>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" class="form-control <?php echo isset($validation_errors['current_password']) ? 'error' : ''; ?>" 
                               placeholder="Enter your current password" 
                               required>
                        <?php if(isset($validation_errors['current_password'])): ?>
                            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['current_password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password <span class="required">*</span></label>
                            <input type="password" name="new_password" class="form-control <?php echo isset($validation_errors['new_password']) ? 'error' : ''; ?>" 
                                   placeholder="e.g., Pass@123 (min 6 characters)" 
                                   required>
                            <?php if(isset($validation_errors['new_password'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['new_password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>" 
                                   placeholder="Re-enter your new password" 
                                   required>
                            <?php if(isset($validation_errors['confirm_password'])): ?>
                                <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="field-requirements">
                        <i class="fas fa-check-circle"></i> <strong>Password Requirements:</strong>
                        <ul>
                            <li>Minimum 6 characters</li>
                            <li>Use a mix of letters and numbers</li>
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
            const emailField = this.querySelector('input[name="email"]');
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