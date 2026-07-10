<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Clear session cache
$_SESSION['cache_cleared'] = time();

// You can add more cache clearing logic here
// For example, delete temporary files, etc.

echo json_encode([
    'success' => true, 
    'message' => 'Cache cleared successfully at ' . date('Y-m-d H:i:s')
]);
exit;
?>