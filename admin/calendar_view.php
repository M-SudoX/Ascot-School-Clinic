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

    <!-- Bootstrap -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
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

        /* Header Styles - SAME AS ADMIN DASHBOARD */
        .top-header {
            background: 
                linear-gradient(90deg, 
                    #ffda6a 0%, 
                    #ffda6a 30%, 
                    #FFF5CC 70%, 
                    #ffffff 100%);
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
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            color: #555;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
            color: #555;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
            color: #555;
        }

        /* Mobile Menu Toggle - SAME AS ADMIN DASHBOARD */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 95px;
            left: 20px;
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

        /* Dashboard Container - SAME AS ADMIN DASHBOARD */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS ADMIN DASHBOARD */
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
            color: #555;
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
            color: #555;
            border-left: 8px solid #ffda6a;
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
            color: #555;
        }

        .nav-item span {
            flex: 1;
            color: #555;
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
        }

        .submenu-item.active {
            background: #e9ecef;
            font-weight: 500;
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

        /* Main Content - SAME AS ADMIN DASHBOARD */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS ADMIN DASHBOARD */
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

        /* Page Header - SAME AS ADMIN DASHBOARD */
        .page-header {
            background: linear-gradient(110deg, #fff7da 50%, #fff7da 50%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(206, 224, 144, 0.2);
            border-left: 10px solid #ffda6a;
        }

        .page-header h1 {
            color: #555;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Dashboard Card - SAME AS ADMIN DASHBOARD */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 2rem;
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
            color: #555;
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
            color: #555;
            background: #fff7da;
            transition: all 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.1);
        }

        /* CALENDAR CONTAINER */
        .calendar-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            max-width: 1400px;
            width: 95%;
        }
        
        /* CALENDAR HEADER */
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendar-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .calendar-header h3 i {
            font-size: 1.7rem;
        }
        
        /* CALENDAR CONTROLS */
        .calendar-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .calendar-controls button {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .calendar-controls button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }
        
        #monthDisplay {
            font-size: 1.3rem;
            font-weight: 600;
            min-width: 180px;
            text-align: center;
            color: white;
        }

        /* CALENDAR GRID */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        /* DAY HEADERS */
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            color: #667eea;
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 15px 0;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 5px;
            border: 1px solid #e9ecef;
        }

        /* DAY CELLS */
        .calendar-day {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            min-height: 100px;
            padding: 12px;
            position: relative;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .calendar-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }
        
        /* TODAY INDICATOR */
        .calendar-day.today {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #764ba2;
        }
        
        /* EMPTY DAYS */
        .calendar-day.text-muted {
            opacity: 0.3;
            cursor: default;
            pointer-events: none;
            background: #f8f9fa;
        }
        
        .calendar-day.text-muted:hover {
            transform: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-color: #e9ecef;
        }
        
        /* DAY NUMBER */
        .calendar-day > div:first-child {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: inherit;
        }

        /* APPOINTMENT BADGE */
        .appointment-count {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 25px;
            border: none;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-header .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-body h5 {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
            font-size: 1.2rem;
            color: #555;
        }

        /* Table Styles */
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .appointments-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .appointments-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .appointments-table td {
            padding: 12px 15px;
            font-size: 0.9rem;
            color: #4b5563;
            border-bottom: 1px solid #e9ecef;
        }

        .appointments-table .time-cell {
            font-weight: 600;
            color: #764ba2;
        }

        .appointments-table .purpose-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* No Appointments Message */
        .no-appointments {
            text-align: center;
            color: #6c757d;
            padding: 40px 20px;
            font-size: 1.1rem;
        }
        
        .no-appointments i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
            display: block;
        }

        /* Badge Styles */
        .badge {
            font-size: 0.75rem;
            padding: 0.4em 0.6em;
        }

        /* Responsive Design - SAME AS ADMIN DASHBOARD */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
            
            .calendar-container {
                padding: 25px;
            }
            
            .calendar-header {
                padding: 18px 25px;
            }
            
            .calendar-header h3 {
                font-size: 1.4rem;
            }
            
            #monthDisplay {
                font-size: 1.2rem;
            }
            
            .calendar-grid {
                gap: 8px;
            }
            
            .calendar-day {
                min-height: 90px;
                padding: 10px;
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
            
            .calendar-container {
                padding: 20px;
                width: 98%;
            }
            
            .calendar-header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .calendar-header h3 {
                font-size: 1.3rem;
            }
            
            .calendar-controls {
                width: 100%;
                justify-content: center;
            }
            
            #monthDisplay {
                font-size: 1.1rem;
                min-width: 150px;
            }
            
            .calendar-grid {
                gap: 6px;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 8px;
            }
            
            .calendar-day > div:first-child {
                font-size: 1.1rem;
            }
            
            .appointment-count {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
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
                top: 85px;
                left: 20px;
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
                padding: 1.5rem;
                width: 100%;
                margin-left: 0;
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

            .calendar-container {
                padding: 15px;
                border-radius: 15px;
            }
            
            .calendar-header {
                padding: 12px 15px;
                margin-bottom: 20px;
            }
            
            .calendar-header h3 {
                font-size: 1.1rem;
                gap: 8px;
            }
            
            .calendar-header h3 i {
                font-size: 1.2rem;
            }
            
            .calendar-controls button {
                width: 35px;
                height: 35px;
            }
            
            #monthDisplay {
                font-size: 1rem;
                min-width: 130px;
            }
            
            .calendar-day-header {
                font-size: 0.75rem;
                padding: 12px 0;
            }
            
            .calendar-grid {
                gap: 4px;
            }
            
            .calendar-day {
                min-height: 70px;
                padding: 6px;
            }
            
            .calendar-day > div:first-child {
                font-size: 1rem;
            }
            
            .appointment-count {
                width: 22px;
                height: 22px;
                font-size: 0.7rem;
                top: 6px;
                right: 6px;
            }

            /* Responsive table for mobile */
            .appointments-table-container {
                overflow-x: auto;
            }

            .appointments-table {
                min-width: 600px;
            }
        }

        @media (max-width: 576px) {
            .dashboard-card {
                padding: 1.25rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .main-content {
                padding: 1.25rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }
            
            .calendar-container {
                padding: 12px;
            }
            
            .calendar-header {
                padding: 10px 12px;
                border-radius: 12px;
            }
            
            .calendar-header h3 {
                font-size: 1rem;
            }
            
            .calendar-controls {
                gap: 10px;
            }
            
            .calendar-controls button {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            #monthDisplay {
                font-size: 0.9rem;
                min-width: 110px;
            }
            
            .calendar-day-header {
                font-size: 0.7rem;
                padding: 10px 0;
            }
            
            .calendar-grid {
                gap: 3px;
            }
            
            .calendar-day {
                min-height: 60px;
                padding: 5px;
                border-radius: 8px;
            }
            
            .calendar-day > div:first-child {
                font-size: 0.9rem;
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
                padding: 1rem;
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
                padding: 0.75rem;
            }
        }

        /* ANIMATIONS - SAME AS ADMIN DASHBOARD */
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
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - SAME AS ADMIN DASHBOARD -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS ADMIN DASHBOARD -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS ADMIN DASHBOARD -->
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

    <div class="dashboard-container">
        <!-- Sidebar - ADMIN MENU ITEMS WITH ADMIN DASHBOARD STYLING -->
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
                        <a href="users_logs.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            Users Logs
                        </a>
                        <a href="backup_restore.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Back up & Restore
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="announcementMenu">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcement</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="announcementMenu">
                        <a href="new_announcement.php" class="submenu-item">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="announcement_history.php" class="submenu-item">
                            <i class="fas fa-history"></i>
                            History
                        </a>
                    </div>
                </div>
                
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content - FOLLOWING ADMIN DASHBOARD STRUCTURE -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header fade-in">
                <h1><i class="fas fa-calendar-alt me-2"></i>Appointment Calendar</h1>
                <p>View and manage all approved appointments</p>
            </div>

            <!-- Calendar Container -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Monthly Calendar</h3>
                    <div class="card-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
                
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
            </div>
        </main>
    </div>

    <!-- MODAL -->
    <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
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
        // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS - SAME AS ADMIN DASHBOARD
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

        // MOBILE MENU FUNCTIONALITY - SAME AS ADMIN DASHBOARD
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
            
            list.innerHTML = `
                <h5>${monthNames[month]} ${day}, ${year}</h5>
                <div class="appointments-table-container">
                    ${dayApps.length === 0 ? 
                        `<div class="no-appointments">
                            <i class="fas fa-calendar-times"></i><br>
                            No appointments scheduled for this day
                        </div>` : 
                        `<table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Time</th>
                                    <th>Purpose</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dayApps.map(app => `
                                    <tr>
                                        <td>${app.student}</td>
                                        <td>${app.studentId}</td>
                                        <td class="time-cell">${app.time}</td>
                                        <td class="purpose-cell">${app.purpose}</td>
                                        <td>${app.contact}</td>
                                        <td>
                                            <span class="badge ${app.status === 'Approved' ? 'bg-success' : 'bg-warning'}">
                                                ${app.status}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>`
                    }
                </div>
            `;
            modal.show();
        }

        document.getElementById('prevMonth').onclick = () => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(currentMonth, currentYear); };
        document.getElementById('nextMonth').onclick = () => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(currentMonth, currentYear); };

        renderCalendar(currentMonth, currentYear);
    </script>
</body>
</html>