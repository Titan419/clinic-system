<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Fetch doctors for display
$doctors = [];
if ($db) {
    try {
        $query = "SELECT u.id, u.full_name, d.specialization, d.qualification, d.experience_years 
                  FROM doctors d 
                  JOIN users u ON d.user_id = u.id 
                  WHERE u.is_active = 1
                  LIMIT 4";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching doctors: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClinicCare - Healthcare Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --light-bg: #ecf0f1;
            --dark-text: #2c3e50;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--dark-text); line-height: 1.6; }
        .header { background: white; box-shadow: var(--box-shadow); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1400px; margin: 0 auto; }
        .logo { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); }
        .logo span { color: var(--secondary-color); }
        .nav-links { display: flex; gap: 0.5rem; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--dark-text); font-weight: 500; padding: 8px 16px; border-radius: var(--border-radius); transition: all 0.3s; }
        .nav-links a:hover { background: var(--secondary-color); color: white; }
        .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.3s; margin: 0 5px; }
        .btn-primary { background: var(--secondary-color); color: white; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(52,152,219,0.3); }
        .btn-success { background: var(--success-color); color: white; }
        .btn-success:hover { background: #219a52; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(39,174,96,0.3); }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 600px; display: flex; align-items: center; text-align: center; color: white; padding: 80px 20px; margin-top: 70px; }
        .hero-content { max-width: 800px; margin: 0 auto; }
        .hero h1 { font-size: 3rem; margin-bottom: 20px; animation: fadeInUp 1s ease; }
        .hero p { font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9; animation: fadeInUp 1s ease 0.2s both; }
        .hero-buttons { display: flex; gap: 20px; justify-content: center; animation: fadeInUp 1s ease 0.4s both; }
        .hero-buttons .btn { min-width: 150px; padding: 15px 30px; font-size: 16px; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .section-title { text-align: center; font-size: 2.5rem; margin-bottom: 50px; color: var(--primary-color); position: relative; padding-bottom: 20px; }
        .section-title::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 100px; height: 3px; background: var(--secondary-color); }
        .services { padding: 80px 0; background: #f8f9fa; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 30px; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .service-card { background: white; padding: 40px 30px; border-radius: var(--border-radius); text-align: center; box-shadow: var(--box-shadow); transition: transform 0.3s; }
        .service-card:hover { transform: translateY(-10px); }
        .service-card i { font-size: 3rem; color: var(--secondary-color); margin-bottom: 20px; }
        .service-card h3 { margin-bottom: 15px; color: var(--primary-color); }
        .service-card p { color: #666; margin-bottom: 20px; }
        .doctors { padding: 80px 0; }
        .doctors-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap: 30px; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .doctor-card { background: white; border-radius: var(--border-radius); padding: 30px; text-align: center; box-shadow: var(--box-shadow); transition: all 0.3s; }
        .doctor-card:hover { transform: translateY(-10px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .doctor-avatar { width: 120px; height: 120px; border-radius: 50%; background: var(--secondary-color); display: flex; align-items: center; justify-content: center; font-size: 48px; color: white; margin: 0 auto 20px; border: 5px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .doctor-card h4 { font-size: 1.3rem; margin-bottom: 10px; color: var(--primary-color); }
        .specialty { color: var(--secondary-color); font-weight: 600; margin-bottom: 10px; }
        .experience { color: #666; font-size: 0.9rem; margin-bottom: 20px; }
        .footer { background: var(--primary-color); color: white; padding: 60px 0 20px; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 40px; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .footer-section h3, .footer-section h4 { margin-bottom: 20px; color: white; }
        .footer-section p { opacity: 0.8; line-height: 1.6; }
        .footer-section ul { list-style: none; padding: 0; }
        .footer-section ul li { margin-bottom: 10px; }
        .footer-section ul li a { color: white; opacity: 0.8; text-decoration: none; transition: opacity 0.3s; }
        .footer-section ul li a:hover { opacity: 1; }
        .footer-section i { margin-right: 10px; color: var(--secondary-color); }
        .footer-bottom { text-align: center; padding-top: 40px; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); opacity: 0.6; }
        .auth-buttons { display: flex; gap: 10px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--secondary-color); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        @media (max-width: 768px) { .navbar { flex-direction: column; gap: 15px; } .nav-links { flex-wrap: wrap; justify-content: center; } .hero h1 { font-size: 2rem; } .hero-buttons { flex-direction: column; align-items: center; } .hero-buttons .btn { width: 100%; } }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo"><i class="fas fa-clinic-medical"></i> <span>Clinic</span>Care</div>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#doctors">Doctors</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="user-info">
                        <span><?php echo $_SESSION['full_name']; ?></span>
                        <div class="user-avatar"><?php echo substr($_SESSION['full_name'], 0, 1); ?></div>
                        <a href="logout.php" class="btn btn-primary">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-success">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <section id="home" class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Your Health, Our Priority</h1>
                <p>Book appointments with top doctors instantly. Manage your health records online.</p>
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-primary">Get Started <i class="fas fa-arrow-right"></i></a>
                    <a href="#services" class="btn btn-success">Learn More <i class="fas fa-info-circle"></i></a>
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="services">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="services-grid">
                <div class="service-card"><i class="fas fa-calendar-check"></i><h3>Online Appointment</h3><p>Book appointments 24/7</p><a href="register.php" class="btn btn-primary">Book Now</a></div>
                <div class="service-card"><i class="fas fa-user-md"></i><h3>Expert Doctors</h3><p>Qualified professionals</p><a href="#doctors" class="btn btn-primary">Meet Doctors</a></div>
                <div class="service-card"><i class="fas fa-file-medical"></i><h3>Medical Records</h3><p>Access your history</p><a href="register.php" class="btn btn-primary">View Records</a></div>
                <div class="service-card"><i class="fas fa-clock"></i><h3>24/7 Support</h3><p>Round-the-clock care</p><a href="#contact" class="btn btn-primary">Contact Us</a></div>
            </div>
        </div>
    </section>

    <section id="doctors" class="doctors">
        <div class="container">
            <h2 class="section-title">Our Expert Doctors</h2>
            <div class="doctors-grid">
                <?php if(!empty($doctors)): ?>
                    <?php foreach($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="doctor-avatar"><?php echo substr($doctor['full_name'], 0, 1); ?></div>
                        <h4>Dr. <?php echo $doctor['full_name']; ?></h4>
                        <p class="specialty"><?php echo $doctor['specialization']; ?></p>
                        <p class="experience"><?php echo $doctor['experience_years']; ?> years experience</p>
                        <a href="register.php" class="btn btn-primary">Book Appointment</a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="doctor-card">
                        <div class="doctor-avatar">J</div>
                        <h4>Dr. John Smith</h4>
                        <p class="specialty">General Physician</p>
                        <p class="experience">10 years experience</p>
                        <a href="register.php" class="btn btn-primary">Book Appointment</a>
                    </div>
                    <div class="doctor-card">
                        <div class="doctor-avatar">S</div>
                        <h4>Dr. Sarah Jones</h4>
                        <p class="specialty">Pediatrician</p>
                        <p class="experience">8 years experience</p>
                        <a href="register.php" class="btn btn-primary">Book Appointment</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="contact" class="services" style="background: white;">
        <div class="container">
            <h2 class="section-title">Contact Us</h2>
            <div style="max-width: 600px; margin: 0 auto;">
                <form onsubmit="event.preventDefault(); alert('Thank you! We will contact you soon.');">
                    <div class="form-group"><input type="text" class="form-control" placeholder="Your Name" required></div>
                    <div class="form-group"><input type="email" class="form-control" placeholder="Your Email" required></div>
                    <div class="form-group"><textarea class="form-control" rows="5" placeholder="Your Message" required></textarea></div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section"><h3><i class="fas fa-clinic-medical"></i> ClinicCare</h3><p>Modern healthcare management system.</p></div>
                <div class="footer-section"><h4>Quick Links</h4><ul><li><a href="#home">Home</a></li><li><a href="#services">Services</a></li><li><a href="#doctors">Doctors</a></li></ul></div>
                <div class="footer-section"><h4>Contact</h4><p><i class="fas fa-phone"></i> +255 123 456 789</p><p><i class="fas fa-envelope"></i> info@cliniccare.com</p></div>
            </div>
            <div class="footer-bottom"><p>&copy; 2024 ClinicCare. All rights reserved.</p></div>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if(target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });
    </script>
</body>
</html>