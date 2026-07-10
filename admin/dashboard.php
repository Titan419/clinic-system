<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total patients
$query = "SELECT COUNT(*) as count FROM patients";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total doctors
$query = "SELECT COUNT(*) as count FROM doctors";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent appointments
$query = "SELECT a.*, 
          p.user_id as patient_user_id, 
          pu.full_name as patient_name,
          d.user_id as doctor_user_id,
          du.full_name as doctor_name,
          ts.slot_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users pu ON p.user_id = pu.id
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users du ON d.user_id = du.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          ORDER BY a.created_at DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <h1>Admin Dashboard</h1>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_patients']; ?></h3>
                            <p>Total Patients</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_doctors']; ?></h3>
                            <p>Total Doctors</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['today_appointments']; ?></h3>
                            <p>Today's Appointments</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['pending_appointments']; ?></h3>
                            <p>Pending Appointments</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Appointments</h3>
                        <a href="appointments.php" class="btn btn-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo $appointment['patient_name']; ?></td>
                                    <td>Dr. <?php echo $appointment['doctor_name']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($appointment['slot_time'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-appointment.php?id=<?php echo $appointment['id']; ?>" 
                                           class="btn btn-sm btn-primary">View</a>
                                        <a href="update-status.php?id=<?php echo $appointment['id']; ?>&status=confirmed" 
                                           class="btn btn-sm btn-success">Confirm</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>