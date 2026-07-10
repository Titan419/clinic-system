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

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get doctor details
$query = "SELECT u.*, d.specialization, d.qualification, d.experience_years, 
          d.consultation_fee, d.available_days, d.start_time, d.end_time
          FROM users u
          JOIN doctors d ON u.id = d.user_id
          WHERE u.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $doctor_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: doctors.php');
    exit();
}

$success = '';
$error = '';
$validation_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
    $start_time = $_POST['start_time'] ?? '09:00';
    $end_time = $_POST['end_time'] ?? '17:00';
    
    // Validate
    $validation_errors = [];
    
    if (empty($full_name)) {
        $validation_errors['full_name'] = "Full name is required";
    }
    
    if (empty($email)) {
        $validation_errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = "Invalid email format";
    }
    
    if (empty($specialization)) {
        $validation_errors['specialization'] = "Specialization is required";
    }
    
    if (empty($qualification)) {
        $validation_errors['qualification'] = "Qualification is required";
    }
    
    if ($experience_years <= 0) {
        $validation_errors['experience_years'] = "Experience years must be greater than 0";
    }
    
    if ($consultation_fee <= 0) {
        $validation_errors['consultation_fee'] = "Consultation fee must be greater than 0";
    }
    
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
            $user_stmt->bindParam(':id', $doctor_id);
            $user_stmt->execute();
            
            // Update doctors table
            $doctor_query = "UPDATE doctors SET specialization = :specialization, 
                            qualification = :qualification, experience_years = :experience_years,
                            consultation_fee = :consultation_fee, available_days = :available_days,
                            start_time = :start_time, end_time = :end_time
                            WHERE user_id = :user_id";
            $doctor_stmt = $db->prepare($doctor_query);
            $doctor_stmt->bindParam(':specialization', $specialization);
            $doctor_stmt->bindParam(':qualification', $qualification);
            $doctor_stmt->bindParam(':experience_years', $experience_years);
            $doctor_stmt->bindParam(':consultation_fee', $consultation_fee);
            $doctor_stmt->bindParam(':available_days', $available_days);
            $doctor_stmt->bindParam(':start_time', $start_time);
            $doctor_stmt->bindParam(':end_time', $end_time);
            $doctor_stmt->bindParam(':user_id', $doctor_id);
            $doctor_stmt->execute();
            
            $db->commit();
            $success = "✅ Doctor updated successfully!";
            
            // Refresh doctor data
            $stmt->execute();
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "❌ Error: " . $e->getMessage();
        }
    } else {
        $error = "⚠️ Please fix the errors below.";
    }
}

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$available_days = !empty($doctor['available_days']) ? explode(',', $doctor['available_days']) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - ClinicCare</title>
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

        /* SIDEBAR STYLES - FULL OPTIONS */
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

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            max-width: 1000px;
            margin: 0 auto;
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

        .form-control[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
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
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.3);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
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

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- SIDEBAR - FULL OPTIONS -->
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
                <li><a href="reset-password.php"><i class="fas fa-key"></i> Reset Password</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-user-edit"></i> Edit Doctor</h1>
                <div>
                    <a href="view-doctor.php?id=<?php echo $doctor_id; ?>" class="btn btn-primary">
                        <i class="fas fa-eye"></i> View Profile
                    </a>
                    <a href="doctors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Doctors
                    </a>
                </div>
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
                <form method="POST" action="">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                                <input type="text" name="full_name" class="form-control <?php echo isset($validation_errors['full_name']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($doctor['full_name']); ?>" 
                                       placeholder="e.g., Dr. John Smith" required>
                                <?php if(isset($validation_errors['full_name'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['full_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['phone']); ?>" 
                                       placeholder="e.g., +255712345678">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($doctor['email']); ?>" 
                                       placeholder="doctor@clinic.com" required>
                                <?php if(isset($validation_errors['email'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Address</label>
                                <input type="text" name="address" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['address']); ?>" 
                                       placeholder="e.g., 123 Medical Street">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-stethoscope"></i> Specialization <span class="required">*</span></label>
                                <input type="text" name="specialization" class="form-control <?php echo isset($validation_errors['specialization']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($doctor['specialization']); ?>" 
                                       placeholder="e.g., Cardiologist" required>
                                <?php if(isset($validation_errors['specialization'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['specialization']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Qualification <span class="required">*</span></label>
                                <input type="text" name="qualification" class="form-control <?php echo isset($validation_errors['qualification']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($doctor['qualification']); ?>" 
                                       placeholder="e.g., MD, PhD" required>
                                <?php if(isset($validation_errors['qualification'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['qualification']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Experience (Years) <span class="required">*</span></label>
                                <input type="number" name="experience_years" class="form-control <?php echo isset($validation_errors['experience_years']) ? 'error' : ''; ?>" 
                                       value="<?php echo $doctor['experience_years']; ?>" 
                                       placeholder="e.g., 10" min="0" required>
                                <?php if(isset($validation_errors['experience_years'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['experience_years']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-money-bill"></i> Consultation Fee (TSh) <span class="required">*</span></label>
                                <input type="number" name="consultation_fee" class="form-control <?php echo isset($validation_errors['consultation_fee']) ? 'error' : ''; ?>" 
                                       value="<?php echo $doctor['consultation_fee']; ?>" 
                                       placeholder="e.g., 50000" min="0" step="1000" required>
                                <?php if(isset($validation_errors['consultation_fee'])): ?>
                                    <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $validation_errors['consultation_fee']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Working Schedule Section -->
                    <div class="form-section">
                        <h3><i class="fas fa-clock"></i> Working Schedule</h3>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Available Days</label>
                            <div class="days-checkbox">
                                <?php foreach($days_of_week as $day): ?>
                                <div class="day-item">
                                    <input type="checkbox" name="available_days[]" value="<?php echo $day; ?>" 
                                           id="day_<?php echo $day; ?>"
                                           <?php echo in_array($day, $available_days) ? 'checked' : ''; ?>>
                                    <label for="day_<?php echo $day; ?>"><?php echo $day; ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="working-hours">
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Start Time</label>
                                <input type="time" name="start_time" class="form-control" 
                                       value="<?php echo $doctor['start_time']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> End Time</label>
                                <input type="time" name="end_time" class="form-control" 
                                       value="<?php echo $doctor['end_time']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="doctors.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Doctor
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
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