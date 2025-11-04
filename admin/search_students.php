<?php
// START SESSION TO ACCESS ADMIN AUTHENTICATION DATA
session_start();

// CHECK IF ADMIN IS LOGGED IN BY VERIFYING ADMIN_ID SESSION VARIABLE EXISTS
if (!isset($_SESSION['admin_id'])) {
    // IF NOT LOGGED IN, REDIRECT TO ADMIN LOGIN PAGE
    header("Location: admin_login.php");
    exit(); // TERMINATE SCRIPT EXECUTION
}

// INCLUDE DATABASE CONNECTION FILE
require_once '../includes/db_connect.php';

// INITIALIZE VARIABLES
$success = '';
$error = '';
$search_results = [];
$search_query = '';
$year_level_filter = '';
$gender_filter = '';
$status_filter = '';

// ARCHIVE STUDENT FUNCTIONALITY (SAME AS students.php)
if (isset($_GET['archive_id'])) {
    $archive_id = $_GET['archive_id'];

    try {
        // Get student number before archiving for consultation records
        $student_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id = ?");
        $student_stmt->execute([$archive_id]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $student_number = $student['student_number'];
            
            // Update student_information table to set archived status and archive date
            $archive_stmt = $pdo->prepare("UPDATE student_information SET archived = 1, archived_at = NOW() WHERE id = ?");
            $archive_success = $archive_stmt->execute([$archive_id]);
            
            if ($archive_success) {
                // Also archive all consultations for this student
                $consultation_stmt = $pdo->prepare("UPDATE consultations SET is_archived = 1 WHERE student_number = ?");
                $consultation_stmt->execute([$student_number]);
                
                $success = "Student archived successfully! All consultation records have also been archived.";
            } else {
                $error = "Failed to archive student!";
            }
        } else {
            $error = "Student not found!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// BULK ARCHIVE STUDENTS (SAME AS students.php)
if (isset($_POST['bulk_archive']) && isset($_POST['selected_students'])) {
    $selected_students = $_POST['selected_students'];
    
    if (!empty($selected_students)) {
        try {
            // Get student numbers for the selected students
            $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
            $student_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id IN ($placeholders)");
            $student_stmt->execute($selected_students);
            $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $student_numbers = array_column($students, 'student_number');
            
            // Archive students
            $archive_stmt = $pdo->prepare("UPDATE student_information SET archived = 1, archived_at = NOW() WHERE id IN ($placeholders)");
            $archive_success = $archive_stmt->execute($selected_students);
            
            if ($archive_success && !empty($student_numbers)) {
                // Archive all consultations for these students
                $consultation_placeholders = str_repeat('?,', count($student_numbers) - 1) . '?';
                $consultation_stmt = $pdo->prepare("UPDATE consultations SET is_archived = 1 WHERE student_number IN ($consultation_placeholders)");
                $consultation_stmt->execute($student_numbers);
                
                $success = "Successfully archived " . count($selected_students) . " student(s)! All consultation records have also been archived.";
            } else {
                $error = "Failed to archive selected students!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "No students selected for archiving!";
    }
}

// SEARCH FUNCTIONALITY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_query = trim($_POST['search_query'] ?? '');
    $year_level_filter = trim($_POST['year_level'] ?? '');
    $gender_filter = trim($_POST['gender'] ?? '');
    $status_filter = trim($_POST['status'] ?? '');
    
    try {
        $where_conditions = [];
        $params = [];
        
        // Base search query with medical history join (UPDATED)
        if (!empty($search_query)) {
            $search_term = "%$search_query%";
            $where_conditions[] = "(si.student_number LIKE ? OR si.fullname LIKE ? OR u.email LIKE ? OR si.course_year LIKE ? OR si.cellphone_number LIKE ?)";
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        }
        
        // Year level filter
        if (!empty($year_level_filter)) {
            $where_conditions[] = "si.course_year LIKE ?";
            $params[] = "%$year_level_filter%";
        }
        
        // Gender filter
        if (!empty($gender_filter)) {
            $where_conditions[] = "si.sex = ?";
            $params[] = $gender_filter;
        }
        
        // Status filter (assuming active students have complete profiles)
        if (!empty($status_filter)) {
            if ($status_filter === 'Active') {
                $where_conditions[] = "(si.course_year IS NOT NULL AND si.course_year != '' AND si.cellphone_number IS NOT NULL AND si.cellphone_number != '')";
            } elseif ($status_filter === 'Inactive') {
                $where_conditions[] = "(si.course_year IS NULL OR si.course_year = '' OR si.cellphone_number IS NULL OR si.cellphone_number = '')";
            }
        }
        
        // Build final query with medical history (UPDATED)
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
            INNER JOIN student_information si ON u.student_number = si.student_number 
            LEFT JOIN medical_history mh ON u.student_number = mh.student_number
            WHERE (si.archived = 0 OR si.archived IS NULL)
        ";
        
        if (!empty($where_conditions)) {
            $query .= " AND " . implode(" AND ", $where_conditions);
        }
        
        $query .= " ORDER BY si.fullname ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($search_results)) {
            $error = "No students found matching your search criteria.";
        } else {
            $success = "Found " . count($search_results) . " student(s) matching your search.";
        }
        
    } catch (PDOException $e) {
        $error = "Search failed: " . $e->getMessage();
    }
}

// FUNCTION TO CHECK IF PROFILE IS COMPLETE (SAME AS students.php)
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

// FUNCTION TO GET COMPLETION STATUS WITH DETAILS (SAME AS students.php)
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
    <title>Search Students - ASCOT Clinic (Admin)</title>

    <!-- Bootstrap -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <style>
        /* ALL THE SAME CSS FROM YOUR ORIGINAL FILE - NO CHANGES */
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

        /* Info Cards Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .info-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .info-card h5 {
            color: #555;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        /* NEW STYLES ADDED FOR ARCHIVE FUNCTIONALITY */
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

        /* Bulk Action Styles */
        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }
        .bulk-actions .form-check {
            margin-bottom: 0.5rem;
        }
        .selected-count {
            background: var(--warning);
            color: #000;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        /* Checkbox Styles */
        .student-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
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

            .info-grid {
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

            .info-grid {
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

            .bulk-actions {
                padding: 0.75rem;
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

            .info-card {
                padding: 1.25rem;
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
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="search_students.php" class="submenu-item active">
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
                    <h1>Search Students 🔍</h1>
                    <p>Find students quickly using various search criteria</p>
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

            <!-- Search Form -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Search Students</h3>
                    <div class="card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                
                <div class="search-container">
                    <form method="POST" action="">
                        <div class="d-flex align-items-center">
                            <div class="search-input-group flex-grow-1">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" name="search_query" 
                                       placeholder="Enter student name, student number, email, course, or contact number..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>"
                                       aria-label="Search students">
                            </div>
                            &nbsp;<button type="submit" name="search" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <a href="search_students.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-refresh me-2"></i>Clear
                            </a>
                        </div>
                        <?php if (!empty($search_query)): ?>
                            <div class="search-stats">
                                <i class="fas fa-info-circle me-1"></i>
                                Searching for: "<?php echo htmlspecialchars($search_query); ?>"
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])): ?>
                <?php if (!empty($search_results)): ?>
                    <!-- Results Count -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Search Results</h3>
                            <div class="card-icon">
                                <i class="fas fa-list"></i>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i> 
                            Found <strong><?php echo count($search_results); ?></strong> student(s) matching your search
                        </div>

                        <!-- Bulk Actions -->
                        <div class="bulk-actions" id="bulkActions" style="display: none;">
                            <form method="POST" action="search_students.php" id="bulkArchiveForm">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                            <label class="form-check-label fw-bold" for="selectAll">
                                                Select All 
                                                <span class="selected-count" id="selectedCount">0 selected</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="button" class="btn btn-warning btn-sm" id="bulkArchiveBtn">
                                            <i class="fas fa-archive me-1"></i>Archive Selected
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" id="clearSelection">
                                            <i class="fas fa-times me-1"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Results Table -->
                        <div class="table-responsive">
                            <form method="POST" action="search_students.php" id="studentTableForm">
                                <table class="table table-bordered table-hover text-center align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" class="select-all-checkbox" id="selectAllCheckbox">
                                            </th>
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
                                        <?php foreach ($search_results as $student): 
                                            $status = getProfileStatus($student);
                                            $isComplete = $status['is_complete'];
                                            $rowClass = $isComplete ? '' : 'incomplete-profile';
                                            $studentId = $student['id'] ?? $student['user_id'] ?? 0;
                                        ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td>
                                                    <input type="checkbox" class="student-checkbox" name="selected_students[]" value="<?php echo $studentId; ?>">
                                                </td>
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
                                                            <button class="btn btn-warning btn-sm archive-student" 
                                                                    data-id="<?php echo $studentId; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($student['fullname']); ?>"
                                                                    data-number="<?php echo htmlspecialchars($student['student_number']); ?>"
                                                                    title="Archive">
                                                                <i class="fas fa-archive"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Edit via Students</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <input type="hidden" name="bulk_archive" value="1">
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Results -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">No Results Found</h3>
                            <div class="card-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No students found</h5>
                            <p class="text-muted">Try searching with different keywords or check your spelling</p>
                            <a href="search_students.php" class="btn btn-primary">
                                <i class="fas fa-refresh"></i> Try Again
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Initial State - Quick Info Cards -->
                <div class="info-grid fade-in">
                    <div class="info-card">
                        <i class="fas fa-id-card text-primary"></i>
                        <h5>Search by Student Number</h5>
                        <p>Find students using their unique ID number</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-user text-success"></i>
                        <h5>Search by Name</h5>
                        <p>Find students by full or partial name</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-envelope text-warning"></i>
                        <h5>Search by Email</h5>
                        <p>Find students using email address</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-graduation-cap text-info"></i>
                        <h5>Search by Course</h5>
                        <p>Find students by course or year level</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Archive Confirmation Modal (SAME AS students.php) -->
    <div class="modal fade" id="archiveStudentModal" tabindex="-1" aria-labelledby="archiveStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="archiveStudentModalLabel">Confirm Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive student: <strong id="studentNameToArchive"></strong>?</p>
                    <p><strong>Student ID:</strong> <span id="studentNumberToArchive"></span></p>
                    <p class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Archiving will:
                    </p>
                    <ul class="text-warning small">
                        <li>Remove student from active student list</li>
                        <li>Archive all consultation records for this student</li>
                        <li>Remove student from view records page</li>
                        <li>Keep all records for historical purposes</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-warning" id="confirmArchive">Archive Student</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Archive Confirmation Modal (SAME AS students.php) -->
    <div class="modal fade" id="bulkArchiveModal" tabindex="-1" aria-labelledby="bulkArchiveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkArchiveModalLabel">Confirm Bulk Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive <strong id="selectedStudentsCount"></strong> selected student(s)?</p>
                    <p class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Archiving will:
                    </p>
                    <ul class="text-warning small">
                        <li>Remove students from active student list</li>
                        <li>Archive all consultation records for these students</li>
                        <li>Remove students from view records page</li>
                        <li>Keep all records for historical purposes</li>
                    </ul>
                    <div id="selectedStudentsList" class="mt-3 small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmBulkArchive">Archive Selected Students</button>
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

            // Archive student confirmation (SAME AS students.php)
            const archiveButtons = document.querySelectorAll('.archive-student');
            const archiveModal = new bootstrap.Modal(document.getElementById('archiveStudentModal'));
            const studentNameArchiveElement = document.getElementById('studentNameToArchive');
            const studentNumberArchiveElement = document.getElementById('studentNumberToArchive');
            const confirmArchiveButton = document.getElementById('confirmArchive');
            
            archiveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    const studentNumber = this.getAttribute('data-number');
                    
                    studentNameArchiveElement.textContent = studentName;
                    studentNumberArchiveElement.textContent = studentNumber;
                    confirmArchiveButton.href = 'search_students.php?archive_id=' + studentId;
                    archiveModal.show();
                });
            });

            // Bulk Archive Functionality (SAME AS students.php)
            const bulkActions = document.getElementById('bulkActions');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const selectAll = document.getElementById('selectAll');
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
            const clearSelection = document.getElementById('clearSelection');
            const bulkArchiveModal = new bootstrap.Modal(document.getElementById('bulkArchiveModal'));
            const selectedStudentsCount = document.getElementById('selectedStudentsCount');
            const selectedStudentsList = document.getElementById('selectedStudentsList');
            const confirmBulkArchive = document.getElementById('confirmBulkArchive');
            const studentTableForm = document.getElementById('studentTableForm');

            // Update selected count and show/hide bulk actions
            function updateSelectedCount() {
                const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
                const count = checkedBoxes.length;
                selectedCount.textContent = count + ' selected';
                
                if (count > 0) {
                    bulkActions.style.display = 'block';
                } else {
                    bulkActions.style.display = 'none';
                }
                
                // Update select all checkbox state
                selectAllCheckbox.checked = count > 0 && count === studentCheckboxes.length;
                selectAll.checked = count > 0 && count === studentCheckboxes.length;
            }

            // Select all checkboxes
            function toggleSelectAll(checked) {
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = checked;
                });
                updateSelectedCount();
            }

            // Event listeners for checkboxes
            selectAllCheckbox.addEventListener('change', function() {
                toggleSelectAll(this.checked);
            });

            selectAll.addEventListener('change', function() {
                toggleSelectAll(this.checked);
            });

            studentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Clear selection
            clearSelection.addEventListener('click', function() {
                toggleSelectAll(false);
            });

            // Bulk archive confirmation
            bulkArchiveBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one student to archive.');
                    return;
                }

                selectedStudentsCount.textContent = checkedBoxes.length;
                
                // Show first few selected student names
                let studentNames = [];
                checkedBoxes.forEach((checkbox, index) => {
                    if (index < 5) { // Show only first 5 names
                        const row = checkbox.closest('tr');
                        const name = row.cells[2].textContent;
                        const number = row.cells[1].textContent;
                        studentNames.push({name: name, number: number});
                    }
                });
                
                let namesHtml = '<strong>Selected Students:</strong><br>';
                studentNames.forEach(student => {
                    namesHtml += `<i class="fas fa-user me-2"></i>${student.name} (${student.number})<br>`;
                });
                
                if (checkedBoxes.length > 5) {
                    namesHtml += `... and ${checkedBoxes.length - 5} more`;
                }
                
                selectedStudentsList.innerHTML = namesHtml;
                bulkArchiveModal.show();
            });

            // Confirm bulk archive
            confirmBulkArchive.addEventListener('click', function() {
                studentTableForm.submit();
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Focus on search input on page load
            const searchInput = document.querySelector('input[name="search_query"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Enter key search
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
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