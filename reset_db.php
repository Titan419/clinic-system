<?php
function getDatabaseConfig() {
    $databaseUrl = getenv('DATABASE_URL');

    if (!empty($databaseUrl)) {
        $parsed = parse_url($databaseUrl);
        if ($parsed !== false) {
            return [
                'host' => $parsed['host'] ?? getenv('DB_HOST') ?: 'localhost',
                'username' => $parsed['user'] ?? getenv('DB_USERNAME') ?: 'root',
                'password' => $parsed['pass'] ?? getenv('DB_PASSWORD') ?: '',
                'port' => $parsed['port'] ?? getenv('DB_PORT') ?: null,
            ];
        }
    }

    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'port' => getenv('DB_PORT') ?: null,
    ];
}

$config = getDatabaseConfig();
$host = $config['host'];
$username = $config['username'];
$password = $config['password'];
$port = $config['port'];

try {
    $dsn = "mysql:host=$host";
    if (!empty($port)) {
        $dsn .= ";port=$port";
    }

    // Connect without database
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop database if exists
    $pdo->exec("DROP DATABASE IF EXISTS clinic_system");
    echo "✅ Old database dropped<br>";
    
    // Create new database
    $pdo->exec("CREATE DATABASE clinic_system");
    echo "✅ New database created<br>";
    
    echo "<h2>Database reset successfully!</h2>";
    echo "<a href='install.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Click here to install fresh database</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>