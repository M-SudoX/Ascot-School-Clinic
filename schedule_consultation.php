<?php
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

/* ===============================
   ✅ CREATE NEW CONSULTATION
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $student_id = $_SESSION['student_id'];
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $concern = $_POST['concern'] ?? '';
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO consultation_requests (student_id, date, time, requested, notes, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$student_id, $date, $time, $concern, $notes]);
        
        // ✅ SPECIFIC ACTION: Consultation Scheduled
        logActivity($pdo, $student_id, "Scheduled consultation: " . $concern);
        
        $_SESSION['success_message'] = 'Your consultation request has been submitted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database Error: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ EDIT CONSULTATION
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['consultation_id'];
    $date = $_POST['edit_date'];
    $time = $_POST['edit_time'];
    $concern = $_POST['edit_concern'];
    $notes = $_POST['edit_notes'];

    try {
        $stmt = $pdo->prepare("UPDATE consultation_requests SET date = ?, time = ?, requested = ?, notes = ? WHERE id = ? AND status IN ('Pending', 'Approved')");
        $stmt->execute([$date, $time, $concern, $notes, $id]);
        
        // ✅ SPECIFIC ACTION: Consultation Edited
        logActivity($pdo, $student_id, "Edited consultation: " . $concern);
        
        $_SESSION['success_message'] = 'Consultation updated successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ CANCEL CONSULTATION
================================= */
if (isset($_GET['cancel'])) {
    $id = $_GET['cancel'];
    try {
        // Kunin muna ang consultation details bago i-delete
        $get_stmt = $pdo->prepare("SELECT requested FROM consultation_requests WHERE id = ?");
        $get_stmt->execute([$id]);
        $consultation = $get_stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM consultation_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        
        // ✅ SPECIFIC ACTION: Consultation Cancelled
        if ($consultation) {
            logActivity($pdo, $student_id, "Cancelled consultation: " . $consultation['requested']);
        }
        
        $_SESSION['success_message'] = 'Consultation cancelled and deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

// ... (REST OF YOUR EXISTING SCHEDULE_CONSULTATION CODE) ...

/* ===============================
   ✅ CANCEL CONSULTATION (DELETE RECORD)
================================= */
if (isset($_GET['cancel'])) {
    $id = $_GET['cancel'];
    try {
        $stmt = $pdo->prepare("DELETE FROM consultation_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        
        // ✅ I-LOG ANG PAG-CANCEL NG CONSULTATION
        logActivity($pdo, $student_id, "Cancelled consultation request");
        
        $_SESSION['success_message'] = 'Consultation cancelled and deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ DISPLAY MESSAGES - FIXED VARIABLES
================================= */
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

/* ===============================
   ✅ FETCH CONSULTATIONS
================================= */
$student_id = $_SESSION['student_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM consultation_requests WHERE student_id = ? ORDER BY date DESC");
    $stmt->execute([$student_id]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $consultations = [];
    $error_message = "Error fetching consultations: " . $e->getMessage();
}

// ✅ Helper functions
function formatTime($time) { 
    return date('g:i A', strtotime($time)); 
}

function formatDate($date) { 
    return date('M d', strtotime($date)); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule Consultation - ASCOT Online School Clinic</title>

  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/schedule_consultation.css" rel="stylesheet">
  
  <style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --accent-color: #e74c3c;
        --success-color: #27ae60;
        --warning-color: #f39c12;
        --sidebar-width: 280px;
        --header-height: 80px;
    }

    * {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* ========== ENHANCED HEADER DESIGN ========== */
    .header {
        background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255,255,255,0.2);
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        padding: 15px 0;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        height: var(--header-height);
    }

    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        opacity: 0.05;
        z-index: -1;
    }

    .header .logo-img {
        height: 80px;
        width: 80px;
        margin-top: -15px;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
        transition: transform 0.3s ease;
    }

    .header .logo-img:hover {
        transform: scale(1.05);
    }

    .header .college-info {
        text-align: center;
    }

    .header .college-info h4 {
        font-size: 1rem;
        margin-bottom: 0.2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #2c3e50, #3498db);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .header .college-info p {
        font-size: 0.85rem;
        margin-bottom: 0;
        color: #7f8c8d;
        font-weight: 600;
        letter-spacing: 1px;
    }

    /* ========== ENHANCED SIDEBAR DESIGN ========== */
    .sidebar {
        background: linear-gradient(135deg, rgba(44, 62, 80, 0.95) 0%, rgba(52, 73, 94, 0.98) 100%);
        backdrop-filter: blur(20px);
        border-right: 1px solid rgba(255,255,255,0.1);
        box-shadow: 8px 0 32px rgba(0,0,0,0.2);
        min-height: calc(100vh - var(--header-height));
        padding: 30px 0;
        position: fixed;
        top: var(--header-height);
        left: 0;
        width: var(--sidebar-width);
        z-index: 999;
        overflow-y: auto;
        margin-top: -45px
    }

    .sidebar .nav {
        padding: 0 20px;
    }

    .sidebar .nav-link {
        color: #ecf0f1 !important;
        padding: 15px 20px;
        margin: 8px 0;
        border-radius: 12px;
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s ease;
    }

    .sidebar .nav-link:hover::before {
        left: 100%;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.2) 0%, rgba(41, 128, 185, 0.2) 100%);
        border-left: 4px solid #3498db;
        transform: translateX(8px);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }

    .sidebar .nav-link i {
        width: 25px;
        text-align: center;
        margin-right: 15px;
        font-size: 1.1rem;
        transition: transform 0.3s ease;
    }

    .sidebar .nav-link:hover i {
        transform: scale(1.2);
    }

    .sidebar .nav-link.active i {
        color: #3498db;
    }

    .logout-btn .nav-link {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.2) 0%, rgba(192, 57, 43, 0.2) 100%);
        border: 1px solid rgba(231, 76, 60, 0.3);
        margin-top: 20px;
    }

    .logout-btn .nav-link:hover {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.3) 0%, rgba(192, 57, 43, 0.3) 100%);
        border-left: 4px solid #e74c3c;
        transform: translateX(8px);
    }

    /* ========== MOBILE SIDEBAR ENHANCEMENTS ========== */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1100;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 1.3rem;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        transition: all 0.3s ease;
    }

    .mobile-menu-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 998;
        backdrop-filter: blur(5px);
    }

    /* ========== MAIN CONTENT ENHANCEMENTS ========== */
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 30px;
        background: rgba(248, 249, 250, 0.95);
        backdrop-filter: blur(10px);
        min-height: calc(100vh - var(--header-height));
        margin-top: var(--header-height);
    }

    /* ========== HEADER INFO SECTION ========== */
    .header-info-section {
        background: linear-gradient(135deg, rgba(255, 218, 106, 0.9) 0%, rgba(255, 247, 222, 0.95) 100%);
        backdrop-filter: blur(10px);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        text-align: center;
    }

    .header-info-section h3 {
        color: #2c3e50;
        font-weight: 800;
        font-size: 2rem;
        margin-bottom: 10px;
        background: linear-gradient(135deg, #2c3e50, #3498db);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .header-info-section p {
        color: #7f8c8d;
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }

    /* ========== CONSULTATION FORM ENHANCEMENT ========== */
    .consultation-form-container {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
        backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.2);
        position: relative;
        overflow: hidden;
    }

    .consultation-form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .consultation-form-container h4 {
        color: #2c3e50;
        font-weight: 700;
        margin-bottom: 25px;
        font-size: 1.5rem;
        border-bottom: 3px solid #3498db;
        padding-bottom: 10px;
    }

    /* ========== FORM STYLES ========== */
    .form-label {
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 12px 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: rgba(255,255,255,0.8);
    }

    .form-control:focus, .form-select:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        transform: translateY(-2px);
    }

    .form-text {
        font-size: 0.85rem;
        color: #6c757d;
    }

    /* ========== BUTTON STYLES ========== */
    .btn-primary {
        background: linear-gradient(135deg, #3498db, #2980b9);
        border: none;
        border-radius: 25px;
        padding: 12px 30px;
        font-weight: 700;
        font-size: 1.1rem;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.6);
        background: linear-gradient(135deg, #2980b9, #21618c);
    }

    /* ========== CONSULTATION SCHEDULE SECTION ========== */
    .consultation-schedule {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
        backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        position: relative;
        overflow: hidden;
    }

    .consultation-schedule::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }

    .schedule-title {
        color: #2c3e50;
        font-weight: 800;
        font-size: 1.5rem;
        border-bottom: 3px solid #e74c3c;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    /* ========== TABLE STYLES ========== */
    .table {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .table-dark {
        background: linear-gradient(135deg, #2c3e50, #34495e) !important;
    }

    .table th {
        border: none;
        font-weight: 700;
        padding: 15px;
    }

    .table td {
        padding: 15px;
        vertical-align: middle;
        border-color: #e9ecef;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(248, 249, 250, 0.5);
    }

    .table-hover tbody tr:hover {
        background-color: rgba(52, 152, 219, 0.1);
        transform: translateX(5px);
        transition: all 0.3s ease;
    }

    /* ========== STATUS BADGES ========== */
    .status-pending { 
        background: linear-gradient(135deg, #fff3cd, #ffeaa7); 
        color: #856404; 
        padding: 8px 12px; 
        border-radius: 20px; 
        font-size: 0.85rem; 
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(133, 100, 4, 0.2);
    }
    .status-approved { 
        background: linear-gradient(135deg, #d4edda, #c3e6cb); 
        color: #155724; 
        padding: 8px 12px; 
        border-radius: 20px; 
        font-size: 0.85rem; 
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(21, 87, 36, 0.2);
    }
    .status-rejected { 
        background: linear-gradient(135deg, #f8d7da, #f5c6cb); 
        color: #721c24; 
        padding: 8px 12px; 
        border-radius: 20px; 
        font-size: 0.85rem; 
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(114, 28, 36, 0.2);
    }
    .status-completed { 
        background: linear-gradient(135deg, #cce7ff, #b3d9ff); 
        color: #004085; 
        padding: 8px 12px; 
        border-radius: 20px; 
        font-size: 0.85rem; 
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(0, 64, 133, 0.2);
    }

    /* ========== ACTION BUTTONS ========== */
    .btn-action {
        border: none;
        background: rgba(255,255,255,0.8);
        padding: 8px 12px;
        margin: 0 3px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .btn-view { color: #17a2b8; }
    .btn-edit { color: #ffc107; }
    .btn-cancel { color: #dc3545; }
    
    .btn-action:hover {
        transform: scale(1.1);
        background: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    /* ========== MODAL STYLES ========== */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }

    .modal-header {
        border-bottom: 2px solid rgba(0,0,0,0.1);
        padding: 20px 25px;
    }

    .modal-body {
        padding: 25px;
    }

    .modal-footer {
        border-top: 2px solid rgba(0,0,0,0.1);
        padding: 20px 25px;
    }

    .bg-primary {
        background: linear-gradient(135deg, #3498db, #2980b9) !important;
    }

    .bg-warning {
        background: linear-gradient(135deg, #ffc107, #e0a800) !important;
    }

    /* ========== ALERT STYLES ========== */
    .alert {
        border-radius: 12px;
        border: none;
        margin: 15px 0;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
        padding: 15px 20px;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.9) 0%, rgba(33, 154, 82, 0.95) 100%);
        color: white;
    }

    .alert-danger {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.9) 0%, rgba(192, 57, 43, 0.95) 100%);
        color: white;
    }

    /* ========== RESPONSIVE BREAKPOINTS ========== */
    @media (max-width: 1199.98px) {
        .consultation-form-container,
        .consultation-schedule {
            padding: 25px;
        }
    }

    @media (max-width: 991.98px) {
        .sidebar {
            left: -100%;
            width: 300px;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar.active {
            left: 0;
        }

        .mobile-menu-btn {
            display: block;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .main-content {
            margin-left: 0;
            padding: 20px;
        }

        .header-info-section h3 {
            font-size: 1.6rem;
        }
    }

    @media (max-width: 767.98px) {
        :root {
            --header-height: 70px;
        }

        .header {
            padding: 10px 0;
        }

        .header .logo-img {
            height: 40px;
        }

        .main-content {
            padding: 15px;
            margin-top: 70px;
        }

        .consultation-form-container,
        .consultation-schedule {
            padding: 20px;
        }

        .header-info-section {
            padding: 20px;
        }

        .header-info-section h3 {
            font-size: 1.4rem;
        }

        .btn-action {
            padding: 6px 10px;
            margin: 0 2px;
        }

        .table-responsive {
            font-size: 0.9rem;
        }

        .mobile-menu-btn {
            top: 15px;
            left: 15px;
            padding: 10px 14px;
            font-size: 1.2rem;
        }
    }

    @media (max-width: 575.98px) {
        .header-info-section h3 {
            font-size: 1.3rem;
        }

        .consultation-form-container,
        .consultation-schedule {
            padding: 15px;
        }

        .btn-primary {
            width: 100%;
            justify-content: center;
        }

        .table td, .table th {
            padding: 10px 8px;
        }

        .status-pending, .status-approved, 
        .status-rejected, .status-completed {
            font-size: 0.75rem;
            padding: 6px 10px;
        }
    }

    /* ========== ANIMATIONS ========== */
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

    /* ========== CUSTOM SCROLLBAR ========== */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.1);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #3498db, #2980b9);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #2980b9, #21618c);
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

    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="logo">
                        <img src="img/logo.png" alt="Aurora State College of Technology Logo" class="logo-img">
                    </div>
                </div>
                <div class="col">
                    <div class="college-info text-center">
                        <h4>Republic of the Philippines</h4>
                        <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
                        <p>ONLINE SCHOOL CLINIC</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar + Main -->
    <div class="container-fluid">
        <div class="row">
            <!-- ENHANCED SIDEBAR NAVIGATION -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link active" href="schedule_consultation.php"><i class="fas fa-calendar-plus"></i> Schedule Consultation</a>
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                    <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                    <a class="nav-link" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </nav>
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="header-info-section fade-in">
                    <h3>Schedule Consultation</h3>
                    <p>Book your medical consultation with the school clinic</p>
                </div>

                <!-- Alerts - FIXED: Using properly initialized variables -->
                <div id="alertContainer" class="alert-container fade-in">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <strong>Success!</strong> <?= htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> <?= htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Consultation Form -->
                <div class="consultation-form-container fade-in">
                    <h4 class="mb-4"><i class="fas fa-calendar-plus me-2"></i>New Consultation Request</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label"><strong>Date:</strong></label>
                                    <input type="date" name="date" class="form-control" 
                                           min="<?= date('Y-m-d'); ?>" 
                                           value="<?= date('Y-m-d'); ?>" 
                                           required>
                                    <small class="form-text text-muted">Select your preferred date</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label"><strong>Time:</strong></label>
                                    <select name="time" class="form-control" required>
                                        <option value="">Select Time</option>
                                        <option value="08:00">8:00 AM</option>
                                        <option value="08:30">8:30 AM</option>
                                        <option value="09:00">9:00 AM</option>
                                        <option value="09:30">9:30 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="10:30">10:30 AM</option>
                                        <option value="13:00">1:00 PM</option>
                                        <option value="13:30">1:30 PM</option>
                                        <option value="14:00">2:00 PM</option>
                                        <option value="14:30">2:30 PM</option>
                                        <option value="15:00">3:00 PM</option>
                                        <option value="15:30">3:30 PM</option>
                                    </select>
                                    <small class="form-text text-muted">Choose your preferred time</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="form-label"><strong>Reason/Concern:</strong></label>
                            <select name="concern" class="form-control" required>
                                <option value="">Select Concern</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Medical Clearance">Medical Clearance</option>
                                <option value="General Consultation">General Consultation</option>
                                <option value="First Aid">First Aid</option>
                                <option value="Health Checkup">Health Checkup</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Dental Checkup">Dental Checkup</option>
                                <option value="Mental Health Consultation">Mental Health Consultation</option>
                                <option value="Vaccination">Vaccination</option>
                                <option value="Other">Other</option>
                            </select>
                            <small class="form-text text-muted">What is the reason for your consultation?</small>
                        </div>
                        
                        <div class="form-group mb-4">
                            <label class="form-label"><strong>Additional Notes (Optional):</strong></label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="Please provide any additional information about your condition or concerns..."></textarea>
                            <small class="form-text text-muted">Any details that might help the medical staff</small>
                        </div>
                        
                        <div class="form-actions text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> SUBMIT CONSULTATION REQUEST
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Consultation Table -->
                <div class="consultation-schedule fade-in">
                    <h3 class="schedule-title"><i class="fas fa-calendar-alt me-2"></i>YOUR CONSULTATION SCHEDULE</h3>
                    
                    <?php if (empty($consultations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No consultation requests yet</h5>
                            <p class="text-muted">Schedule your first consultation using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="schedule-table-container">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Concern</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($consultations as $c): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars(formatDate($c['date'])); ?></strong></td>
                                                <td><?= htmlspecialchars(formatTime($c['time'])); ?></td>
                                                <td><?= htmlspecialchars($c['requested']); ?></td>
                                                <td>
                                                    <span class="status-<?= strtolower($c['status']); ?>">
                                                        <?= htmlspecialchars($c['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn-action btn-view" 
                                                            onclick='viewConsultation(<?= json_encode($c); ?>)'
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($c['status'] === 'Pending' || $c['status'] === 'Approved'): ?>
                                                        <button class="btn-action btn-edit" 
                                                                onclick='openEditModal(<?= json_encode($c); ?>)'
                                                                title="Edit Consultation">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($c['status'] === 'Pending'): ?>
                                                        <a href="?cancel=<?= $c['id']; ?>" 
                                                           class="btn-action btn-cancel" 
                                                           onclick="return confirm('Are you sure you want to cancel this consultation?')"
                                                           title="Cancel Consultation">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Consultation Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewBody">
                    <!-- Content will be loaded by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="consultation_id" id="edit_consultation_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Consultation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Date:</strong></label>
                        <input type="date" id="edit_date" name="edit_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Time:</strong></label>
                        <input type="time" id="edit_time" name="edit_time" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Concern:</strong></label>
                        <input type="text" id="edit_concern" name="edit_concern" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Notes:</strong></label>
                        <textarea id="edit_notes" name="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));

        function viewConsultation(c) {
            const body = document.getElementById('viewBody');
            body.innerHTML = `
                <div class="consultation-details">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Date:</strong><br>${c.date}</p>
                            <p><strong>Time:</strong><br>${c.time}</p>
                            <p><strong>Status:</strong><br><span class="status-${c.status.toLowerCase()}">${c.status}</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Concern:</strong><br>${c.requested}</p>
                            <p><strong>Created:</strong><br>${c.created_at}</p>
                        </div>
                    </div>
                    ${c.notes ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Additional Notes:</strong></p>
                            <div class="alert alert-info">${c.notes}</div>
                        </div>
                    </div>` : ''}
                </div>
            `;
            viewModal.show();
        }

        function openEditModal(c) {
            document.getElementById('edit_consultation_id').value = c.id;
            document.getElementById('edit_date').value = c.date;
            document.getElementById('edit_time').value = c.time;
            document.getElementById('edit_concern').value = c.requested;
            document.getElementById('edit_notes').value = c.notes || '';
            editModal.show();
        }

        // MOBILE SIDEBAR FUNCTIONALITY
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                
                const icon = mobileMenuBtn.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                } else {
                    icon.className = 'fas fa-bars';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
                }
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
            }
            
            if (mobileMenuBtn && sidebar && sidebarOverlay) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991.98) {
                            closeSidebar();
                        }
                    });
                });
                
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                });
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });

            // Add loading animations
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>