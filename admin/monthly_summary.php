<?php
session_start();
include 'admin_logger.php';
include '../includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Log admin access
logAdminAction($_SESSION['admin_name'] ?? 'Admin User', 'Accessed Monthly Summary Report');

// Get selected month and year from GET parameters, default to current month
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month range
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('n');
}

$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$month = $month_names[$selected_month] . ' ' . $selected_year;

// REAL DATABASE QUERIES - FIXED COUNTING WITH DISTINCT

// Function to get monthly consultation data - IMPROVED DUPLICATION HANDLING
function getMonthlyConsultationData($month, $year) {
    global $pdo;
    
    $data = [
        'total_consultations' => 0,
        'departments' => [],
        'diagnostics' => [],
        'year_levels' => []
    ];
    
    try {
        // Total consultations for the selected month - FIXED: Use DISTINCT to avoid duplicates
        $query = "SELECT COUNT(DISTINCT id) as total 
                  FROM consultations 
                  WHERE MONTH(consultation_date) = ? AND YEAR(consultation_date) = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$month, $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['total_consultations'] = $row['total'];
        
        // Consultations by department - FIXED: Use DISTINCT and proper grouping
        $query = "SELECT si.department, COUNT(DISTINCT c.id) as count 
                  FROM consultations c 
                  JOIN student_information si ON c.student_number = si.student_number
                  WHERE MONTH(c.consultation_date) = ? AND YEAR(c.consultation_date) = ?
                  GROUP BY si.department";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$month, $year]);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($departments as $dept) {
            $data['departments'][$dept['department']] = $dept['count'];
        }
        
        // Consultations by diagnosis - FIXED: Use DISTINCT and handle empty diagnoses
        $query = "SELECT diagnosis, COUNT(DISTINCT id) as count 
                  FROM consultations 
                  WHERE MONTH(consultation_date) = ? AND YEAR(consultation_date) = ?
                  AND diagnosis IS NOT NULL AND diagnosis != ''
                  GROUP BY diagnosis 
                  ORDER BY count DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$month, $year]);
        $diagnostics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($diagnostics as $diag) {
            if (!empty($diag['diagnosis'])) {
                $data['diagnostics'][$diag['diagnosis']] = $diag['count'];
            }
        }
        
        // Consultations by year level - FIXED: Use DISTINCT
        $query = "SELECT si.year_level, COUNT(DISTINCT c.id) as count 
                  FROM consultations c 
                  JOIN student_information si ON c.student_number = si.student_number
                  WHERE MONTH(c.consultation_date) = ? AND YEAR(c.consultation_date) = ?
                  GROUP BY si.year_level";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$month, $year]);
        $year_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($year_levels as $level) {
            $data['year_levels'][$level['year_level']] = $level['count'];
        }
        
    } catch (PDOException $e) {
        error_log("Monthly summary error: " . $e->getMessage());
        // Fallback to empty data
    }
    
    return $data;
}

// Function to get yearly data for the bar chart - FIXED: Use DISTINCT
function getYearlyConsultationData($year) {
    global $pdo;
    
    $monthly_totals = [];
    
    try {
        for ($m = 1; $m <= 12; $m++) {
            $query = "SELECT COUNT(DISTINCT id) as total 
                      FROM consultations 
                      WHERE MONTH(consultation_date) = ? AND YEAR(consultation_date) = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$m, $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $monthly_totals[$m] = $row['total'];
        }
    } catch (PDOException $e) {
        error_log("Yearly data error: " . $e->getMessage());
        // Fallback to zeros
        for ($m = 1; $m <= 12; $m++) {
            $monthly_totals[$m] = 0;
        }
    }
    
    return $monthly_totals;
}

// Get data for selected month
$data = getMonthlyConsultationData($selected_month, $selected_year);

// Get yearly data for bar chart
$all_months_data = getYearlyConsultationData($selected_year);

$total_consultations = $data['total_consultations'];
$departments = $data['departments'];
$diagnostics = $data['diagnostics'];
$year_levels = $data['year_levels'];

