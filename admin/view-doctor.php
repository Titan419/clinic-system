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
          WHERE u.id = :id AND u.user_type = 'doctor'";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $doctor_id);
$stmt->execute();
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: doctors.php');
    exit();
}

// Get doctor statistics
$stats = [];

// Total appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = :user_id)";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $doctor_id);
$stmt->execute();
$stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Completed appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = :user_id) AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $doctor_id);
$stmt->execute();
$stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = :user_id) AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $doctor_id);
$stmt->execute();
$stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total patients
$query = "SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = :user_id)";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $doctor_id);
$stmt->execute();
$stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent appointments
$query = "SELECT a.*, u.full_name as patient_name, ts.slot_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE a.doctor_id = (SELECT id FROM doctors WHERE user_id = :user_id)
          ORDER BY a.appointment_date DESC, ts.slot_time DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $doctor_id);
$stmt->execute();
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Doctor - ClinicCare</title>
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

        .doctor-profile {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .doctor-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .doctor-info h2 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .doctor-info p {
            color: #7f8c8d;
            margin: 8px 0;
            font-size: 1.1rem;
        }

        .doctor-info i {
            width: 25px;
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
            border-left: 4px solid #3498db;
        }

        .stat-card i {
            font-size: 2rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .details-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .details-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .details-card h3 i {
            color: #3498db;
        }

        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            width: 200px;
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

        .days-badge {
            display: inline-block;
            padding: 5px 12px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            font-size: 13px;
            margin-right: 5px;
            margin-bottom: 5px;
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

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .badge {
            padding: 4px 10px;
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

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .doctor-profile {
                flex-direction: column;
                text-align: center;
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
                <h1><i class="fas fa-user-md"></i> Doctor Profile</h1>
                <div>
                    <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit Doctor
                    </a>
                    <a href="doctors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Doctors
                    </a>
                </div>
            </div>
            
            <!-- Doctor Profile Header -->
            <div class="doctor-profile">
                <div class="doctor-avatar-large">
                    <?php echo substr($doctor['full_name'], 0, 1); ?>
                </div>
                <div class="doctor-info">
                    <h2>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h2>
                    <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                    <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($doctor['qualification']); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <div class="value"><?php echo $stats['total_appointments']; ?></div>
                    <div class="label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="value"><?php echo $stats['total_patients']; ?></div>
                    <div class="label">Patients Treated</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="value"><?php echo $stats['completed']; ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="value"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            
            <!-- Professional Details -->
            <div class="details-card">
                <h3><i class="fas fa-briefcase"></i> Professional Details</h3>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-stethoscope"></i> Specialization:</span>
                    <span class="info-value"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-graduation-cap"></i> Qualification:</span>
                    <span class="info-value"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar"></i> Experience:</span>
                    <span class="info-value"><?php echo $doctor['experience_years']; ?> years</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-money-bill"></i> Consultation Fee:</span>
                    <span class="info-value">TSh <?php echo number_format($doctor['consultation_fee']); ?></span>
                </div>
            </div>
            
            <!-- Working Schedule -->
            <div class="details-card">
                <h3><i class="fas fa-clock"></i> Working Schedule</h3>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Available Days:</span>
                    <span class="info-value">
                        <?php 
                        $days = explode(',', $doctor['available_days']);
                        foreach($days as $day) {
                            echo '<span class="days-badge">' . trim($day) . '</span>';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-clock"></i> Working Hours:</span>
                    <span class="info-value">
                        <?php echo date('h:i A', strtotime($doctor['start_time'])); ?> - 
                        <?php echo date('h:i A', strtotime($doctor['end_time'])); ?>
                    </span>
                </div>
            </div>
            
            <!-- Recent Appointments -->
            <div class="details-card">
                <h3><i class="fas fa-history"></i> Recent Appointments</h3>
                
                <?php if(empty($recent_appointments)): ?>
                    <p style="text-align: center; padding: 30px; color: #7f8c8d;">
                        <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                        No appointments found
                    </p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_appointments as $apt): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($apt['slot_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($apt['reason_for_visit']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $apt['status']; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Doctor
                </a>
                <a href="reset-password.php?user_id=<?php echo $doctor['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-key"></i> Reset Password
                </a>
                <?php if($doctor['is_active']): ?>
                    <a href="toggle-doctor.php?id=<?php echo $doctor['id']; ?>&action=deactivate" class="btn btn-danger" onclick="return confirm('Deactivate this doctor?')">
                        <i class="fas fa-ban"></i> Deactivate
                    </a>
                <?php else: ?>
                    <a href="toggle-doctor.php?id=<?php echo $doctor['id']; ?>&action=activate" class="btn btn-success">
                        <i class="fas fa-check"></i> Activate
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>