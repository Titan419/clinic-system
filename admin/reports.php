<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get statistics
$total_patients = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$total_doctors = $db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$total_appointments = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$completed = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();
$pending = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f4f6f9; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #2c3e50; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 { font-size: 2.5rem; color: #3498db; margin-bottom: 10px; }
        .stat-card p { color: #666; }
        .back-btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h1><i class="fas fa-chart-bar"></i> Clinic Reports</h1>
        
        <div class="stats-grid">
            <div class="stat-card"><h3><?php echo $total_patients; ?></h3><p>Total Patients</p></div>
            <div class="stat-card"><h3><?php echo $total_doctors; ?></h3><p>Total Doctors</p></div>
            <div class="stat-card"><h3><?php echo $total_appointments; ?></h3><p>Total Appointments</p></div>
            <div class="stat-card"><h3><?php echo $completed; ?></h3><p>Completed</p></div>
            <div class="stat-card"><h3><?php echo $pending; ?></h3><p>Pending</p></div>
        </div>
    </div>
</body>
</html>