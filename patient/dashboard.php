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

// Get statistics for PATIENT
$stats = [];

// Upcoming appointments
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE patient_id = :patient_id 
          AND appointment_date >= CURDATE() 
          AND status IN ('pending', 'confirmed')";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$stats['upcoming'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Completed appointments
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE patient_id = :patient_id 
          AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total doctors visited (distinct)
$query = "SELECT COUNT(DISTINCT doctor_id) as count FROM appointments 
          WHERE patient_id = :patient_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$stats['doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending appointments
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE patient_id = :patient_id 
          AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total medical records
$query = "SELECT COUNT(*) as count FROM medical_records 
          WHERE patient_id = :patient_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$stats['records'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get upcoming appointments
$query = "SELECT a.*, 
          d.user_id as doctor_user_id,
          u.full_name as doctor_name,
          d.specialization,
          ts.slot_time
          FROM appointments a
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE a.patient_id = :patient_id 
          AND a.appointment_date >= CURDATE()
          AND a.status IN ('pending', 'confirmed')
          ORDER BY a.appointment_date ASC, ts.slot_time ASC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent medical records
$query = "SELECT mr.*, u.full_name as doctor_name, d.specialization
          FROM medical_records mr
          JOIN doctors d ON mr.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          WHERE mr.patient_id = :patient_id
          ORDER BY mr.created_at DESC
          LIMIT 3";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$recent_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - ClinicCare</title>
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

        .user-welcome p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
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

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }

        .welcome-card h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
        }

        .stat-icon.primary {
            background: rgba(52,152,219,0.1);
            color: #3498db;
        }

        .stat-icon.success {
            background: rgba(46,204,113,0.1);
            color: #2ecc71;
        }

        .stat-icon.warning {
            background: rgba(241,196,15,0.1);
            color: #f1c40f;
        }

        .stat-icon.info {
            background: rgba(155,89,182,0.1);
            color: #9b59b6;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .stat-info p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title h2 {
            color: #2c3e50;
            font-size: 1.5rem;
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

        .appointments-list, .records-list {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .appointment-item, .record-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .appointment-item:last-child, .record-item:last-child {
            border-bottom: none;
        }

        .appointment-item:hover, .record-item:hover {
            background: #f8f9fa;
        }

        .appointment-date {
            background: #3498db;
            color: white;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            min-width: 80px;
            margin-right: 20px;
        }

        .appointment-date .day {
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
        }

        .appointment-date .month {
            font-size: 14px;
            opacity: 0.9;
        }

        .appointment-info, .record-info {
            flex: 1;
        }

        .appointment-info h4, .record-info h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .appointment-info p, .record-info p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .appointment-info i, .record-info i {
            width: 16px;
            color: #3498db;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
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

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
            border: 1px solid #f0f0f0;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #3498db;
        }

        .action-card i {
            font-size: 32px;
            color: #3498db;
            margin-bottom: 10px;
        }

        .action-card h4 {
            margin-bottom: 5px;
        }

        .action-card p {
            color: #7f8c8d;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .appointment-item {
                flex-direction: column;
                text-align: center;
            }
            
            .appointment-date {
                margin-right: 0;
                margin-bottom: 15px;
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
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="book-appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="medical-records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
                <p>Your health is our priority. Manage your appointments and medical records easily.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['upcoming']; ?></h3>
                        <p>Upcoming Appointments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed']; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['doctors']; ?></h3>
                        <p>Doctors Visited</p>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="section-title">
                <h2><i class="fas fa-calendar-alt" style="color: #3498db;"></i> Upcoming Appointments</h2>
                <a href="book-appointment.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Book New
                </a>
            </div>

            <div class="appointments-list">
                <?php if(empty($upcoming)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h3>No upcoming appointments</h3>
                        <p>Book your first appointment with our expert doctors.</p>
                        <a href="book-appointment.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($upcoming as $apt): ?>
                    <div class="appointment-item">
                        <div class="appointment-date">
                            <div class="day"><?php echo date('d', strtotime($apt['appointment_date'])); ?></div>
                            <div class="month"><?php echo date('M', strtotime($apt['appointment_date'])); ?></div>
                        </div>
                        <div class="appointment-info">
                            <h4>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></h4>
                            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($apt['specialization']); ?></p>
                            <p><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($apt['slot_time'])); ?></p>
                            <p><i class="fas fa-tag"></i> <?php echo htmlspecialchars($apt['reason_for_visit']); ?></p>
                        </div>
                        <div>
                            <span class="badge badge-<?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Medical Records -->
            <div class="section-title">
                <h2><i class="fas fa-file-medical" style="color: #3498db;"></i> Recent Medical Records</h2>
                <a href="medical-records.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View All
                </a>
            </div>

            <div class="records-list">
                <?php if(empty($recent_records)): ?>
                    <div class="empty-state">
                        <i class="fas fa-notes-medical"></i>
                        <h3>No medical records yet</h3>
                        <p>Your medical records will appear here after your first doctor's visit.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($recent_records as $record): ?>
                    <div class="record-item">
                        <div style="margin-right: 20px;">
                            <i class="fas fa-file-medical" style="font-size: 32px; color: #3498db;"></i>
                        </div>
                        <div class="record-info">
                            <h4>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></h4>
                            <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($record['specialization']); ?></p>
                            <p><i class="fas fa-diagnoses"></i> <?php echo htmlspecialchars(substr($record['diagnosis'], 0, 50)) . '...'; ?></p>
                            <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($record['created_at'])); ?></p>
                        </div>
                        <div>
                            <a href="view-record.php?id=<?php echo $record['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="book-appointment.php" class="action-card">
                    <i class="fas fa-calendar-plus"></i>
                    <h4>Book Appointment</h4>
                    <p>Schedule a visit with our doctors</p>
                </a>
                <a href="my-appointments.php" class="action-card">
                    <i class="fas fa-list"></i>
                    <h4>All Appointments</h4>
                    <p>View your appointment history</p>
                </a>
                <a href="medical-records.php" class="action-card">
                    <i class="fas fa-file-medical"></i>
                    <h4>Medical Records</h4>
                    <p>Access your health records</p>
                </a>
                <a href="profile.php" class="action-card">
                    <i class="fas fa-user-edit"></i>
                    <h4>Update Profile</h4>
                    <p>Manage your information</p>
                </a>
            </div>
        </main>
    </div>
</body>
</html>