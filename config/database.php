<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $this->loadConfig();
    }

    private function loadConfig() {
        $databaseUrl = getenv('DATABASE_URL');

        if (!empty($databaseUrl)) {
            $parsed = parse_url($databaseUrl);
            if ($parsed !== false) {
                $this->host = $parsed['host'] ?? getenv('DB_HOST') ?: 'localhost';
                $this->db_name = ltrim($parsed['path'] ?? '', '/') ?: (getenv('DB_NAME') ?: 'clinic_system');
                $this->username = $parsed['user'] ?? getenv('DB_USERNAME') ?: 'root';
                $this->password = $parsed['pass'] ?? getenv('DB_PASSWORD') ?: '';
                $this->port = $parsed['port'] ?? getenv('DB_PORT') ?: null;
                return;
            }
        }

        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'clinic_system';
        $this->username = getenv('DB_USERNAME') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->port = getenv('DB_PORT') ?: null;
    }

    public function getConnection() {
        $this->conn = null;
        $dsn = "mysql:host=" . $this->host;

        if (!empty($this->port)) {
            $dsn .= ";port=" . $this->port;
        }

        $dsn .= ";dbname=" . $this->db_name;

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

function isDoctor() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'doctor';
}

function isPatient() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'patient';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>