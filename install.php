<?php
function getDatabaseConfig() {
    $databaseUrl = getenv('DATABASE_URL');

    if (!empty($databaseUrl)) {
        $parsed = parse_url($databaseUrl);
        if ($parsed !== false) {
            return [
                'host' => $parsed['host'] ?? getenv('DB_HOST') ?: 'localhost',
                'dbname' => ltrim($parsed['path'] ?? '', '/') ?: (getenv('DB_NAME') ?: 'clinic_system'),
                'username' => $parsed['user'] ?? getenv('DB_USERNAME') ?: 'root',
                'password' => $parsed['pass'] ?? getenv('DB_PASSWORD') ?: '',
                'port' => $parsed['port'] ?? getenv('DB_PORT') ?: null,
            ];
        }
    }

    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'clinic_system',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'port' => getenv('DB_PORT') ?: null,
    ];
}

$config = getDatabaseConfig();
$host = $config['host'];
$dbname = $config['dbname'];
$username = $config['username'];
$password = $config['password'];
$port = $config['port'];

try {
    $dsn = "mysql:host=$host";
    if (!empty($port)) {
        $dsn .= ";port=$port";
    }

    // Create connection
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        user_type ENUM('patient', 'doctor', 'admin') DEFAULT 'patient',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE
    )";
    $pdo->exec($sql);
    echo "✅ Users table created<br>";
    
    // Create patients table
    $sql = "CREATE TABLE IF NOT EXISTS patients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT UNIQUE,
        date_of_birth DATE,
        blood_group VARCHAR(5),
        emergency_contact VARCHAR(20),
        medical_history TEXT,
        allergies TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "✅ Patients table created<br>";
    
    // Create doctors table
    $sql = "CREATE TABLE IF NOT EXISTS doctors (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT UNIQUE,
        specialization VARCHAR(100),
        qualification TEXT,
        experience_years INT,
        consultation_fee DECIMAL(10,2),
        available_days VARCHAR(100),
        start_time TIME,
        end_time TIME,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "✅ Doctors table created<br>";
    
    // Create time_slots table
    $sql = "CREATE TABLE IF NOT EXISTS time_slots (
        id INT PRIMARY KEY AUTO_INCREMENT,
        slot_time TIME NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    )";
    $pdo->exec($sql);
    echo "✅ Time slots table created<br>";
    
    // Create appointments table
    $sql = "CREATE TABLE IF NOT EXISTS appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        time_slot_id INT NOT NULL,
        reason_for_visit TEXT,
        symptoms TEXT,
        status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
    )";
    $pdo->exec($sql);
    echo "✅ Appointments table created<br>";
    
    // Insert time slots
    $slots = ['09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '11:30:00', 
              '12:00:00', '12:30:00', '14:00:00', '14:30:00', '15:00:00', '15:30:00',
              '16:00:00', '16:30:00'];
    
    foreach($slots as $slot) {
        $pdo->exec("INSERT INTO time_slots (slot_time) VALUES ('$slot')");
    }
    echo "✅ Time slots inserted<br>";
    
    // Insert admin user (password: admin123)
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password, full_name, user_type) VALUES 
                ('admin@clinic.com', '$admin_password', 'System Administrator', 'admin')");
    echo "✅ Admin user created<br>";
    
    // Insert doctor (password: doctor123)
    $doctor_password = password_hash('doctor123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password, full_name, phone, user_type) VALUES 
                ('dr.smith@clinic.com', '$doctor_password', 'John Smith', '+255712345678', 'doctor')");
    $doctor_id = $pdo->lastInsertId();
    
    $pdo->exec("INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee, available_days, start_time, end_time) 
                VALUES ($doctor_id, 'General Physician', 'MD, MBBS', 10, 50000, 'Monday,Tuesday,Wednesday,Thursday,Friday', '09:00:00', '17:00:00')");
    echo "✅ Doctor user created<br>";
    
    // Insert patient (password: patient123)
    $patient_password = password_hash('patient123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (email, password, full_name, phone, address, user_type) VALUES 
                ('john@example.com', '$patient_password', 'John Doe', '+255712345680', 'Dar es Salaam', 'patient')");
    $patient_id = $pdo->lastInsertId();
    
    $pdo->exec("INSERT INTO patients (user_id, date_of_birth, blood_group, emergency_contact) VALUES 
                ($patient_id, '1990-05-15', 'O+', '+255712345681')");
    echo "✅ Patient user created<br>";
    
    echo "<h2 style='color: green;'>✅ INSTALLATION COMPLETE!</h2>";
    echo "<h3>Login Credentials:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@clinic.com / admin123</li>";
    echo "<li><strong>Doctor:</strong> dr.smith@clinic.com / doctor123</li>";
    echo "<li><strong>Patient:</strong> john@example.com / patient123</li>";
    echo "</ul>";
    echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Homepage</a>";
    
} catch(PDOException $e) {
    echo "<h2 style='color: red;'>❌ Installation Failed!</h2>";
    echo "Error: " . $e->getMessage();
}
?>