<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_GET['doctor_id']) || !isset($_GET['date']) || !isset($_GET['time_slot'])) {
    echo json_encode(['available' => false, 'error' => 'Missing parameters']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT id FROM appointments 
          WHERE doctor_id = :doctor_id 
          AND appointment_date = :date 
          AND time_slot_id = :time_slot 
          AND status NOT IN ('cancelled', 'no_show')";

$stmt = $db->prepare($query);
$stmt->bindParam(':doctor_id', $_GET['doctor_id']);
$stmt->bindParam(':date', $_GET['date']);
$stmt->bindParam(':time_slot', $_GET['time_slot']);
$stmt->execute();

$available = $stmt->rowCount() == 0;

echo json_encode(['available' => $available]);
?>