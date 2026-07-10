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

// Get all medical records for this patient
$query = "SELECT mr.*, 
          u.full_name as doctor_name,
          d.specialization,
          a.appointment_date,
          a.reason_for_visit
          FROM medical_records mr
          JOIN doctors d ON mr.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          LEFT JOIN appointments a ON mr.appointment_id = a.id
          WHERE mr.patient_id = :patient_id
          ORDER BY mr.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats = [];

// Total records
$stats['total'] = count($medical_records);

// Last visit
$query = "SELECT MAX(appointment_date) as last_visit FROM appointments 
          WHERE patient_id = :patient_id AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['last_visit'] = $result['last_visit'] ? date('M d, Y', strtotime($result['last_visit'])) : 'No visits yet';

// Common diagnosis
$query = "SELECT diagnosis, COUNT(*) as count FROM medical_records 
          WHERE patient_id = :patient_id AND diagnosis IS NOT NULL 
          GROUP BY diagnosis ORDER BY count DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$common = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['common_diagnosis'] = $common ? $common['diagnosis'] : 'None';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - ClinicCare</title>
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
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .page-header h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .page-header p {
            color: #7f8c8d;
            margin-top: 5px;
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

        .records-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .record-card {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .record-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .record-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
        }

        .record-date {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .record-date i {
            color: #3498db;
            font-size: 1.2rem;
        }

        .record-date strong {
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .doctor-info i {
            color: #27ae60;
        }

        .record-body {
            padding: 20px;
        }

        .diagnosis-section, .prescription-section, .notes-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .diagnosis-section h4, .prescription-section h4, .notes-section h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .diagnosis-section h4 i {
            color: #e74c3c;
        }

        .prescription-section h4 i {
            color: #27ae60;
        }

        .notes-section h4 i {
            color: #f39c12;
        }

        .diagnosis-section p, .prescription-section p, .notes-section p {
            color: #34495e;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-primary {
            background: #3498db;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .btn {
            padding: 12px 24px;
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

        .filter-section {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box:focus {
            outline: none;
            border-color: #3498db;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .record-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-file-medical"></i> Medical Records</h1>
                    <p>View your complete medical history and health records</p>
                </div>
                <a href="download-records.php" class="btn btn-success">
                    <i class="fas fa-download"></i> Download All
                </a>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Records</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['last_visit']; ?></h3>
                        <p>Last Visit</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($stats['common_diagnosis']); ?></h3>
                        <p>Common Diagnosis</p>
                    </div>
                </div>
            </div>

            <!-- Search Filter -->
            <div class="filter-section">
                <input type="text" class="search-box" id="searchRecords" 
                       placeholder="🔍 Search by doctor, diagnosis, or prescription...">
            </div>

            <!-- Medical Records List -->
            <div class="records-container" id="recordsContainer">
                <?php if(empty($medical_records)): ?>
                    <div class="empty-state">
                        <i class="fas fa-notes-medical"></i>
                        <h3>No Medical Records Found</h3>
                        <p>Your medical records will appear here after your first doctor's visit.</p>
                        <a href="book-appointment.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book an Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($medical_records as $record): ?>
                    <div class="record-card">
                        <div class="record-header">
                            <div class="record-date">
                                <i class="fas fa-calendar-alt"></i>
                                <strong><?php echo date('F j, Y', strtotime($record['created_at'])); ?></strong>
                                <span class="badge badge-primary">Record #<?php echo $record['id']; ?></span>
                            </div>
                            <div class="doctor-info">
                                <i class="fas fa-user-md"></i>
                                <span>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></span>
                                <small>(<?php echo htmlspecialchars($record['specialization']); ?>)</small>
                            </div>
                        </div>
                        
                        <div class="record-body">
                            <?php if($record['appointment_date']): ?>
                            <p style="margin-bottom: 15px; color: #7f8c8d;">
                                <i class="fas fa-clock"></i> 
                                Visit Date: <?php echo date('F j, Y', strtotime($record['appointment_date'])); ?>
                                <?php if($record['reason_for_visit']): ?>
                                    - Reason: <?php echo htmlspecialchars($record['reason_for_visit']); ?>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if($record['diagnosis']): ?>
                            <div class="diagnosis-section">
                                <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                                <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['prescription']): ?>
                            <div class="prescription-section">
                                <h4><i class="fas fa-prescription"></i> Prescription</h4>
                                <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['notes']): ?>
                            <div class="notes-section">
                                <h4><i class="fas fa-edit"></i> Additional Notes</h4>
                                <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['follow_up_date']): ?>
                            <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px;">
                                <i class="fas fa-calendar-plus" style="color: #1976d2;"></i>
                                <strong>Follow-up Date:</strong> <?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Search functionality
    document.getElementById('searchRecords').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const records = document.querySelectorAll('.record-card');
        
        records.forEach(record => {
            const text = record.textContent.toLowerCase();
            if (text.includes(searchText)) {
                record.style.display = 'block';
            } else {
                record.style.display = 'none';
            }
        });
    });

    // Print functionality
    function printRecord(recordId) {
        window.open('print-record.php?id=' + recordId, '_blank');
    }

    // Download as PDF
    function downloadPDF(recordId) {
        window.location.href = 'download-record.php?id=' + recordId;
    }
    </script>
</body>
</html>