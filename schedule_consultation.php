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
    
    // Handle "Other" concern
    if ($concern === 'Other' && !empty($_POST['other_concern'])) {
        $concern = $_POST['other_concern'];
    }

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
  <title>Schedule Consultation - ASCOT Clinic</title>
  
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

    /* Header Styles - SAME AS DASHBOARD */
    .top-header {
        background: 
        linear-gradient(90deg, 
            #ffda6a 0%, 
            #ffda6a 30%, 
            #FFF5CC 70%, 
            #ffffff 100%);
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

    /* Mobile Menu Toggle - SAME AS DASHBOARD */
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

    /* Dashboard Container - SAME AS DASHBOARD */
    .dashboard-container {
        display: flex;
        min-height: calc(100vh - 80px);
    }

    /* Sidebar Styles - SAME AS DASHBOARD */
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

    .nav-item.logout {
        color: var(--danger);
        margin-top: auto;
    }

    .nav-item.logout:hover {
        background: rgba(220, 53, 69, 0.1);
    }

    /* Main Content - SAME AS DASHBOARD */
    .main-content {
        flex: 1;
        padding: 1.5rem;
        overflow-x: hidden;
        margin-left: 260px;
        margin-top: 0;
    }

    /* Sidebar Overlay for Mobile - SAME AS DASHBOARD */
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

    /* WELCOME SECTION - SAME AS DASHBOARD */
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

    /* KEEPING YOUR ORIGINAL CONSULTATION STYLES */
    .header-info-section {
        background: linear-gradient(135deg, rgba(255, 218, 106, 0.9) 0%, rgba(255, 247, 222, 0.95) 100%);
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

    /* Consultation Form Container */
    .consultation-form-container {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
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
        background: linear-gradient(135deg, #ffda6a, #ffda6a);
    }

    .consultation-form-container h4 {
        color: #555;
        font-weight: 700;
        margin-bottom: 25px;
        font-size: 1.5rem;
        border-bottom: 3px solid #ffda6a;
        padding-bottom: 10px;
    }

    /* Form Styles */
    .form-label {
        font-weight: 700;
        color: #555;
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

    /* Button Styles */
    .btn-primary {
        background: linear-gradient(135deg, #ffda6a, #ffda6a);
        border: none;
        border-radius: 25px;
        padding: 12px 30px;
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #555;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
       
    }

    /* Consultation Schedule Section */
    .consultation-schedule {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
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
        background: linear-gradient(135deg, #ffda6a, #ffda6a);
    }

    .schedule-title {
        color: #555;
        font-weight: 800;
        font-size: 1.5rem;
        border-bottom: 3px solid #ffda6a;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    /* Table Styles */
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

    /* Status Badges */
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

    /* Action Buttons */
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

    /* Modal Styles */
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

    /* Alert Styles */
    .alert {
        border-radius: 12px;
        border: none;
        margin: 15px 0;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
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

    /* Responsive Design - COMBINED FROM BOTH */
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

        .consultation-form-container,
        .consultation-schedule {
            padding: 25px;
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
    }

    @media (max-width: 576px) {
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

        .main-content {
            padding: 1.75rem 1rem 1rem;
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

    /* Animations */
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
    <!-- Mobile Menu Toggle Button - SAME AS DASHBOARD -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS DASHBOARD -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS DASHBOARD -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Sidebar - SAME AS DASHBOARD -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="student_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <a href="update_profile.php" class="nav-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>

                <a href="schedule_consultation.php" class="nav-item active">
                    <i class="fas fa-calendar-plus"></i>
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
            <!-- WELCOME SECTION - SAME AS DASHBOARD -->
            <div>
                <div>
                    
                </div>
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
                <form method="POST" action="" id="consultationForm">
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
                        <select name="concern" class="form-control" id="concernSelect" required>
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
                        
                        <!-- Other Concern Textbox (Hidden by default) -->
                        <div id="otherConcernContainer" class="mt-3" style="display: none;">
                            <label class="form-label"><strong>Please specify your concern:</strong></label>
                            <input type="text" name="other_concern" id="otherConcern" class="form-control" 
                                   placeholder="Please describe your specific concern...">
                            <small class="form-text text-muted">Type your specific reason for consultation</small>
                        </div>
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
        </main>
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

        // Function to handle concern selection
        document.addEventListener('DOMContentLoaded', function() {
            const concernSelect = document.getElementById('concernSelect');
            const otherConcernContainer = document.getElementById('otherConcernContainer');
            const otherConcernInput = document.getElementById('otherConcern');
            const consultationForm = document.getElementById('consultationForm');

            concernSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    otherConcernContainer.style.display = 'block';
                    otherConcernInput.required = true;
                } else {
                    otherConcernContainer.style.display = 'none';
                    otherConcernInput.required = false;
                    otherConcernInput.value = ''; // Clear the input when not needed
                }
            });

            // Form submission handling
            consultationForm.addEventListener('submit', function(e) {
                if (concernSelect.value === 'Other' && otherConcernInput.value.trim() === '') {
                    e.preventDefault();
                    alert('Please specify your concern in the "Other" field.');
                    otherConcernInput.focus();
                    return;
                }
            });

            // MOBILE MENU FUNCTIONALITY - SAME AS DASHBOARD
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
    </script>
</body>
</html>