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

// Get doctor name and info
$query = "SELECT u.*, d.specialization, d.qualification, d.experience_years 
          FROM users u
          JOIN doctors d ON u.id = d.user_id
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle status update
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    $update = "UPDATE appointments SET status = :status WHERE id = :id AND doctor_id = :doctor_id";
    $update_stmt = $db->prepare($update);
    $update_stmt->bindParam(':status', $status);
    $update_stmt->bindParam(':id', $appointment_id);
    $update_stmt->bindParam(':doctor_id', $doctor_id);
    
    if ($update_stmt->execute()) {
        $success = "Appointment status updated to " . $status;
    } else {
        $error = "Failed to update status";
    }
}

// Get statistics
$stats = [];

// Today's appointments
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE doctor_id = :doctor_id 
          AND appointment_date = CURDATE()";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Completed appointments today
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE doctor_id = :doctor_id 
          AND appointment_date = CURDATE()
          AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['completed_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending appointments today
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE doctor_id = :doctor_id 
          AND appointment_date = CURDATE()
          AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['pending_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total patients (distinct)
$query = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments 
          WHERE doctor_id = :doctor_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get today's appointments with ALL details
$query = "SELECT a.*, 
          p.user_id as patient_user_id,
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
          WHERE a.doctor_id = :doctor_id 
          AND a.appointment_date = CURDATE()
          ORDER BY ts.slot_time ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed appointments history
$query = "SELECT a.*, 
          u.full_name as patient_name,
          u.phone as patient_phone,
          ts.slot_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE a.doctor_id = :doctor_id 
          AND a.status = 'completed'
          ORDER BY a.appointment_date DESC, ts.slot_time DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$completed_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - ClinicCare</title>
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

        .appointments-list {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .appointment-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-item:hover {
            background: #f8f9fa;
        }

        .appointment-time {
            background: #3498db;
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            min-width: 120px;
            margin-right: 25px;
        }

        .appointment-time .time {
            font-size: 18px;
            font-weight: bold;
        }

        .appointment-time .date {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .appointment-info {
            flex: 1;
        }

        .appointment-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .appointment-info p {
            color: #7f8c8d;
            font-size: 14px;
            margin: 5px 0;
        }

        .appointment-info i {
            width: 20px;
            color: #3498db;
            margin-right: 5px;
        }

        .badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
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

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
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

        .status-form {
            display: inline-block;
        }

        .status-select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 5px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            border-radius: 5px;
            font-weight: 500;
        }

        .tab-btn.active {
            background: #3498db;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .appointment-item {
                flex-direction: column;
                text-align: center;
            }
            
            .appointment-time {
                margin-right: 0;
                margin-bottom: 15px;
                width: 100%;
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
                <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($user['specialization']); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h1>Welcome back, Dr. <?php echo htmlspecialchars($user['full_name']); ?>! 👨‍⚕️</h1>
                <p>Today is <?php echo date('l, F j, Y'); ?>. You have <?php echo $stats['today']; ?> appointments today.</p>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['today']; ?></h3>
                        <p>Today's Appointments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed_today']; ?></h3>
                        <p>Completed Today</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_today']; ?></h3>
                        <p>Pending Today</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_patients']; ?></h3>
                        <p>Total Patients</p>
                    </div>
                </div>
            </div>

            <!-- Tab Buttons -->
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('today')">Today's Appointments</button>
                <button class="tab-btn" onclick="showTab('completed')">Completed History</button>
            </div>

            <!-- Today's Appointments Tab -->
            <div id="today-tab" class="tab-content active">
                <div class="section-title">
                    <h2><i class="fas fa-clock"></i> Today's Schedule</h2>
                </div>

                <div class="appointments-list">
                    <?php if(empty($today_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h3>No appointments today</h3>
                            <p>Enjoy your day! Check back later for new appointments.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($today_appointments as $apt): ?>
                        <div class="appointment-item">
                            <div class="appointment-time">
                                <div class="time"><?php echo date('h:i A', strtotime($apt['slot_time'])); ?></div>
                            </div>
                            <div class="appointment-info">
                                <h4><?php echo htmlspecialchars($apt['patient_name']); ?></h4>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($apt['patient_phone']); ?></p>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($apt['patient_email']); ?></p>
                                <?php if($apt['blood_group']): ?>
                                    <p><i class="fas fa-tint"></i> Blood Group: <?php echo $apt['blood_group']; ?></p>
                                <?php endif; ?>
                                <?php if($apt['emergency_contact']): ?>
                                    <p><i class="fas fa-phone-alt"></i> Emergency: <?php echo $apt['emergency_contact']; ?></p>
                                <?php endif; ?>
                                <p><i class="fas fa-notes-medical"></i> Reason: <?php echo htmlspecialchars($apt['reason_for_visit']); ?></p>
                                <?php if($apt['symptoms']): ?>
                                    <p><i class="fas fa-stethoscope"></i> Symptoms: <?php echo htmlspecialchars($apt['symptoms']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge badge-<?php echo $apt['status']; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                                <div class="action-buttons">
                                    <a href="view-patient.php?patient_id=<?php echo $apt['patient_id']; ?>" class="btn btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if($apt['status'] != 'completed'): ?>
                                    <form method="POST" class="status-form" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" name="update_status" class="btn btn-success">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="add-prescription.php?appointment_id=<?php echo $apt['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-prescription"></i> Prescribe
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed History Tab -->
            <div id="completed-tab" class="tab-content">
                <div class="section-title">
                    <h2><i class="fas fa-history"></i> Completed Appointments History</h2>
                </div>

                <div class="appointments-list">
                    <?php if(empty($completed_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No completed appointments yet</h3>
                            <p>Your completed appointments will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($completed_appointments as $apt): ?>
                        <div class="appointment-item">
                            <div class="appointment-time">
                                <div class="time"><?php echo date('h:i A', strtotime($apt['slot_time'])); ?></div>
                                <div class="date"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></div>
                            </div>
                            <div class="appointment-info">
                                <h4><?php echo htmlspecialchars($apt['patient_name']); ?></h4>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($apt['patient_phone']); ?></p>
                                <p><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars($apt['reason_for_visit']); ?></p>
                            </div>
                            <div>
                                <span class="badge badge-completed">Completed</span>
                                <div class="action-buttons">
                                    <a href="view-patient.php?patient_id=<?php echo $apt['patient_id']; ?>" class="btn btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="view-prescription.php?appointment_id=<?php echo $apt['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-file-prescription"></i> Prescription
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>