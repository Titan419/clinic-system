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

// Handle patient deletion
if(isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Soft delete - deactivate user
    $query = "UPDATE users SET is_active = FALSE WHERE id = :id AND user_type = 'patient'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    
    if($stmt->execute()) {
        $success = "Patient deactivated successfully!";
    } else {
        $error = "Failed to deactivate patient.";
    }
}

// Get all patients
$query = "SELECT u.*, p.date_of_birth, p.blood_group, p.emergency_contact,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as total_appointments,
          (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'completed') as completed_appointments
          FROM users u
          JOIN patients p ON u.id = p.user_id
          WHERE u.user_type = 'patient' AND u.is_active = TRUE
          ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - ClinicCare</title>
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
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <h1>Manage Patients</h1>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Search and Filter -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Search Patients</h3>
                    </div>
                    <div class="search-box">
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Search by name, email, phone..." style="width: 100%;">
                    </div>
                </div>
                
                <!-- Patients List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Patients (<?php echo count($patients); ?>)</h3>
                        <a href="add-patient.php" class="btn btn-success">Add New Patient</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table" id="patients-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Contact</th>
                                    <th>Blood Group</th>
                                    <th>Emergency Contact</th>
                                    <th>Total Appointments</th>
                                    <th>Completed</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($patients as $patient): ?>
                                <tr>
                                    <td>#<?php echo $patient['id']; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 10px;">
                                                <?php echo substr($patient['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo $patient['full_name']; ?></strong><br>
                                                <small><?php echo $patient['email']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $patient['phone']; ?></td>
                                    <td><span class="badge badge-info"><?php echo $patient['blood_group'] ?? 'N/A'; ?></span></td>
                                    <td><?php echo $patient['emergency_contact'] ?? 'N/A'; ?></td>
                                    <td><?php echo $patient['total_appointments']; ?></td>
                                    <td><?php echo $patient['completed_appointments']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
                                    <td>
                                        <a href="view-patient.php?id=<?php echo $patient['id']; ?>" 
                                           class="btn btn-sm btn-primary">View</a>
                                        <a href="edit-patient.php?id=<?php echo $patient['id']; ?>" 
                                           class="btn btn-sm btn-success">Edit</a>
                                        <a href="?delete=<?php echo $patient['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to deactivate this patient?')">Deactivate</a>
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

    <script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById('patients-table');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        for(let row of rows) {
            const name = row.cells[1].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const phone = row.cells[2].textContent.toLowerCase();
            
            if(name.includes(searchText) || email.includes(searchText) || phone.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
    </script>

    <script src="../assets/js/script.js"></script>
</body>
</html>