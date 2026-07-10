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
$doctor_id = $doctor['id'];

// Get doctor name
$query = "SELECT full_name FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get patient ID from URL
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

// Get patient's appointment history with this doctor
$query = "SELECT a.*, ts.slot_time,
          (SELECT diagnosis FROM medical_records WHERE appointment_id = a.id) as diagnosis,
          (SELECT prescription FROM medical_records WHERE appointment_id = a.id) as prescription
          FROM appointments a
          JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE a.patient_id = :patient_id AND a.doctor_id = :doctor_id
          ORDER BY a.appointment_date DESC, ts.slot_time DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get medical records summary
$query = "SELECT COUNT(*) as total, MAX(created_at) as last_record 
          FROM medical_records 
          WHERE patient_id = :patient_id AND doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$records_summary = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - ClinicCare</title>
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

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
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

        .patient-profile {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            font-weight: bold;
        }

        .profile-title h2 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .profile-title p {
            color: #7f8c8d;
            margin: 5px 0;
        }

        .profile-title i {
            width: 20px;
            color: #3498db;
            margin-right: 5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .info-card i {
            font-size: 1.5rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .info-card h4 {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .info-card p {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .medical-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .medical-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .medical-section h3 i {
            color: #3498db;
        }

        .medical-section p {
            color: #2c3e50;
            line-height: 1.6;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }

        .appointments-table {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .appointments-table h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            color: #2c3e50;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
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
            
            .info-grid {
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
                <h1><i class="fas fa-user"></i> Patient Profile</h1>
                <div>
                    <a href="add-prescription.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-success">
                        <i class="fas fa-prescription"></i> Add Prescription
                    </a>
                    <a href="my-patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                </div>
            </div>
            
            <!-- Patient Profile -->
            <div class="patient-profile">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo substr($patient['full_name'], 0, 1); ?>
                    </div>
                    <div class="profile-title">
                        <h2><?php echo htmlspecialchars($patient['full_name']); ?></h2>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></p>
                        <?php if($patient['address']): ?>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($patient['address']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <i class="fas fa-calendar"></i>
                        <h4>Date of Birth</h4>
                        <p><?php echo $patient['date_of_birth'] ? date('F j, Y', strtotime($patient['date_of_birth'])) : 'Not provided'; ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-tint"></i>
                        <h4>Blood Group</h4>
                        <p><?php echo $patient['blood_group'] ?: 'Not provided'; ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-phone-alt"></i>
                        <h4>Emergency Contact</h4>
                        <p><?php echo $patient['emergency_contact'] ?: 'Not provided'; ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-notes-medical"></i>
                        <h4>Medical Records</h4>
                        <p><?php echo $records_summary['total'] ?? 0; ?> records</p>
                        <small>Last: <?php echo $records_summary['last_record'] ? date('M d, Y', strtotime($records_summary['last_record'])) : 'Never'; ?></small>
                    </div>
                </div>
                
                <?php if($patient['medical_history']): ?>
                <div class="medical-section">
                    <h3><i class="fas fa-history"></i> Medical History</h3>
                    <p><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if($patient['allergies']): ?>
                <div class="medical-section">
                    <h3><i class="fas fa-allergies"></i> Allergies</h3>
                    <p><?php echo nl2br(htmlspecialchars($patient['allergies'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Appointment History -->
            <div class="appointments-table">
                <h3><i class="fas fa-history"></i> Appointment History with You</h3>
                
                <?php if(empty($appointments)): ?>
                    <p style="text-align: center; padding: 40px; color: #7f8c8d;">
                        <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                        No appointment history found with this patient.
                    </p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Diagnosis</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($appointments as $apt): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($apt['slot_time'])); ?></td>
                            <td><?php echo htmlspecialchars($apt['reason_for_visit']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $apt['status']; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($apt['diagnosis']): ?>
                                    <?php echo htmlspecialchars(substr($apt['diagnosis'], 0, 30)) . '...'; ?>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">No diagnosis</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <?php if($apt['status'] != 'completed'): ?>
                                    <a href="start-appointment.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-play"></i> Start
                                    </a>
                                <?php endif; ?>
                                <?php if($apt['prescription']): ?>
                                    <a href="view-prescription.php?appointment_id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-file-prescription"></i> View RX
                                    </a>
                                <?php else: ?>
                                    <a href="add-prescription.php?appointment_id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-prescription"></i> Add RX
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>