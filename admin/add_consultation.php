<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student information
$student = [];
try {
    $student_stmt = $pdo->prepare("
        SELECT si.*, u.email 
        FROM student_information si 
        JOIN users u ON si.student_number = u.student_number 
        WHERE si.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Student fetch error: " . $e->getMessage());
}

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i');

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $symptoms = trim($_POST['symptoms'] ?? '');
        $temperature = trim($_POST['temperature'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $blood_pressure = trim($_POST['blood_pressure'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $heart_rate = trim($_POST['heart_rate'] ?? '');
        $attending_staff = trim($_POST['attending_staff'] ?? '');
        $consultation_date = trim($_POST['consultation_date'] ?? $current_date);
        $consultation_time = trim($_POST['consultation_time'] ?? $current_time);
        $physician_notes = trim($_POST['physician_notes'] ?? '');
        
        // Validate ALL fields are required
        if (empty($symptoms) || empty($temperature) || empty($diagnosis) || 
            empty($blood_pressure) || empty($treatment) || empty($heart_rate) || 
            empty($attending_staff) || empty($consultation_date) || empty($consultation_time) ||
            empty($physician_notes)) {
            throw new Exception("Please fill in ALL fields. All fields are required.");
        }
        
        if (!$student) {
            throw new Exception("Student information not found.");
        }
        
        // Insert consultation record
        $insert_stmt = $pdo->prepare("
            INSERT INTO consultations (
                student_number, symptoms, temperature, diagnosis, 
                blood_pressure, treatment, heart_rate, attending_staff, 
                consultation_date, consultation_time, physician_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insert_stmt->execute([
            $student['student_number'],
            $symptoms,
            $temperature,
            $diagnosis,
            $blood_pressure,
            $treatment,
            $heart_rate,
            $attending_staff,
            $consultation_date,
            $consultation_time,
            $physician_notes
        ]);
        
        // Store success message in session and redirect
        $_SESSION['success_message'] = "Consultation record saved successfully!";
        
        // Redirect to prevent form resubmission
        header("Location: add_consultation.php?id=" . $student_id);
        exit();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Consultation save error: " . $e->getMessage());
    }
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Consultation - ASCOT Clinic</title>
    
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
            --accent: #ffda6a;
            --accent-light: #fff7da;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --border-radius: 16px;
            --shadow: 0 8px 32px rgba(0,0,0,0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            padding-top: 100px;
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header Styles - ENHANCED */
        .top-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            padding: 0.75rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 100px;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            transition: var(--transition);
        }

        .logo-img:hover {
            transform: scale(1.05);
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.75rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .school-name {
            font-size: 1.2rem;
            font-weight: 800;
            margin: 0.2rem 0;
            line-height: 1.2;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--text-dark), #495057);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .clinic-title {
            font-size: 0.85rem;
            opacity: 0.9;
            font-weight: 600;
            color: var(--text-dark);
            letter-spacing: 0.5px;
        }

        /* Mobile Menu Toggle - ENHANCED */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 110px;
            left: 20px;
            z-index: 1025;
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }

        /* Dashboard Container - ENHANCED */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        /* Sidebar Styles - ENHANCED */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 2px 0 20px rgba(0,0,0,0.08);
            padding: 2rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 100px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1020;
            border-right: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
            gap: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-weight: 600;
            border-radius: 0 12px 12px 0;
            margin: 0.25rem 0;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            transition: var(--transition);
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.8);
            color: var(--primary);
            transform: translateX(5px);
        }

        .nav-item:hover::before {
            width: 100%;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(255,218,106,0.15) 0%, transparent 100%);
            color: var(--text-dark);
            border-left: 6px solid var(--accent);
        }

        .nav-item.active::before {
            width: 100%;
        }

        .nav-item i {
            width: 25px;
            margin-right: 1rem;
            font-size: 1.2rem;
            color: inherit;
            transition: var(--transition);
        }

        .nav-item span {
            flex: 1;
            color: inherit;
            font-size: 0.95rem;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .nav-item .arrow.rotate {
            transform: rotate(180deg);
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
            border-left: 6px solid transparent;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(248, 249, 250, 0.8);
        }

        .submenu.show {
            max-height: 500px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem 0.75rem 3.5rem;
            color: var(--text-light);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .submenu-item:hover {
            background: rgba(233, 236, 239, 0.8);
            color: var(--primary);
        }

        .submenu-item.active {
            background: rgba(255, 218, 106, 0.15);
            color: var(--text-dark);
            border-left: 4px solid var(--accent);
        }

        .submenu-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        /* Main Content - ENHANCED */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            margin-left: 280px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - ENHANCED */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 100px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1019;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Page Header - ENHANCED */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(233, 236, 239, 0.8);
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .page-header h1 i {
            color: var(--primary);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
        }

        .back-btn {
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--gray);
            color: white;
            box-shadow: var(--shadow);
        }

        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
            box-shadow: 0 6px 25px rgba(108, 117, 125, 0.4);
        }

        /* Student Info Card - ENHANCED */
        .student-info-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border-left: 6px solid var(--accent);
            transition: var(--transition);
        }

        .student-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .student-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-name::before {
            content: '👤';
            font-size: 1.2rem;
        }

        .student-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .student-details span {
            background: rgba(248, 249, 250, 0.8);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 500;
            border: 1px solid rgba(233, 236, 239, 0.5);
            transition: var(--transition);
        }

        .student-details span:hover {
            background: rgba(255, 218, 106, 0.2);
            color: var(--text-dark);
        }

        /* Consultation Form - ENHANCED */
        .consultation-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
            transition: var(--transition);
        }

        .consultation-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .form-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .required-field::after {
            content: " *";
            color: var(--danger);
        }

        .form-control {
            padding: 14px 16px;
            border: 2px solid rgba(233, 236, 239, 0.8);
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .is-invalid {
            border-color: var(--danger) !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }

        .error-message {
            color: var(--danger);
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(233, 236, 239, 0.8), transparent);
            margin: 1rem 0;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .save-btn, .reset-btn {
            padding: 14px 32px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow);
        }

        .save-btn {
            background: linear-gradient(135deg, var(--success), #218838);
            color: white;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(40, 167, 69, 0.4);
        }

        .save-btn:disabled {
            background: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .reset-btn {
            background: linear-gradient(135deg, var(--gray), #5a6268);
            color: white;
        }

        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(108, 117, 125, 0.4);
        }

        /* Success Alert - ENHANCED */
        .success-alert {
            background: rgba(212, 237, 218, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(195, 230, 203, 0.8);
            color: #155724;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border-left: 6px solid var(--success);
            animation: slideIn 0.5s ease-out;
            box-shadow: var(--shadow);
        }

        .success-alert i {
            color: var(--success);
            margin-right: 0.5rem;
        }

        .success-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .success-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .success-btn.primary {
            background: var(--success);
            color: white;
        }

        .success-btn.secondary {
            background: var(--gray);
            color: white;
        }

        .success-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Alert Styles - ENHANCED */
        .alert {
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: none;
            box-shadow: var(--shadow);
        }

        .alert-danger {
            background: rgba(248, 215, 218, 0.95);
            backdrop-filter: blur(20px);
            color: #721c24;
            border-left: 6px solid var(--danger);
        }

        .dynamic-error-alert {
            animation: slideIn 0.5s ease-out;
        }

        /* Custom styles for datalist dropdown */
        .staff-datalist-container {
            position: relative;
        }

        .staff-datalist-container input {
            width: 100%;
        }

        .staff-datalist-container::after {
            content: '\f0d7';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        /* Responsive Design - ENHANCED */
        @media (max-width: 1200px) {
            .sidebar {
                width: 260px;
            }
            
            .main-content {
                margin-left: 260px;
            }
        }

        @media (max-width: 992px) {
            .school-name {
                font-size: 1rem;
            }

            .logo-img {
                width: 60px;
                height: 60px;
            }

            .consultation-form {
                padding: 2rem;
            }

            .student-info-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 100px;
            }
            
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                top: 110px;
                left: 20px;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 100px;
                height: calc(100vh - 100px);
                z-index: 1020;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 300px;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(30px);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                top: 100px;
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

            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-buttons {
                flex-direction: column;
                width: 100%;
            }

            .student-info-card, .consultation-form {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .student-details {
                flex-direction: column;
                gap: 10px;
            }

            .form-actions {
                flex-direction: column;
            }

            .save-btn, .reset-btn {
                width: 100%;
                justify-content: center;
            }

            .success-actions {
                flex-direction: column;
            }

            .success-btn {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.4rem;
            }

            .consultation-form {
                padding: 1.25rem;
            }

            .student-info-card {
                padding: 1.25rem;
            }

            .main-content {
                padding: 1.25rem;
            }
            
            .mobile-menu-toggle {
                top: 105px;
                width: 45px;
                height: 45px;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img {
                width: 50px;
                height: 50px;
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
                top: 105px;
                left: 15px;
            }
            
            .main-content {
                padding: 1rem;
            }

            .consultation-form {
                padding: 1rem;
            }

            .student-info-card {
                padding: 1rem;
            }
        }

        /* ANIMATIONS - ENHANCED */
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

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.8s ease-out;
        }

        .slide-in-left {
            animation: slideInLeft 0.6s ease-out;
        }

        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), #6a4a9a);
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - ENHANCED -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - ENHANCED -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- HEADER - ENHANCED -->
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

    <!-- DASHBOARD CONTAINER - ENHANCED -->
    <div class="dashboard-container">
        <!-- SIDEBAR - ENHANCED -->
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
                        <?php if ($student): ?>
                            <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="submenu-item">
                                <i class="fas fa-file-medical"></i>
                                Consultation History
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn active" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                        <?php if ($student): ?>
                            <a href="add_consultation.php?id=<?php echo $student_id; ?>" class="submenu-item active">
                                <i class="fas fa-plus-circle"></i>
                                Add Consultation
                            </a>
                        <?php endif; ?>
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header fade-in">
                <h1><i class="fas fa-plus-circle"></i> Add New Consultation</h1>
                <div class="header-buttons">
                    <?php if ($student): ?>
                        <a href="students.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
                    <?php else: ?>
                        <a href="students.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="success-alert fade-in">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <div>
                            <h5 class="mb-1" style="color: #155724;">Success!</h5>
                            <p class="mb-2"><?php echo $success; ?></p>
                            <div class="success-actions">
                                <?php if ($student): ?>
                                    <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="success-btn primary">
                                        <i class="fas fa-history"></i> View Consultation History
                                    </a>
                                    <a href="add_consultation.php?id=<?php echo $student_id; ?>" class="success-btn secondary">
                                        <i class="fas fa-plus"></i> Add Another Consultation
                                    </a>
                                <?php endif; ?>
                                <a href="students.php" class="success-btn secondary">
                                    <i class="fas fa-users"></i> Back to Students
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Error:</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($student): ?>
                <!-- Student Information -->
                <div class="student-info-card fade-in">
                    <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                    <div class="student-details">
                        <span>ID: <?php echo htmlspecialchars($student['student_number']); ?></span>
                        <span>Course: <?php echo htmlspecialchars($student['course_year']); ?></span>
                        <span>Age/Sex: <?php echo htmlspecialchars($student['age']); ?>/<?php echo htmlspecialchars($student['sex']); ?></span>
                    </div>
                </div>

                <!-- Consultation Form -->
                <form method="POST" class="consultation-form fade-in" id="consultationForm">
                    <div class="form-container">
                        <!-- Medical Information Section -->
                        <div class="medical-info-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="symptoms" class="required-field">Symptoms:</label>
                                    <input type="text" id="symptoms" name="symptoms" class="form-control" 
                                           placeholder="Enter symptoms" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="symptomsError">Please enter symptoms</div>
                                </div>
                                <div class="form-group">
                                    <label for="temperature" class="required-field">Temperature:</label>
                                    <input type="text" id="temperature" name="temperature" class="form-control" 
                                           placeholder="e.g., 36.5°C" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="temperatureError">Please enter temperature</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="diagnosis" class="required-field">Diagnosis:</label>
                                    <input type="text" id="diagnosis" name="diagnosis" class="form-control" 
                                           placeholder="Enter diagnosis" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="diagnosisError">Please enter diagnosis</div>
                                </div>
                                <div class="form-group">
                                    <label for="blood_pressure" class="required-field">Blood Pressure:</label>
                                    <input type="text" id="blood_pressure" name="blood_pressure" class="form-control" 
                                           placeholder="e.g., 120/80 mmHg" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="bloodPressureError">Please enter blood pressure</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="treatment" class="required-field">Treatment Given:</label>
                                    <input type="text" id="treatment" name="treatment" class="form-control" 
                                           placeholder="Enter treatment" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="treatmentError">Please enter treatment given</div>
                                </div>
                                <div class="form-group">
                                    <label for="heart_rate" class="required-field">Heart Rate:</label>
                                    <input type="text" id="heart_rate" name="heart_rate" class="form-control" 
                                           placeholder="e.g., 72 bpm" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="heartRateError">Please enter heart rate</div>
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="divider"></div>

                        <!-- Staff and Date Section -->
                        <div class="staff-date-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="attending_staff" class="required-field">Attending Staff:</label>
                                    <div class="staff-datalist-container">
                                        <input type="text" id="attending_staff" name="attending_staff" class="form-control" 
                                               list="staff_list" 
                                               placeholder="Select or type staff name" 
                                               value="Mary Rose Valencerina" 
                                               required>
                                        <datalist id="staff_list">
                                            <option value="Mary Rose Valencerina">
                                        </datalist>
                                    </div>
                                    <div class="error-message" id="staffError">Please enter attending staff name</div>
                                </div>
                                <div class="form-group">
                                    <label for="consultation_date" class="required-field">Consultation Date:</label>
                                    <input type="date" id="consultation_date" name="consultation_date" class="form-control" 
                                           value="<?php echo $current_date; ?>" 
                                           required>
                                    <div class="error-message" id="dateError">Please select consultation date</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="consultation_time" class="required-field">Consultation Time:</label>
                                    <input type="time" id="consultation_time" name="consultation_time" class="form-control" 
                                           value="<?php echo $current_time; ?>" 
                                           required>
                                    <div class="error-message" id="timeError">Please select consultation time</div>
                                </div>
                                <div class="form-group">
                                    <!-- Spacer to maintain grid layout -->
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="divider"></div>

                        <!-- Physician's Notes -->
                        <div class="physician-notes-section">
                            <div class="form-group">
                                <label for="physician_notes" class="required-field">Physician's notes:</label>
                                <textarea id="physician_notes" name="physician_notes" class="form-control" rows="4" 
                                          placeholder="Enter additional notes..." required></textarea>
                                <div class="error-message" id="physicianNotesError">Please enter physician's notes</div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="form-actions">
                            <button type="submit" class="save-btn" id="submitBtn">
                                <i class="fas fa-save"></i> Save Consultation
                            </button>
                            <button type="reset" class="reset-btn">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <div class="alert alert-danger text-center fade-in">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h4>Student Not Found</h4>
                    <p>The requested student record could not be found.</p>
                    <a href="students.php" class="btn btn-primary">Back to Students</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // MOBILE MENU FUNCTIONALITY - ENHANCED
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleMobileMenu() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
                
                // Add animation class
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.add('slide-in-left');
                } else {
                    sidebar.classList.remove('slide-in-left');
                }
            }

            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            sidebarOverlay.addEventListener('click', toggleMobileMenu);

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

            // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS - ENHANCED
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

            // Form Validation and Functionality
            const form = document.getElementById('consultationForm');
            const submitBtn = document.getElementById('submitBtn');
            const requiredFields = form?.querySelectorAll('[required]');

            if (form && requiredFields) {
                let formSubmitted = false;
                
                // Real-time validation only after first submit attempt
                requiredFields.forEach(field => {
                    field.addEventListener('input', function() {
                        if (formSubmitted) {
                            validateField(this);
                        }
                    });
                    
                    field.addEventListener('blur', function() {
                        if (formSubmitted) {
                            validateField(this);
                        }
                    });
                });
                
                function validateField(field) {
                    const errorElement = document.getElementById(field.id + 'Error');
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        if (errorElement) errorElement.style.display = 'block';
                        return false;
                    } else {
                        field.classList.remove('is-invalid');
                        if (errorElement) errorElement.style.display = 'none';
                        return true;
                    }
                }
                
                // Form submission - STRICTER VALIDATION
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Always prevent default first
                    
                    let isValid = true;
                    let firstInvalidField = null;
                    formSubmitted = true;
                    
                    // Validate all required fields
                    requiredFields.forEach(field => {
                        if (!validateField(field)) {
                            isValid = false;
                            if (!firstInvalidField) {
                                firstInvalidField = field;
                            }
                        }
                    });
                    
                    if (!isValid) {
                        // Show error message
                        showErrorMessage("Please fill in ALL fields before submitting the form. All fields are required.");
                        
                        // Scroll to first error
                        if (firstInvalidField) {
                            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstInvalidField.focus();
                        }
                        return false;
                    }
                    
                    // If all validations pass, show loading and submit
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    submitBtn.disabled = true;
                    
                    // Programmatically submit the form
                    this.submit();
                });
                
                // Function to show error message
                function showErrorMessage(message) {
                    // Remove existing error alerts
                    const existingAlerts = document.querySelectorAll('.dynamic-error-alert');
                    existingAlerts.forEach(alert => alert.remove());
                    
                    // Create new error alert
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger alert-dismissible fade show dynamic-error-alert';
                    errorAlert.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Validation Error:</strong> ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    
                    // Insert after the success message or at the top of main content
                    const successAlert = document.querySelector('.success-alert');
                    if (successAlert) {
                        successAlert.parentNode.insertBefore(errorAlert, successAlert.nextSibling);
                    } else {
                        const pageHeader = document.querySelector('.page-header');
                        if (pageHeader) {
                            pageHeader.parentNode.insertBefore(errorAlert, pageHeader.nextSibling);
                        }
                    }
                    
                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        if (errorAlert.parentNode) {
                            errorAlert.style.opacity = '0';
                            errorAlert.style.transition = 'opacity 0.5s ease';
                            setTimeout(() => {
                                if (errorAlert.parentNode) {
                                    errorAlert.parentNode.removeChild(errorAlert);
                                }
                            }, 500);
                        }
                    }, 5000);
                }
                
                // Auto-format inputs
                const tempInput = document.getElementById('temperature');
                if (tempInput) {
                    tempInput.addEventListener('blur', function() {
                        let value = this.value.trim();
                        if (value && !value.includes('°')) {
                            value = value.replace(/[CF]$/i, '').trim();
                            this.value = value + '°C';
                        }
                    });
                }
                
                const bpInput = document.getElementById('blood_pressure');
                if (bpInput) {
                    bpInput.addEventListener('blur', function() {
                        let value = this.value.trim();
                        if (value && !value.toLowerCase().includes('mmhg')) {
                            value = value.replace(/[^0-9\/]/g, '');
                            if (value) this.value = value + ' mmHg';
                        }
                    });
                }
                
                const hrInput = document.getElementById('heart_rate');
                if (hrInput) {
                    hrInput.addEventListener('blur', function() {
                        let value = this.value.trim();
                        if (value && !value.toLowerCase().includes('bpm')) {
                            value = value.replace(/\D/g, '');
                            if (value) this.value = value + ' bpm';
                        }
                    });
                }
            }

            // Auto-hide success message after 10 seconds
            const successAlert = document.querySelector('.success-alert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 10000);
            }

            // Update consultation time to current time when page loads
            function updateCurrentTime() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentTime = `${hours}:${minutes}`;
                
                const timeInput = document.getElementById('consultation_time');
                if (timeInput) {
                    timeInput.value = currentTime;
                }
            }

            // Update time immediately and then every minute
            updateCurrentTime();
            setInterval(updateCurrentTime, 60000);

            // Add fade-in animations to elements
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.15}s`;
            });
        });
    </script>
</body>
</html>