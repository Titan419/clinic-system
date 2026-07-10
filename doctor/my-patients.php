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

// Get ALL patients who have had appointments with this doctor
$query = "SELECT DISTINCT 
          p.id as patient_id,
          u.id as user_id,
          u.full_name,
          u.email,
          u.phone,
          u.address,
          p.date_of_birth,
          p.blood_group,
          p.emergency_contact,
          p.medical_history,
          p.allergies,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND doctor_id = :doctor_id) as total_visits,
          (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = p.id AND doctor_id = :doctor_id) as last_visit_date,
          (SELECT status FROM appointments WHERE patient_id = p.id AND doctor_id = :doctor_id ORDER BY appointment_date DESC LIMIT 1) as last_status,
          (SELECT reason_for_visit FROM appointments WHERE patient_id = p.id AND doctor_id = :doctor_id ORDER BY appointment_date DESC LIMIT 1) as last_reason
          FROM patients p
          JOIN users u ON p.user_id = u.id
          JOIN appointments a ON p.id = a.patient_id
          WHERE a.doctor_id = :doctor_id
          ORDER BY last_visit_date DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - ClinicCare</title>
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
        }

        .page-header h1 {
            font-size: 2rem;
            color: #2c3e50;
        }

        .page-header p {
            color: #7f8c8d;
            margin-top: 5px;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }

        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .patient-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #f0f0f0;
        }

        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #3498db;
        }

        .patient-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-right: 15px;
            font-weight: bold;
        }

        .patient-name h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }

        .patient-name p {
            color: #7f8c8d;
            font-size: 13px;
        }

        .patient-details {
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #2c3e50;
            font-size: 14px;
        }

        .detail-item i {
            width: 25px;
            color: #3498db;
            font-size: 14px;
        }

        .medical-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .blood-group {
            background: #e3f2fd;
            color: #1976d2;
        }

        .allergy {
            background: #ffebee;
            color: #c62828;
        }

        .visit-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .stat-label {
            color: #7f8c8d;
        }

        .stat-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .patient-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            flex: 1;
            justify-content: center;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
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
        }

        .badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
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
            
            .patients-grid {
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
                <h1><i class="fas fa-users"></i> My Patients</h1>
                <p>Patients who have had appointments with you</p>
            </div>
            
            <!-- Search Box -->
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Search patients by name, email, phone, or blood group...">
            </div>
            
            <!-- Patients Grid -->
            <div class="patients-grid" id="patientsGrid">
                <?php if(empty($patients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Patients Yet</h3>
                        <p>You haven't had any appointments yet. Check back after your first patient visit.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($patients as $patient): ?>
                    <div class="patient-card">
                        <div class="patient-header">
                            <div class="patient-avatar">
                                <?php echo substr($patient['full_name'], 0, 1); ?>
                            </div>
                            <div class="patient-name">
                                <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></p>
                            </div>
                        </div>
                        
                        <div class="patient-details">
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($patient['phone']); ?></span>
                            </div>
                            
                            <?php if($patient['date_of_birth']): ?>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span>DOB: <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($patient['address']): ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($patient['address']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 10px;">
                                <?php if($patient['blood_group']): ?>
                                    <span class="medical-tag blood-group">
                                        <i class="fas fa-tint"></i> <?php echo $patient['blood_group']; ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if($patient['emergency_contact']): ?>
                                    <span class="medical-tag" style="background: #e8f5e9; color: #2e7d32;">
                                        <i class="fas fa-phone-alt"></i> Emergency
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="visit-stats">
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-calendar-check"></i> Total Visits:</span>
                                <span class="stat-value"><?php echo $patient['total_visits']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-clock"></i> Last Visit:</span>
                                <span class="stat-value">
                                    <?php echo $patient['last_visit_date'] ? date('M d, Y', strtotime($patient['last_visit_date'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-tag"></i> Last Status:</span>
                                <span class="stat-value">
                                    <span class="badge badge-<?php echo $patient['last_status']; ?>">
                                        <?php echo ucfirst($patient['last_status'] ?? 'N/A'); ?>
                                    </span>
                                </span>
                            </div>
                            <?php if($patient['last_reason']): ?>
                            <div class="stat-row">
                                <span class="stat-label"><i class="fas fa-notes-medical"></i> Last Reason:</span>
                                <span class="stat-value"><?php echo htmlspecialchars(substr($patient['last_reason'], 0, 30)) . '...'; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($patient['medical_history']): ?>
                        <div style="margin-bottom: 10px; padding: 10px; background: #fff3e0; border-radius: 5px;">
                            <small><strong>Medical History:</strong> <?php echo htmlspecialchars(substr($patient['medical_history'], 0, 50)) . '...'; ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($patient['allergies']): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: #ffebee; border-radius: 5px;">
                            <small><strong>Allergies:</strong> <?php echo htmlspecialchars(substr($patient['allergies'], 0, 50)) . '...'; ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="patient-actions">
                            <a href="view-patient.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Profile
                            </a>
                            <a href="patient-history.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-info">
                                <i class="fas fa-history"></i> History
                            </a>
                            <a href="add-prescription.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-success">
                                <i class="fas fa-prescription"></i> Prescribe
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const cards = document.querySelectorAll('.patient-card');
        
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (text.includes(searchText)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>