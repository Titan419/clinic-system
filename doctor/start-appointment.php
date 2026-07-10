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
          ts.slot_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
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

// Update appointment status to arrived
if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed') {
    $update = "UPDATE appointments SET status = 'arrived' WHERE id = :id";
    $update_stmt = $db->prepare($update);
    $update_stmt->bindParam(':id', $appointment_id);
    $update_stmt->execute();
    $appointment['status'] = 'arrived';
}

$success = '';
$error = '';

// Handle form submission for adding notes
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_note'])) {
        $notes = trim($_POST['notes']);
        
        $update = "UPDATE appointments SET notes = CONCAT(IFNULL(notes, ''), :notes) WHERE id = :id";
        $update_stmt = $db->prepare($update);
        $notes = "\n[" . date('Y-m-d H:i') . "] " . $notes;
        $update_stmt->bindParam(':notes', $notes);
        $update_stmt->bindParam(':id', $appointment_id);
        
        if ($update_stmt->execute()) {
            $success = "Note added successfully!";
        } else {
            $error = "Failed to add note.";
        }
    }
    
    if (isset($_POST['complete_appointment'])) {
        $update = "UPDATE appointments SET status = 'completed' WHERE id = :id";
        $update_stmt = $db->prepare($update);
        $update_stmt->bindParam(':id', $appointment_id);
        
        if ($update_stmt->execute()) {
            header('Location: add-prescription.php?appointment_id=' . $appointment_id);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Appointment - ClinicCare</title>
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

        .appointment-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .appointment-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .appointment-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
        }

        .appointment-header h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-badge.arrived {
            background: #cce5ff;
            color: #004085;
        }

        .patient-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .patient-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .patient-name h2 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .patient-name p {
            color: #7f8c8d;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-item i {
            color: #3498db;
            margin-right: 10px;
        }

        .info-item strong {
            color: #2c3e50;
        }

        .info-item p {
            color: #34495e;
            margin-top: 5px;
        }

        .medical-alert {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .medical-alert i {
            margin-right: 10px;
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

        .notes-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .notes-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .notes-section h3 i {
            color: #3498db;
            margin-right: 10px;
        }

        .notes-history {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .note-item {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .note-item:last-child {
            border-bottom: none;
        }

        .note-time {
            color: #7f8c8d;
            font-size: 12px;
        }

        .note-text {
            color: #2c3e50;
            margin-top: 5px;
        }

        textarea.form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            min-height: 100px;
            margin-bottom: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
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
            <div class="appointment-container">
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Appointment Header -->
                <div class="appointment-header">
                    <h1><i class="fas fa-play-circle"></i> Start Appointment</h1>
                    <span class="status-badge arrived">
                        <i class="fas fa-check-circle"></i> Status: Arrived
                    </span>
                </div>
                
                <!-- Patient Information -->
                <div class="patient-card">
                    <div class="patient-header">
                        <div class="patient-avatar">
                            <?php echo substr($appointment['patient_name'], 0, 1); ?>
                        </div>
                        <div class="patient-name">
                            <h2><?php echo htmlspecialchars($appointment['patient_name']); ?></h2>
                            <p>Patient ID: #<?php echo $appointment['patient_id']; ?></p>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <i class="fas fa-calendar"></i>
                            <strong>Appointment Date:</strong>
                            <p><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($appointment['slot_time'])); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-notes-medical"></i>
                            <strong>Reason for Visit:</strong>
                            <p><?php echo htmlspecialchars($appointment['reason_for_visit']); ?></p>
                        </div>
                        
                        <?php if($appointment['symptoms']): ?>
                        <div class="info-item">
                            <i class="fas fa-stethoscope"></i>
                            <strong>Symptoms:</strong>
                            <p><?php echo htmlspecialchars($appointment['symptoms']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <strong>Contact:</strong>
                            <p><?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                            <p><?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                        </div>
                        
                        <?php if($appointment['date_of_birth']): ?>
                        <div class="info-item">
                            <i class="fas fa-calendar-alt"></i>
                            <strong>Date of Birth:</strong>
                            <p><?php echo date('F j, Y', strtotime($appointment['date_of_birth'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($appointment['blood_group']): ?>
                        <div class="info-item">
                            <i class="fas fa-tint"></i>
                            <strong>Blood Group:</strong>
                            <p><?php echo $appointment['blood_group']; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($appointment['medical_history'] || $appointment['allergies']): ?>
                    <div class="medical-alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Medical Alerts:</strong>
                        <?php if($appointment['medical_history']): ?>
                            <p>History: <?php echo htmlspecialchars($appointment['medical_history']); ?></p>
                        <?php endif; ?>
                        <?php if($appointment['allergies']): ?>
                            <p>Allergies: <?php echo htmlspecialchars($appointment['allergies']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Notes Section -->
                <div class="notes-section">
                    <h3><i class="fas fa-edit"></i> Consultation Notes</h3>
                    
                    <?php if($appointment['notes']): ?>
                    <div class="notes-history">
                        <?php 
                        $notes = explode("\n", $appointment['notes']);
                        foreach($notes as $note):
                            if(trim($note)):
                        ?>
                        <div class="note-item">
                            <div class="note-text"><?php echo nl2br(htmlspecialchars($note)); ?></div>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <textarea name="notes" class="form-control" placeholder="Add consultation notes..."></textarea>
                        <button type="submit" name="add_note" class="btn btn-info">
                            <i class="fas fa-plus"></i> Add Note
                        </button>
                    </form>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="complete_appointment" class="btn btn-success">
                            <i class="fas fa-check"></i> Complete & Add Prescription
                        </button>
                    </form>
                    
                    <a href="add-prescription.php?appointment_id=<?php echo $appointment_id; ?>" class="btn btn-primary">
                        <i class="fas fa-prescription"></i> Add Prescription
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>