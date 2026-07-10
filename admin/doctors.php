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

// Handle doctor deletion
if(isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    $query = "UPDATE users SET is_active = FALSE WHERE id = :id AND user_type = 'doctor'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    
    if($stmt->execute()) {
        $success = "Doctor deactivated successfully!";
    } else {
        $error = "Failed to deactivate doctor.";
    }
}

// Get all doctors
$query = "SELECT u.*, d.specialization, d.qualification, d.experience_years, 
          d.consultation_fee, d.available_days, d.start_time, d.end_time,
          (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id) as total_appointments,
          (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND appointment_date = CURDATE()) as today_appointments
          FROM users u
          JOIN doctors d ON u.id = d.user_id
          WHERE u.user_type = 'doctor' AND u.is_active = TRUE
          ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - ClinicCare</title>
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
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <h1>Manage Doctors</h1>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Add Doctor Button -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Doctors List (<?php echo count($doctors); ?>)</h3>
                        <a href="add-doctor.php" class="btn btn-success">Add New Doctor</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Specialization</th>
                                    <th>Qualification</th>
                                    <th>Experience</th>
                                    <th>Consultation Fee</th>
                                    <th>Schedule</th>
                                    <th>Today's Appointments</th>
                                    <th>Total Appointments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($doctors as $doctor): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 10px;">
                                                <?php echo substr($doctor['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <strong>Dr. <?php echo $doctor['full_name']; ?></strong><br>
                                                <small><?php echo $doctor['email']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-primary"><?php echo $doctor['specialization']; ?></span></td>
                                    <td><?php echo $doctor['qualification']; ?></td>
                                    <td><?php echo $doctor['experience_years']; ?> years</td>
                                    <td>TSh <?php echo number_format($doctor['consultation_fee']); ?></td>
                                    <td>
                                        <small>
                                            <strong>Days:</strong> <?php echo $doctor['available_days']; ?><br>
                                            <strong>Time:</strong> <?php echo date('h:i A', strtotime($doctor['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($doctor['end_time'])); ?>
                                        </small>
                                    </td>
                                    <td><?php echo $doctor['today_appointments']; ?></td>
                                    <td><?php echo $doctor['total_appointments']; ?></td>
                                    <td>
                                        <a href="view-doctor.php?id=<?php echo $doctor['id']; ?>" 
                                           class="btn btn-sm btn-primary">View</a>
                                        <a href="edit-doctor.php?id=<?php echo $doctor['id']; ?>" 
                                           class="btn btn-sm btn-success">Edit</a>
                                        <a href="?delete=<?php echo $doctor['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to deactivate this doctor?')">Deactivate</a>
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
    <!-- In the doctors.php file, add this button at the top -->
<div class="page-header">
    <h1><i class="fas fa-user-md"></i> Manage Doctors</h1>
    <a href="add-doctor.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Add New Doctor
    </a>
</div>
</body>
</html>