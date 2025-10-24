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

// ✅ FETCH UPCOMING APPOINTMENTS
try {
    $appointment_stmt = $pdo->prepare("
        SELECT date, time, requested, status 
        FROM consultation_requests 
        WHERE student_id = ? AND date >= CURDATE() AND status IN ('Pending', 'Approved')
        ORDER BY date ASC, time ASC 
        LIMIT 3
    ");
    $appointment_stmt->execute([$student_id]);
    $upcoming_appointments = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $upcoming_appointments = [];
}

// ✅ FETCH APPOINTMENTS FOR CALENDAR
try {
    $calendar_stmt = $pdo->prepare("
        SELECT date, time, requested, status 
        FROM consultation_requests 
        WHERE student_id = ? AND status IN ('Pending', 'Approved', 'Completed')
        ORDER BY date ASC
    ");
    $calendar_stmt->execute([$student_id]);
    $calendar_appointments = $calendar_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $calendar_appointments = [];
}

// ✅ FETCH RECENT ACTIVITIES - SAME FILTER AS ACTIVITY_LOGS.PHP
try {
    $activity_stmt = $pdo->prepare("
        SELECT action, log_date 
        FROM activity_logs 
        WHERE student_id = ?
        AND action NOT LIKE '%viewed%' 
        AND action NOT LIKE '%accessed%' 
        AND action NOT LIKE '%logged in%' 
        AND action NOT LIKE '%logged out%'
        ORDER BY log_date DESC 
        LIMIT 5
    ");
    $activity_stmt->execute([$student_id]);
    $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_activities = [];
}

// Use PDO - Secure database access
// PDO was used to PROTECT the student information

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ASCOT Clinic</title>
    
    <!-- Bootstrap -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 80px;
            line-height: 1.6;
        }

        /* Header Styles - IMPROVED */
        .top-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 0.75rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 80px;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }

        .logo-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Mobile Menu Toggle - COMPLETELY FIXED POSITION */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 95px; /* MAS MALAYO SA HEADER */
            left: 20px; /* MAS MALAYO SA GILID */
            z-index: 1025;
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        /* Dashboard Container - IMPROVED */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - IMPROVED */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1020;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.25rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
        }

        .nav-item span {
            flex: 1;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }

        .nav-item .arrow.rotate {
            transform: rotate(180deg);
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #f8f9fa;
        }

        .submenu.show {
            max-height: 500px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1.25rem 0.7rem 3.25rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: var(--primary);
        }

        .submenu-item i {
            width: 18px;
            margin-right: 0.7rem;
            font-size: 0.9rem;
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - IMPROVED */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - IMPROVED */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1019;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Welcome Section - IMPROVED */
        .welcome-section {
            background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(102,126,234,0.2);
            border-left: 5px solid var(--primary);
        }

        .welcome-content h1 {
            color: var(--primary);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Dashboard Grid - IMPROVED */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title {
            color: var(--primary);
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            background: var(--primary);
            transition: all 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.1);
        }

        /* INFO CARD STYLES - IMPROVED */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
            display: flex;
            align-items: center;
        }

        .info-label::before {
            content: '•';
            color: var(--primary);
            margin-right: 0.5rem;
            font-weight: bold;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark);
            text-align: right;
        }

        /* APPOINTMENTS CARD - IMPROVED */
        .appointment-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-date {
            background: var(--primary);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            min-width: 70px;
        }

        .appointment-date .day {
            font-size: 1.3rem;
            font-weight: 700;
            display: block;
            line-height: 1;
        }

        .appointment-date .month {
            font-size: 0.8rem;
            font-weight: 600;
            display: block;
        }

        .appointment-details {
            flex: 1;
        }

        .appointment-details h6 {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-weight: 600;
        }

        .appointment-details p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .appointment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
            display: block;
        }

        /* ACTIVITIES CARD - IMPROVED */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            background: var(--primary);
            flex-shrink: 0;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details p {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-weight: 500;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* QUICK ACTIONS - IMPROVED */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .action-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        /* Responsive Design - COMPLETELY FIXED MOBILE SPACING */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 992px) {
            .school-name {
                font-size: 1rem;
            }

            .logo-img {
                width: 50px;
                height: 50px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .top-header {
                height: 70px;
                padding: 0.5rem 0;
            }
            
            .mobile-menu-toggle {
                display: block;
                top: 85px; /* MAS MALAYO SA HEADER */
                left: 20px; /* MAS MALAYO SA GILID */
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
                z-index: 1020;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                top: 70px;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 2rem 1.25rem 1.25rem; /* MAS MALAKING PADDING SA ITAAS */
                width: 100%;
                margin-left: 0;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                margin-top: 1.5rem; /* MAS MALAKING SPACE SA ITAAS NG MGA BUTTON */
            }

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.9rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .dashboard-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .action-btn {
                padding: 1rem 1.25rem;
                font-size: 0.9rem;
            }

            .dashboard-card {
                padding: 1.25rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-content h1 {
                font-size: 1.5rem;
            }

            .main-content {
                padding: 1.75rem 1rem 1rem; /* ADJUSTED PADDING */
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img {
                width: 40px;
                height: 40px;
            }
            
            .school-name {
                font-size: 0.8rem;
            }
            
            .republic, .clinic-title {
                font-size: 0.6rem;
            }
            
            .mobile-menu-toggle {
                width: 45px;
                height: 45px;
                top: 80px;
                left: 15px;
            }
            
            .main-content {
                padding: 1.5rem 1rem 1rem;
            }
        }

        @media (max-width: 375px) {
            .mobile-menu-toggle {
                top: 75px;
                left: 15px;
                width: 40px;
                height: 40px;
            }
            
            .main-content {
                padding: 1.25rem 0.75rem 0.75rem;
            }
        }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        .stagger-animation > * {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - COMPLETELY FIXED POSITION -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - IMPROVED -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - IMPROVED (INALIS NA ANG WELCOME MESSAGE SA RIGHT) -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
                <!-- INALIS NA ANG WELCOME MESSAGE AT STUDENT NAME SA RIGHT SIDE -->
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Sidebar - IMPROVED -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="student_dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <a href="update_profile.php" class="nav-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>

                <a href="schedule_consultation.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule Consultation</span>
                </a>

                <a href="student_report.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Report</span>
                </a>

                <a href="student_announcement.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcement</span>
                </a>

                <a href="activity_logs.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Activity Logs</span>
                </a>
                
                <a href="logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- WELCOME SECTION -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $student_info['fullname'])[0]); ?>! 👋</h1>
                    <p>Here's what's happening with your health consultations today</p>
                </div>
            </div>

            <!-- DASHBOARD GRID -->
            <div class="dashboard-grid">
                <!-- STUDENT INFORMATION CARD -->
                <div class="dashboard-card info-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Student Information</h3>
                        <div class="card-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    
                    <div class="stagger-animation">
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
                                    ? '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Not set</span>'
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
                                    ? '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Not set</span>'
                                    : htmlspecialchars($contact);
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="quick-actions">
                        <a href="update_profile.php" class="action-btn">
                            <i class="fas fa-edit"></i> Update Profile
                        </a>
                    </div>
                </div>

                <!-- UPCOMING APPOINTMENTS CARD -->
                <div class="dashboard-card appointments-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Appointments</h3>
                        <div class="card-icon" id="calendarIcon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="stagger-animation">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="appointment-date">
                                        <span class="day"><?php echo date('d', strtotime($appointment['date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($appointment['date'])); ?></span>
                                    </div>
                                    <div class="appointment-details">
                                        <h6><?php echo htmlspecialchars($appointment['requested']); ?></h6>
                                        <p><i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($appointment['time'])); ?></p>
                                    </div>
                                    <span class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <p>No upcoming appointments</p>
                            <small>Schedule your first consultation</small>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <a href="schedule_consultation.php" class="action-btn">
                            <i class="fas fa-plus"></i> New Appointment
                        </a>
                    </div>
                </div>

                <!-- RECENT ACTIVITIES CARD -->
                <div class="dashboard-card activities-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activities</h3>
                        <div class="card-icon">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($recent_activities)): ?>
                        <div class="stagger-animation">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo htmlspecialchars($activity['action']); ?></p>
                                        <span class="activity-time">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($activity['log_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                            <small>Your activities will appear here</small>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <a href="activity_logs.php" class="action-btn">
                            <i class="fas fa-list"></i> View All Activities
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // MOBILE MENU FUNCTIONALITY - IMPROVED
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
            });

            // Close sidebar when clicking nav items on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                    });
                });
            }

            // LOADING ANIMATIONS
            const staggerElements = document.querySelectorAll('.stagger-animation > *');
            staggerElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
            });
        });
    </script>
</body>
</html>
