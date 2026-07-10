<?php
// Absolute path to config
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * LOGIN METHOD
     */
    public function login($email, $password) {
        try {
            if (!$this->conn) {
                error_log("Database connection failed");
                return false;
            }
            
            $query = "SELECT * FROM users WHERE email = :email AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check password (plain text for demo)
                if($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * REGISTER METHOD - THIS IS WHAT YOU NEED
     */
    public function register($data) {
        try {
            if (!$this->conn) {
                error_log("Database connection failed");
                return false;
            }
            
            // Check if email exists
            $check = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $this->conn->prepare($check);
            $check_stmt->bindParam(':email', $data['email']);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                error_log("Email already exists: " . $data['email']);
                return false;
            }
            
            // Start transaction
            $this->conn->beginTransaction();
            
            // Insert into users table
            $query = "INSERT INTO users (email, password, full_name, phone, address, user_type, is_active) 
                      VALUES (:email, :password, :full_name, :phone, :address, 'patient', 1)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $data['password']); // Plain text for demo
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':phone', $data['phone']);
            $stmt->bindParam(':address', $data['address']);
            $stmt->execute();
            
            $user_id = $this->conn->lastInsertId();
            
            // Insert into patients table
            $query2 = "INSERT INTO patients (user_id, date_of_birth, emergency_contact) 
                       VALUES (:user_id, :dob, :emergency)";
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->bindParam(':user_id', $user_id);
            $stmt2->bindParam(':dob', $data['dob']);
            $stmt2->bindParam(':emergency', $data['emergency_contact']);
            $stmt2->execute();
            
            $this->conn->commit();
            error_log("Registration successful: " . $data['email']);
            return true;
            
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * LOGOUT METHOD
     */
    public function logout() {
        session_destroy();
        return true;
    }
}
?>