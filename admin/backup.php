<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Get all tables
$database = new Database();
$db = $database->getConnection();

// Get all table names
$tables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sqlScript = "";
foreach ($tables as $table) {
    // Get create table statement
    $create = $db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
    $sqlScript .= "\n\n" . $create[1] . ";\n\n";
    
    // Get data
    $rows = $db->query("SELECT * FROM $table");
    $rowCount = $rows->rowCount();
    
    if ($rowCount > 0) {
        $sqlScript .= "INSERT INTO $table VALUES";
        
        $i = 0;
        while ($row = $rows->fetch(PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $value) {
                $values[] = is_null($value) ? 'NULL' : $db->quote($value);
            }
            $sqlScript .= ($i++ == 0 ? '' : ',') . '(' . implode(',', $values) . ')';
        }
        $sqlScript .= ";\n";
    }
}

// Set headers for download
header('Content-Type: text/sql');
header('Content-Disposition: attachment; filename="clinic_backup_' . date('Y-m-d_H-i-s') . '.sql"');
header('Content-Length: ' . strlen($sqlScript));

echo $sqlScript;
exit;
?>