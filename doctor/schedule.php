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

// Get selected date or default to today
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Get appointments for selected date
$query = "SELECT a.*, 
          p.user_id as patient_user_id,
          u.full_name as patient_name,
          u.phone as patient_phone,
          u.email as patient_email,
          ts.slot_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN time_slots ts ON a.time_slot_id = ts.id
          WHERE a.doctor_id = :doctor_id AND a.appointment_date = :date
          ORDER BY ts.slot_time ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $doctor_id);
$stmt->bindParam(':date', $selected_date);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get time slots
$query = "SELECT * FROM time_slots WHERE is_active = TRUE ORDER BY slot_time";
$stmt = $db->prepare($query);
$stmt->execute();
$time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of booked slots
$booked_slots = [];
foreach($appointments as $apt) {
    $booked_slots[] = $apt['time_slot_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Doctor Panel</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php" class="active"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
                <li><a href="profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <h1>My Schedule</h1>
                
                <!-- Date Selector -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Select Date</h3>
                    </div>
                    <form method="GET" action="" class="date-selector">
                        <div class="form-group">
                            <input type="date" name="date" id="date" class="form-control" 
                                   value="<?php echo $selected_date; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <button type="submit" class="btn btn-primary">View Schedule</button>
                        </div>
                    </form>
                </div>
                
                <!-- Schedule Grid -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Schedule for <?php echo date('l, F j, Y', strtotime($selected_date)); ?>
                        </h3>
                    </div>
                    
                    <div class="schedule-grid">
                        <?php foreach($time_slots as $slot): ?>
                        <div class="schedule-slot <?php echo in_array($slot['id'], $booked_slots) ? 'booked' : 'available'; ?>">
                            <div class="slot-time">
                                <?php echo date('h:i A', strtotime($slot['slot_time'])); ?>
                            </div>
                            <div class="slot-status">
                                <?php if(in_array($slot['id'], $booked_slots)): ?>
                                    <?php 
                                    // Find the appointment for this slot
                                    $appointment = array_filter($appointments, function($a) use ($slot) {
                                        return $a['time_slot_id'] == $slot['id'];
                                    });
                                    $appointment = reset($appointment);
                                    ?>
                                    <span class="badge badge-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                    <div class="patient-name">
                                        <?php echo $appointment['patient_name']; ?>
                                    </div>
                                    <div class="slot-actions">
                                        <a href="view-appointment.php?id=<?php echo $appointment['id']; ?>" 
                                           class="btn btn-sm btn-primary">View</a>
                                    </div>
                                <?php else: ?>
                                    <span class="available-text">Available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Summary -->
                <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($appointments); ?></h3>
                            <p>Total Appointments</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($time_slots) - count($appointments); ?></h3>
                            <p>Available Slots</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
    .date-selector .form-group {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .schedule-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .schedule-slot {
        background: white;
        border-radius: var(--border-radius);
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .schedule-slot:hover {
        transform: translateY(-2px);
    }
    
    .schedule-slot.available {
        border-left: 4px solid #28a745;
    }
    
    .schedule-slot.booked {
        border-left: 4px solid #dc3545;
    }
    
    .slot-time {
        font-weight: bold;
        color: var(--secondary-color);
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .slot-status {
        min-height: 60px;
    }
    
    .available-text {
        color: #28a745;
        font-weight: 500;
    }
    
    .patient-name {
        margin: 10px 0;
        font-weight: 500;
    }
    
    .slot-actions {
        margin-top: 10px;
    }
    
    @media (max-width: 768px) {
        .schedule-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script src="../assets/js/script.js"></script>
</body>
</html>