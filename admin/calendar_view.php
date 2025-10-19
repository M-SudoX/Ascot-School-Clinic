<?php
session_start();
require '../includes/db_connect.php';

// ✅ Only logged-in admin can access
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ✅ FETCH APPROVED CONSULTATION REQUESTS
$query = "
SELECT 
    c.id,
    u.fullname AS student,
    u.student_number AS studentId,
    u.email AS contact,
    c.requested AS purpose,
    DATE_FORMAT(c.date, '%e') AS day,
    DATE_FORMAT(c.date, '%c') AS month,
    DATE_FORMAT(c.date, '%Y') AS year,
    TIME_FORMAT(c.time, '%h:%i %p') AS time,
    c.status
FROM consultation_requests c
LEFT JOIN users u ON c.student_id = u.id
WHERE c.status IN ('Approved', 'Rescheduled')
ORDER BY c.date, c.time ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar View - ASCOT Clinic</title>

    <!-- BOOTSTRAP / FONT AWESOME -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 100px; /* Added for fixed header */
        }

        /* Header Styles - FIXED */
        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed; /* Added */
            top: 0; /* Added */
            left: 0; /* Added */
            right: 0; /* Added */
            z-index: 1000; /* Added */
            height: 100px; /* Added */
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .school-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0.2rem 0;
        }

        .clinic-title {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Mobile Menu Toggle - FIXED */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 100px; /* Adjusted for fixed header */
            left: 20px;
            z-index: 1001;
            background: #667eea;
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
            transform: scale(1.1);
            background: #764ba2;
        }

        /* Dashboard Container - FIXED */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        /* Sidebar Styles - FIXED */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 0;
            transition: transform 0.3s ease;
            position: fixed; /* Added */
            top: 100px; /* Added */
            left: 0; /* Added */
            bottom: 0; /* Added */
            overflow-y: auto; /* Added */
            z-index: 999; /* Added */
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%; /* Added */
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #667eea;
            border-left: 4px solid #667eea;
        }

        .nav-item i {
            width: 25px;
            margin-right: 1rem;
        }

        .nav-item span {
            flex: 1;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
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
            padding: 0.75rem 1.5rem 0.75rem 3.5rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: #667eea;
        }

        .submenu-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        .nav-item.logout {
            color: #dc3545;
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - FIXED */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            background: linear-gradient(135deg, #ffda6a 0%, #764ba2 100%);
            min-height: calc(100vh - 100px);
            margin-left: 280px; /* Added for sidebar space */
            margin-top: 0; /* Added */
        }

        /* Sidebar Overlay for Mobile - FIXED */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 100px; /* Adjusted for fixed header */
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Calendar Container - Glass Effect */
        .calendar-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        
        /* Calendar Header - Gradient Design */
        .calendar-header {
            background: linear-gradient(135deg, #ffda6a 0%, #fff7da 100%);
            color: white;
            border-radius: 15px;
            padding: 30px 40px;
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 
                0 15px 35px rgba(102, 126, 234, 0.4),
                0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            color: #333;
        }
        
        .calendar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .calendar-header h3 {
            margin: 0;
            font-weight: 800;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .calendar-header h3 i {
            font-size: 2.2rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        /* Month Navigation */
        .calendar-controls {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        
        .calendar-controls button {
            background: rgba(255, 255, 255, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .calendar-controls button:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: scale(1.15) translateY(-2px);
            border-color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }
        
        .calendar-controls button:active {
            transform: scale(1.05);
        }
        
        #monthDisplay {
            font-size: 1.5rem;
            font-weight: 700;
            min-width: 220px;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            color: #333;
        }

        /* Calendar Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px;
            margin-top: 25px;
        }
        
        /* Day Headers - Modern Style */
        .calendar-day-header {
            text-align: center;
            font-weight: 800;
            color: #667eea;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            padding: 18px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 14px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 2px solid rgba(102, 126, 234, 0.1);
        }

        /* Day Cells - Premium Design */
        .calendar-day {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 2px solid #e9ecef;
            border-radius: 18px;
            min-height: 130px;
            padding: 18px;
            position: relative;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
        .calendar-day::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .calendar-day:hover::before {
            transform: scaleX(1);
        }
        
        .calendar-day:hover {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            transform: translateY(-8px) scale(1.03);
            box-shadow: 
                0 20px 40px rgba(102, 126, 234, 0.25),
                0 0 0 3px rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }
        
        /* Today Indicator - Stunning Effect */
        .calendar-day.today {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #764ba2;
            box-shadow: 
                0 15px 40px rgba(102, 126, 234, 0.6),
                0 0 0 4px rgba(255, 255, 255, 0.5),
                0 0 30px rgba(102, 126, 234, 0.4) inset;
            transform: scale(1.08);
            animation: todayPulse 3s ease-in-out infinite;
        }
        
        @keyframes todayPulse {
            0%, 100% {
                box-shadow: 
                    0 15px 40px rgba(102, 126, 234, 0.6),
                    0 0 0 4px rgba(255, 255, 255, 0.5),
                    0 0 30px rgba(102, 126, 234, 0.4) inset;
            }
            50% {
                box-shadow: 
                    0 20px 50px rgba(102, 126, 234, 0.8),
                    0 0 0 6px rgba(255, 255, 255, 0.7),
                    0 0 40px rgba(102, 126, 234, 0.6) inset;
            }
        }
        
        .calendar-day.today:hover {
            transform: translateY(-8px) scale(1.1);
            box-shadow: 
                0 25px 60px rgba(102, 126, 234, 0.8),
                0 0 0 5px rgba(255, 255, 255, 0.6);
        }
        
        .calendar-day.today::before {
            display: none;
        }
        
        /* Empty Days */
        .calendar-day.text-muted {
            opacity: 0.25;
            cursor: default;
            pointer-events: none;
            background: #f8f9fa;
        }
        
        /* Day Number */
        .calendar-day > div:first-child {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        /* Appointment Badge - Eye-Catching */
        .appointment-count {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 6px 16px rgba(239, 68, 68, 0.5),
                0 0 0 3px rgba(255, 255, 255, 0.5);
            animation: bounce 2s infinite;
            z-index: 2;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: scale(1) translateY(0);
            }
            50% {
                transform: scale(1.15) translateY(-4px);
            }
        }

        /* Modal - Premium Design */
        .modal-content {
            border-radius: 24px;
            border: none;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 35px;
            border: none;
        }
        
        .modal-header .modal-title {
            font-size: 1.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .modal-body {
            padding: 35px;
            background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%);
        }
        
        .modal-body h5 {
            font-weight: 800;
            margin-bottom: 30px;
            padding-bottom: 18px;
            border-bottom: 4px solid;
            border-image: linear-gradient(90deg, #667eea, #764ba2) 1;
            font-size: 1.4rem;
        }

        /* Appointment Items - Elegant Cards */
        .appointment-item {
            background: white;
            border-left: 6px solid #667eea;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 18px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .appointment-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: linear-gradient(180deg, #667eea, #764ba2);
            transition: width 0.4s ease;
        }
        
        .appointment-item:hover::before {
            width: 12px;
        }
        
        .appointment-item:hover {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            transform: translateX(12px) scale(1.02);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.25);
        }
        
        .appointment-item strong {
            color: #667eea;
            font-size: 1.25rem;
            display: block;
            margin-bottom: 15px;
            font-weight: 800;
        }
        
        .appointment-item i {
            color: #764ba2;
            margin-right: 10px;
            width: 22px;
            font-size: 1rem;
        }
        
        .appointment-item > *:not(:last-child) {
            margin-bottom: 10px;
        }
        
        .appointment-item div {
            font-size: 1rem;
            color: #4b5563;
            line-height: 1.6;
        }
        
        /* No Appointments Message */
        .no-appointments {
            text-align: center;
            color: #9ca3af;
            padding: 60px 30px;
            font-size: 1.2rem;
        }
        
        .no-appointments i {
            font-size: 5rem;
            color: #d1d5db;
            margin-bottom: 25px;
            display: block;
            opacity: 0.5;
        }
        
        /* Responsive Design - FIXED */
        @media (max-width: 1024px) {
            .calendar-container {
                padding: 30px;
            }
            
            .calendar-grid {
                gap: 10px;
            }
            
            .calendar-day {
                min-height: 100px;
                padding: 12px;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 100px; /* Adjusted for fixed header */
                height: calc(100vh - 100px); /* Adjusted for fixed header */
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px; /* Added */
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 1rem;
                width: 100%;
                margin-left: 0; /* Reset margin for mobile */
            }

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.85rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .calendar-container {
                padding: 20px;
                border-radius: 20px;
            }
            
            .calendar-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 25px;
            }
            
            .calendar-header h3 {
                font-size: 1.5rem;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 10px;
            }
            
            .calendar-day > div:first-child {
                font-size: 1.2rem;
            }
            
            .appointment-count {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .calendar-grid {
                gap: 8px;
            }
            
            .calendar-day {
                min-height: 60px;
                padding: 8px;
            }
            
            .calendar-day > div:first-child {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - FIXED -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - FIXED -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- HEADER - FIXED -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <!-- DASHBOARD CONTAINER - FIXED -->
    <div class="dashboard-container">
        <!-- SIDEBAR - FIXED -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="studentMenu">
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="search_students.php" class="submenu-item">
                            <i class="fas fa-search"></i>
                            Search Students
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn active" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="appointmentsMenu">
                        <a href="calendar_view.php" class="submenu-item active">
                            <i class="fas fa-calendar-alt"></i>
                            Calendar View
                        </a>
                        <a href="approvals.php" class="submenu-item">
                            <i class="fas fa-check-circle"></i>
                            Approvals
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="reportsMenu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="reportsMenu">
                        <a href="monthly_summary.php" class="submenu-item">
                            <i class="fas fa-file-invoice"></i>
                            Monthly Summary
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="adminMenu">
                        <a href="user_management.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            User Management
                        </a>
                        <a href="access_logs.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Access Logs
                        </a>
                    </div>
                </div>

                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="calendar-container">
                <div class="calendar-header">
                    <h3><i class="fas fa-calendar-alt"></i> Appointment Calendar</h3>
                    <div class="calendar-controls">
                        <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                        <span id="monthDisplay"></span>
                        <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>

                <div class="calendar-grid">
                    <div class="calendar-day-header">Mon</div>
                    <div class="calendar-day-header">Tue</div>
                    <div class="calendar-day-header">Wed</div>
                    <div class="calendar-day-header">Thu</div>
                    <div class="calendar-day-header">Fri</div>
                    <div class="calendar-day-header">Sat</div>
                    <div class="calendar-day-header">Sun</div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL -->
    <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Appointments</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="appointmentsList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // MOBILE MENU FUNCTIONALITY - FIXED
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

        // Close sidebar when clicking submenu items on mobile
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.submenu-item').forEach(item => {
                item.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                });
            });
        }

        // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS - FIXED
        document.querySelectorAll('.dropdown-btn').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const submenu = document.getElementById(targetId);
                const arrow = this.querySelector('.arrow');

                document.querySelectorAll('.submenu').forEach(menu => {
                    if (menu.id !== targetId && menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                        if (otherBtn) {
                            otherBtn.querySelector('.arrow').classList.remove('rotate');
                        }
                    }
                });

                submenu.classList.toggle('show');
                arrow.classList.toggle('rotate');
            });
        });

        // CALENDAR FUNCTIONALITY
        const appointments = <?php echo json_encode($appointments); ?>;
        const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        function renderCalendar(month, year) {
            const grid = document.querySelector('.calendar-grid');
            while (grid.children.length > 7) grid.removeChild(grid.lastChild);

            document.getElementById('monthDisplay').textContent = `${monthNames[month]} ${year}`;
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const adjustedFirst = firstDay === 0 ? 6 : firstDay - 1;
            const today = new Date();

            for (let i = 0; i < adjustedFirst; i++) {
                const d = document.createElement('div');
                d.className = 'calendar-day text-muted';
                grid.appendChild(d);
            }

            for (let i = 1; i <= daysInMonth; i++) {
                const d = document.createElement('div');
                d.className = 'calendar-day';
                if (month === today.getMonth() && year === today.getFullYear() && i === today.getDate()) {
                    d.classList.add('today');
                }
                d.innerHTML = `<div>${i}</div>`;

                const dayApps = appointments.filter(a => a.day == i && a.month == month + 1 && a.year == year);
                if (dayApps.length > 0) {
                    const badge = document.createElement('div');
                    badge.className = 'appointment-count';
                    badge.textContent = dayApps.length;
                    d.appendChild(badge);
                    d.addEventListener('click', () => showAppointments(dayApps, i, month, year));
                }
                grid.appendChild(d);
            }
        }

        function showAppointments(dayApps, day, month, year) {
            const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
            const list = document.getElementById('appointmentsList');
            list.innerHTML = `<h5>${monthNames[month]} ${day}, ${year}</h5>`;
            if (dayApps.length === 0) {
                list.innerHTML += `<div class="no-appointments"><i class="fas fa-calendar-times"></i><br>No appointments scheduled for this day</div>`;
            } else {
                dayApps.forEach(a => {
                    list.innerHTML += `
                        <div class="appointment-item">
                            <strong><i class="fas fa-user"></i> ${a.student}</strong>
                            <div><i class="fas fa-id-card"></i> ${a.studentId}</div>
                            <div><i class="fas fa-clock"></i> ${a.time}</div>
                            <div><i class="fas fa-stethoscope"></i> ${a.purpose}</div>
                            <div><i class="fas fa-envelope"></i> ${a.contact}</div>
                        </div>`;
                });
            }
            modal.show();
        }

        document.getElementById('prevMonth').onclick = () => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(currentMonth, currentYear); };
        document.getElementById('nextMonth').onclick = () => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(currentMonth, currentYear); };

        renderCalendar(currentMonth, currentYear);
    </script>
</body>
</html>