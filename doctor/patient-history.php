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

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// Get patient details
$query = "SELECT u.*, p.date_of_birth, p.blood_group, p.emergency_contact, 
          p.medical_history, p.allergies
          FROM users u
          JOIN patients p ON u.id = p.user_id
          WHERE p.id = :patient_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: my-patients.php');
    exit();
}

// Get all appointments with this doctor
$query = "SELECT a.*, ts.slot_time,
          mr.diagnosis, mr.prescription, mr.notes, mr.follow_up_date, mr.created_at as record_date
          FROM appointments a
          JOIN time_slots ts ON a.time_slot_id = ts.id
          LEFT JOIN medical_records mr ON a.id = mr.appointment_id
          WHERE a.patient_id = :patient_id AND a.doctor_id = :doctor_id
          ORDER BY a.appointment_date DESC, ts.slot_time DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats = [];

$query = "SELECT COUNT(*) as total FROM appointments WHERE patient_id = :patient_id AND doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['total_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM appointments WHERE patient_id = :patient_id AND doctor_id = :doctor_id AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT MIN(appointment_date) as first FROM appointments WHERE patient_id = :patient_id AND doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['first_visit'] = $stmt->fetch(PDO::FETCH_ASSOC)['first'];

$query = "SELECT MAX(appointment_date) as last FROM appointments WHERE patient_id = :patient_id AND doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['last_visit'] = $stmt->fetch(PDO::FETCH_ASSOC)['last'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient History - ClinicCare</title>
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
        }

        .page-header h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .patient-summary {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
        }

        .patient-info h2 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .patient-info p {
            color: #7f8c8d;
            margin: 5px 0;
        }

        .patient-info i {
            width: 20px;
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

        .history-timeline {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            padding: 20px;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
            transition: transform 0.3s;
        }

        .timeline-item:hover {
            transform: translateX(5px);
            background: #f0f0f0;
        }

        .timeline-date {
            min-width: 120px;
            padding-right: 20px;
        }

        .timeline-date .date {
            font-weight: bold;
            color: #2c3e50;
        }

        .timeline-date .time {
            color: #7f8c8d;
            font-size: 13px;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-content h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .timeline-content p {
            color: #34495e;
            margin: 5px 0;
        }

        .timeline-content i {
            color: #3498db;
            width: 20px;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            color: #bdc3c7;
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
            
            .patient-summary {
                flex-direction: column;
                text-align: center;
            }
            
            .timeline-item {
                flex-direction: column;
            }
            
            .timeline-date {
                margin-bottom: 10px;
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
                <li><a href="my-patients.php" class="active"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-history"></i> Patient History</h1>
                <div>
                    <a href="view-patient.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-info">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                    <a href="my-patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            
            <!-- Patient Summary -->
            <div class="patient-summary">
                <div class="patient-avatar">
                    <?php echo substr($patient['full_name'], 0, 1); ?>
                </div>
                <div class="patient-info">
                    <h2><?php echo htmlspecialchars($patient['full_name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></p>
                    <?php if($patient['blood_group']): ?>
                        <p><i class="fas fa-tint"></i> Blood Group: <?php echo $patient['blood_group']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3><?php echo $stats['total_visits']; ?></h3>
                    <p>Total Visits</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3><?php echo $stats['first_visit'] ? date('M d, Y', strtotime($stats['first_visit'])) : 'N/A'; ?></h3>
                    <p>First Visit</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['last_visit'] ? date('M d, Y', strtotime($stats['last_visit'])) : 'N/A'; ?></h3>
                    <p>Last Visit</p>
                </div>
            </div>
            
            <!-- History Timeline -->
            <div class="history-timeline">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-timeline"></i> Visit History</h3>
                
                <?php if(empty($history)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No History Found</h3>
                        <p>This patient has no visit history with you.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($history as $record): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <div class="date"><?php echo date('M d, Y', strtotime($record['appointment_date'])); ?></div>
                            <div class="time"><?php echo date('h:i A', strtotime($record['slot_time'])); ?></div>
                            <span class="badge badge-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span>
                        </div>
                        <div class="timeline-content">
                            <h4><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars($record['reason_for_visit']); ?></h4>
                            
                            <?php if($record['symptoms']): ?>
                                <p><i class="fas fa-stethoscope"></i> <strong>Symptoms:</strong> <?php echo htmlspecialchars($record['symptoms']); ?></p>
                            <?php endif; ?>
                            
                            <?php if($record['diagnosis']): ?>
                                <p><i class="fas fa-diagnoses"></i> <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?></p>
                            <?php endif; ?>
                            
                            <?php if($record['prescription']): ?>
                                <p><i class="fas fa-prescription"></i> <strong>Prescription:</strong> <?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if($record['notes']): ?>
                                <p><i class="fas fa-edit"></i> <strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></p>
                            <?php endif; ?>
                            
                            <?php if($record['follow_up_date']): ?>
                                <p><i class="fas fa-calendar-alt"></i> <strong>Follow-up:</strong> <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?></p>
                            <?php endif; ?>
                            
                            <div style="margin-top: 10px;">
                                <?php if($record['status'] != 'completed'): ?>
                                    <a href="start-appointment.php?id=<?php echo $record['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-play"></i> Start Appointment
                                    </a>
                                <?php endif; ?>
                                
                                <?php if($record['prescription']): ?>
                                    <a href="view-prescription.php?appointment_id=<?php echo $record['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-file-prescription"></i> View Prescription
                                    </a>
                                <?php else: ?>
                                    <a href="add-prescription.php?appointment_id=<?php echo $record['id']; ?>" class="btn btn-info">
                                        <i class="fas fa-prescription"></i> Add Prescription
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>