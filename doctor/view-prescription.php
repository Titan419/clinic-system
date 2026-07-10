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

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

// Get prescription details
$query = "SELECT mr.*, 
          u.full_name as patient_name,
          u.phone as patient_phone,
          u.email as patient_email,
          u.address as patient_address,
          p.date_of_birth,
          p.blood_group,
          a.appointment_date,
          a.reason_for_visit,
          ts.slot_time,
          du.full_name as doctor_name
          FROM medical_records mr
          JOIN patients p ON mr.patient_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN doctors d ON mr.doctor_id = d.id
          JOIN users du ON d.user_id = du.id
          LEFT JOIN appointments a ON mr.appointment_id = a.id
          LEFT JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE mr.appointment_id = :appointment_id AND mr.doctor_id = :doctor_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    header('Location: my-patients.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Prescription - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your styles here */
        .prescription-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .prescription-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3498db;
        }
        
        .clinic-name {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .clinic-info {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .prescription-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 20px 0;
            text-align: center;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            width: 120px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value {
            flex: 1;
            color: #34495e;
        }
        
        .section {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        
        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(52,152,219,0.4);
        }
        
        @media print {
            .sidebar, .print-btn, .btn {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
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
                <div class="user-avatar-large"><?php echo substr($user['full_name'], 0, 1); ?></div>
                <h4>Dr. <?php echo htmlspecialchars($user['full_name']); ?></h4>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="prescription-container">
                <div class="prescription-header">
                    <div class="clinic-name">🏥 ClinicCare</div>
                    <div class="clinic-info">123 Healthcare Street, Dar es Salaam, Tanzania</div>
                    <div class="clinic-info">Tel: +255 123 456 789 | Email: info@cliniccare.com</div>
                    <div class="prescription-title">MEDICAL PRESCRIPTION</div>
                </div>
                
                <!-- Patient Information -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">Patient Information</h3>
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($record['patient_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of Birth:</span>
                        <span class="info-value"><?php echo $record['date_of_birth'] ? date('M d, Y', strtotime($record['date_of_birth'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Blood Group:</span>
                        <span class="info-value"><?php echo $record['blood_group'] ?: 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($record['patient_phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($record['patient_email']); ?></span>
                    </div>
                </div>
                
                <!-- Doctor Information -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">Doctor Information</h3>
                    <div class="info-row">
                        <span class="info-label">Doctor Name:</span>
                        <span class="info-value">Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($record['created_at'])); ?></span>
                    </div>
                </div>
                
                <!-- Appointment Details -->
                <?php if($record['appointment_date']): ?>
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">Appointment Details</h3>
                    <div class="info-row">
                        <span class="info-label">Appointment Date:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($record['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($record['slot_time'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Reason for Visit:</span>
                        <span class="info-value"><?php echo htmlspecialchars($record['reason_for_visit']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Medical Information -->
                <div class="section">
                    <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                    <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                </div>
                
                <div class="section">
                    <h3><i class="fas fa-prescription"></i> Prescription</h3>
                    <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                </div>
                
                <?php if($record['notes']): ?>
                <div class="section">
                    <h3><i class="fas fa-edit"></i> Additional Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if($record['follow_up_date']): ?>
                <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
                    <i class="fas fa-calendar-alt" style="color: #1976d2;"></i>
                    <strong>Follow-up Date:</strong> <?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?>
                </div>
                <?php endif; ?>
                
                <!-- Doctor Signature -->
                <div style="margin-top: 50px; text-align: right;">
                    <div style="margin-bottom: 10px;">_________________________</div>
                    <div>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></div>
                    <div style="font-size: 12px; color: #7f8c8d;">Licensed Medical Practitioner</div>
                </div>
            </div>
            
            <button onclick="window.print()" class="print-btn">
                <i class="fas fa-print"></i> Print Prescription
            </button>
        </main>
    </div>
</body>
</html>