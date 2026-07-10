<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get patient details
$query = "SELECT u.*, p.id as patient_record_id, p.date_of_birth, p.blood_group, 
          p.emergency_contact, p.medical_history, p.allergies
          FROM users u
          JOIN patients p ON u.id = p.user_id
          WHERE u.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $patient_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    $blood_group = $_POST['blood_group'];
    $emergency_contact = trim($_POST['emergency_contact']);
    $medical_history = trim($_POST['medical_history']);
    $allergies = trim($_POST['allergies']);
    
    try {
        $db->beginTransaction();
        
        // Update users table
        $user_query = "UPDATE users SET full_name = :full_name, phone = :phone, 
                       address = :address WHERE id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':full_name', $full_name);
        $user_stmt->bindParam(':phone', $phone);
        $user_stmt->bindParam(':address', $address);
        $user_stmt->bindParam(':id', $patient_id);
        $user_stmt->execute();
        
        // Update patients table
        $patient_query = "UPDATE patients SET date_of_birth = :date_of_birth, 
                          blood_group = :blood_group, emergency_contact = :emergency_contact,
                          medical_history = :medical_history, allergies = :allergies
                          WHERE user_id = :user_id";
        $patient_stmt = $db->prepare($patient_query);
        $patient_stmt->bindParam(':date_of_birth', $date_of_birth);
        $patient_stmt->bindParam(':blood_group', $blood_group);
        $patient_stmt->bindParam(':emergency_contact', $emergency_contact);
        $patient_stmt->bindParam(':medical_history', $medical_history);
        $patient_stmt->bindParam(':allergies', $allergies);
        $patient_stmt->bindParam(':user_id', $patient_id);
        $patient_stmt->execute();
        
        $db->commit();
        $success = "Patient updated successfully!";
        
        // Refresh patient data
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - ClinicCare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #2c3e50 0%, #1e2b37 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .sidebar-header h3 i {
            color: #3498db;
            margin-right: 10px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-size: 14px;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            font-size: 1.1rem;
            color: #3498db;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: #f5f7fb;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #3498db;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52,152,219,0.3);
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label i {
            color: #3498db;
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-control[readonly] {
            background: #e9ecef;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-clinic-medical"></i> ClinicCare</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logs.php"><i class="fas fa-history"></i> System Logs</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            <div style="position: absolute; bottom: 20px; left: 20px; color: rgba(255,255,255,0.5); font-size: 12px;">
                <i class="fas fa-user-shield"></i> Admin: <?php echo $_SESSION['full_name']; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>
                    <i class="fas fa-user-edit"></i>
                    Edit Patient
                </h1>
                <div>
                    <a href="view-patient.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($patient['email']); ?>" readonly disabled>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo $patient['date_of_birth']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tint"></i> Blood Group</label>
                            <select name="blood_group" class="form-control">
                                <option value="">Select Blood Group</option>
                                <?php
                                $groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach($groups as $group) {
                                    $selected = ($patient['blood_group'] == $group) ? 'selected' : '';
                                    echo "<option value='$group' $selected>$group</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone-alt"></i> Emergency Contact</label>
                            <input type="text" name="emergency_contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" class="form-control"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-notes-medical"></i> Medical History</label>
                        <textarea name="medical_history" class="form-control" placeholder="Any previous medical conditions, surgeries, etc."><?php echo htmlspecialchars($patient['medical_history']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-allergies"></i> Allergies</label>
                        <textarea name="allergies" class="form-control" placeholder="Any known allergies"><?php echo htmlspecialchars($patient['allergies']); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary" onclick="return confirm('Reset all changes?')">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Patient
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>