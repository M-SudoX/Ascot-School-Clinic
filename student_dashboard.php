<?php
// ==================== SESSION AT SECURITY ====================
session_start();  // SIMULIN ANG SESSION PARA MA-ACCESS ANG USER DATA
require 'includes/db_connect.php';
require 'includes/activity_logger.php';  // IKONEK SA DATABASE GAMIT ANG PDO

// ✅ SECURITY CHECK: TINITIGNAN KUNG NAKA-LOGIN ANG USER
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");  // KUNG HINDI NAKA-LOGIN, BALIK SA LOGIN PAGE
    exit();  // ITIGIL ANG EXECUTION
}

$student_id = $_SESSION['student_id'];
$student_number = $_SESSION['student_number'] ?? ($_SESSION['student_id'] ?? 'N/A');

// ✅ I-LOG ANG PAG-ACCESS SA DASHBOARD (automatic duplicate prevention na)
logActivity($pdo, $student_id, "Accessed dashboard");

$stmt = $pdo->prepare("SELECT fullname, student_number, course_year, cellphone_number 
                       FROM student_information 
                       WHERE student_number = :student_number LIMIT 1");

$stmt->execute([':student_number' => $student_number]);

$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ ERROR HANDLING: BACKUP SYSTEM KUNG WALANG MAKUHA SA DATABASE
if (!$student_info) {
    // GUMAMIT NG SESSION DATA KUNG WALANG RECORD SA DATABASE
    $student_info = [
        'fullname' => $_SESSION['fullname'] ?? 'N/A',
        'student_number' => $student_number,
        'course_year' => 'Not set',
        'cellphone_number' => 'Not set'
    ];
} else {
    // ✅ UPDATE ANG SESSION DATA PARA CONSISTENT ANG INFORMATION
    $_SESSION['fullname'] = $student_info['fullname'];
    $_SESSION['student_number'] = $student_info['student_number']; // SIGURADUHING NA-SET
}

// Use PDO - Secure database access
// PDO was used to PROTECT the student information

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ASCOT Online School Clinic</title>
    <!-- CSS FILES FOR STYLING -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">  <!-- BOOTSTRAP FRAMEWORK -->
    <link href="assets/webfonts/all.min.css" rel="stylesheet">   <!-- FONT AWESOME ICONS -->
    <link href="assets/css/student_dashboard.css" rel="stylesheet"> <!-- CUSTOM STYLES -->
    <style>
        /* RESPONSIVE STYLES */
        @media (max-width: 768px) {
            /* HEADER RESPONSIVE */
            .header {
                padding: 10px 0;
            }
            .header .logo-img {
                height: 45px !important;
            }
            .header .college-info h4 {
                font-size: 0.8rem !important;
                margin-bottom: 0.1rem !important;
                line-height: 1.2;
            }
            .header .college-info p {
                font-size: 0.75rem !important;
                margin-bottom: 0 !important;
            }
            
            /* MOBILE SIDEBAR - HIDDEN BY DEFAULT */
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                background: linear-gradient(135deg, #2c3e50, #34495e);
                transition: left 0.3s ease-in-out;
                box-shadow: 2px 0 15px rgba(0,0,0,0.3);
                padding-top: 60px;
                overflow-y: auto;
            }
            
            /* SIDEBAR WHEN ACTIVE (OPEN) */
            .sidebar.active {
                left: 0;
            }
            
            /* SIDEBAR OVERLAY */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                z-index: 1040;
                backdrop-filter: blur(2px);
            }
            .sidebar-overlay.active {
                display: block;
            }
            
            /* MOBILE MENU BUTTON */
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1060;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 10px 14px;
                font-size: 1.3rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
            }
            .mobile-menu-btn:hover {
                background: #0056b3;
                transform: scale(1.05);
            }
            
            /* SIDEBAR NAVIGATION STYLES FOR MOBILE */
            .sidebar .nav {
                padding: 0 15px;
            }
            .sidebar .nav-link {
                color: #ecf0f1 !important;
                padding: 15px 20px;
                margin: 5px 0;
                border-radius: 8px;
                transition: all 0.3s ease;
                font-size: 1rem;
                border-left: 4px solid transparent;
            }
            .sidebar .nav-link:hover,
            .sidebar .nav-link.active {
                background: rgba(255,255,255,0.1);
                border-left: 4px solid #3498db;
                transform: translateX(5px);
            }
            .sidebar .nav-link i {
                width: 25px;
                text-align: center;
                margin-right: 10px;
                font-size: 1.1rem;
            }
            
            /* LOGOUT BUTTON IN SIDEBAR */
            .sidebar .logout-btn {
                padding: 0 15px;
                margin-top: 20px;
            }
            .sidebar .logout-btn .nav-link {
                background: rgba(231, 76, 60, 0.2);
                border: 1px solid rgba(231, 76, 60, 0.3);
            }
            .sidebar .logout-btn .nav-link:hover {
                background: rgba(231, 76, 60, 0.3);
                border-left: 4px solid #e74c3c;
            }
            
            /* MAIN CONTENT ADJUSTMENT FOR MOBILE */
            .main-content {
                margin-left: 0 !important;
                padding: 15px !important;
                margin-top: 70px;
            }
            
            /* INFO CARDS RESPONSIVE */
            .info-card, .calendar-widget {
                margin-bottom: 20px;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            
            .info-row {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 15px;
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            .info-row:last-child {
                border-bottom: none;
            }
            .info-label {
                font-weight: bold;
                margin-bottom: 5px;
                font-size: 0.9rem;
                color: #555;
            }
            .info-value {
                font-size: 1rem;
                color: #333;
            }
            
            /* CALENDAR RESPONSIVE */
            .calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 4px;
                font-size: 0.75rem;
            }
            .calendar-header-day {
                font-size: 0.7rem;
                padding: 8px 2px;
                background: #f8f9fa;
                font-weight: bold;
                text-align: center;
            }
            .calendar-day {
                padding: 10px 2px;
                font-size: 0.8rem;
                text-align: center;
                border: 1px solid #e9ecef;
                border-radius: 4px;
                background: white;
            }
            .calendar-day.today {
                background: #007bff;
                color: white;
                font-weight: bold;
            }
            
            /* HEADINGS RESPONSIVE */
            .info-card h3, .calendar-widget h3 {
                font-size: 1.4rem;
                color: #2c3e50;
                margin-bottom: 20px;
                border-bottom: 2px solid #3498db;
                padding-bottom: 10px;
            }
            .calendar-header {
                font-size: 1rem;
                font-weight: bold;
                color: #7f8c8d;
                margin-bottom: 15px;
                text-align: center;
            }
            .next-appointment h6 {
                font-size: 1rem;
                color: #2c3e50;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            /* EXTRA SMALL DEVICES */
            .header .college-info h4 {
                font-size: 0.75rem !important;
            }
            .header .college-info p {
                font-size: 0.7rem !important;
            }
            
            .info-card, .calendar-widget {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .sidebar {
                width: 260px;
                left: -260px;
            }
            
            .mobile-menu-btn {
                top: 12px;
                left: 12px;
                padding: 8px 12px;
                font-size: 1.2rem;
            }
            
            .calendar-grid {
                gap: 2px;
            }
            .calendar-day {
                padding: 8px 1px;
                font-size: 0.75rem;
            }
        }
        
        @media (min-width: 769px) {
            /* DESKTOP STYLES - HIDE MOBILE ELEMENTS */
            .mobile-menu-btn {
                display: none;
            }
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* SMOOTH TRANSITIONS */
        * {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body>
    <!-- MOBILE MENU BUTTON -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- HEADER SECTION - SCHOOL BRANDING -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img"> <!-- SCHOOL LOGO -->
                </div>
                <div class="col">
                    <div class="college-info">
                        <h4>Republic of the Philippines</h4>
                        <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
                        <p>ONLINE SCHOOL CLINIC</p>  <!-- SYSTEM TITLE -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS MESSAGE DISPLAY -->
    <?php if (isset($_SESSION['success'])): ?>
        <div id="successAlert" class="alert alert-soft-success fade show small" role="alert">
            ✅ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>  <!-- IPAKITA AT TANGGALIN ANG SUCCESS MESSAGE -->
        </div>
    <?php endif; ?>

    <!-- MAIN LAYOUT CONTAINER -->
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR NAVIGATION -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <!-- MOBILE SIDEBAR HEADER -->
                <div class="d-block d-md-none text-center mb-4">
                    <img src="img/logo.png" alt="ASCOT Logo" style="height: 50px; margin-bottom: 10px;">
                    <h6 class="text-white">Student Portal</h6>
                    <small class="text-muted"><?php echo htmlspecialchars($student_info['fullname']); ?></small>
                </div>
                
                <nav class="nav flex-column">
                    <!-- NAVIGATION LINKS -->
                    <a class="nav-link active" href="student_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="update_profile.php">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </a>
                    <a class="nav-link" href="schedule_consultation.php">
                        <i class="fas fa-calendar-alt"></i> Schedule Consultation
                    </a>
                    <a class="nav-link" href="student_report.php">
                        <i class="fas fa-chart-bar"></i> Report
                    </a>
                    <a class="nav-link" href="student_announcement.php">
                        <i class="fas fa-bullhorn"></i> Announcement
                    </a>
                    <a class="nav-link" href="activity_logs.php">
                        <i class="fas fa-clipboard-list"></i> Activity Logs
                    </a>
                </nav>
                <!-- LOGOUT BUTTON -->
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- INFORMATION DISPLAY ROW -->
                <div class="row mt-4">
                    <!-- STUDENT INFORMATION CARD -->
                    <div class="col-xl-6 col-lg-12 mb-4">
                        <div class="info-card fade-in">
                            <h3>Student Information</h3>
                            <br>
                            
                            <!-- DISPLAY STUDENT FULL NAME -->
                            <div class="info-row">
                                <span class="info-label">Full Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($student_info['fullname']); ?></span>
                            </div>
                            
                            <!-- DISPLAY STUDENT ID NUMBER -->
                            <div class="info-row">
                                <span class="info-label">ID Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($student_info['student_number']); ?></span>
                            </div>
                            
                            <!-- DISPLAY COURSE AND YEAR -->
                            <div class="info-row">
                                <span class="info-label">Course/Year:</span>
                                <span class="info-value">
                                    <?php 
                                    $course_year = $student_info['course_year'] ?? 'Not set';
                                    echo (empty($course_year) || $course_year === 'Not set') 
                                        ? '<span class="text-warning">Not set</span>'
                                        : htmlspecialchars($course_year);
                                    ?>
                                </span>
                            </div>
                            
                            <!-- DISPLAY CONTACT NUMBER -->
                            <div class="info-row">
                                <span class="info-label">Contact No:</span>
                                <span class="info-value">
                                    <?php 
                                    $contact = $student_info['cellphone_number'] ?? 'Not set';
                                    echo (empty($contact) || $contact === 'Not set') 
                                        ? '<span class="text-warning">Not set</span>'
                                        : htmlspecialchars($contact);
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- CALENDAR WIDGET -->
                    <div class="col-xl-6 col-lg-12 mb-4">
                        <div class="calendar-widget fade-in">
                            <h3>Consultation Schedule Overview</h3>
                            <br>
                            <div class="calendar-header">APPOINTMENT CALENDAR</div>
                            <div class="calendar-grid" id="calendar">
                                <!-- CALENDAR HEADER - DAYS OF WEEK -->
                                <div class="calendar-header-day">Sun</div>
                                <div class="calendar-header-day">Mon</div>
                                <div class="calendar-header-day">Tue</div>
                                <div class="calendar-header-day">Wed</div>
                                <div class="calendar-header-day">Thu</div>
                                <div class="calendar-header-day">Fri</div>
                                <div class="calendar-header-day">Sat</div>
                                
                                <!-- DYNAMIC CALENDAR DAYS GENERATION -->
                                <?php 
                                $days_in_month = date('t');
                                for ($i = 1; $i <= $days_in_month; $i++): 
                                ?>
                                    <div class="calendar-day <?php echo $i == date('j') ? 'today' : ''; ?>">
                                        <?php echo $i; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                             <br>
                            <div class="next-appointment">
                                <h6>Next Upcoming Appointment</h6>
                                <div class="appointment-date">No upcoming appointments</div>
                                <div class="appointment-time">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT FILES -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.getElementById("successAlert");
            if (alertBox) {
                setTimeout(() => {
                    alertBox.classList.remove("show");
                    setTimeout(() => alertBox.remove(), 300);
                }, 3000);
            }

            // MOBILE SIDEBAR FUNCTIONALITY
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            if (mobileMenuBtn && sidebar && sidebarOverlay) {
                // TOGGLE SIDEBAR ON BUTTON CLICK
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                
                // CLOSE SIDEBAR WHEN OVERLAY IS CLICKED
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                // CLOSE SIDEBAR WHEN NAV LINK IS CLICKED (ON MOBILE)
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 768) {
                            closeSidebar();
                        }
                    });
                });
                
                // CLOSE SIDEBAR ON ESC KEY PRESS
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                });
            }
            
            // AUTO-CLOSE SIDEBAR ON WINDOW RESIZE (if resized to desktop)
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });
            
            // SWIPE GESTURE SUPPORT FOR MOBILE
            let touchStartX = 0;
            let touchEndX = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                const swipeThreshold = 50;
                const swipeDistance = touchEndX - touchStartX;
                
                // SWIPE RIGHT TO OPEN SIDEBAR
                if (swipeDistance > swipeThreshold && window.innerWidth <= 768) {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
                // SWIPE LEFT TO CLOSE SIDEBAR
                else if (swipeDistance < -swipeThreshold && window.innerWidth <= 768) {
                    closeSidebar();
                }
            }
        });
    </script>
</body>
</html>