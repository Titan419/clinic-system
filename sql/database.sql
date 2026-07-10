-- Create Database
CREATE DATABASE IF NOT EXISTS clinic_system;
USE clinic_system;

-- Users Table (for all user types)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_type ENUM('patient', 'doctor', 'admin', 'receptionist') DEFAULT 'patient',
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Patients Table (additional patient info)
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    date_of_birth DATE,
    blood_group VARCHAR(5),
    emergency_contact VARCHAR(20),
    medical_history TEXT,
    allergies TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Doctors Table (additional doctor info)
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    specialization VARCHAR(100),
    qualification TEXT,
    experience_years INT,
    consultation_fee DECIMAL(10,2),
    available_days VARCHAR(100), -- e.g., "Monday,Tuesday,Wednesday"
    start_time TIME,
    end_time TIME,
    max_patients_per_day INT DEFAULT 20,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Time Slots Table
CREATE TABLE time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slot_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Appointments Table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    time_slot_id INT NOT NULL,
    reason_for_visit TEXT,
    symptoms TEXT,
    status ENUM('pending', 'confirmed', 'arrived', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
    UNIQUE KEY unique_appointment (doctor_id, appointment_date, time_slot_id)
);

-- Medical Records Table
CREATE TABLE medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    diagnosis TEXT,
    prescription TEXT,
    notes TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
);

-- Notifications Table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT,
    type ENUM('appointment', 'reminder', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default time slots (30-minute intervals)
INSERT INTO time_slots (slot_time) VALUES 
('09:00:00'), ('09:30:00'), ('10:00:00'), ('10:30:00'),
('11:00:00'), ('11:30:00'), ('12:00:00'), ('12:30:00'),
('14:00:00'), ('14:30:00'), ('15:00:00'), ('15:30:00'),
('16:00:00'), ('16:30:00');

-- Insert default admin
INSERT INTO users (username, email, password, full_name, user_type) VALUES 
('admin', 'admin@clinic.com', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'admin');

-- Insert sample doctor
INSERT INTO users (username, email, password, full_name, phone, user_type) VALUES 
('dr.smith', 'dr.smith@clinic.com', '$2y$10$YourHashedPasswordHere', 'Dr. John Smith', '+255712345678', 'doctor');

INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee, available_days, start_time, end_time) 
VALUES (2, 'General Physician', 'MD, MBBS', 10, 50000, 'Monday,Tuesday,Wednesday,Thursday,Friday', '09:00:00', '17:00:00');