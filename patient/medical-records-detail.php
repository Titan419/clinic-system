<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isPatient()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get patient ID
$query = "SELECT id FROM patients WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_id = $patient['id'];

// Get specific medical record
$query = "SELECT mr.*, 
          u.full_name as doctor_name,
          u.email as doctor_email,
          u.phone as doctor_phone,
          d.specialization,
          d.qualification,
          a.appointment_date,
          a.reason_for_visit,
          a.symptoms
          FROM medical_records mr
          JOIN doctors d ON mr.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          LEFT JOIN appointments a ON mr.appointment_id = a.id
          WHERE mr.id = :record_id AND mr.patient_id = :patient_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':record_id', $record_id);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->execute();
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    header('Location: medical-records.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Record Details - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your styles here */
        .record-detail {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .doctor-profile {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 25px;
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
        }
        
        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(52,152,219,0.4);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Same sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-clinic-medical"></i> ClinicCare</h3>
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
        
        <main class="main-content">
            <div class="record-detail">
                <h1>Medical Record Details</h1>
                <!-- Add record details here -->
            </div>
        </main>
    </div>
    <!-- In the record item, change the view button to: -->
<a href="view-record.php?id=<?php echo $record['id']; ?>" class="btn btn-primary btn-sm">
    <i class="fas fa-eye"></i> View Details
</a>
</body>
</html>