// DEBUG: Get actual records for verification - FIXED DUPLICATION
try {
    $debug_query = "SELECT DISTINCT c.id, c.consultation_date, c.student_number, c.diagnosis, si.fullname
                   FROM consultations c 
                   JOIN student_information si ON c.student_number = si.student_number
                   WHERE MONTH(c.consultation_date) = ? AND YEAR(c.consultation_date) = ?
                   ORDER BY c.consultation_date";
    $debug_stmt = $pdo->prepare($debug_query);
    $debug_stmt->execute([$selected_month, $selected_year]);
    $actual_records = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $actual_records = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Summary - ASCOT Clinic</title>

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
            color: var(--primary);
        }

        .submenu-item.active {
            background: #e9ecef;
            color: var(--primary);
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
            padding: 2rem;
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

        /* Monthly Summary Specific Styles */
        .month-selector {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .month-btn {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #495057;
        }

        .month-btn:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .month-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .year-selector {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .year-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s ease;
        }

        .year-btn:hover {
            transform: scale(1.2);
        }

        .current-year {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a3a5f;
            min-width: 80px;
            text-align: center;
        }

        .summary-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #1a3a5f;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e9ecef, transparent);
            margin: 2rem 0;
        }

        .report-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-report {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-report:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Bar Chart Styles */
        .bar-chart-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 0.5rem;
            height: 200px;
            margin-bottom: 1rem;
            overflow-x: auto;
            padding: 0 1rem;
        }

        .bar {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            min-width: 40px;
        }

        .bar-rect {
            width: 35px;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .bar-rect:hover {
            opacity: 0.8;
        }

        .bar-label {
            font-size: 0.7rem;
            font-weight: 600;
        }

        .bar-count {
            font-size: 0.7rem;
            color: #666;
            font-weight: 600;
        }

        /* Debug Section */
        .debug-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .debug-header {
            color: #856404;
            font-weight: bold;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive Design - SAME AS ADMIN DASHBOARD */
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

            .dashboard-card {
                padding: 1.5rem;
            }

            .report-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn-report {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }

            .month-selector {
                gap: 0.25rem;
            }

            .month-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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

            .month-selector {
                gap: 0.2rem;
            }

            .month-btn {
                padding: 0.35rem 0.7rem;
                font-size: 0.8rem;
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

            .dashboard-card {
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
                    <button class="nav-item dropdown-btn" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="appointmentsMenu">
                        <a href="calendar_view.php" class="submenu-item">
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
                    <button class="nav-item dropdown-btn active" data-target="reportsMenu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="reportsMenu">
                        <a href="monthly_summary.php" class="submenu-item active">
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
                <h1><i class="fas fa-file-invoice me-2"></i>Monthly Summary Report</h1>
                <p>Comprehensive overview of clinic consultations and statistics</p>
            </div>

            <!-- Monthly Summary Container -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Monthly Statistics</h3>
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
                
                <div class="monthly-summary">
                    <!-- Year Selector -->
                    <div class="year-selector">
                        <button class="year-btn" onclick="changeYear(<?php echo $selected_year - 1; ?>)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="current-year"><?php echo $selected_year; ?></div>
                        <button class="year-btn" onclick="changeYear(<?php echo $selected_year + 1; ?>)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Month Selector -->
                    <div class="month-selector">
                        <?php 
                        $month_abbr = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
                        for ($i = 1; $i <= 12; $i++): 
                            $is_active = ($i == $selected_month) ? 'active' : '';
                        ?>
                            <button class="month-btn <?php echo $is_active; ?>" 
                                    onclick="selectMonth(<?php echo $i; ?>, <?php echo $selected_year; ?>)">
                                <?php echo $month_abbr[$i-1]; ?>
                            </button>
                        <?php endfor; ?>
                    </div>

                    <!-- Bar Chart - ALL 12 MONTHS -->
                    <div class="bar-chart-container">
                        <h5 style="color: #1a3a5f; margin-bottom: 1.5rem; text-align: center;">
                            <i class="fas fa-chart-bar me-2"></i>Monthly Consultations for <?php echo $selected_year; ?>
                        </h5>
                        
                        <div class="bar-chart">
                            <?php 
                            $max = max($all_months_data) > 0 ? max($all_months_data) : 1;
                            
                            for ($m = 1; $m <= 12; $m++): 
                                $height = ($all_months_data[$m] / $max) * 150;
                                $is_selected = ($m == $selected_month) ? '4px solid #667eea' : 'none';
                                $bar_color = ($m == $selected_month) ? '#667eea' : '#4facfe';
                            ?>
                            <div class="bar">
                                <div class="bar-rect" 
                                     style="height: <?php echo $height; ?>px; background: <?php echo $bar_color; ?>; border: <?php echo $is_selected; ?>;"
                                     onclick="selectMonth(<?php echo $m; ?>, <?php echo $selected_year; ?>)"
                                     title="<?php echo $month_names[$m]; ?>: <?php echo $all_months_data[$m]; ?> consultations">
                                </div>
                                <span class="bar-label" style="color: <?php echo ($m == $selected_month) ? '#667eea' : '#333'; ?>;">
                                    <?php echo strtoupper(substr($month_names[$m], 0, 3)); ?>
                                </span>
                                <span class="bar-count">
                                    <?php echo $all_months_data[$m]; ?>
                                </span>
                            </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div style="text-align: center; color: #666; font-size: 0.8rem;">
                            Click on bars to view monthly details
                        </div>
                    </div>

                    <h2 class="summary-title">MONTHLY SUMMARY (<?php echo strtoupper($month); ?>)</h2>

                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_consultations; ?></div>
                            <div class="stat-label">Total Consultations</div>
                            <small class="text-muted">Based on <?php echo count($actual_records); ?> actual records</small>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($diagnostics); ?></div>
                            <div class="stat-label">Different Diagnoses</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($departments); ?></div>
                            <div class="stat-label">Departments Served</div>
                        </div>
                    </div>

                    <!-- Detailed Breakdown -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px;">
                            <h5 style="color: #1a3a5f; margin-bottom: 1rem;">
                                <i class="fas fa-stethoscope me-2"></i>Top Diagnoses
                            </h5>
                            <?php if (!empty($diagnostics)): ?>
                                <?php 
                                arsort($diagnostics);
                                $topDiagnostics = array_slice($diagnostics, 0, 5, true);
                                ?>
                                <?php foreach($topDiagnostics as $diagnosis => $count): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                    <span><?php echo htmlspecialchars($diagnosis); ?></span>
                                    <span style="background: #667eea; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem;">
                                        <?php echo $count; ?> cases
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No diagnosis data available</p>
                            <?php endif; ?>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px;">
                            <h5 style="color: #1a3a5f; margin-bottom: 1rem;">
                                <i class="fas fa-building me-2"></i>By Department
                            </h5>
                            <?php if (!empty($departments)): ?>
                                <?php arsort($departments); ?>
                                <?php foreach($departments as $dept => $count): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                    <span><?php echo htmlspecialchars($dept); ?></span>
                                    <span style="font-weight: 600; color: #667eea;"><?php echo $count; ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No department data available</p>
                            <?php endif; ?>
                            
                            <h5 style="color: #1a3a5f; margin-top: 1.5rem; margin-bottom: 1rem;">
                                <i class="fas fa-graduation-cap me-2"></i>By Year Level
                            </h5>
                            <?php if (!empty($year_levels)): ?>
                                <?php foreach($year_levels as $level => $count): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                                    <span><?php echo htmlspecialchars($level); ?></span>
                                    <span style="font-weight: 600; color: #667eea;"><?php echo $count; ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No year level data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Consultations Table -->
                    <div style="margin-top: 2rem;">
                        <h4 style="color: #1a3a5f; margin-bottom: 1rem;">
                            <i class="fas fa-list me-2"></i>Recent Consultations for <?php echo $month; ?>
                        </h4>
                        
                        <?php if (!empty($actual_records)): ?>
                            <div style="overflow-x: auto; background: white; border-radius: 10px; padding: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #2c3e50; color: white;">
                                            <th style="padding: 12px 15px; text-align: left;">Date</th>
                                            <th style="padding: 12px 15px; text-align: left;">Student</th>
                                            <th style="padding: 12px 15px; text-align: left;">Student Number</th>
                                            <th style="padding: 12px 15px; text-align: left;">Diagnosis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($actual_records as $consultation): ?>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 12px 15px;"><?php echo date('M d, Y', strtotime($consultation['consultation_date'])); ?></td>
                                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($consultation['fullname']); ?></td>
                                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($consultation['student_number']); ?></td>
                                            <td style="padding: 12px 15px;"><?php echo htmlspecialchars($consultation['diagnosis']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> 
                                No consultation records found for <?php echo $month; ?>.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Debug Section - Shows actual records for verification -->
                    <?php if (isset($_GET['debug'])): ?>
                    <div class="debug-section">
                        <div class="debug-header">
                            <i class="fas fa-bug"></i>
                            Debug Information - Actual Records in Database
                        </div>
                        
                        <p><strong>Total Records Found:</strong> <?php echo count($actual_records); ?></p>
                        <p><strong>Counted in Summary:</strong> <?php echo $total_consultations; ?></p>
                        
                        <?php if (!empty($actual_records)): ?>
                            <div style="overflow-x: auto; margin-top: 1rem;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                                    <thead>
                                        <tr style="background: #856404; color: white;">
                                            <th style="padding: 8px; text-align: left;">ID</th>
                                            <th style="padding: 8px; text-align: left;">Date</th>
                                            <th style="padding: 8px; text-align: left;">Student Number</th>
                                            <th style="padding: 8px; text-align: left;">Student Name</th>
                                            <th style="padding: 8px; text-align: left;">Diagnosis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($actual_records as $record): ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 8px;"><?php echo $record['id']; ?></td>
                                            <td style="padding: 8px;"><?php echo $record['consultation_date']; ?></td>
                                            <td style="padding: 8px;"><?php echo $record['student_number']; ?></td>
                                            <td style="padding: 8px;"><?php echo $record['fullname']; ?></td>
                                            <td style="padding: 8px;"><?php echo $record['diagnosis']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No records found for debugging.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="divider"></div>

                    <div class="report-actions">
                        <button class="btn-report" onclick="printReport()">
                            <i class="fas fa-print"></i>
                            Print Report
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

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

        // Month and Year selection functions
        function selectMonth(month, year) {
            window.location.href = `?month=${month}&year=${year}`;
        }

        function changeYear(year) {
            window.location.href = `?month=<?php echo $selected_month; ?>&year=${year}`;
        }

        // Report functions
        function printReport() {
            const originalTitle = document.title;
            document.title = "Monthly Summary - <?php echo $month; ?> - ASCOT Clinic";
            window.print();
            document.title = originalTitle;
        }

        function exportToExcel() {
            const month = "<?php echo $month; ?>";
            const total = "<?php echo $total_consultations; ?>";
            
            let csvContent = "Monthly Summary Report - " + month + "\n\n";
            csvContent += "Total Consultations," + total + "\n\n";
            
            csvContent += "Top Diagnoses\n";
            <?php 
            arsort($diagnostics);
            $topDiagnostics = array_slice($diagnostics, 0, 5, true);
            foreach($topDiagnostics as $diagnosis => $count): 
            ?>
            csvContent += "<?php echo $diagnosis; ?>,<?php echo $count; ?>\n";
            <?php endforeach; ?>
            
            csvContent += "\nBy Department\n";
            <?php foreach($departments as $dept => $count): ?>
            csvContent += "<?php echo $dept; ?>,<?php echo $count; ?>\n";
            <?php endforeach; ?>
            
            csvContent += "\nBy Year Level\n";
            <?php foreach($year_levels as $level => $count): ?>
            csvContent += "<?php echo $level; ?>,<?php echo $count; ?>\n";
            <?php endforeach; ?>
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'monthly_summary_<?php echo strtolower(str_replace(" ", "_", $month)); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>