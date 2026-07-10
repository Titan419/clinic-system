<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get patient details
$query = "SELECT u.*, p.date_of_birth, p.blood_group, p.emergency_contact, 
          p.medical_history, p.allergies,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as total_appointments,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'completed') as completed_appointments
          FROM users u
          JOIN patients p ON u.id = p.user_id
          WHERE u.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $patient_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Get recent appointments
$apt_query = "SELECT a.*, d.user_id as doctor_user_id, 
              du.full_name as doctor_name, ts.slot_time
              FROM appointments a
              JOIN doctors d ON a.doctor_id = d.id
              JOIN users du ON d.user_id = du.id
              JOIN time_slots ts ON a.time_slot_id = ts.id
              WHERE a.patient_id = (SELECT id FROM patients WHERE user_id = :user_id)
              ORDER BY a.appointment_date DESC
              LIMIT 5";
$apt_stmt = $db->prepare($apt_query);
$apt_stmt->bindParam(':user_id', $patient_id);
$apt_stmt->execute();
$appointments = $apt_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .patient-profile {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
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
        
        .section-title {
            color: #2c3e50;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .medical-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .medical-info p {
            margin: 10px 0;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
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
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ClinicCare</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logs.php"><i class="fas fa-history"></i> Logs</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>Patient Profile</h1>
                <div>
                    <a href="edit-patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            
            <div class="patient-profile">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo substr($patient['full_name'], 0, 1); ?>
                    </div>
                    <div class="profile-title">
                        <h2><?php echo htmlspecialchars($patient['full_name']); ?></h2>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <i class="fas fa-calendar"></i>
                        <h4>Date of Birth</h4>
                        <p><?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not provided'; ?></p>
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
                        <i class="fas fa-calendar-check"></i>
                        <h4>Total Appointments</h4>
                        <p><?php echo $patient['total_appointments']; ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-check-circle"></i>
                        <h4>Completed</h4>
                        <p><?php echo $patient['completed_appointments']; ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-clock"></i>
                        <h4>Member Since</h4>
                        <p><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></p>
                    </div>
                </div>
                
                <h3 class="section-title"><i class="fas fa-notes-medical"></i> Medical Information</h3>
                <div class="medical-info">
                    <h4>Medical History:</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['medical_history'] ?: 'No medical history recorded')); ?></p>
                    
                    <h4 style="margin-top: 15px;">Allergies:</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['allergies'] ?: 'No allergies recorded')); ?></p>
                </div>
                
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Appointments</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($appointments as $apt): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                            <td><?php echo date('h:i A', strtotime($apt['slot_time'])); ?></td>
                            <td><?php echo htmlspecialchars($apt['reason_for_visit']); ?></td>
                            <td><span class="badge badge-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>