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

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get appointment details
$query = "SELECT a.*, 
          p.user_id as patient_user_id,
          pu.full_name as patient_name,
          pu.email as patient_email,
          pu.phone as patient_phone,
          pu.address as patient_address,
          p.date_of_birth,
          p.blood_group,
          p.emergency_contact,
          p.medical_history,
          p.allergies,
          d.user_id as doctor_user_id,
          du.full_name as doctor_name,
          du.email as doctor_email,
          du.phone as doctor_phone,
          d.specialization,
          d.qualification,
          d.consultation_fee,
          ts.slot_time,
          mr.diagnosis,
          mr.prescription,
          mr.notes as medical_notes,
          mr.follow_up_date
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users pu ON p.user_id = pu.id
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users du ON d.user_id = du.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          LEFT JOIN medical_records mr ON a.id = mr.appointment_id
          WHERE a.id = :appointment_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->execute();
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    header('Location: appointments.php');
    exit();
}

// Calculate age from date of birth if available
$age = '';
if (!empty($appointment['date_of_birth'])) {
    $dob = new DateTime($appointment['date_of_birth']);
    $now = new DateTime();
    $age = $dob->diff($now)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment #<?php echo $appointment['id']; ?> - ClinicCare</title>
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

        .appointment-header {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .appointment-header h1 {
            font-size: 2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .appointment-header h1 i {
            color: #3498db;
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

        .badge-no_show {
            background: #f8d7da;
            color: #721c24;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
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

        .info-item {
            display: flex;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            width: 140px;
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

        .avatar-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }

        .patient-header, .doctor-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .patient-name h3, .doctor-name h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .patient-name p, .doctor-name p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .medical-alert {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .medical-alert i {
            font-size: 1.2rem;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
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

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .medical-section {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .medical-section h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
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
                <li><a href="appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="appointment-header">
                <h1><i class="fas fa-calendar-check"></i> Appointment #<?php echo $appointment['id']; ?></h1>
                <span class="badge badge-<?php echo $appointment['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                </span>
            </div>

            <!-- Appointment Info Row -->
            <div class="row">
                <!-- Date & Time Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h2>Appointment Details</h2>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date:</span>
                        <span class="info-value"><?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Time:</span>
                        <span class="info-value"><?php echo date('h:i A', strtotime($appointment['slot_time'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-tag"></i> Status:</span>
                        <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Booked:</span>
                        <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($appointment['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Reason Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-notes-medical"></i>
                        <h2>Visit Details</h2>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-question-circle"></i> Reason:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['reason_for_visit'] ?? 'Not specified'); ?></span>
                    </div>
                    <?php if(!empty($appointment['symptoms'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-stethoscope"></i> Symptoms:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($appointment['symptoms'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Patient Info Row -->
            <div class="row">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h2>Patient Information</h2>
                    </div>
                    
                    <div class="patient-header">
                        <div class="avatar-circle">
                            <?php echo substr($appointment['patient_name'] ?? 'P', 0, 1); ?>
                        </div>
                        <div class="patient-name">
                            <h3><?php echo htmlspecialchars($appointment['patient_name'] ?? 'Unknown'); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['patient_email'] ?? 'Not provided'); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date of Birth:</span>
                        <span class="info-value">
                            <?php echo !empty($appointment['date_of_birth']) ? date('M d, Y', strtotime($appointment['date_of_birth'])) : 'Not provided'; ?>
                            <?php if($age): ?> (<?php echo $age; ?> years)<?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if(!empty($appointment['blood_group'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-tint"></i> Blood Group:</span>
                        <span class="info-value"><?php echo $appointment['blood_group']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($appointment['emergency_contact'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-phone-alt"></i> Emergency Contact:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['emergency_contact']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($appointment['patient_address'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['patient_address']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($appointment['medical_history'])): ?>
                    <div class="medical-alert">
                        <i class="fas fa-history"></i>
                        <div>
                            <strong>Medical History:</strong><br>
                            <?php echo nl2br(htmlspecialchars($appointment['medical_history'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($appointment['allergies'])): ?>
                    <div class="medical-alert" style="background: #f8d7da; color: #721c24;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Allergies:</strong><br>
                            <?php echo nl2br(htmlspecialchars($appointment['allergies'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Doctor Info Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-md"></i>
                        <h2>Doctor Information</h2>
                    </div>
                    
                    <div class="doctor-header">
                        <div class="avatar-circle" style="background: #27ae60;">
                            <?php echo substr($appointment['doctor_name'] ?? 'D', 0, 1); ?>
                        </div>
                        <div class="doctor-name">
                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Unknown'); ?></h3>
                            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($appointment['specialization'] ?? 'General Physician'); ?></p>
                            <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($appointment['qualification'] ?? 'MD'); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-phone"></i> Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['doctor_phone'] ?? 'Not provided'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-envelope"></i> Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($appointment['doctor_email'] ?? 'Not provided'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-money-bill"></i> Consultation Fee:</span>
                        <span class="info-value">TSh <?php echo number_format($appointment['consultation_fee'] ?? 0); ?></span>
                    </div>
                </div>
            </div>

            <!-- Medical Records Section (if any) -->
            <?php if(!empty($appointment['diagnosis']) || !empty($appointment['prescription']) || !empty($appointment['medical_notes'])): ?>
            <div class="card" style="margin-top: 25px;">
                <div class="card-header">
                    <i class="fas fa-file-medical"></i>
                    <h2>Medical Records</h2>
                </div>
                
                <?php if(!empty($appointment['diagnosis'])): ?>
                <div class="medical-section">
                    <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                    <p><?php echo nl2br(htmlspecialchars($appointment['diagnosis'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($appointment['prescription'])): ?>
                <div class="medical-section" style="background: #f0f7e8;">
                    <h3><i class="fas fa-prescription"></i> Prescription</h3>
                    <p><?php echo nl2br(htmlspecialchars($appointment['prescription'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($appointment['medical_notes'])): ?>
                <div class="medical-section" style="background: #fef7e0;">
                    <h3><i class="fas fa-edit"></i> Doctor's Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($appointment['medical_notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($appointment['follow_up_date'])): ?>
                <div style="margin-top: 15px; padding: 15px; background: #d4edda; border-radius: 8px;">
                    <i class="fas fa-calendar-alt" style="color: #155724;"></i>
                    <strong style="color: #155724;">Follow-up Date:</strong>
                    <span style="color: #155724;"><?php echo date('F j, Y', strtotime($appointment['follow_up_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
                
                <?php if($appointment['status'] == 'pending'): ?>
                    <a href="update-appointment.php?id=<?php echo $appointment['id']; ?>&status=confirmed" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirm Appointment
                    </a>
                    <a href="update-appointment.php?id=<?php echo $appointment['id']; ?>&status=cancelled" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                        <i class="fas fa-times"></i> Cancel Appointment
                    </a>
                <?php endif; ?>
                
                <?php if($appointment['status'] == 'confirmed'): ?>
                    <a href="update-appointment.php?id=<?php echo $appointment['id']; ?>&status=arrived" class="btn btn-warning">
                        <i class="fas fa-user-check"></i> Mark as Arrived
                    </a>
                <?php endif; ?>
                
                <?php if($appointment['status'] == 'arrived'): ?>
                    <a href="update-appointment.php?id=<?php echo $appointment['id']; ?>&status=completed" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Mark as Completed
                    </a>
                <?php endif; ?>
                
                <?php if($appointment['status'] == 'completed' && !empty($appointment['prescription'])): ?>
                    <a href="../doctor/view-prescription.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-file-prescription"></i> View Prescription
                    </a>
                <?php endif; ?>
                
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </main>
    </div>
</body>
</html>