<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Fetch all consultation records from database
$records = [];
$search = $_GET['search'] ?? '';

try {
    // Build query with search - FIXED TO PREVENT DUPLICATES AND ONLY SHOW NON-ARCHIVED
    $query = "
        SELECT DISTINCT
            c.id,
            c.consultation_date,
            c.consultation_time,
            c.diagnosis,
            c.symptoms,
            c.temperature,
            c.blood_pressure,
            c.heart_rate,
            c.treatment,
            c.attending_staff,
            c.physician_notes,
            c.created_at,
            si.fullname as student_name,
            si.student_number,
            si.address
        FROM consultations c 
        JOIN (
            -- Get only the LATEST student_information record for each student
            SELECT s1.*
            FROM student_information s1
            INNER JOIN (
                SELECT student_number, MAX(created_at) as max_created
                FROM student_information 
                WHERE (archived = 0 OR archived IS NULL)
                GROUP BY student_number
            ) s2 ON s1.student_number = s2.student_number AND s1.created_at = s2.max_created
        ) si ON c.student_number = si.student_number 
        WHERE c.is_archived = 0  -- Only show non-archived records
    ";
    
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $query .= " AND (si.fullname LIKE ? OR c.diagnosis LIKE ? OR si.student_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY c.consultation_date DESC, c.consultation_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("View records error: " . $e->getMessage());
    $error = "Error loading records: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Records - ASCOT Clinic</title>
    
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

        /* Page Header - SIMILAR TO ADMIN DASHBOARD */
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

        /* Search Section */
        .search-section {
            margin-bottom: 1rem;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .search-btn, .clear-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .search-btn {
            background: #667eea;
            color: white;
        }

        .search-btn:hover {
            background: #5a6fd8;
        }

        .clear-btn {
            background: #6c757d;
            color: white;
        }

        .clear-btn:hover {
            background: #5a6268;
        }

        /* Records Count */
        .records-count {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #ffda6a;
        }

        /* Records Table */
        .records-table-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table th {
            background: #2c3e50;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }

        .records-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .records-table tr:hover {
            background: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .view-btn, .edit-btn, .certificate-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .view-btn {
            background: #28a745;
            color: white;
        }

        .view-btn:hover {
            background: #218838;
        }

        .edit-btn {
            background: #ffc107;
            color: #212529;
        }

        .edit-btn:hover {
            background: #e0a800;
        }

        .certificate-btn {
            background: #17a2b8;
            color: white;
        }

        .certificate-btn:hover {
            background: #138496;
        }

        /* Modal Styles */
        .detail-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .section-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .detail-item {
            display: flex;
            margin-bottom: 0.5rem;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }

        .detail-value {
            color: #6c757d;
            flex: 1;
        }

        .vital-signs-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .vital-sign {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .vital-icon {
            font-size: 1.5rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .vital-label {
            font-size: 0.8rem;
            color: #6c757d;
            display: block;
        }

        .vital-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            display: block;
        }

        .notes-container {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }

        .no-notes {
            color: #6c757d;
            font-style: italic;
        }

        /* Certificate Modal Styles */
        .certificate-form .form-group {
            margin-bottom: 1.5rem;
        }

        .certificate-form label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: block;
        }

        .certificate-form .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .certificate-form .form-control:focus {
            border-color: #667eea;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .certificate-form .form-control[readonly] {
            background-color: #f8f9fa;
            opacity: 1;
        }

        .certificate-form .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 1rem;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .certificate-form .form-select:focus {
            border-color: #667eea;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .certificate-form textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .certificate-form .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.375rem;
            transition: all 0.15s ease-in-out;
        }

        .certificate-form .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
        }

        .certificate-form .btn-primary:hover {
            background-color: #5a6fd8;
            border-color: #5a6fd8;
        }

        .certificate-form .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .certificate-form .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        /* Laboratory Fields Styles */
        .lab-checkboxes {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-check {
            margin-bottom: 5px;
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

            .dashboard-card, .records-table-container {
                padding: 1.5rem;
            }

            .search-box {
                flex-direction: column;
            }

            .search-box input {
                width: 100%;
            }

            .records-table {
                font-size: 0.8rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .vital-signs-row {
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

            .records-count {
                flex-direction: column;
                gap: 10px;
                text-align: center;
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
                    <button class="nav-item dropdown-btn active" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item active">
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
                <h1><i class="fas fa-folder-open me-2"></i>Consultation Records</h1>
                <p>Manage and view all consultation records</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> Consultation record saved successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search Section -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Search Records</h3>
                    <div class="card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                <form method="GET" class="search-section" id="searchForm">
                    <div class="search-box">
                        <input type="text" name="search" id="searchInput" placeholder="Search by student name, diagnosis, or student ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="view_records.php" class="clear-btn">
                                <i class="fas fa-times"></i> Clear Search
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Records Count -->
            <div class="records-count fade-in">
                <span>Total Records: <strong><?php echo count($records); ?></strong></span>
                <?php if (!empty($search)): ?>
                    <span class="search-results">Search results for: "<strong><?php echo htmlspecialchars($search); ?></strong>"</span>
                <?php endif; ?>
            </div>

            <!-- Records Table -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Consultation Records</h3>
                    <div class="card-icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
                <div class="records-table-container">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                                        <p class="text-muted">
                                            <?php if (!empty($search)): ?>
                                                No consultation records found for "<?php echo htmlspecialchars($search); ?>"
                                            <?php else: ?>
                                                No consultation records found.
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($search)): ?>
                                            <a href="view_records.php" class="btn btn-primary btn-sm mt-2">
                                                <i class="fas fa-list"></i> Show All Records
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($records as $record): ?>
                                <tr>
                                    <td><?php echo date('m-d-Y', strtotime($record['consultation_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($record['consultation_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['student_number']); ?></td>
                                    <td><?php echo htmlspecialchars($record['diagnosis']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="view-btn" data-id="<?php echo $record['id']; ?>" 
                                                    data-bs-toggle="modal" data-bs-target="#recordModal"
                                                    data-student="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                    data-date="<?php echo date('F j, Y', strtotime($record['consultation_date'])); ?>"
                                                    data-time="<?php echo date('h:i A', strtotime($record['consultation_time'])); ?>"
                                                    data-diagnosis="<?php echo htmlspecialchars($record['diagnosis']); ?>"
                                                    data-symptoms="<?php echo htmlspecialchars($record['symptoms']); ?>"
                                                    data-temperature="<?php echo htmlspecialchars($record['temperature']); ?>"
                                                    data-blood-pressure="<?php echo htmlspecialchars($record['blood_pressure']); ?>"
                                                    data-treatment="<?php echo htmlspecialchars($record['treatment']); ?>"
                                                    data-heart-rate="<?php echo htmlspecialchars($record['heart_rate']); ?>"
                                                    data-staff="<?php echo htmlspecialchars($record['attending_staff']); ?>"
                                                    data-notes="<?php echo htmlspecialchars($record['physician_notes']); ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                           
                                            </a>
                                            <button class="certificate-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#certificateModal"
                                                    data-id="<?php echo $record['id']; ?>"
                                                    data-student="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                    data-address="<?php echo htmlspecialchars($record['address'] ?? 'Not specified'); ?>"
                                                    data-diagnosis="<?php echo htmlspecialchars($record['diagnosis']); ?>"
                                                    data-recommendation="<?php echo htmlspecialchars($record['physician_notes']); ?>">
                                                <i class="fas fa-certificate"></i> Issue Certificate
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Record Details Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1" aria-labelledby="recordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="recordModalLabel">
                        <i class="fas fa-file-medical me-2"></i>Consultation Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Patient Information Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-user-injured me-2"></i>Patient Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Student Name:</span>
                                    <span class="detail-value" id="modalStudent">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Consultation Date:</span>
                                    <span class="detail-value" id="modalDate">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Consultation Time:</span>
                                    <span class="detail-value" id="modalTime">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Assessment Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-stethoscope me-2"></i>Medical Assessment
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Symptoms:</span>
                                    <span class="detail-value" id="modalSymptoms">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Diagnosis:</span>
                                    <span class="detail-value" id="modalDiagnosis">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vital Signs Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-heartbeat me-2"></i>Vital Signs
                        </h6>
                        <div class="vital-signs-row">
                            <div class="vital-sign">
                                <div class="vital-icon">
                                    <i class="fas fa-thermometer-half"></i>
                                </div>
                                <div class="vital-info">
                                    <span class="vital-label">Temperature</span>
                                    <span class="vital-value" id="modalTemperature">-</span>
                                </div>
                            </div>
                            <div class="vital-sign">
                                <div class="vital-icon">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <div class="vital-info">
                                    <span class="vital-label">Blood Pressure</span>
                                    <span class="vital-value" id="modalBloodPressure">-</span>
                                </div>
                            </div>
                            <div class="vital-sign">
                                <div class="vital-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="vital-info">
                                    <span class="vital-label">Heart Rate</span>
                                    <span class="vital-value" id="modalHeartRate">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Treatment Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-pills me-2"></i>Treatment & Management
                        </h6>
                        <div class="detail-item">
                            <span class="detail-label">Treatment Given:</span>
                            <span class="detail-value" id="modalTreatment">-</span>
                        </div>
                    </div>

                    <!-- Staff Information -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-user-md me-2"></i>Staff Information
                        </h6>
                        <div class="detail-item">
                            <span class="detail-label">Attending Staff:</span>
                            <span class="detail-value" id="modalStaff">-</span>
                        </div>
                    </div>

                    <!-- Physician's Notes -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-notes-medical me-2"></i>Physician's Notes
                        </h6>
                        <div class="notes-container">
                            <div class="notes-content" id="modalNotes">
                                <span class="no-notes">No notes provided</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificate Modal -->
    <div class="modal fade" id="certificateModal" tabindex="-1" aria-labelledby="certificateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="certificateModalLabel">
                        <i class="fas fa-certificate me-2"></i>Issue New Certificate
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="certificate-form" action="process_certificate.php" method="POST" target="_blank">
                    <div class="modal-body">
                        <div class="detail-section">
                            <h6 class="section-title">Student Information</h6>
                            <div class="form-group">
                                <label for="certStudentName">Student Name</label>
                                <input type="text" class="form-control" id="certStudentName" name="student_name" readonly>
                            </div>
                            <div class="form-group">
                                <label for="certAddress">Address</label>
                                <textarea class="form-control" id="certAddress" name="address" rows="2" readonly></textarea>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h6 class="section-title">Medical Information</h6>
                            <div class="form-group">
                                <label for="certDiagnosis">Diagnosis</label>
                                <input type="text" class="form-control" id="certDiagnosis" name="diagnosis" readonly>
                            </div>
                            <div class="form-group">
                                <label for="certRecommendation">Recommendation</label>
                                <textarea class="form-control" id="certRecommendation" name="recommendation" rows="3" required></textarea>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h6 class="section-title">Certificate Details</h6>
                            <div class="form-group">
                                <label for="certType">Certificate Type</label>
                                <select class="form-select" id="certType" name="certificate_type" required>
                                    <option value="">Select Certificate Type</option>
                                    <option value="Medical Certificate">Medical Certificate</option>
                                    <option value="Dental Certificate">Dental Certificate</option>
                                    <option value="Laboratory Request Form">Laboratory Request Form</option>
                                    <option value="Excuse Slip">Excuse Slip</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="certDateIssued">Date Issued</label>
                                <input type="date" class="form-control" id="certDateIssued" name="date_issued" required>
                            </div>
                        </div>

                        <!-- Laboratory Request Form Fields (Initially Hidden) -->
                        <div id="laboratoryFields" style="display: none;">
                            <div class="detail-section">
                                <h6 class="section-title">Laboratory Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="certStudentNumber">Student Number</label>
                                            <input type="text" class="form-control" id="certStudentNumber" name="student_number">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="certCourseYear">Course & Year</label>
                                            <input type="text" class="form-control" id="certCourseYear" name="course_year">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="certSchedule">Schedule</label>
                                            <input type="text" class="form-control" id="certSchedule" name="schedule">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="certCellphone">Cellphone Number</label>
                                            <input type="text" class="form-control" id="certCellphone" name="cellphone_number">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Laboratory Tests:</label>
                                    <div class="lab-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="lab_tests[]" value="CBC" id="testCBC">
                                            <label class="form-check-label" for="testCBC">CBC</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="lab_tests[]" value="CXR" id="testCXR">
                                            <label class="form-check-label" for="testCXR">CXR</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="lab_tests[]" value="UA" id="testUA">
                                            <label class="form-check-label" for="testUA">U/A</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="lab_tests[]" value="PREGNANCY" id="testPregnancy">
                                            <label class="form-check-label" for="testPregnancy">PREGNANCY TEST (for female)</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="certMedicalOfficer">Medical Examination Officer</label>
                                    <input type="text" class="form-control" id="certMedicalOfficer" name="medical_officer">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-print me-1"></i> Generate & Print
                        </button>
                    </div>
                    <input type="hidden" id="certConsultationId" name="consultation_id">
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
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

        // Record Modal functionality
        const recordModal = document.getElementById('recordModal');
        if (recordModal) {
            recordModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Patient Information
                document.getElementById('modalStudent').textContent = button.getAttribute('data-student') || 'Not specified';
                document.getElementById('modalDate').textContent = button.getAttribute('data-date') || 'Not specified';
                document.getElementById('modalTime').textContent = button.getAttribute('data-time') || 'Not specified';
                
                // Medical Assessment
                document.getElementById('modalSymptoms').textContent = button.getAttribute('data-symptoms') || 'Not specified';
                document.getElementById('modalDiagnosis').textContent = button.getAttribute('data-diagnosis') || 'Not specified';
                
                // Vital Signs
                document.getElementById('modalTemperature').textContent = button.getAttribute('data-temperature') || 'Not recorded';
                document.getElementById('modalBloodPressure').textContent = button.getAttribute('data-blood-pressure') || 'Not recorded';
                document.getElementById('modalHeartRate').textContent = button.getAttribute('data-heart-rate') || 'Not recorded';
                
                // Treatment
                document.getElementById('modalTreatment').textContent = button.getAttribute('data-treatment') || 'No treatment provided';
                
                // Staff Information
                document.getElementById('modalStaff').textContent = button.getAttribute('data-staff') || 'Not specified';
                
                // Physician's Notes
                const notes = button.getAttribute('data-notes');
                const notesElement = document.getElementById('modalNotes');
                if (notes && notes.trim() !== '') {
                    notesElement.innerHTML = `<p>${notes.replace(/\n/g, '<br>')}</p>`;
                    notesElement.classList.remove('no-notes');
                } else {
                    notesElement.innerHTML = '<span class="no-notes">No notes provided</span>';
                    notesElement.classList.add('no-notes');
                }
            });
        }

        // Certificate Modal functionality
        const certificateModal = document.getElementById('certificateModal');
        if (certificateModal) {
            certificateModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Extract info from data-* attributes
                const consultationId = button.getAttribute('data-id');
                const studentName = button.getAttribute('data-student');
                const address = button.getAttribute('data-address');
                const diagnosis = button.getAttribute('data-diagnosis');
                const recommendation = button.getAttribute('data-recommendation');
                
                // Update the modal's content
                document.getElementById('certStudentName').value = studentName || 'Not specified';
                document.getElementById('certAddress').value = address || 'Not specified';
                document.getElementById('certDiagnosis').value = diagnosis || 'Not specified';
                document.getElementById('certRecommendation').value = recommendation || '';
                document.getElementById('certConsultationId').value = consultationId;
                
                // Set today's date as default for date issued
                const today = new Date();
                const formattedDate = today.toISOString().split('T')[0];
                document.getElementById('certDateIssued').value = formattedDate;
                
                // Show/hide laboratory form fields based on certificate type
                const certType = document.getElementById('certType');
                const labFields = document.getElementById('laboratoryFields');
                
                certType.addEventListener('change', function() {
                    if (this.value === 'Laboratory Request Form') {
                        labFields.style.display = 'block';
                    } else {
                        labFields.style.display = 'none';
                    }
                });
                
                // Trigger change event on page load
                certType.dispatchEvent(new Event('change'));
            });
        }

        // Print functionality
        function printConsultationDetails() {
            const modalContent = document.querySelector('.modal-content').cloneNode(true);
            const printWindow = window.open('', '_blank');
            
            // Remove buttons and add print styles
            const footer = modalContent.querySelector('.modal-footer');
            if (footer) footer.remove();
            
            const header = modalContent.querySelector('.modal-header');
            if (header) {
                header.classList.remove('bg-primary', 'text-white');
                header.classList.add('bg-light', 'text-dark');
            }
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Consultation Details - ASCOT Clinic</title>
                    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
                    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px;
                            color: #333;
                        }
                        .detail-section { 
                            margin-bottom: 20px; 
                            border-bottom: 1px solid #eee; 
                            padding-bottom: 15px; 
                        }
                        .section-title { 
                            color: #2c3e50; 
                            font-weight: bold; 
                            margin-bottom: 10px;
                            font-size: 16px;
                        }
                        .detail-item { 
                            margin-bottom: 8px; 
                            display: flex;
                            justify-content: space-between;
                        }
                        .detail-label { 
                            font-weight: bold; 
                            color: #555;
                            min-width: 150px;
                        }
                        .detail-value { 
                            color: #333;
                            text-align: right;
                        }
                        .vital-sign { 
                            text-align: center; 
                            padding: 10px;
                            border: 1px solid #ddd;
                            border-radius: 5px;
                            margin: 5px;
                        }
                        .vital-icon { 
                            font-size: 20px; 
                            color: #3498db; 
                            margin-bottom: 5px; 
                        }
                        .vital-label { 
                            display: block; 
                            font-size: 12px; 
                            color: #666; 
                        }
                        .vital-value { 
                            display: block; 
                            font-size: 14px; 
                            font-weight: bold; 
                            color: #2c3e50; 
                        }
                        .notes-container { 
                            background: #f8f9fa; 
                            padding: 15px; 
                            border-radius: 5px;
                            border-left: 4px solid #3498db;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #3498db;
                            padding-bottom: 15px;
                        }
                        .print-header h3 {
                            color: #2c3e50;
                            margin-bottom: 5px;
                        }
                        .print-header p {
                            color: #666;
                            margin: 0;
                        }
                        @media print {
                            body { margin: 0; }
                            .detail-section { break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h3>ASCOT Clinic - Consultation Record</h3>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    ${modalContent.innerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }
        // Quick search with Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });

        // Auto-focus on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>