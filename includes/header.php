<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClinicCare - <?php echo $page_title ?? 'Healthcare Management'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo">
                <i class="fas fa-clinic-medical"></i>
                <span>Clinic</span>Care
            </div>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li><a href="../<?php echo $_SESSION['user_type']; ?>/dashboard.php">Dashboard</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="../login.php">Login</a></li>
                    <li><a href="../register.php">Register</a></li>
                <?php endif; ?>
            </ul>
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <div class="user-avatar"><?php echo substr($_SESSION['full_name'], 0, 1); ?></div>
                </div>
            <?php endif; ?>
        </nav>
    </header>
    <main class="main-content">