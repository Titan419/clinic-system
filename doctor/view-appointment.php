<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

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
$doctor_id = $doctor['id'];

// Get doctor name
$query = "SELECT full_name FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get appointment details
$query = "SELECT a.*, 
          u.full_name as patient_name,
          u.phone as patient_phone,
          u.email as patient_email,
          u.address as patient_address,
          p.date_of_birth,
          p.blood_group,
          p.emergency_contact,
          p.medical_history,
          p.allergies,
          ts.slot_time,
          mr.diagnosis,
          mr.prescription,
          mr.notes as medical_notes,
          mr.follow_up_date
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          LEFT JOIN medical_records mr ON a.id = mr.appointment_id
          WHERE a.id = :appointment_id AND a.doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - ClinicCare</title>
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

        .appointment-detail {
            max-width: 1000px;
            margin: 0 auto;
        }

        .detail-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
        }

        .detail-header h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .badge-completed {
            background: #cce5ff;
            color: #004085;
        }

        .badge-arrived {
            background: #cce5ff;
            color: #004085;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
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

        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            width: 150px;
            font-weight: 600;
            color: #2c3e50;
        }

        .info-label i {
            width: 20px;
            color: #3498db;
            margin-right: 5px;
        }

        .info-value {
            flex: 1;
            color: #34495e;
        }

        .medical-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .medical-section h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
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
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .action-buttons {
                flex-direction: column;
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
                    <?php echo substr($user['full_name'], 0, 1); ?>
                </div>
                <h4>Dr. <?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p><i class="fas fa-stethoscope"></i> Physician</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="appointment-detail">
                <!-- Header -->
                <div class="detail-header">
                    <h1><i class="fas fa-calendar-check"></i> Appointment Details</h1>
                    <span class="badge badge-<?php echo $appointment['status']; ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </div>
                
                <!-- Appointment Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h2>Appointment Information</h2>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date & Time:</span>
                        <span class="info-value">
                            <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?> at 
                            <?php echo date('h:i A', strtotime($appointment['slot_time'])); ?>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-notes-medical"></i> Reason for Visit:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['reason_for_visit']); ?></span>
                    </div>
                    
                    <?php if($appointment['symptoms']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-stethoscope"></i> Symptoms:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['symptoms']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Patient Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h2>Patient Information</h2>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user"></i> Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['patient_name']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-phone"></i> Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['patient_phone']); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-envelope"></i> Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['patient_email']); ?></span>
                    </div>
                    
                    <?php if($appointment['address']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['patient_address']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($appointment['date_of_birth']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date of Birth:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($appointment['date_of_birth'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($appointment['blood_group']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-tint"></i> Blood Group:</span>
                        <span class="info-value"><?php echo $appointment['blood_group']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Medical Information -->
                <?php if($appointment['medical_history'] || $appointment['allergies'] || $appointment['diagnosis']): ?>
                <div class="detail-card">
                    <div class="card-header">
                        <i class="fas fa-notes-medical"></i>
                        <h2>Medical Information</h2>
                    </div>
                    
                    <?php if($appointment['medical_history']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-history"></i> Medical History:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['medical_history'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($appointment['allergies']): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-allergies"></i> Allergies:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['allergies'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($appointment['diagnosis']): ?>
                    <div class="medical-section">
                        <h3><i class="fas fa-stethoscope"></i> Diagnosis & Prescription</h3>
                        <div class="info-row">
                            <span class="info-label">Diagnosis:</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['diagnosis'])); ?></span>
                        </div>
                        
                        <?php if($appointment['prescription']): ?>
                        <div class="info-row">
                            <span class="info-label">Prescription:</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['prescription'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($appointment['follow_up_date']): ?>
                        <div class="info-row">
                            <span class="info-label">Follow-up Date:</span>
                            <span class="info-value"><?php echo date('F j, Y', strtotime($appointment['follow_up_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Notes -->
                <?php if($appointment['notes']): ?>
                <div class="detail-card">
                    <div class="card-header">
                        <i class="fas fa-edit"></i>
                        <h2>Consultation Notes</h2>
                    </div>
                    <div class="info-row">
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <?php if($appointment['status'] != 'completed'): ?>
                        <a href="start-appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-play"></i> Start Appointment
                        </a>
                    <?php endif; ?>
                    
                    <?php if($appointment['prescription']): ?>
                        <a href="view-prescription.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success">
                            <i class="fas fa-file-prescription"></i> View Prescription
                        </a>
                    <?php else: ?>
                        <a href="add-prescription.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-info">
                            <i class="fas fa-prescription"></i> Add Prescription
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>