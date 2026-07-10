<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_GET['doctor_id']) || !isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all time slots
$query = "SELECT id FROM time_slots WHERE is_active = TRUE";
$stmt = $db->prepare($query);
$stmt->execute();
$all_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get booked slots
$query = "SELECT time_slot_id FROM appointments 
          WHERE doctor_id = :doctor_id 
          AND appointment_date = :date 
          AND status NOT IN ('cancelled', 'no_show')";

$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $_GET['doctor_id']);
$stmt->bindParam(':date', $_GET['date']);
$stmt->execute();
$booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'available_slots' => $all_slots,
    'booked_slots' => $booked_slots
]);
?>