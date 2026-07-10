<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if(!isLoggedIn() || !isPatient()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$database = new Database();
$db = $database->getConnection();

// Get patient ID
$query = "SELECT id FROM patients WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

// Check availability
$check_query = "SELECT id FROM appointments 
                WHERE doctor_id = :doctor_id 
                AND appointment_date = :date 
                AND time_slot_id = :time_slot 
                AND status NOT IN ('cancelled', 'no_show')";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':doctor_id', $data['doctor_id']);
$check_stmt->bindParam(':date', $data['appointment_date']);
$check_stmt->bindParam(':time_slot', $data['time_slot']);
$check_stmt->execute();

if($check_stmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Time slot not available']);
    exit();
}

// Book appointment
$insert_query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, time_slot_id, reason_for_visit, symptoms, status) 
                 VALUES (:patient_id, :doctor_id, :appointment_date, :time_slot, :reason, :symptoms, 'pending')";
$insert_stmt = $db->prepare($insert_query);
$insert_stmt->bindParam(':patient_id', $patient['id']);
$insert_stmt->bindParam(':doctor_id', $data['doctor_id']);
$insert_stmt->bindParam(':appointment_date', $data['appointment_date']);
$insert_stmt->bindParam(':time_slot', $data['time_slot']);
$insert_stmt->bindParam(':reason', $data['reason']);
$insert_stmt->bindParam(':symptoms', $data['symptoms']);

if($insert_stmt->execute()) {
    // Create notification for admin and doctor
    $appointment_id = $db->lastInsertId();
    
    // Notify admin
    $notify_query = "INSERT INTO notifications (user_id, title, message, type) 
                     SELECT id, 'New Appointment', 'A new appointment has been booked', 'appointment' 
                     FROM users WHERE user_type = 'admin'";
    $db->prepare($notify_query)->execute();
    
    // Notify doctor
    $doctor_query = "SELECT user_id FROM doctors WHERE id = :doctor_id";
    $doctor_stmt = $db->prepare($doctor_query);
    $doctor_stmt->bindParam(':doctor_id', $data['doctor_id']);
    $doctor_stmt->execute();
    $doctor = $doctor_stmt->fetch(PDO::FETCH_ASSOC);
    
    if($doctor) {
        $notify_doctor = "INSERT INTO notifications (user_id, title, message, type) 
                          VALUES (:user_id, 'New Appointment', 'You have a new appointment booking', 'appointment')";
        $notify_doctor_stmt = $db->prepare($notify_doctor);
        $notify_doctor_stmt->bindParam(':user_id', $doctor['user_id']);
        $notify_doctor_stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Appointment booked successfully', 'appointment_id' => $appointment_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to book appointment']);
}
?>