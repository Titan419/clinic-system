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

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$patient_info = null;
$appointment_info = null;

if ($appointment_id) {
    // Get appointment and patient info
    $query = "SELECT a.*, u.full_name as patient_name, u.phone, p.id as patient_id
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              JOIN users u ON p.user_id = u.id
              WHERE a.id = :appointment_id AND a.doctor_id = :doctor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->bindParam(':doctor_id', $doctor_id);
    $stmt->execute();
    $appointment_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment_info) {
        $patient_id = $appointment_info['patient_id'];
    }
} elseif ($patient_id) {
    // Get patient info
    $query = "SELECT u.*, p.id as patient_id
              FROM users u
              JOIN patients p ON u.id = p.user_id
              WHERE p.id = :patient_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$appointment_info && !$patient_info) {
    header('Location: my-patients.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $diagnosis = trim($_POST['diagnosis']);
    $prescription = trim($_POST['prescription']);
    $notes = trim($_POST['notes']);
    $follow_up = $_POST['follow_up_date'] ?: null;
    
    if (empty($diagnosis) || empty($prescription)) {
        $error = "Diagnosis and prescription are required";
    } else {
        // Check if medical record already exists
        $check = "SELECT id FROM medical_records WHERE appointment_id = :appointment_id";
        $check_stmt = $db->prepare($check);
        $check_stmt->bindParam(':appointment_id', $appointment_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Update existing record
            $query = "UPDATE medical_records SET 
                      diagnosis = :diagnosis,
                      prescription = :prescription,
                      notes = :notes,
                      follow_up_date = :follow_up
                      WHERE appointment_id = :appointment_id";
            $stmt = $db->prepare($query);
        } else {
            // Insert new record
            $query = "INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, prescription, notes, follow_up_date) 
                      VALUES (:patient_id, :doctor_id, :appointment_id, :diagnosis, :prescription, :notes, :follow_up)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':patient_id', $patient_id);
            $stmt->bindParam(':doctor_id', $doctor_id);
        }
        
        $stmt->bindParam(':diagnosis', $diagnosis);
        $stmt->bindParam(':prescription', $prescription);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':follow_up', $follow_up);
        
        if ($appointment_id) {
            $stmt->bindParam(':appointment_id', $appointment_id);
        }
        
        if ($stmt->execute()) {
            // Update appointment status to completed
            if ($appointment_id) {
                $update = "UPDATE appointments SET status = 'completed' WHERE id = :id";
                $update_stmt = $db->prepare($update);
                $update_stmt->bindParam(':id', $appointment_id);
                $update_stmt->execute();
            }
            
            $success = "Prescription added successfully!";
        } else {
            $error = "Failed to add prescription";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Prescription - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your styles here - similar to previous */
        .prescription-form {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .patient-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
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
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
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
                <div class="user-avatar-large"><?php echo substr($user['full_name'], 0, 1); ?></div>
                <h4>Dr. <?php echo htmlspecialchars($user['full_name']); ?></h4>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> My Schedule</a></li>
                <li><a href="profile.php"><i class="fas fa-user-md"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <main class="main-content">
            <div class="prescription-form">
                <h1 style="margin-bottom: 25px;"><i class="fas fa-prescription"></i> Add Prescription</h1>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Patient Info -->
                <div class="patient-info">
                    <h3>Patient Information</h3>
                    <?php if($appointment_info): ?>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($appointment_info['patient_name']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($appointment_info['phone']); ?></p>
                        <p><strong>Appointment Date:</strong> <?php echo date('M d, Y', strtotime($appointment_info['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($appointment_info['slot_time'])); ?></p>
                        <p><strong>Reason:</strong> <?php echo htmlspecialchars($appointment_info['reason_for_visit']); ?></p>
                    <?php elseif($patient_info): ?>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient_info['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient_info['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient_info['phone']); ?></p>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-stethoscope"></i> Diagnosis</label>
                        <textarea name="diagnosis" class="form-control" placeholder="Enter diagnosis..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-pills"></i> Prescription</label>
                        <textarea name="prescription" class="form-control" placeholder="Enter prescription details..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-edit"></i> Additional Notes</label>
                        <textarea name="notes" class="form-control" placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Follow-up Date (Optional)</label>
                        <input type="date" name="follow_up_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Prescription
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>