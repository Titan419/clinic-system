<?php
require_once '../config/database.php';
header('Content-Type: application/json');
$database = new Database();
$db = $database->getConnection();
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
if (!$patient_id || !$date) {
 echo json_encode(['error' => 'Missing parameters']);
 exit();
}
// Get patient's booked slots for this date
$query = "SELECT time_slot_id FROM appointments
 WHERE patient_id = :patient_id
 AND appointment_date = :date
 AND status NOT IN ('cancelled')";
$stmt = $db->prepare($query);
$stmt->bindParam(':patient_id', $patient_id);
$stmt->bindParam(':date', $date);
$stmt->execute();
$booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode([
 'booked_slots' => $booked_slots
]);
?>