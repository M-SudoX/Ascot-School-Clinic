<?php
//i use session authentication for admin only
//PDO for - SQL injection protection

// START SESSION TO ACCESS SESSION VARIABLES AND MAINTAIN USER STATE
session_start();

// CHECK IF STUDENT IS LOGGED IN BY VERIFYING STUDENT_ID EXISTS IN SESSION
if (!isset($_SESSION['admin_id'])) {
    // IF NOT LOGGED IN, REDIRECT TO STUDENT LOGIN PAGE
    header("Location: admin_login.php");
    exit(); // TERMINATE SCRIPT EXECUTION IMMEDIATELY
}

// INCLUDE DATABASE CONNECTION FILE TO ESTABLISH DATABASE CONNECTION
require_once '../includes/db_connect.php';

// Handle form submissions
$success = '';
$error = '';

// DELETE STUDENT
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    //PDO for Secure Data Access - Protected database operations for sensitive student information
    
    try {
        // First get student_number before deleting
        $get_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id = ?");
        $get_stmt->execute([$delete_id]);
        $student = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $student_number = $student['student_number'];
            
            // Delete from student_information table
            $delete_stmt = $pdo->prepare("DELETE FROM student_information WHERE id = ?");
            $delete_success = $delete_stmt->execute([$delete_id]);
            
            // Also delete from users table
            if ($delete_success) {
                $delete_user_stmt = $pdo->prepare("DELETE FROM users WHERE student_number = ?");
                $delete_user_stmt->execute([$student_number]);
                
                $success = "Student deleted successfully!";
            }
        } else {
            $error = "Student not found!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Fetch all students with medical history check - UPDATED QUERY TO INCLUDE SEARCH
$students = [];
$params = [];

try {
    // UPDATED QUERY: Added search functionality
    $query = "
        SELECT 
            si.id,
            si.student_number,
            si.fullname,
            si.course_year,
            si.cellphone_number,
            si.address,
            si.age,
            si.sex,
            si.created_at,
            u.email,
            u.created_at as user_created,
            mh.medical_attention,
            mh.medical_conditions,
            mh.other_conditions,
            mh.previous_hospitalization,
            mh.hosp_year,
            mh.surgery,
            mh.surgery_details,
            mh.food_allergies,
            mh.medicine_allergies
        FROM users u 
        INNER JOIN (
            SELECT s1.*
            FROM student_information s1
            INNER JOIN (
                SELECT student_number, MAX(created_at) as max_created
                FROM student_information 
                GROUP BY student_number
            ) s2 ON s1.student_number = s2.student_number AND s1.created_at = s2.max_created
        ) si ON u.student_number = si.student_number 
        LEFT JOIN medical_history mh ON u.student_number = mh.student_number
        WHERE 1=1
    ";

    // Apply status filter
    if ($filter === 'incomplete') {
        $query .= " AND (
            si.course_year IS NULL OR si.course_year = '' OR 
            si.cellphone_number IS NULL OR si.cellphone_number = '' OR
            si.fullname IS NULL OR si.fullname = '' OR
            si.address IS NULL OR si.address = '' OR
            si.age IS NULL OR si.age = '' OR
            si.sex IS NULL OR si.sex = '' OR
            mh.medical_attention IS NULL OR mh.medical_attention = '' OR
            mh.medical_conditions IS NULL OR mh.medical_conditions = '' OR
            mh.previous_hospitalization IS NULL OR mh.previous_hospitalization = '' OR
            mh.surgery IS NULL OR mh.surgery = ''
        )";
    }

    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (si.student_number LIKE ? OR si.fullname LIKE ? OR u.email LIKE ? OR si.course_year LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= " ORDER BY si.student_number ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Failed to fetch students: " . $e->getMessage();
    error_log("Student fetch error: " . $e->getMessage());
}

// Calculate statistics - UPDATED TO INCLUDE PART 2
$total_students = count($students);
$complete_profiles = 0;
$incomplete_profiles = 0;

foreach ($students as $student) {
    if (isProfileComplete($student)) {
        $complete_profiles++;
    } else {
        $incomplete_profiles++;
    }
}

// FUNCTION TO CHECK IF PROFILE IS COMPLETE (BOTH PART 1 AND PART 2)
function isProfileComplete($student) {
    // PART 1: Student Information Requirements
    $part1_complete = !empty($student['fullname']) && 
                     !empty($student['address']) && 
                     !empty($student['age']) && 
                     !empty($student['sex']) && 
                     !empty($student['course_year']) && 
                     !empty($student['cellphone_number']);
    
    // PART 2: Medical History Requirements
    $part2_complete = !empty($student['medical_attention']) && 
                     !empty($student['previous_hospitalization']) && 
                     !empty($student['surgery']);
    
    return $part1_complete && $part2_complete;
}

// FUNCTION TO GET COMPLETION STATUS WITH DETAILS
function getProfileStatus($student) {
    $missing_fields = [];
    
    // Check Part 1 fields
    if (empty($student['fullname'])) $missing_fields[] = 'Full Name';
    if (empty($student['address'])) $missing_fields[] = 'Address';
    if (empty($student['age'])) $missing_fields[] = 'Age';
    if (empty($student['sex'])) $missing_fields[] = 'Sex';
    if (empty($student['course_year'])) $missing_fields[] = 'Course/Year';
    if (empty($student['cellphone_number'])) $missing_fields[] = 'Cellphone Number';
    
    // Check Part 2 fields
    if (empty($student['medical_attention'])) $missing_fields[] = 'Medical Attention';
    if (empty($student['previous_hospitalization'])) $missing_fields[] = 'Previous Hospitalization';
    if (empty($student['surgery'])) $missing_fields[] = 'Surgery Information';
    
    return [
        'is_complete' => empty($missing_fields),
        'missing_fields' => $missing_fields,
        'part1_complete' => !empty($student['fullname']) && !empty($student['address']) && !empty($student['age']) && !empty($student['sex']) && !empty($student['course_year']) && !empty($student['cellphone_number']),
        'part2_complete' => !empty($student['medical_attention']) && !empty($student['previous_hospitalization']) && !empty($student['surgery'])
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - ASCOT Clinic (Admin)</title>

    <!-- Bootstrap & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
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

        /* Header Styles - SAME AS STUDENT */
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

        /* Mobile Menu Toggle - SAME AS STUDENT */
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

        /* Dashboard Container - SAME AS STUDENT */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS STUDENT */
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

        /* Main Content - SAME AS STUDENT */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS STUDENT */
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

        /* Welcome Section - SAME AS STUDENT */
        .welcome-section {
            background: linear-gradient(110deg, #fff7da 50%, #fff7da 50%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(206, 224, 144, 0.2);
            border-left: 10px solid #ffda6a;
        }

        .welcome-content h1 {
            color: #555;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Dashboard Card - SAME AS STUDENT */
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

        /* Search Bar Styles */
        .search-container {
            background: transparent;
            border: none;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        .search-input-group {
            position: relative;
            max-width: 600px;
        }
        .search-input-group .form-control {
            padding-left: 3rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            font-size: 1rem;
            height: 50px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .search-input-group .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }
        .search-input-group .btn {
            border-radius: 8px;
            height: 50px;
            padding: 0 2rem;
            margin-left: 0.5rem;
        }
        .search-stats {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        /* Filter Buttons */
        .filter-active {
            background-color: #0d6efd !important;
            color: white !important;
        }
        .filter-badge {
            font-size: 0.75em;
            margin-left: 5px;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #555;
            display: block;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* Table Styles */
        .incomplete-profile {
            background-color: #fff3cd !important;
        }
        .status-badge {
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .completion-details {
            font-size: 0.8em;
            color: #6c757d;
        }
        .part-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .part-complete {
            background-color: #28a745;
        }
        .part-incomplete {
            background-color: #dc3545;
        }
        .missing-fields-tooltip {
            cursor: help;
            border-bottom: 1px dotted #6c757d;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Responsive Design - SAME AS STUDENT */
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

            .stats-grid {
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
                padding: 2rem 1.25rem 1.25rem;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-input-group {
                max-width: 100%;
            }
            
            .search-input-group .form-control {
                height: 45px;
            }
            
            .search-input-group .btn {
                height: 45px;
                padding: 0 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
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
                padding: 1.75rem 1rem 1rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 2rem;
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

        /* ANIMATIONS - SAME AS STUDENT */
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
    <!-- Mobile Menu Toggle Button - SAME AS STUDENT -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS STUDENT -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS STUDENT -->
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
        <!-- Sidebar - ADMIN MENU ITEMS WITH STUDENT STYLING -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn active" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="studentMenu">
                        <a href="students.php" class="submenu-item active">
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

        <!-- Main Content - FOLLOWING STUDENT DASHBOARD STRUCTURE -->
        <main class="main-content">
            <!-- WELCOME SECTION -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Student Management 👨‍🎓</h1>
                    <p>Manage and view all student profiles and information</p>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Professional Search Bar -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Search Students</h3>
                    <div class="card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                
                <div class="search-container">
                    <form method="GET" action="students.php">
                        <div class="d-flex align-items-center">
                            <div class="search-input-group flex-grow-1">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search students by ID, name, email, or course..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            &nbsp;<button class="btn btn-primary" type="submit">
                               <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                        <?php if (!empty($search)): ?>
                            <div class="search-stats">
                                <i class="fas fa-info-circle me-1"></i>
                                Found <?php echo $total_students; ?> student(s) matching "<?php echo htmlspecialchars($search); ?>"
                                <a href="students.php" class="text-danger ms-2 text-decoration-none">
                                    <i class="fas fa-times me-1"></i>Clear search
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Filters</h3>
                    <div class="card-icon">
                        <i class="fas fa-filter"></i>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <a href="students.php?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="btn btn-outline-primary <?php echo $filter === 'all' ? 'filter-active' : ''; ?>">
                                All Students
                                <span class="badge bg-secondary filter-badge"><?php echo $total_students; ?></span>
                            </a>
                            <a href="students.php?filter=incomplete<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="btn btn-outline-warning <?php echo $filter === 'incomplete' ? 'filter-active' : ''; ?>">
                                Incomplete Profiles
                                <span class="badge bg-warning filter-badge"><?php echo $incomplete_profiles; ?></span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="text-muted small">
                            Showing <?php echo $total_students; ?> student(s)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Statistics -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $complete_profiles; ?></div>
                    <div class="stat-label">Complete Profiles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $incomplete_profiles; ?></div>
                    <div class="stat-label">Incomplete Profiles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_students > 0 ? round(($complete_profiles / $total_students) * 100) : 0; ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
            </div>

            <!-- Student Table -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Student List</h3>
                    <div class="card-icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course/Year</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Completion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                        <p class="text-muted">No students found with current filters.</p>
                                        <a href="students.php" class="btn btn-primary">
                                            <i class="fas fa-refresh"></i> Reset Filters
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): 
                                    $status = getProfileStatus($student);
                                    $isComplete = $status['is_complete'];
                                    $rowClass = $isComplete ? '' : 'incomplete-profile';
                                    $studentId = $student['id'] ?? $student['user_id'] ?? 0;
                                ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['fullname'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($student['course_year'] ?? 'Not set'); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['cellphone_number'] ?? 'Not set'); ?></td>
                                        <td>
                                            <?php if ($isComplete): ?>
                                                <span class="badge bg-success status-badge">Complete</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning status-badge">Incomplete</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="completion-details">
                                                <div>
                                                    <span class="part-status <?php echo $status['part1_complete'] ? 'part-complete' : 'part-incomplete'; ?>"></span>
                                                    Part 1: <?php echo $status['part1_complete'] ? 'Complete' : 'Incomplete'; ?>
                                                </div>
                                                <div>
                                                    <span class="part-status <?php echo $status['part2_complete'] ? 'part-complete' : 'part-incomplete'; ?>"></span>
                                                    Part 2: <?php echo $status['part2_complete'] ? 'Complete' : 'Incomplete'; ?>
                                                </div>
                                                <?php if (!$isComplete && !empty($status['missing_fields'])): ?>
                                                    <small class="text-danger missing-fields-tooltip" title="Missing: <?php echo implode(', ', $status['missing_fields']); ?>">
                                                        Missing <?php echo count($status['missing_fields']); ?> fields
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($studentId && $studentId > 0): ?>
                                                    <a href="view_student.php?id=<?php echo $studentId; ?>" class="btn btn-success btn-sm" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="consultation_history.php?id=<?php echo $studentId; ?>" class="btn btn-info btn-sm" title="Consultation History">
                                                        <i class="fas fa-file-medical"></i>
                                                    </a>
                                                    <button class="btn btn-danger btn-sm delete-student" 
                                                            data-id="<?php echo $studentId; ?>" 
                                                            data-name="<?php echo htmlspecialchars($student['fullname']); ?>"
                                                            title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">Edit via Search</span>
                                                <?php endif; ?>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteStudentModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete student: <strong id="studentNameToDelete"></strong>?</p>
                    <p class="text-danger">This action cannot be undone and will remove both user account and student records.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmDelete">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS
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

            // MOBILE MENU FUNCTIONALITY - SAME AS STUDENT
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
            
            // Delete student confirmation
            const deleteButtons = document.querySelectorAll('.delete-student');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));
            const studentNameElement = document.getElementById('studentNameToDelete');
            const confirmDeleteButton = document.getElementById('confirmDelete');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    studentNameElement.textContent = studentName;
                    confirmDeleteButton.href = 'students.php?delete_id=' + studentId;
                    deleteModal.show();
                });
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Auto-focus search input
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }

            // LOADING ANIMATIONS
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
            });
        });
    </script>
</body>
</html>