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

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_status = isset($_GET['status']) ? $_GET['status'] : '';

// Valid statuses
$valid_statuses = ['pending', 'confirmed', 'arrived', 'completed', 'cancelled', 'no_show'];

if ($appointment_id && in_array($new_status, $valid_statuses)) {
    $query = "UPDATE appointments SET status = :status WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':id', $appointment_id);
    
    if ($stmt->execute()) {
        // Redirect back to appointments page with success message
        header('Location: appointments.php?success=status_updated');
    } else {
        header('Location: appointments.php?error=update_failed');
    }
} else {
    header('Location: appointments.php');
}
exit();