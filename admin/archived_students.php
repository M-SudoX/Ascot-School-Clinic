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

// UNARCHIVE STUDENT (RESTORE)
if (isset($_GET['unarchive_id'])) {
    $unarchive_id = $_GET['unarchive_id'];

    try {
        // Get student number before restoring for consultation records
        $student_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id = ?");
        $student_stmt->execute([$unarchive_id]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $student_number = $student['student_number'];
            
            // Update student_information table to set archived status to 0
            $unarchive_stmt = $pdo->prepare("UPDATE student_information SET archived = 0, archived_at = NULL WHERE id = ?");
            $unarchive_success = $unarchive_stmt->execute([$unarchive_id]);
            
            if ($unarchive_success) {
                // Also restore all consultations for this student
                $consultation_stmt = $pdo->prepare("UPDATE consultations SET is_archived = 0 WHERE student_number = ?");
                $consultation_stmt->execute([$student_number]);
                
                $success = "Student restored successfully! All consultation records have also been restored.";
            } else {
                $error = "Failed to restore student!";
            }
        } else {
            $error = "Student not found!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// BULK RESTORE STUDENTS
if (isset($_POST['bulk_restore']) && isset($_POST['selected_students'])) {
    $selected_students = $_POST['selected_students'];
    
    if (!empty($selected_students)) {
        try {
            // Get student numbers for the selected students
            $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
            $student_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id IN ($placeholders)");
            $student_stmt->execute($selected_students);
            $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $student_numbers = array_column($students, 'student_number');
            
            // Restore students
            $restore_stmt = $pdo->prepare("UPDATE student_information SET archived = 0, archived_at = NULL WHERE id IN ($placeholders)");
            $restore_success = $restore_stmt->execute($selected_students);
            
            if ($restore_success && !empty($student_numbers)) {
                // Restore all consultations for these students
                $consultation_placeholders = str_repeat('?,', count($student_numbers) - 1) . '?';
                $consultation_stmt = $pdo->prepare("UPDATE consultations SET is_archived = 0 WHERE student_number IN ($consultation_placeholders)");
                $consultation_stmt->execute($student_numbers);
                
                $success = "Successfully restored " . count($selected_students) . " student(s)! All consultation records have also been restored.";
            } else {
                $error = "Failed to restore selected students!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "No students selected for restoration!";
    }
}

// DELETE ARCHIVED STUDENT
if (isset($_GET['delete_archived_id'])) {
    $delete_id = $_GET['delete_archived_id'];

    try {
        // First get student_number before deleting
        $get_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id = ?");
        $get_stmt->execute([$delete_id]);
        $student = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $student_number = $student['student_number'];
            
            // Start transaction for data consistency
            $pdo->beginTransaction();
            
            // Delete consultation records
            $consultation_stmt = $pdo->prepare("DELETE FROM consultations WHERE student_number = ?");
            $consultation_stmt->execute([$student_number]);
            
            // Delete medical history
            $medical_stmt = $pdo->prepare("DELETE FROM medical_history WHERE student_number = ?");
            $medical_stmt->execute([$student_number]);
            
            // Delete from student_information table
            $delete_stmt = $pdo->prepare("DELETE FROM student_information WHERE id = ?");
            $delete_success = $delete_stmt->execute([$delete_id]);
            
            // Also delete from users table
            if ($delete_success) {
                $delete_user_stmt = $pdo->prepare("DELETE FROM users WHERE student_number = ?");
                $delete_user_stmt->execute([$student_number]);
                
                $pdo->commit();
                $success = "Archived student permanently deleted! All related records have been removed.";
            } else {
                $pdo->rollBack();
                $error = "Failed to delete student!";
            }
        } else {
            $error = "Student not found!";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Database error: " . $e->getMessage();
    }
}

// BULK DELETE ARCHIVED STUDENTS
if (isset($_POST['bulk_delete']) && isset($_POST['selected_students'])) {
    $selected_students = $_POST['selected_students'];
    
    if (!empty($selected_students)) {
        try {
            // Get student numbers for the selected students
            $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
            $student_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id IN ($placeholders)");
            $student_stmt->execute($selected_students);
            $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $student_numbers = array_column($students, 'student_number');
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete consultation records
            if (!empty($student_numbers)) {
                $consultation_placeholders = str_repeat('?,', count($student_numbers) - 1) . '?';
                $consultation_stmt = $pdo->prepare("DELETE FROM consultations WHERE student_number IN ($consultation_placeholders)");
                $consultation_stmt->execute($student_numbers);
                
                // Delete medical history
                $medical_stmt = $pdo->prepare("DELETE FROM medical_history WHERE student_number IN ($consultation_placeholders)");
                $medical_stmt->execute($student_numbers);
                
                // Delete users
                $user_stmt = $pdo->prepare("DELETE FROM users WHERE student_number IN ($consultation_placeholders)");
                $user_stmt->execute($student_numbers);
            }
            
            // Delete student information
            $delete_stmt = $pdo->prepare("DELETE FROM student_information WHERE id IN ($placeholders)");
            $delete_success = $delete_stmt->execute($selected_students);
            
            if ($delete_success) {
                $pdo->commit();
                $success = "Successfully permanently deleted " . count($selected_students) . " student(s)! All related records have been removed.";
            } else {
                $pdo->rollBack();
                $error = "Failed to delete selected students!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "No students selected for deletion!";
    }
}

// Get search from URL
$search = $_GET['search'] ?? '';

// Fetch archived students
$students = [];
$params = [];

try {
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
            si.archived_at,
            u.email,
            u.created_at as user_created
        FROM users u 
        INNER JOIN student_information si ON u.student_number = si.student_number 
        WHERE si.archived = 1
    ";

    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (si.student_number LIKE ? OR si.fullname LIKE ? OR u.email LIKE ? OR si.course_year LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= " ORDER BY si.archived_at DESC, si.student_number ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Failed to fetch archived students: " . $e->getMessage();
}

$total_archived = count($students);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Students - ASCOT Clinic (Admin)</title>

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

        /* Danger Zone Styles */
        .danger-zone {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
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
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="archived_students.php" class="submenu-item active">
                            <i class="fas fa-archive"></i>
                            Archived Students
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
                    <h1>Archived Students 🗃️</h1>
                    <p>View and manage archived student records</p>
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

            <!-- Search Bar -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Search Archived Students</h3>
                    <div class="card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                
                <div class="search-container">
                    <form method="GET" action="archived_students.php">
                        <div class="d-flex align-items-center">
                            <div class="search-input-group flex-grow-1">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search archived students by ID, name, email, or course..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            &nbsp;<button class="btn btn-primary" type="submit">
                               <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                        <?php if (!empty($search)): ?>
                            <div class="search-stats">
                                <i class="fas fa-info-circle me-1"></i>
                                Found <?php echo $total_archived; ?> archived student(s) matching "<?php echo htmlspecialchars($search); ?>"
                                <a href="archived_students.php" class="text-danger ms-2 text-decoration-none">
                                    <i class="fas fa-times me-1"></i>Clear search
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Archived Students Statistics -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_archived; ?></div>
                    <div class="stat-label">Total Archived</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <a href="students.php" class="text-decoration-none text-dark">
                            <i class="fas fa-arrow-left me-2"></i>Back to Active
                        </a>
                    </div>
                    <div class="stat-label">Return to Active Students</div>
                </div>
            </div>

            <!-- Archived Students Table -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Archived Student List</h3>
                    <div class="card-icon">
                        <i class="fas fa-archive"></i>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions" style="display: none;">
                    <form method="POST" action="archived_students.php" id="bulkActionForm">
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
                                <button type="button" class="btn btn-success btn-sm" id="bulkRestoreBtn">
                                    <i class="fas fa-undo me-1"></i>Restore Selected
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn">
                                    <i class="fas fa-trash me-1"></i>Delete Selected
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="clearSelection">
                                    <i class="fas fa-times me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <form method="POST" action="archived_students.php" id="studentTableForm">
                        <table class="table table-bordered table-hover text-center align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="select-all-checkbox" id="selectAllCheckbox">
                                    </th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course/Year</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Archived Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-archive fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">No archived students found.</p>
                                            <a href="students.php" class="btn btn-primary">
                                                <i class="fas fa-users me-2"></i>View Active Students
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): 
                                        $studentId = $student['id'];
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="student-checkbox" name="selected_students[]" value="<?php echo $studentId; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['fullname'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($student['course_year'] ?? 'Not set'); ?></td>
                                            <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['cellphone_number'] ?? 'Not set'); ?></td>
                                            <td><?php echo !empty($student['archived_at']) ? date('M d, Y h:i A', strtotime($student['archived_at'])) : 'Unknown'; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($studentId && $studentId > 0): ?>
                                                        <a href="view_student.php?id=<?php echo $studentId; ?>" class="btn btn-success btn-sm" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="archived_students.php?unarchive_id=<?php echo $studentId; ?>" class="btn btn-primary btn-sm unarchive-student" 
                                                                data-id="<?php echo $studentId; ?>" 
                                                                data-name="<?php echo htmlspecialchars($student['fullname']); ?>"
                                                                title="Restore">
                                                            <i class="fas fa-undo"></i>
                                                        </a>
                                                        <a href="archived_students.php?delete_archived_id=<?php echo $studentId; ?>" class="btn btn-danger btn-sm delete-archived-student" 
                                                                data-id="<?php echo $studentId; ?>" 
                                                                data-name="<?php echo htmlspecialchars($student['fullname']); ?>"
                                                                title="Permanently Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">N/A</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <input type="hidden" name="bulk_restore" id="bulkRestoreInput" value="1">
                        <input type="hidden" name="bulk_delete" id="bulkDeleteInput" value="1">
                    </form>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="dashboard-card danger-zone fade-in">
                <div class="card-header">
                    <h3 class="card-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                    </h3>
                </div>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-circle me-2"></i>Warning</h5>
                    <p class="mb-2">Permanently deleting students will:</p>
                    <ul class="mb-2">
                        <li>Remove all student information permanently</li>
                        <li>Delete all consultation records for this student</li>
                        <li>Remove medical history records</li>
                        <li>Delete user account</li>
                        <li><strong>This action cannot be undone!</strong></li>
                    </ul>
                    <p class="mb-0">Consider restoring students instead if you might need the data later.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Unarchive Confirmation Modal -->
    <div class="modal fade" id="unarchiveStudentModal" tabindex="-1" aria-labelledby="unarchiveStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unarchiveStudentModalLabel">Confirm Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to restore student: <strong id="studentNameToUnarchive"></strong>?</p>
                    <p class="text-success">
                        <i class="fas fa-info-circle"></i> 
                        Restoring will:
                    </p>
                    <ul class="text-success small">
                        <li>Return student to active student list</li>
                        <li>Restore all consultation records for this student</li>
                        <li>Make student visible in view records page</li>
                        <li>Make student visible in consultation history</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-success" id="confirmUnarchive">Restore Student</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Restore Confirmation Modal -->
    <div class="modal fade" id="bulkRestoreModal" tabindex="-1" aria-labelledby="bulkRestoreModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkRestoreModalLabel">Confirm Bulk Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to restore <strong id="selectedStudentsCountRestore"></strong> selected student(s)?</p>
                    <p class="text-success">
                        <i class="fas fa-info-circle"></i> 
                        Restoring will:
                    </p>
                    <ul class="text-success small">
                        <li>Return students to active student list</li>
                        <li>Restore all consultation records for these students</li>
                        <li>Make students visible in view records page</li>
                        <li>Make students visible in consultation history</li>
                    </ul>
                    <div id="selectedStudentsListRestore" class="mt-3 small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmBulkRestore">Restore Selected Students</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Archived Confirmation Modal -->
    <div class="modal fade" id="deleteArchivedStudentModal" tabindex="-1" aria-labelledby="deleteArchivedStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteArchivedStudentModalLabel">Permanent Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning: This action cannot be undone!</h6>
                    </div>
                    <p>Are you sure you want to permanently delete archived student: <strong id="studentNameToDeleteArchived"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-circle"></i> 
                        This will permanently delete:
                    </p>
                    <ul class="text-danger small">
                        <li>All student information</li>
                        <li>All consultation records</li>
                        <li>Medical history data</li>
                        <li>User account</li>
                    </ul>
                    <p><strong>This action is irreversible!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmDeleteArchived">Permanently Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="bulkDeleteModalLabel">Confirm Bulk Permanent Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning: This action cannot be undone!</h6>
                    </div>
                    <p>Are you sure you want to permanently delete <strong id="selectedStudentsCountDelete"></strong> selected student(s)?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-circle"></i> 
                        This will permanently delete:
                    </p>
                    <ul class="text-danger small">
                        <li>All student information</li>
                        <li>All consultation records</li>
                        <li>Medical history data</li>
                        <li>User accounts</li>
                    </ul>
                    <p><strong>This action is irreversible!</strong></p>
                    <div id="selectedStudentsListDelete" class="mt-3 small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDelete">Permanently Delete Selected</button>
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
            
            // Unarchive student confirmation
            const unarchiveButtons = document.querySelectorAll('.unarchive-student');
            const unarchiveModal = new bootstrap.Modal(document.getElementById('unarchiveStudentModal'));
            const studentNameUnarchiveElement = document.getElementById('studentNameToUnarchive');
            const confirmUnarchiveButton = document.getElementById('confirmUnarchive');
            
            unarchiveButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    studentNameUnarchiveElement.textContent = studentName;
                    confirmUnarchiveButton.href = 'archived_students.php?unarchive_id=' + studentId;
                    unarchiveModal.show();
                });
            });

            // Delete archived student confirmation
            const deleteArchivedButtons = document.querySelectorAll('.delete-archived-student');
            const deleteArchivedModal = new bootstrap.Modal(document.getElementById('deleteArchivedStudentModal'));
            const studentNameDeleteArchivedElement = document.getElementById('studentNameToDeleteArchived');
            const confirmDeleteArchivedButton = document.getElementById('confirmDeleteArchived');
            
            deleteArchivedButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    studentNameDeleteArchivedElement.textContent = studentName;
                    confirmDeleteArchivedButton.href = 'archived_students.php?delete_archived_id=' + studentId;
                    deleteArchivedModal.show();
                });
            });

            // Bulk Archive Functionality
            const bulkActions = document.getElementById('bulkActions');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const selectAll = document.getElementById('selectAll');
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            const clearSelection = document.getElementById('clearSelection');
            const bulkRestoreModal = new bootstrap.Modal(document.getElementById('bulkRestoreModal'));
            const selectedStudentsCountRestore = document.getElementById('selectedStudentsCountRestore');
            const selectedStudentsListRestore = document.getElementById('selectedStudentsListRestore');
            const confirmBulkRestore = document.getElementById('confirmBulkRestore');
            const bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
            const selectedStudentsCountDelete = document.getElementById('selectedStudentsCountDelete');
            const selectedStudentsListDelete = document.getElementById('selectedStudentsListDelete');
            const confirmBulkDelete = document.getElementById('confirmBulkDelete');
            const studentTableForm = document.getElementById('studentTableForm');
            const bulkRestoreInput = document.getElementById('bulkRestoreInput');
            const bulkDeleteInput = document.getElementById('bulkDeleteInput');

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

            // Bulk restore confirmation
            bulkRestoreBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one student to restore.');
                    return;
                }

                selectedStudentsCountRestore.textContent = checkedBoxes.length;
                
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
                
                selectedStudentsListRestore.innerHTML = namesHtml;
                bulkRestoreModal.show();
            });

            // Confirm bulk restore
            confirmBulkRestore.addEventListener('click', function() {
                // Set the restore input and submit
                bulkRestoreInput.disabled = false;
                bulkDeleteInput.disabled = true;
                studentTableForm.submit();
            });

            // Bulk delete confirmation
            bulkDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one student to delete.');
                    return;
                }

                selectedStudentsCountDelete.textContent = checkedBoxes.length;
                
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
                
                selectedStudentsListDelete.innerHTML = namesHtml;
                bulkDeleteModal.show();
            });

            // Confirm bulk delete
            confirmBulkDelete.addEventListener('click', function() {
                // Set the delete input and submit
                bulkRestoreInput.disabled = true;
                bulkDeleteInput.disabled = false;
                studentTableForm.submit();
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