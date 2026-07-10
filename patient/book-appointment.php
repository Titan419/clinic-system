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

// Get all doctors
$query = "SELECT d.*, u.full_name, u.email, u.phone 
          FROM doctors d
          JOIN users u ON d.user_id = u.id
          WHERE u.is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get time slots
$query = "SELECT * FROM time_slots WHERE is_active = 1 ORDER BY slot_time";
$stmt = $db->prepare($query);
$stmt->execute();
$time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $time_slot_id = $_POST['time_slot'];
    $reason = sanitize($_POST['reason']);
    $symptoms = sanitize($_POST['symptoms']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($doctor_id)) {
        $errors[] = "Please select a doctor";
    }
    
    if (empty($appointment_date)) {
        $errors[] = "Please select a date";
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Appointment date cannot be in the past";
    }
    
    if (empty($time_slot_id)) {
        $errors[] = "Please select a time slot";
    }
    
    if (empty($reason)) {
        $errors[] = "Please provide a reason for visit";
    }
    
    // Check if slot is available
    if (empty($errors)) {
        $check = "SELECT id FROM appointments 
                  WHERE doctor_id = :doctor_id 
                  AND appointment_date = :date 
                  AND time_slot_id = :slot 
                  AND status NOT IN ('cancelled')";
        $check_stmt = $db->prepare($check);
        $check_stmt->bindParam(':doctor_id', $doctor_id);
        $check_stmt->bindParam(':date', $appointment_date);
        $check_stmt->bindParam(':slot', $time_slot_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Selected time slot is already booked. Please choose another time.";
        }
    }
    
    // If no errors, book appointment
    if (empty($errors)) {
        $insert = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, time_slot_id, reason_for_visit, symptoms, status) 
                   VALUES (:patient_id, :doctor_id, :date, :slot, :reason, :symptoms, 'pending')";
        $insert_stmt = $db->prepare($insert);
        $insert_stmt->bindParam(':patient_id', $patient_id);
        $insert_stmt->bindParam(':doctor_id', $doctor_id);
        $insert_stmt->bindParam(':date', $appointment_date);
        $insert_stmt->bindParam(':slot', $time_slot_id);
        $insert_stmt->bindParam(':reason', $reason);
        $insert_stmt->bindParam(':symptoms', $symptoms);
        
        if ($insert_stmt->execute()) {
            $success = "Appointment booked successfully!";
        } else {
            $error = "Failed to book appointment. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Function to check if slot is available (for AJAX)
function isSlotAvailable($db, $doctor_id, $date, $time_slot_id) {
    $query = "SELECT id FROM appointments 
              WHERE doctor_id = :doctor_id 
              AND appointment_date = :date 
              AND time_slot_id = :slot 
              AND status NOT IN ('cancelled')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':slot', $time_slot_id);
    $stmt->execute();
    return $stmt->rowCount() == 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - ClinicCare</title>
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #3498db;
        }

        .page-header p {
            color: #7f8c8d;
            margin-top: 5px;
        }

        .booking-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .doctors-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .doctors-list h2, .booking-form h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .doctors-list h2 i, .booking-form h2 i {
            color: #3498db;
        }

        .doctor-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .doctor-card:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(52,152,219,0.1);
            transform: translateY(-2px);
        }

        .doctor-card.selected {
            border: 2px solid #3498db;
            background: rgba(52,152,219,0.05);
        }

        .doctor-avatar {
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
        }

        .doctor-info h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .doctor-info .specialty {
            color: #3498db;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .doctor-info .experience {
            color: #7f8c8d;
            font-size: 13px;
        }

        .doctor-info .fee {
            color: #27ae60;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }

        .booking-form {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group label i {
            color: #3498db;
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .time-slot {
            padding: 10px;
            text-align: center;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .time-slot:hover {
            border-color: #3498db;
            background: rgba(52,152,219,0.05);
        }

        .time-slot.selected {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .time-slot.booked {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .time-slot.booked:hover {
            transform: none;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
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
            width: 100%;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.3);
        }

        .btn-primary:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .selected-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .selected-info p {
            margin: 5px 0;
            color: #2c3e50;
        }

        .selected-info i {
            color: #3498db;
            width: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .booking-container {
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
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p><i class="fas fa-user"></i> Patient</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="book-appointment.php" class="active"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="medical-records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-calendar-plus"></i> Book an Appointment</h1>
                <p>Schedule a visit with our expert doctors</p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="booking-container">
                <!-- Doctors List -->
                <div class="doctors-list">
                    <h2><i class="fas fa-user-md"></i> Select a Doctor</h2>
                    
                    <?php foreach($doctors as $doctor): ?>
                    <div class="doctor-card" onclick="selectDoctor(<?php echo $doctor['id']; ?>, '<?php echo addslashes($doctor['full_name']); ?>', '<?php echo $doctor['specialization']; ?>', <?php echo $doctor['consultation_fee']; ?>)">
                        <div class="doctor-avatar">
                            <?php echo substr($doctor['full_name'], 0, 1); ?>
                        </div>
                        <div class="doctor-info">
                            <h4>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h4>
                            <div class="specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
                            <div class="experience"><?php echo $doctor['experience_years']; ?> years experience</div>
                            <div class="fee">TSh <?php echo number_format($doctor['consultation_fee']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Booking Form -->
                <div class="booking-form">
                    <h2><i class="fas fa-calendar-alt"></i> Appointment Details</h2>
                    
                    <form method="POST" action="" id="appointmentForm">
                        <input type="hidden" name="doctor_id" id="selectedDoctorId" required>
                        
                        <div class="selected-info" id="selectedDoctorInfo" style="display: none;">
                            <p><i class="fas fa-user-md"></i> <strong>Doctor:</strong> <span id="displayDoctorName"></span></p>
                            <p><i class="fas fa-stethoscope"></i> <strong>Specialty:</strong> <span id="displaySpecialty"></span></p>
                            <p><i class="fas fa-money-bill"></i> <strong>Fee:</strong> TSh <span id="displayFee"></span></p>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Appointment Date</label>
                            <input type="date" name="appointment_date" id="appointmentDate" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" 
                                   onchange="checkAvailability()" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Available Time Slots</label>
                            <div class="time-slots" id="timeSlots">
                                <?php foreach($time_slots as $slot): ?>
                                <div class="time-slot" data-slot-id="<?php echo $slot['id']; ?>" 
                                     data-slot-time="<?php echo date('h:i A', strtotime($slot['slot_time'])); ?>"
                                     onclick="selectTimeSlot(<?php echo $slot['id']; ?>)">
                                    <?php echo date('h:i A', strtotime($slot['slot_time'])); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="time_slot" id="selectedTimeSlot" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-notes-medical"></i> Reason for Visit</label>
                            <input type="text" name="reason" class="form-control" 
                                   placeholder="e.g., Routine checkup, fever, consultation" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-stethoscope"></i> Symptoms (Optional)</label>
                            <textarea name="symptoms" class="form-control" 
                                      placeholder="Describe your symptoms"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-calendar-check"></i> Book Appointment
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    let selectedDoctor = null;
    let selectedTimeSlot = null;
    let bookedSlots = [];
    
    function selectDoctor(doctorId, doctorName, specialty, fee) {
        selectedDoctor = doctorId;
        document.getElementById('selectedDoctorId').value = doctorId;
        
        // Update display
        document.getElementById('displayDoctorName').textContent = 'Dr. ' + doctorName;
        document.getElementById('displaySpecialty').textContent = specialty;
        document.getElementById('displayFee').textContent = fee.toLocaleString();
        document.getElementById('selectedDoctorInfo').style.display = 'block';
        
        // Remove selected class from all doctor cards
        document.querySelectorAll('.doctor-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        event.currentTarget.classList.add('selected');
        
        // Check availability if date is selected
        if (document.getElementById('appointmentDate').value) {
            checkAvailability();
        }
    }
    
    function selectTimeSlot(slotId) {
        selectedTimeSlot = slotId;
        document.getElementById('selectedTimeSlot').value = slotId;
        
        // Remove selected class from all slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });
        
        // Add selected class to clicked slot
        const selectedSlot = document.querySelector(`.time-slot[data-slot-id="${slotId}"]`);
        if (selectedSlot && !selectedSlot.classList.contains('booked')) {
            selectedSlot.classList.add('selected');
        }
    }
    
    function checkAvailability() {
        const doctorId = document.getElementById('selectedDoctorId').value;
        const date = document.getElementById('appointmentDate').value;
        
        if (!doctorId || !date) return;
        
        // Show loading state
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.add('loading');
        });
        
        // Fetch available slots
        fetch(`../api/check-availability.php?doctor_id=${doctorId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                updateTimeSlots(data.available_slots, data.booked_slots);
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
    
    function updateTimeSlots(availableSlots, booked) {
        // Reset all slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('booked', 'selected', 'loading');
            slot.style.pointerEvents = 'auto';
            slot.style.opacity = '1';
        });
        
        // Mark booked slots
        booked.forEach(slotId => {
            const slot = document.querySelector(`.time-slot[data-slot-id="${slotId}"]`);
            if (slot) {
                slot.classList.add('booked');
                slot.style.pointerEvents = 'none';
                slot.style.opacity = '0.5';
            }
        });
        
        bookedSlots = booked;
    }
    
    // Form validation
    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        if (!selectedDoctor) {
            e.preventDefault();
            alert('Please select a doctor');
            return;
        }
        
        if (!document.getElementById('appointmentDate').value) {
            e.preventDefault();
            alert('Please select a date');
            return;
        }
        
        if (!selectedTimeSlot) {
            e.preventDefault();
            alert('Please select a time slot');
            return;
        }
        
        if (bookedSlots.includes(selectedTimeSlot)) {
            e.preventDefault();
            alert('This time slot is no longer available. Please select another.');
            return;
        }
    });
    
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