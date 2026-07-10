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
$patient_id = $patient['id'];

// Get patient name
$query = "SELECT full_name FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get medical record details
$query = "SELECT mr.*, 
          u.full_name as doctor_name,
          u.email as doctor_email,
          u.phone as doctor_phone,
          d.specialization,
          d.qualification,
          a.appointment_date,
          a.reason_for_visit,
          a.symptoms,
          ts.slot_time
          FROM medical_records mr
          JOIN doctors d ON mr.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          LEFT JOIN appointments a ON mr.appointment_id = a.id
          LEFT JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE mr.id = :record_id AND mr.patient_id = :patient_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':record_id', $record_id);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    header('Location: medical-records.php');
    exit();
}

// Get previous records from same doctor
$query = "SELECT mr.id, mr.created_at, mr.diagnosis, a.appointment_date
          FROM medical_records mr
          LEFT JOIN appointments a ON mr.appointment_id = a.id
          WHERE mr.patient_id = :patient_id AND mr.doctor_id = :doctor_id AND mr.id != :record_id
          ORDER BY mr.created_at DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $record['doctor_id']);
$stmt->bindParam(':record_id', $record_id);
$stmt->execute();
$previous_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Record Details - ClinicCare</title>
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

        .record-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .record-header {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .record-header h1 {
            font-size: 2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .record-header h1 i {
            color: #3498db;
        }

        .record-date-badge {
            background: #3498db;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
        }

        .doctor-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 25px;
            border-left: 5px solid #3498db;
        }

        .doctor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            font-weight: bold;
        }

        .doctor-info h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .doctor-info p {
            color: #7f8c8d;
            margin: 5px 0;
        }

        .doctor-info i {
            width: 20px;
            color: #3498db;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 3px solid #3498db;
        }

        .info-card i {
            font-size: 1.5rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .info-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .info-card p {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section h3 i {
            color: #3498db;
        }

        .diagnosis-box {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .prescription-box {
            background: #f0f7e8;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .notes-box {
            background: #fef7e0;
            padding: 20px;
            border-radius: 10px;
        }

        .follow-up-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .follow-up-box i {
            color: #856404;
            font-size: 1.2rem;
        }

        .follow-up-box strong {
            color: #856404;
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

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .previous-records {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .previous-records h4 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .record-link {
            display: block;
            padding: 10px;
            margin-bottom: 5px;
            background: white;
            border-radius: 5px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
        }

        .record-link:hover {
            background: #e8f4fd;
            transform: translateX(5px);
        }

        .record-link i {
            color: #3498db;
            margin-right: 10px;
        }

        @media print {
            .sidebar, .action-buttons, .previous-records {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .doctor-card {
                flex-direction: column;
                text-align: center;
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
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p><i class="fas fa-user"></i> Patient</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="book-appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="medical-records.php" class="active"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="record-container">
                <!-- Header -->
                <div class="record-header">
                    <h1><i class="fas fa-file-medical"></i> Medical Record</h1>
                    <div class="record-date-badge">
                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
                    </div>
                </div>

                <!-- Doctor Information -->
                <div class="doctor-card">
                    <div class="doctor-avatar">
                        <?php echo substr($record['doctor_name'], 0, 1); ?>
                    </div>
                    <div class="doctor-info">
                        <h2>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h2>
                        <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($record['specialization']); ?></p>
                        <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($record['qualification']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($record['doctor_phone']); ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($record['doctor_email']); ?></p>
                    </div>
                </div>

                <!-- Quick Info Grid -->
                <div class="info-grid">
                    <?php if($record['appointment_date']): ?>
                    <div class="info-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Appointment Date</h3>
                        <p><?php echo date('F j, Y', strtotime($record['appointment_date'])); ?></p>
                        <small><?php echo date('h:i A', strtotime($record['slot_time'])); ?></small>
                    </div>
                    <?php endif; ?>

                    <?php if($record['reason_for_visit']): ?>
                    <div class="info-card">
                        <i class="fas fa-notes-medical"></i>
                        <h3>Reason for Visit</h3>
                        <p><?php echo htmlspecialchars($record['reason_for_visit']); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if($record['symptoms']): ?>
                    <div class="info-card">
                        <i class="fas fa-stethoscope"></i>
                        <h3>Symptoms</h3>
                        <p><?php echo htmlspecialchars($record['symptoms']); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if($record['follow_up_date']): ?>
                    <div class="info-card">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Follow-up Date</h3>
                        <p><?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Diagnosis Section -->
                <?php if($record['diagnosis']): ?>
                <div class="section">
                    <h3><i class="fas fa-stethoscope"></i> Diagnosis</h3>
                    <div class="diagnosis-box">
                        <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Prescription Section -->
                <?php if($record['prescription']): ?>
                <div class="section">
                    <h3><i class="fas fa-prescription"></i> Prescription</h3>
                    <div class="prescription-box">
                        <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Doctor's Notes -->
                <?php if($record['notes']): ?>
                <div class="section">
                    <h3><i class="fas fa-edit"></i> Doctor's Notes</h3>
                    <div class="notes-box">
                        <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Follow-up Reminder -->
                <?php if($record['follow_up_date']): ?>
                <div class="follow-up-box">
                    <i class="fas fa-bell"></i>
                    <div>
                        <strong>Follow-up Reminder:</strong> Please schedule a follow-up appointment for 
                        <strong><?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?></strong>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Previous Records from Same Doctor -->
                <?php if(!empty($previous_records)): ?>
                <div class="previous-records">
                    <h4><i class="fas fa-history"></i> Previous Records from Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h4>
                    <?php foreach($previous_records as $prev): ?>
                    <a href="view-record.php?id=<?php echo $prev['id']; ?>" class="record-link">
                        <i class="fas fa-file-medical"></i>
                        <?php echo date('M d, Y', strtotime($prev['created_at'])); ?> - 
                        <?php echo htmlspecialchars(substr($prev['diagnosis'], 0, 50)) . '...'; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="medical-records.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Records
                    </a>
                    <a href="print-record.php?id=<?php echo $record['id']; ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-print"></i> Print Record
                    </a>
                    <a href="download-record.php?id=<?php echo $record['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download PDF
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Print functionality
    function printRecord() {
        window.print();
    }
    </script>
</body>
</html>