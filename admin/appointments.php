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

// Handle status update
if(isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE appointments SET status = :status WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $appointment_id);
    
    if($stmt->execute()) {
        $success = "Appointment status updated successfully!";
    } else {
        $error = "Failed to update status.";
    }
}

// Get filter from URL
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Base query
$query = "SELECT a.*, 
          p.user_id as patient_user_id, 
          pu.full_name as patient_name,
          pu.phone as patient_phone,
          pu.email as patient_email,
          d.user_id as doctor_user_id,
          du.full_name as doctor_name,
          du.email as doctor_email,
          ts.slot_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN users pu ON p.user_id = pu.id
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users du ON d.user_id = du.id
          JOIN time_slots ts ON a.time_slot_id = ts.id";

// Add filters
$conditions = [];
$params = [];

if ($filter_status != 'all') {
    $conditions[] = "a.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_date)) {
    $conditions[] = "a.appointment_date = :date";
    $params[':date'] = $filter_date;
}

// Add WHERE clause if there are conditions
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Add ORDER BY
$query .= " ORDER BY a.appointment_date DESC, ts.slot_time ASC";

// Prepare and execute
$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique statuses for filter dropdown
$status_query = "SELECT DISTINCT status FROM appointments";
$status_stmt = $db->prepare($status_query);
$status_stmt->execute();
$statuses = $status_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - ClinicCare</title>
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
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
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
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            color: #3498db;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #3498db;
        }

        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .filter-group label i {
            color: #3498db;
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
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

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #34495e;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .patient-details {
            line-height: 1.4;
        }

        .patient-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .patient-contact {
            font-size: 12px;
            color: #7f8c8d;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .badge-completed {
            background: #cce5ff;
            color: #004085;
        }

        .badge-arrived {
            background: #cce5ff;
            color: #004085;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-no_show {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
        }

        .stat-card .label {
            color: #7f8c8d;
            font-size: 13px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-sm {
                width: 100%;
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
                <li><a href="appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> Manage Appointments</h1>
                <span class="badge badge-primary">Total: <?php echo count($appointments); ?></span>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Summary Stats -->
            <div class="summary-stats">
                <?php
                $total = count($appointments);
                $pending = 0;
                $confirmed = 0;
                $completed = 0;
                $cancelled = 0;
                
                foreach($appointments as $apt) {
                    switch($apt['status']) {
                        case 'pending': $pending++; break;
                        case 'confirmed': $confirmed++; break;
                        case 'completed': $completed++; break;
                        case 'cancelled': $cancelled++; break;
                    }
                }
                ?>
                <div class="stat-card">
                    <div class="value"><?php echo $total; ?></div>
                    <div class="label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #f39c12;"><?php echo $pending; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #3498db;"><?php echo $confirmed; ?></div>
                    <div class="label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #27ae60;"><?php echo $completed; ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #e74c3c;"><?php echo $cancelled; ?></div>
                    <div class="label">Cancelled</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Filter by Status</label>
                    <select class="form-control" id="statusFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="arrived" <?php echo $filter_status == 'arrived' ? 'selected' : ''; ?>>Arrived</option>
                        <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="no_show" <?php echo $filter_status == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Filter by Date</label>
                    <input type="date" class="form-control" id="dateFilter" value="<?php echo $filter_date; ?>" onchange="applyFilters()">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="appointments.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset Filters
                    </a>
                </div>
            </div>
            
            <!-- Appointments Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($appointments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <i class="fas fa-calendar-times" style="font-size: 48px; color: #bdc3c7; margin-bottom: 10px;"></i>
                                <p style="color: #7f8c8d;">No appointments found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($appointments as $apt): ?>
                            <tr>
                                <td>#<?php echo $apt['id']; ?></td>
                                <td>
                                    <div class="patient-info">
                                        <div class="patient-avatar">
                                            <?php echo substr($apt['patient_name'], 0, 1); ?>
                                        </div>
                                        <div class="patient-details">
                                            <div class="patient-name"><?php echo htmlspecialchars($apt['patient_name']); ?></div>
                                            <div class="patient-contact"><?php echo htmlspecialchars($apt['patient_phone']); ?></div>
                                            <div class="patient-contact"><?php echo htmlspecialchars($apt['patient_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="patient-details">
                                        <div class="patient-name">Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></div>
                                        <div class="patient-contact"><?php echo htmlspecialchars($apt['doctor_email']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;"><?php echo date('h:i A', strtotime($apt['slot_time'])); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars(substr($apt['reason_for_visit'], 0, 30)) . '...'; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view-appointment.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if($apt['status'] == 'pending'): ?>
                                            <a href="update-appointment.php?id=<?php echo $apt['id']; ?>&status=confirmed" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Confirm
                                            </a>
                                            <a href="update-appointment.php?id=<?php echo $apt['id']; ?>&status=cancelled" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this appointment?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php elseif($apt['status'] == 'confirmed'): ?>
                                            <a href="update-appointment.php?id=<?php echo $apt['id']; ?>&status=arrived" class="btn btn-sm btn-warning">
                                                <i class="fas fa-user-check"></i> Arrived
                                            </a>
                                        <?php elseif($apt['status'] == 'arrived'): ?>
                                            <a href="update-appointment.php?id=<?php echo $apt['id']; ?>&status=completed" class="btn btn-sm btn-success">
                                                <i class="fas fa-check-circle"></i> Complete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
    function applyFilters() {
        const status = document.getElementById('statusFilter').value;
        const date = document.getElementById('dateFilter').value;
        
        let url = 'appointments.php?';
        if (status !== 'all') {
            url += 'status=' + status + '&';
        }
        if (date) {
            url += 'date=' + date;
        }
        
        window.location.href = url;
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>