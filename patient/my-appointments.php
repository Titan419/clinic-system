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

// Handle appointment cancellation
if(isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    $query = "UPDATE appointments SET status = 'cancelled' WHERE id = :id AND patient_id = :patient_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $appointment_id);
    $stmt->bindParam(':patient_id', $patient_id);
    
    if($stmt->execute()) {
        $success = "Appointment cancelled successfully!";
    } else {
        $error = "Failed to cancel appointment.";
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'upcoming';

// Get appointments
$query = "SELECT a.*, 
          d.user_id as doctor_user_id,
          u.full_name as doctor_name,
          d.specialization,
          d.consultation_fee,
          ts.slot_time
          FROM appointments a
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE a.patient_id = :patient_id";

if($filter == 'upcoming') {
    $query .= " AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed')";
} elseif($filter == 'past') {
    $query .= " AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled', 'no_show'))";
}

$query .= " ORDER BY a.appointment_date DESC, ts.slot_time DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Patient Panel</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="book-appointment.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php" class="active"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="medical-records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <h1>My Appointments</h1>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Filter Tabs -->
                <div class="card">
                    <div class="tab-buttons">
                        <a href="?filter=upcoming" class="tab-btn <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                            Upcoming Appointments
                        </a>
                        <a href="?filter=past" class="tab-btn <?php echo $filter == 'past' ? 'active' : ''; ?>">
                            Past Appointments
                        </a>
                        <a href="?filter=all" class="tab-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                            All Appointments
                        </a>
                    </div>
                </div>
                
                <!-- Appointments List -->
                <div class="appointments-list">
                    <?php if(empty($appointments)): ?>
                        <div class="card text-center">
                            <p>No appointments found.</p>
                            <a href="book-appointment.php" class="btn btn-primary">Book an Appointment</a>
                        </div>
                    <?php else: ?>
                        <?php foreach($appointments as $appointment): ?>
                        <div class="appointment-card card">
                            <div class="appointment-header">
                                <div class="doctor-info">
                                    <div class="doctor-avatar">
                                        <?php echo substr($appointment['doctor_name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <h4>Dr. <?php echo $appointment['doctor_name']; ?></h4>
                                        <p class="specialty"><?php echo $appointment['specialization']; ?></p>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            
                            <div class="appointment-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($appointment['slot_time'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <span>Fee: TSh <?php echo number_format($appointment['consultation_fee']); ?></span>
                                </div>
                                <?php if($appointment['reason_for_visit']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-notes-medical"></i>
                                    <span><?php echo $appointment['reason_for_visit']; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="appointment-actions">
                                <a href="view-appointment.php?id=<?php echo $appointment['id']; ?>" 
                                   class="btn btn-sm btn-primary">View Details</a>
                                <?php if($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn btn-sm btn-danger">
                                            Cancel Appointment
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if($appointment['status'] == 'completed'): ?>
                                    <a href="add-review.php?appointment_id=<?php echo $appointment['id']; ?>" 
                                       class="btn btn-sm btn-success">Add Review</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <style>
    .tab-buttons {
        display: flex;
        gap: 10px;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    
    .tab-btn {
        padding: 10px 20px;
        text-decoration: none;
        color: #666;
        border-radius: 5px 5px 0 0;
        transition: all 0.3s;
    }
    
    .tab-btn:hover {
        background: #f5f5f5;
    }
    
    .tab-btn.active {
        background: var(--secondary-color);
        color: white;
    }
    
    .appointments-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .appointment-card {
        transition: transform 0.3s;
    }
    
    .appointment-card:hover {
        transform: translateY(-2px);
    }
    
    .appointment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .doctor-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .appointment-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #666;
    }
    
    .detail-item i {
        color: var(--secondary-color);
        width: 20px;
    }
    
    .appointment-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        border-top: 1px solid #eee;
        padding-top: 15px;
    }
    </style>

    <script src="../assets/js/script.js"></script>
</body>
</html>