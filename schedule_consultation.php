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

// ✅ FIX #1: CSRF TOKEN GENERATION
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ===============================
   ✅ CREATE NEW CONSULTATION
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    
    // ✅ FIX #2: CSRF TOKEN VALIDATION
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Invalid security token. Please try again.';
        header("Location: schedule_consultation.php");
        exit();
    }
    
    $student_id = $_SESSION['student_id'];
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $concern = $_POST['concern'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // ✅ FIX #3: INPUT LENGTH VALIDATION
    if (strlen($concern) > 255) {
        $_SESSION['error_message'] = 'Concern is too long. Maximum 255 characters allowed.';
        header("Location: schedule_consultation.php");
        exit();
    }
    
    if (strlen($notes) > 1000) {
        $_SESSION['error_message'] = 'Notes are too long. Maximum 1000 characters allowed.';
        header("Location: schedule_consultation.php");
        exit();
    }
    
    // Handle "Other" concern
    if ($concern === 'Other' && !empty($_POST['other_concern'])) {
        $concern = $_POST['other_concern'];
        // Validate other concern length too
        if (strlen($concern) > 255) {
            $_SESSION['error_message'] = 'Other concern is too long. Maximum 255 characters allowed.';
            header("Location: schedule_consultation.php");
            exit();
        }
    }

    // ✅ VALIDATION: Check if the selected date/time is in the past
    $selected_datetime = strtotime($date . ' ' . $time);
    $current_datetime = time();
    
    if ($selected_datetime <= $current_datetime) {
        $_SESSION['error_message'] = 'You cannot schedule a consultation for a past date/time. Please select a future date and time.';
        header("Location: schedule_consultation.php");
        exit();
    }

   // ✅ FIX #5: CONSULTATION LIMIT CHECK (MAX 3)
  try {
    $limit_stmt = $pdo->prepare("SELECT COUNT(*) as consultation_count FROM consultation_requests WHERE student_id = ? AND status IN ('Pending', 'Approved')");
    $limit_stmt->execute([$student_id]);
    $consultation_count = $limit_stmt->fetch(PDO::FETCH_ASSOC)['consultation_count'];
    
    if ($consultation_count >= 3) {
        $_SESSION['error_message'] = 'You have reached the maximum limit of 3 active consultations. Please wait for some to be completed or cancel existing ones.';
        header("Location: schedule_consultation.php");
        exit();
    }
        
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
    
    // ✅ FIX #2: CSRF TOKEN VALIDATION
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Invalid security token. Please try again.';
        header("Location: schedule_consultation.php");
        exit();
    }
    
    $id = $_POST['consultation_id'];
    $date = $_POST['edit_date'];
    $time = $_POST['edit_time'];
    $concern = $_POST['edit_concern'];
    $notes = $_POST['edit_notes'];

    // ✅ FIX #3: INPUT LENGTH VALIDATION
    if (strlen($concern) > 255) {
        $_SESSION['error_message'] = 'Concern is too long. Maximum 255 characters allowed.';
        header("Location: schedule_consultation.php");
        exit();
    }
    
    if (strlen($notes) > 1000) {
        $_SESSION['error_message'] = 'Notes are too long. Maximum 1000 characters allowed.';
        header("Location: schedule_consultation.php");
        exit();
    }

    // ✅ VALIDATION: Check if the selected date/time is in the past
    $selected_datetime = strtotime($date . ' ' . $time);
    $current_datetime = time();
    
    if ($selected_datetime <= $current_datetime) {
        $_SESSION['error_message'] = 'You cannot schedule a consultation for a past date/time. Please select a future date and time.';
        header("Location: schedule_consultation.php");
        exit();
    }

    try {
        // ✅ FIX #4: VERIFY OWNERSHIP BEFORE EDITING
        $check_stmt = $pdo->prepare("SELECT id FROM consultation_requests WHERE id = ? AND student_id = ? AND status IN ('Pending', 'Approved')");
        $check_stmt->execute([$id, $student_id]);
        
        if (!$check_stmt->fetch()) {
            $_SESSION['error_message'] = 'Consultation not found or you are not authorized to edit it.';
            header("Location: schedule_consultation.php");
            exit();
        }
        
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
        // ✅ FIX #4: VERIFY OWNERSHIP BEFORE CANCELLING
        $check_stmt = $pdo->prepare("SELECT requested FROM consultation_requests WHERE id = ? AND student_id = ? AND status = 'Pending'");
        $check_stmt->execute([$id, $student_id]);
        $consultation = $check_stmt->fetch();
        
        if (!$consultation) {
            $_SESSION['error_message'] = 'Consultation not found or you are not authorized to cancel it.';
            header("Location: schedule_consultation.php");
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM consultation_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        
        // ✅ SPECIFIC ACTION: Consultation Cancelled
        logActivity($pdo, $student_id, "Cancelled consultation: " . $consultation['requested']);
        
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
    
    // ✅ Get consultation count for display
    $active_consultations = array_filter($consultations, function($c) {
        return in_array($c['status'], ['Pending', 'Approved']);
    });
    $consultation_count = count($active_consultations);
    
} catch (PDOException $e) {
    $consultations = [];
    $consultation_count = 0;
    $error_message = "Error fetching consultations: " . $e->getMessage();
}

// ✅ Helper functions
function formatTime($time) { 
    return date('g:i A', strtotime($time)); 
}

function formatDate($date) { 
    return date('M d', strtotime($date)); 
}

// ✅ Get current date and time for validation
$current_date = date('Y-m-d');
$current_time = date('H:i');
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
        padding-top: 80px;
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
        height: 80px;
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
        width: 60px;
        height: 60px;
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
        font-size: 0.7rem;
        opacity: 0.9;
        letter-spacing: 0.5px;
        color: var(--text-dark);
        font-weight: 600;
    }

    .school-name {
        font-size: 1.1rem;
        font-weight: 800;
        margin: 0.1rem 0;
        line-height: 1.2;
        color: var(--text-dark);
        background: linear-gradient(135deg, var(--text-dark), #495057);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .clinic-title {
        font-size: 0.8rem;
        opacity: 0.9;
        font-weight: 600;
        color: var(--text-dark);
        letter-spacing: 0.5px;
    }

    /* Mobile Menu Toggle - ENHANCED */
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
        min-height: calc(100vh - 80px);
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
        top: 80px;
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
        width: 24px;
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

    .nav-item.logout {
        color: var(--danger);
        margin-top: auto;
        border-left: 6px solid transparent;
    }

    .nav-item.logout:hover {
        background: rgba(220, 53, 69, 0.1);
        color: var(--danger);
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
        top: 80px;
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

    /* HEADER INFO SECTION - ENHANCED */
    .header-info-section {
        background: linear-gradient(135deg, rgba(255, 218, 106, 0.95) 0%, rgba(255, 247, 222, 0.98) 100%);
        padding: 2.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255,255,255,0.3);
        text-align: center;
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
    }

    .header-info-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.3;
    }

    .header-info-section h3 {
        color: var(--text-dark);
        font-weight: 800;
        font-size: 2.2rem;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, var(--text-dark), #495057);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        position: relative;
        z-index: 2;
    }

    .header-info-section p {
        color: var(--text-light);
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
        position: relative;
        z-index: 2;
        letter-spacing: 0.5px;
    }

    /* Consultation Limit Badge */
    .consultation-limit-badge {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .consultation-limit-badge.warning {
        background: linear-gradient(135deg, var(--warning), #e0a800);
    }

    .consultation-limit-badge.danger {
        background: linear-gradient(135deg, var(--danger), #c82333);
    }

    /* Consultation Form Container - ENHANCED */
    .consultation-form-container {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(20px);
        border-radius: var(--border-radius);
        padding: 2.5rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255,255,255,0.3);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .consultation-form-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }

    .consultation-form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .consultation-form-container:hover::before {
        left: 100%;
    }

    .consultation-form-container::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(135deg, var(--accent), #ffd24a);
        border-radius: 5px 5px 0 0;
    }

    .consultation-form-container h4 {
        color: var(--text-dark);
        font-weight: 800;
        margin-bottom: 2rem;
        font-size: 1.6rem;
        border-bottom: 3px solid var(--accent-light);
        padding-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    /* Form Styles - ENHANCED */
    .form-label {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-label::before {
        content: '•';
        color: var(--primary);
        font-weight: bold;
        font-size: 1.2rem;
    }

    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        font-size: 1rem;
        transition: var(--transition);
        background: rgba(255,255,255,0.9);
        font-weight: 500;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
        background: rgba(255,255,255,0.95);
    }

    .form-text {
        font-size: 0.85rem;
        color: var(--text-light);
        margin-top: 0.5rem;
        font-weight: 500;
    }

    /* Button Styles - ENHANCED */
    .btn-primary {
        background: linear-gradient(135deg, var(--accent), #ffd24a);
        border: none;
        border-radius: 25px;
        padding: 1.25rem 3rem;
        font-weight: 700;
        font-size: 1.1rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--text-dark);
        box-shadow: 0 6px 20px rgba(255,218,106,0.4);
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(255,218,106,0.5);
        color: var(--text-dark);
    }

    .btn-primary:disabled {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-primary:disabled:hover {
        transform: none;
        box-shadow: none;
    }

    /* Consultation Schedule Section - ENHANCED */
    .consultation-schedule {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(20px);
        border-radius: var(--border-radius);
        padding: 2.5rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255,255,255,0.3);
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .consultation-schedule:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }

    .consultation-schedule::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .consultation-schedule:hover::before {
        left: 100%;
    }

    .consultation-schedule::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(135deg, var(--accent), #ffd24a);
        border-radius: 5px 5px 0 0;
    }

    .schedule-title {
        color: var(--text-dark);
        font-weight: 800;
        font-size: 1.6rem;
        border-bottom: 3px solid var(--accent-light);
        padding-bottom: 1rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    /* Table Styles - ENHANCED */
    .table {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        background: rgba(255,255,255,0.9);
    }

    .table-dark {
        background: linear-gradient(135deg, var(--text-dark), #34495e) !important;
    }

    .table th {
        border: none;
        font-weight: 700;
        padding: 1.25rem 1rem;
        background: linear-gradient(135deg, var(--text-dark), #34495e);
        color: white;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border-color: rgba(233, 236, 239, 0.8);
        color: var(--text-dark);
        font-weight: 500;
        transition: var(--transition);
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(248, 249, 250, 0.7);
    }

    .table-hover tbody tr:hover {
        background-color: rgba(102, 126, 234, 0.08);
        transform: translateX(5px);
    }

    /* Status Badges - ENHANCED */
    .status-pending { 
        background: linear-gradient(135deg, #fff3cd, #ffeaa7); 
        color: #856404; 
        padding: 0.75rem 1.25rem; 
        border-radius: 25px; 
        font-size: 0.85rem; 
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(133, 100, 4, 0.2);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-approved { 
        background: linear-gradient(135deg, #d4edda, #c3e6cb); 
        color: #155724; 
        padding: 0.75rem 1.25rem; 
        border-radius: 25px; 
        font-size: 0.85rem; 
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(21, 87, 36, 0.2);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-rejected { 
        background: linear-gradient(135deg, #f8d7da, #f5c6cb); 
        color: #721c24; 
        padding: 0.75rem 1.25rem; 
        border-radius: 25px; 
        font-size: 0.85rem; 
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(114, 28, 36, 0.2);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-completed { 
        background: linear-gradient(135deg, #cce7ff, #b3d9ff); 
        color: #004085; 
        padding: 0.75rem 1.25rem; 
        border-radius: 25px; 
        font-size: 0.85rem; 
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(0, 64, 133, 0.2);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Action Buttons - ENHANCED */
    .btn-action {
        border: none;
        background: rgba(255,255,255,0.9);
        padding: 0.75rem;
        margin: 0 0.25rem;
        border-radius: 10px;
        transition: var(--transition);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-view { color: var(--info); }
    .btn-edit { color: var(--warning); }
    .btn-cancel { color: var(--danger); }
    
    .btn-action:hover {
        transform: scale(1.1);
        background: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    /* Modal Styles - ENHANCED */
    .modal-content {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow);
        overflow: hidden;
        backdrop-filter: blur(20px);
        background: rgba(255,255,255,0.95);
    }

    .modal-header {
        border-bottom: 2px solid rgba(0,0,0,0.1);
        padding: 1.5rem 2rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
    }

    .modal-header .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .modal-body {
        padding: 2rem;
    }

    .modal-footer {
        border-top: 2px solid rgba(0,0,0,0.1);
        padding: 1.5rem 2rem;
    }

    .bg-warning {
        background: linear-gradient(135deg, var(--warning), #e0a800) !important;
    }

    /* Alert Styles - ENHANCED */
    .alert {
        border-radius: 12px;
        border: none;
        margin: 1rem 0;
        box-shadow: var(--shadow);
        padding: 1.25rem 1.5rem;
        backdrop-filter: blur(10px);
        border-left: 6px solid;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.95) 0%, rgba(33, 154, 82, 0.98) 100%);
        color: white;
        border-left-color: #27ae60;
    }

    .alert-danger {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.95) 0%, rgba(192, 57, 43, 0.98) 100%);
        color: white;
        border-left-color: #e74c3c;
    }

    .alert i {
        margin-right: 0.75rem;
        font-size: 1.2rem;
    }

    /* Empty State - ENHANCED */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-light);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        color: #dee2e6;
        opacity: 0.7;
        display: block;
    }

    .empty-state h5 {
        color: var(--text-light);
        margin-bottom: 1rem;
        font-weight: 600;
        font-size: 1.3rem;
    }

    .empty-state p {
        color: #999;
        font-size: 1rem;
        line-height: 1.6;
        margin: 0;
    }

    /* Autocomplete Styles */
    .autocomplete-container {
        position: relative;
    }

    .autocomplete-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid var(--primary);
        border-top: none;
        border-radius: 0 0 12px 12px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .autocomplete-suggestion {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: var(--transition);
        font-weight: 500;
    }

    .autocomplete-suggestion:hover,
    .autocomplete-suggestion.active {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
        color: var(--primary);
    }

    .autocomplete-suggestion:last-child {
        border-bottom: none;
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
            width: 50px;
            height: 50px;
        }

        .consultation-form-container,
        .consultation-schedule {
            padding: 2rem;
        }

        .header-info-section {
            padding: 2rem;
        }

        .header-info-section h3 {
            font-size: 1.8rem;
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
            display: flex;
            align-items: center;
            justify-content: center;
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
            width: 300px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(30px);
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

        .consultation-form-container,
        .consultation-schedule {
            padding: 1.5rem;
        }

        .header-info-section {
            padding: 1.5rem;
        }

        .header-info-section h3 {
            font-size: 1.5rem;
        }

        .btn-action {
            padding: 0.5rem;
            margin: 0 0.125rem;
            width: 38px;
            height: 38px;
        }

        .table-responsive {
            font-size: 0.9rem;
        }

        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 576px) {
        .header-info-section h3 {
            font-size: 1.3rem;
        }

        .consultation-form-container,
        .consultation-schedule {
            padding: 1.25rem;
        }

        .btn-primary {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        .table td, .table th {
            padding: 1rem 0.75rem;
        }

        .status-pending, .status-approved, 
        .status-rejected, .status-completed {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
        }

        .main-content {
            padding: 1.25rem;
        }
        
        .mobile-menu-toggle {
            top: 80px;
            width: 45px;
            height: 45px;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-header,
        .modal-footer {
            padding: 1.25rem 1.5rem;
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

        .consultation-form-container,
        .consultation-schedule {
            padding: 1rem;
        }

        .header-info-section {
            padding: 1.25rem;
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

        .consultation-form-container,
        .consultation-schedule {
            padding: 0.75rem;
        }

        .table-responsive {
            font-size: 0.8rem;
        }
    }

    /* Animations - ENHANCED */
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

    /* Loading States */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    /* Focus States for Accessibility */
    .focus-visible {
        outline: 3px solid var(--primary);
        outline-offset: 2px;
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

    /* Form Row Enhancements */
    .form-row-enhanced {
        margin-bottom: 1.5rem;
    }

    .form-row-enhanced .form-control:focus,
    .form-row-enhanced .form-select:focus {
        transform: translateY(-2px);
    }

    /* Touch Device Improvements */
    .touch-device .btn-action {
        padding: 1rem;
        width: 44px;
        height: 44px;
    }

    .touch-device .btn-primary {
        min-height: 54px;
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

    <!-- Header - ENHANCED -->
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
        <!-- Sidebar - ENHANCED -->
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
            <!-- Alerts - ENHANCED -->
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

            <!-- HEADER INFO SECTION - ENHANCED -->
            <div class="header-info-section fade-in">
                <h3><i class="fas fa-calendar-plus me-3"></i>Schedule Consultation</h3>
                <p>Book your medical consultation with our healthcare professionals</p>
                
                <!-- Consultation Limit Badge -->
                <div class="consultation-limit-badge <?= $consultation_count >= 4 ? 'warning' : '' ?> <?= $consultation_count >= 5 ? 'danger' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    Active Consultations: <?= $consultation_count ?>/3
                    <?php if ($consultation_count >= 3): ?>
                        <i class="fas fa-exclamation-triangle ms-1"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Consultation Form - ENHANCED -->
            <div class="consultation-form-container fade-in">
                <h4><i class="fas fa-calendar-plus me-2"></i>New Consultation Request</h4>
                <form method="POST" action="" id="consultationForm">
                    <input type="hidden" name="action" value="create">
                    <!-- ✅ CSRF TOKEN FIELD -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="row form-row-enhanced">
                        <div class="col-md-6 mb-4">
                            <div class="form-group">
                                <label class="form-label"><strong>Date:</strong></label>
                                <input type="date" name="date" class="form-control" 
                                       min="<?= $current_date; ?>" 
                                       value="<?= $current_date; ?>" 
                                       required>
                                <small class="form-text">Select your preferred date</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="form-group">
                                <label class="form-label"><strong>Time:</strong></label>
                                <select name="time" class="form-select" id="timeSelect" required>
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
                                <small class="form-text">Choose your preferred time</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label"><strong>Reason/Concern:</strong></label>
                        <select name="concern" class="form-select" id="concernSelect" required>
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
                        <small class="form-text">What is the reason for your consultation?</small>
                        
                        <!-- Other Concern Textbox with Autocomplete -->
                        <div id="otherConcernContainer" class="mt-3" style="display: none;">
                            <label class="form-label"><strong>Please specify your concern:</strong></label>
                            <div class="autocomplete-container">
                                <input type="text" name="other_concern" id="otherConcern" class="form-control" 
                                       placeholder="Start typing to see suggestions...">
                                <div id="autocompleteSuggestions" class="autocomplete-suggestions" style="display: none;"></div>
                            </div>
                            <small class="form-text">Type your specific reason for consultation or select from suggestions</small>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label"><strong>Additional Notes (Optional):</strong></label>
                        <textarea name="notes" class="form-control" rows="4" 
                                  placeholder="Please provide any additional information about your condition or concerns..."></textarea>
                        <small class="form-text">Any details that might help the medical staff</small>
                    </div>
                    
                    <div class="form-actions text-center">
                        <button type="submit" class="btn btn-primary btn-lg" <?= $consultation_count >= 5 ? 'disabled' : '' ?>>
                            <i class="fas fa-paper-plane me-2"></i> 
                            <?= $consultation_count >= 5 ? 'CONSULTATION LIMIT REACHED' : 'SUBMIT CONSULTATION REQUEST' ?>
                        </button>
                        <?php if ($consultation_count >= 5): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>Limit Reached:</strong> You have reached the maximum of 5 active consultations. Please wait for some to be completed or cancel existing ones.
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Consultation Table - ENHANCED -->
            <div class="consultation-schedule fade-in">
                <h3 class="schedule-title"><i class="fas fa-calendar-alt me-2"></i>YOUR CONSULTATION SCHEDULE</h3>
                
                <?php if (empty($consultations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h5>No consultation requests yet</h5>
                        <p>Schedule your first consultation using the form above.</p>
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

    <!-- View Modal - ENHANCED -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Consultation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

    <!-- Edit Modal - ENHANCED -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="consultation_id" id="edit_consultation_id">
                <!-- ✅ CSRF TOKEN FIELD -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Consultation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Date:</strong></label>
                        <input type="date" id="edit_date" name="edit_date" class="form-control" min="<?= $current_date; ?>" required>
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

        // COMPREHENSIVE MEDICAL CONCERNS DICTIONARY - SEPARATE TAGALOG AND ENGLISH
        const medicalConcerns = [
            // TAGALOG TERMS - 500+ terms
            "Lagnat", "Panginginig", "Pawis nang pawis", "Ubo", "Sipon", "Baradong ilong", 
            "Tuloy-tuloy na sipon", "Masakit na lalamunan", "Hirap lumunok", "Pamamaga ng lalamunan",
            "Sakit ng ulo", "Matinding sakit ng ulo", "Migraine", "Pagkahilo", "Pag-ikot ng paligid",
            "Hilo pag tumatayo", "Pagsusuka", "Pagduduwal", "Walang gana kumain", "Pagtatae",
            "Dysentery", "Tubig ang dumi", "Pagdumi ng dugo", "Hirap dumumi", "Masakit dumumi",
            "Pananakit ng tiyan", "Kirot sa tiyan", "Kabag", "Paninikip ng dibdib", "Masikip ang dibdib",
            "Hinat", "Hirap huminga", "Mabilis na paghinga", "Pag-ubo ng plema", "Madugong plema",
            "Pag-ubo sa gabi", "Pag-ubo na matagal", "Panghihina", "Pagkapagod", "Panghihina ng katawan",
            "Pangingimi", "Antok nang antok", "Hindi makatulog", "Pabalik-balik na paggising",
            "Bangungot", "Pagod kahit natulog", "Pananakit ng katawan", "Pananakit ng kalamnan",
            "Pananakit ng buto", "Pananakit ng kasukasuan", "Pamamaga ng kasukasuan", "Matigas na kasukasuan",
            "Pamamaga", "Pamamaga ng paa", "Pamamaga ng kamay", "Pamamaga ng mukha", "Pantal",
            "Pangangati", "Pamamantal", "Pagtubig ng mata", "Pamamaga ng mata", "Pamumula ng mata",
            "Masakit na mata", "Malabong paningin", "Doble ang paningin", "Paglabo ng mata",
            "Pagkakaroon ng spot sa mata", "Sakit sa tainga", "Pagtunog ng tainga", "Pagsara ng tainga",
            "Paglabas ng tubig sa tainga", "Panghihina ng pandinig", "Masakit na ngipin", "Pamamaga ng gilagid",
            "Pagdurugo ng gilagid", "Masakit na panga", "Pagtunog ng panga", "Hirap ngumunguya",
            "Masakit na leeg", "Pananakit ng balikat", "Pananakit ng likod", "Masakit na lower back",
            "Masakit na upper back", "Pananakit ng baywang", "Pananakit ng pigi", "Pananakit ng hita",
            "Pananakit ng tuhod", "Pananakit ng binti", "Pananakit ng talampakan", "Pananakit ng bukong-bukong",
            "Pananakit ng pulso", "Pananakit ng siko", "Pananakit ng kamay", "Pananakit ng daliri",
            "Pamamamanas", "Pangangalay", "Pangingimi ng paa", "Pangingimi ng kamay", "Pangingilig",
            "Pangingilig sa paa", "Pangingilig sa kamay", "Panghihina ng braso", "Panghihina ng binti",
            "Panghihina ng mukha", "Pangingisay", "Panginginig ng kamay", "Panginginig ng paa",
            "Panginginig ng ulo", "Panginginig ng boses", "Kawalan ng malay", "Nawawala sa sarili",
            "Nalilito", "Hirap mag-isip", "Pagkawala ng memorya", "Hirap mag-concentrate", "Pagkabalisa",
            "Pagka-stress", "Depression", "Panic attack", "Takot nang walang dahilan", "Biglaang pag-iyak",
            "Biglaang pagtawa", "Kawalan ng pag-asa", "Kawalan ng interes", "Mabilis magalit", "Mood swings",
            "Pag-iisa", "Hirap makisama", "Hika", "Aatakihin sa hika", "Chronic bronchitis", "Emphysema",
            "Pneumonia", "Tuberculosis", "Pleurisy", "Pulmonary edema", "Pulmonary embolism", "Lung cancer",
            "Acute respiratory infection", "Upper respiratory infection", "Bronchitis", "Sinusitis",
            "Allergic rhinitis", "Hay fever", "Tonsillitis", "Pharyngitis", "Laryngitis", "Croup",
            "Whooping cough", "Influenza", "COVID-19", "SARS", "MERS", "Sleep apnea", "Snoring",
            "Nasal polyps", "Deviated septum", "Nosebleed", "Chronic nosebleed", "Post-nasal drip",
            "Chronic cough", "Smoker's cough", "Shortness of breath on exertion", "Wheezing", "Stridor",
            "Chest congestion", "Rattling chest", "Blue lips", "Blue nails", "Clubbing of fingers",
            "Rapid breathing", "Shallow breathing", "Deep breathing", "Painful breathing",
            "Difficulty breathing lying down", "Orthopnea", "Paroxysmal nocturnal dyspnea", "Hyperventilation",
            "Hypoventilation", "Respiratory failure", "ARDS", "Pulmonary fibrosis", "Sarcoidosis",
            "Asbestosis", "Silicosis", "Farmer's lung", "Hypersensitivity pneumonitis", "Pulmonary hypertension",
            "Cor pulmonale", "Lung abscess", "Empyema", "Pneumothorax", "Hemothorax", "Pleural effusion",
            "Mesothelioma", "Bronchiectasis", "Cystic fibrosis", "Alpha-1 antitrypsin deficiency",
            "Interstitial lung disease", "Occupational lung disease", "Radiation pneumonitis",
            "Chemical pneumonitis", "Aspiration pneumonia", "Ventilator-associated pneumonia",
            "Walking pneumonia", "Atypical pneumonia", "Legionnaires' disease", "Psittacosis", "Q fever",
            "Histoplasmosis", "Coccidioidomycosis", "Blastomycosis", "Cryptococcosis", "Aspergillosis",
            "Candidiasis of lung", "Pneumocystis pneumonia", "Talaromycosis", "Mucormycosis", "Actinomycosis",
            "Nocardiosis", "Tularemia", "Anthrax", "Plague", "Melioidosis", "Glanders", "Pertussis",
            "Diphtheria", "Measles", "Chickenpox", "Adenovirus", "Respiratory syncytial virus",
            "Human metapneumovirus", "Parainfluenza", "Rhinovirus", "Coronavirus", "Enterovirus",
            "High blood pressure", "Low blood pressure", "Hypertension", "Hypotension", "Chest pain",
            "Heart attack", "Heart failure", "Arrhythmia", "Palpitations", "Rapid heartbeat",
            "Slow heartbeat", "Irregular heartbeat", "Coronary artery disease", "Angina", "Stable angina",
            "Unstable angina", "Myocardial infarction", "Cardiomyopathy", "Heart valve disease",
            "Mitral valve prolapse", "Aortic stenosis", "Mitral stenosis", "Heart murmur", "Pericarditis",
            "Endocarditis", "Myocarditis", "Rheumatic heart disease", "Congenital heart disease",
            "Atrial septal defect", "Ventricular septal defect", "Patent ductus arteriosus",
            "Tetralogy of Fallot", "Coarctation of aorta", "Transposition of great arteries", "Heart block",
            "Bundle branch block", "Sick sinus syndrome", "Atrial fibrillation", "Atrial flutter",
            "Ventricular tachycardia", "Ventricular fibrillation", "Long QT syndrome", "Brugada syndrome",
            "Wolff-Parkinson-White syndrome", "Cardiac arrest", "Sudden cardiac death", "Heart enlargement",
            "Cardiac tamponade", "Aortic aneurysm", "Aortic dissection", "Peripheral artery disease",
            "Deep vein thrombosis", "Varicose veins", "Spider veins", "Venous insufficiency", "Lymphedema",
            "Raynaud's phenomenon", "Buerger's disease", "Vasculitis", "Kawasaki disease",
            "Takayasu's arteritis", "Giant cell arteritis", "Polyarteritis nodosa", "Microscopic polyangiitis",
            "Granulomatosis with polyangiitis", "Eosinophilic granulomatosis", "Behcet's disease",
            "Thromboangiitis obliterans", "Atherosclerosis", "Arteriosclerosis", "Hypercholesterolemia",
            "Hyperlipidemia", "High triglycerides", "Metabolic syndrome", "Obesity", "Diabetes",
            "Metabolic disorder", "Familial hypercholesterolemia", "Lipid disorder", "Cardiac rehabilitation",
            "Pacemaker", "Implantable cardioverter defibrillator", "Cardiac catheterization", "Angioplasty",
            "Stent placement", "Coronary artery bypass", "Heart transplant", "Valve replacement",
            "Valve repair", "Ablation", "Cardioversion", "ECG abnormal", "Echocardiogram abnormal",
            "Stress test abnormal", "Holter monitor abnormal", "Cardiac MRI abnormal", "Cardiac CT abnormal",
            "Nuclear stress test abnormal", "Tilt table test abnormal", "Electrophysiology study abnormal",
            "Gastroesophageal reflux", "Heartburn", "Acid reflux", "GERD", "Hiatal hernia", "Esophagitis",
            "Barrett's esophagus", "Esophageal cancer", "Esophageal varices", "Esophageal spasm",
            "Difficulty swallowing", "Painful swallowing", "Gastritis", "Stomach ulcer", "Peptic ulcer",
            "Duodenal ulcer", "Gastric cancer", "Stomach polyps", "Gastroparesis", "Indigestion",
            "Dyspepsia", "Bloating", "Gas", "Flatulence", "Belching", "Nausea", "Vomiting",
            "Food poisoning", "Gastroenteritis", "Stomach flu", "Norovirus", "Rotavirus", "E. coli infection",
            "Salmonella", "Campylobacter", "Shigella", "Cholera", "Typhoid fever", "Parasitic infection",
            "Giardiasis", "Amebiasis", "Cryptosporidiosis", "Inflammatory bowel disease", "Crohn's disease",
            "Ulcerative colitis", "Irritable bowel syndrome", "Diverticulitis", "Diverticulosis",
            "Appendicitis", "Peritonitis", "Celiac disease", "Gluten intolerance", "Lactose intolerance",
            "Food allergy", "Food sensitivity", "Malabsorption", "Pancreatitis", "Pancreatic cancer",
            "Pancreatic insufficiency", "Gallstones", "Cholecystitis", "Biliary colic", "Cholangitis",
            "Primary biliary cholangitis", "Primary sclerosing cholangitis", "Gallbladder cancer",
            "Liver disease", "Hepatitis A", "Hepatitis B", "Hepatitis C", "Hepatitis D", "Hepatitis E",
            "Alcoholic hepatitis", "Autoimmune hepatitis", "Cirrhosis", "Liver cancer", "Liver failure",
            "Liver abscess", "Fatty liver", "NASH", "Wilson's disease", "Hemochromatosis",
            "Alpha-1 antitrypsin deficiency", "Portal hypertension", "Ascites", "Jaundice", "Hemorrhoids",
            "Anal fissure", "Anal fistula", "Perianal abscess", "Rectal prolapse", "Proctitis",
            "Colon cancer", "Rectal cancer", "Colon polyps", "Familial adenomatous polyposis",
            "Hereditary nonpolyposis colorectal cancer", "Ischemic colitis", "Pseudomembranous colitis",
            "Microscopic colitis", "Collagenous colitis", "Lymphocytic colitis", "Radiation enteritis",
            "Short bowel syndrome", "Intestinal obstruction", "Intestinal perforation", "Volvulus",
            "Intussusception", "Migraine", "Tension headache", "Cluster headache", "Sinus headache",
            "Rebound headache", "Stroke", "Ischemic stroke", "Hemorrhagic stroke", "Transient ischemic attack",
            "Brain aneurysm", "Subarachnoid hemorrhage", "Intracerebral hemorrhage", "Subdural hematoma",
            "Epidural hematoma", "Concussion", "Traumatic brain injury", "Brain tumor", "Glioblastoma",
            "Meningioma", "Pituitary tumor", "Acoustic neuroma", "Spinal cord tumor", "Neurofibromatosis",
            "Tuberous sclerosis", "Epilepsy", "Seizure disorder", "Absence seizure", "Tonic-clonic seizure",
            "Myoclonic seizure", "Atonic seizure", "Status epilepticus", "Multiple sclerosis",
            "Parkinson's disease", "Alzheimer's disease", "Dementia", "Vascular dementia",
            "Lewy body dementia", "Frontotemporal dementia", "Huntington's disease",
            "Amyotrophic lateral sclerosis", "Muscular dystrophy", "Myasthenia gravis",
            "Guillain-Barre syndrome", "Peripheral neuropathy", "Diabetic neuropathy",
            "Carpal tunnel syndrome", "Sciatica", "Pinched nerve", "Radiculopathy", "Bell's palsy",
            "Trigeminal neuralgia", "Restless legs syndrome", "Narcolepsy", "Sleep disorders", "Insomnia",
            "Sleep apnea", "REM sleep behavior disorder", "Sleepwalking", "Night terrors", "Encephalitis",
            "Meningitis", "Brain abscess", "Hydrocephalus", "Normal pressure hydrocephalus", "Spina bifida",
            "Cerebral palsy", "Autism spectrum disorder", "ADHD", "Tourette syndrome",
            "Obsessive-compulsive disorder", "Anxiety disorders", "Panic disorder", "Phobias",
            "Post-traumatic stress disorder", "Bipolar disorder", "Schizophrenia", "Major depressive disorder",
            "Dysthymia", "Cyclothymia", "Personality disorders", "Eating disorders", "Anorexia nervosa",
            "Bulimia nervosa", "Binge eating disorder", "Substance abuse", "Alcoholism", "Drug addiction",
            "Withdrawal symptoms", "Delirium", "Wernicke-Korsakoff syndrome", "Central pain syndrome",
            "Complex regional pain syndrome", "Fibromyalgia", "Chronic fatigue syndrome", "Vertigo",
            "Meniere's disease", "Benign paroxysmal positional vertigo", "Labyrinthitis",
            "Vestibular neuritis", "Motion sickness", "Syncope", "Vasovagal syncope", "Cardiac syncope",
            "Neurologic syncope", "Depression", "Major depression", "Clinical depression",
            "Persistent depressive disorder", "Postpartum depression", "Seasonal affective disorder",
            "Bipolar disorder", "Bipolar I disorder", "Bipolar II disorder", "Cyclothymic disorder",
            "Anxiety disorder", "Generalized anxiety disorder", "Panic disorder", "Social anxiety disorder",
            "Specific phobia", "Agoraphobia", "Separation anxiety disorder", "Selective mutism",
            "Obsessive-compulsive disorder", "Body dysmorphic disorder", "Hoarding disorder",
            "Trichotillomania", "Excoriation disorder", "Post-traumatic stress disorder",
            "Acute stress disorder", "Adjustment disorder", "Reactive attachment disorder",
            "Disinhibited social engagement disorder", "Dissociative disorders",
            "Dissociative identity disorder", "Dissociative amnesia",
            "Depersonalization-derealization disorder", "Somatic symptom disorder",
            "Illness anxiety disorder", "Conversion disorder", "Factitious disorder",
            "Feeding and eating disorders", "Anorexia nervosa", "Bulimia nervosa", "Binge-eating disorder",
            "Pica", "Rumination disorder", "Avoidant/restrictive food intake disorder",
            "Sleep-wake disorders", "Insomnia disorder", "Hypersomnolence disorder", "Narcolepsy",
            "Obstructive sleep apnea hypopnea", "Central sleep apnea", "Sleep-related hypoventilation",
            "Circadian rhythm sleep-wake disorders", "Parasomnias",
            "Non-rapid eye movement sleep arousal disorders", "Nightmare disorder",
            "Rapid eye movement sleep behavior disorder", "Restless legs syndrome",
            "Substance-related disorders", "Alcohol-related disorders", "Caffeine-related disorders",
            "Cannabis-related disorders", "Hallucinogen-related disorders", "Inhalant-related disorders",
            "Opioid-related disorders", "Sedative-hypnotic-related disorders", "Stimulant-related disorders",
            "Tobacco-related disorders", "Gambling disorder", "Internet gaming disorder",
            "Neurocognitive disorders", "Delirium", "Major neurocognitive disorder",
            "Mild neurocognitive disorder", "Alzheimer's disease",
            "Frontotemporal neurocognitive disorder", "Neurocognitive disorder with Lewy bodies",
            "Vascular neurocognitive disorder",
            "Neurocognitive disorder due to traumatic brain injury",
            "Substance/medication-induced neurocognitive disorder",
            "Neurocognitive disorder due to HIV infection",
            "Neurocognitive disorder due to prion disease",
            "Neurocognitive disorder due to Parkinson's disease",
            "Neurocognitive disorder due to Huntington's disease", "Personality disorders",
            "Paranoid personality disorder", "Schizoid personality disorder",
            "Schizotypal personality disorder", "Antisocial personality disorder",
            "Borderline personality disorder", "Histrionic personality disorder",
            "Narcissistic personality disorder", "Avoidant personality disorder",
            "Dependent personality disorder", "Obsessive-compulsive personality disorder",
            "Paraphilic disorders", "Voyeuristic disorder", "Exhibitionistic disorder",
            "Frotteuristic disorder", "Sexual masochism disorder", "Sexual sadism disorder",
            "Pedophilic disorder", "Fetishistic disorder", "Transvestic disorder", "Gender dysphoria",

            // ENGLISH TERMS - 500+ terms
            "Fever", "Chills", "Excessive sweating", "Cough", "Cold", "Stuffy nose", 
            "Runny nose", "Sore throat", "Difficulty swallowing", "Swollen throat",
            "Headache", "Severe headache", "Migraine", "Dizziness", "Vertigo",
            "Dizziness when standing", "Vomiting", "Nausea", "Loss of appetite", "Diarrhea",
            "Dysentery", "Watery stool", "Bloody stool", "Constipation", "Painful bowel movement",
            "Stomach pain", "Abdominal cramps", "Bloating", "Chest tightness", "Chest discomfort",
            "Shortness of breath", "Difficulty breathing", "Rapid breathing", "Cough with phlegm",
            "Bloody phlegm", "Night cough", "Persistent cough", "Weakness", "Fatigue", "Body weakness",
            "Lethargy", "Excessive sleepiness", "Insomnia", "Frequent waking",
            "Nightmares", "Tired despite sleeping", "Body pain", "Muscle pain",
            "Bone pain", "Joint pain", "Joint swelling", "Stiff joints",
            "Swelling", "Swollen feet", "Swollen hands", "Facial swelling", "Rash",
            "Itching", "Hives", "Watery eyes", "Swollen eyes", "Red eyes",
            "Eye pain", "Blurred vision", "Double vision", "Blurred vision",
            "Spots in vision", "Ear pain", "Ringing in ears", "Clogged ears",
            "Ear discharge", "Hearing loss", "Toothache", "Swollen gums",
            "Bleeding gums", "Jaw pain", "Jaw clicking", "Difficulty chewing",
            "Neck pain", "Shoulder pain", "Back pain", "Lower back pain",
            "Upper back pain", "Waist pain", "Hip pain", "Thigh pain",
            "Knee pain", "Leg pain", "Foot pain", "Ankle pain",
            "Wrist pain", "Elbow pain", "Hand pain", "Finger pain",
            "Edema", "Numbness", "Foot numbness", "Hand numbness", "Tingling sensation",
            "Foot tingling", "Hand tingling", "Arm weakness", "Leg weakness", "Facial weakness",
            "Seizure", "Hand tremors", "Leg tremors", "Head tremors", "Voice tremors", "Loss of consciousness",
            "Disorientation", "Confusion", "Difficulty thinking", "Memory loss", "Difficulty concentrating", "Anxiety",
            "Stress", "Depression", "Panic attack", "Unexplained fear", "Sudden crying",
            "Sudden laughing", "Hopelessness", "Loss of interest", "Irritability", "Mood swings",
            "Loneliness", "Social difficulty", "Asthma", "Asthma attack", "Chronic bronchitis", "Emphysema",
            "Pneumonia", "Tuberculosis", "Pleurisy", "Pulmonary edema", "Pulmonary embolism", "Lung cancer",
            "Acute respiratory infection", "Upper respiratory infection", "Bronchitis", "Sinusitis",
            "Allergic rhinitis", "Hay fever", "Tonsillitis", "Pharyngitis", "Laryngitis", "Croup",
            "Whooping cough", "Influenza", "COVID-19", "SARS", "MERS", "Sleep apnea", "Snoring",
            "Nasal polyps", "Deviated septum", "Nosebleed", "Chronic nosebleed", "Post-nasal drip",
            "Chronic cough", "Smoker's cough", "Shortness of breath on exertion", "Wheezing", "Stridor",
            "Chest congestion", "Rattling chest", "Blue lips", "Blue nails", "Clubbing of fingers",
            "Rapid breathing", "Shallow breathing", "Deep breathing", "Painful breathing",
            "Difficulty breathing lying down", "Orthopnea", "Paroxysmal nocturnal dyspnea", "Hyperventilation",
            "Hypoventilation", "Respiratory failure", "ARDS", "Pulmonary fibrosis", "Sarcoidosis",
            "Asbestosis", "Silicosis", "Farmer's lung", "Hypersensitivity pneumonitis", "Pulmonary hypertension",
            "Cor pulmonale", "Lung abscess", "Empyema", "Pneumothorax", "Hemothorax", "Pleural effusion",
            "Mesothelioma", "Bronchiectasis", "Cystic fibrosis", "Alpha-1 antitrypsin deficiency",
            "Interstitial lung disease", "Occupational lung disease", "Radiation pneumonitis",
            "Chemical pneumonitis", "Aspiration pneumonia", "Ventilator-associated pneumonia",
            "Walking pneumonia", "Atypical pneumonia", "Legionnaires' disease", "Psittacosis", "Q fever",
            "Histoplasmosis", "Coccidioidomycosis", "Blastomycosis", "Cryptococcosis", "Aspergillosis",
            "Candidiasis of lung", "Pneumocystis pneumonia", "Talaromycosis", "Mucormycosis", "Actinomycosis",
            "Nocardiosis", "Tularemia", "Anthrax", "Plague", "Melioidosis", "Glanders", "Pertussis",
            "Diphtheria", "Measles", "Chickenpox", "Adenovirus", "Respiratory syncytial virus",
            "Human metapneumovirus", "Parainfluenza", "Rhinovirus", "Coronavirus", "Enterovirus",
            "High blood pressure", "Low blood pressure", "Hypertension", "Hypotension", "Chest pain",
            "Heart attack", "Heart failure", "Arrhythmia", "Palpitations", "Rapid heartbeat",
            "Slow heartbeat", "Irregular heartbeat", "Coronary artery disease", "Angina", "Stable angina",
            "Unstable angina", "Myocardial infarction", "Cardiomyopathy", "Heart valve disease",
            "Mitral valve prolapse", "Aortic stenosis", "Mitral stenosis", "Heart murmur", "Pericarditis",
            "Endocarditis", "Myocarditis", "Rheumatic heart disease", "Congenital heart disease",
            "Atrial septal defect", "Ventricular septal defect", "Patent ductus arteriosus",
            "Tetralogy of Fallot", "Coarctation of aorta", "Transposition of great arteries", "Heart block",
            "Bundle branch block", "Sick sinus syndrome", "Atrial fibrillation", "Atrial flutter",
            "Ventricular tachycardia", "Ventricular fibrillation", "Long QT syndrome", "Brugada syndrome",
            "Wolff-Parkinson-White syndrome", "Cardiac arrest", "Sudden cardiac death", "Heart enlargement",
            "Cardiac tamponade", "Aortic aneurysm", "Aortic dissection", "Peripheral artery disease",
            "Deep vein thrombosis", "Varicose veins", "Spider veins", "Venous insufficiency", "Lymphedema",
            "Raynaud's phenomenon", "Buerger's disease", "Vasculitis", "Kawasaki disease",
            "Takayasu's arteritis", "Giant cell arteritis", "Polyarteritis nodosa", "Microscopic polyangiitis",
            "Granulomatosis with polyangiitis", "Eosinophilic granulomatosis", "Behcet's disease",
            "Thromboangiitis obliterans", "Atherosclerosis", "Arteriosclerosis", "Hypercholesterolemia",
            "Hyperlipidemia", "High triglycerides", "Metabolic syndrome", "Obesity", "Diabetes",
            "Metabolic disorder", "Familial hypercholesterolemia", "Lipid disorder", "Cardiac rehabilitation",
            "Pacemaker", "Implantable cardioverter defibrillator", "Cardiac catheterization", "Angioplasty",
            "Stent placement", "Coronary artery bypass", "Heart transplant", "Valve replacement",
            "Valve repair", "Ablation", "Cardioversion", "ECG abnormal", "Echocardiogram abnormal",
            "Stress test abnormal", "Holter monitor abnormal", "Cardiac MRI abnormal", "Cardiac CT abnormal",
            "Nuclear stress test abnormal", "Tilt table test abnormal", "Electrophysiology study abnormal",
            "Gastroesophageal reflux", "Heartburn", "Acid reflux", "GERD", "Hiatal hernia", "Esophagitis",
            "Barrett's esophagus", "Esophageal cancer", "Esophageal varices", "Esophageal spasm",
            "Difficulty swallowing", "Painful swallowing", "Gastritis", "Stomach ulcer", "Peptic ulcer",
            "Duodenal ulcer", "Gastric cancer", "Stomach polyps", "Gastroparesis", "Indigestion",
            "Dyspepsia", "Bloating", "Gas", "Flatulence", "Belching", "Nausea", "Vomiting",
            "Food poisoning", "Gastroenteritis", "Stomach flu", "Norovirus", "Rotavirus", "E. coli infection",
            "Salmonella", "Campylobacter", "Shigella", "Cholera", "Typhoid fever", "Parasitic infection",
            "Giardiasis", "Amebiasis", "Cryptosporidiosis", "Inflammatory bowel disease", "Crohn's disease",
            "Ulcerative colitis", "Irritable bowel syndrome", "Diverticulitis", "Diverticulosis",
            "Appendicitis", "Peritonitis", "Celiac disease", "Gluten intolerance", "Lactose intolerance",
            "Food allergy", "Food sensitivity", "Malabsorption", "Pancreatitis", "Pancreatic cancer",
            "Pancreatic insufficiency", "Gallstones", "Cholecystitis", "Biliary colic", "Cholangitis",
            "Primary biliary cholangitis", "Primary sclerosing cholangitis", "Gallbladder cancer",
            "Liver disease", "Hepatitis A", "Hepatitis B", "Hepatitis C", "Hepatitis D", "Hepatitis E",
            "Alcoholic hepatitis", "Autoimmune hepatitis", "Cirrhosis", "Liver cancer", "Liver failure",
            "Liver abscess", "Fatty liver", "NASH", "Wilson's disease", "Hemochromatosis",
            "Alpha-1 antitrypsin deficiency", "Portal hypertension", "Ascites", "Jaundice", "Hemorrhoids",
            "Anal fissure", "Anal fistula", "Perianal abscess", "Rectal prolapse", "Proctitis",
            "Colon cancer", "Rectal cancer", "Colon polyps", "Familial adenomatous polyposis",
            "Hereditary nonpolyposis colorectal cancer", "Ischemic colitis", "Pseudomembranous colitis",
            "Microscopic colitis", "Collagenous colitis", "Lymphocytic colitis", "Radiation enteritis",
            "Short bowel syndrome", "Intestinal obstruction", "Intestinal perforation", "Volvulus",
            "Intussusception", "Migraine", "Tension headache", "Cluster headache", "Sinus headache",
            "Rebound headache", "Stroke", "Ischemic stroke", "Hemorrhagic stroke", "Transient ischemic attack",
            "Brain aneurysm", "Subarachnoid hemorrhage", "Intracerebral hemorrhage", "Subdural hematoma",
            "Epidural hematoma", "Concussion", "Traumatic brain injury", "Brain tumor", "Glioblastoma",
            "Meningioma", "Pituitary tumor", "Acoustic neuroma", "Spinal cord tumor", "Neurofibromatosis",
            "Tuberous sclerosis", "Epilepsy", "Seizure disorder", "Absence seizure", "Tonic-clonic seizure",
            "Myoclonic seizure", "Atonic seizure", "Status epilepticus", "Multiple sclerosis",
            "Parkinson's disease", "Alzheimer's disease", "Dementia", "Vascular dementia",
            "Lewy body dementia", "Frontotemporal dementia", "Huntington's disease",
            "Amyotrophic lateral sclerosis", "Muscular dystrophy", "Myasthenia gravis",
            "Guillain-Barre syndrome", "Peripheral neuropathy", "Diabetic neuropathy",
            "Carpal tunnel syndrome", "Sciatica", "Pinched nerve", "Radiculopathy", "Bell's palsy",
            "Trigeminal neuralgia", "Restless legs syndrome", "Narcolepsy", "Sleep disorders", "Insomnia",
            "Sleep apnea", "REM sleep behavior disorder", "Sleepwalking", "Night terrors", "Encephalitis",
            "Meningitis", "Brain abscess", "Hydrocephalus", "Normal pressure hydrocephalus", "Spina bifida",
            "Cerebral palsy", "Autism spectrum disorder", "ADHD", "Tourette syndrome",
            "Obsessive-compulsive disorder", "Anxiety disorders", "Panic disorder", "Phobias",
            "Post-traumatic stress disorder", "Bipolar disorder", "Schizophrenia", "Major depressive disorder",
            "Dysthymia", "Cyclothymia", "Personality disorders", "Eating disorders", "Anorexia nervosa",
            "Bulimia nervosa", "Binge eating disorder", "Substance abuse", "Alcoholism", "Drug addiction",
            "Withdrawal symptoms", "Delirium", "Wernicke-Korsakoff syndrome", "Central pain syndrome",
            "Complex regional pain syndrome", "Fibromyalgia", "Chronic fatigue syndrome", "Vertigo",
            "Meniere's disease", "Benign paroxysmal positional vertigo", "Labyrinthitis",
            "Vestibular neuritis", "Motion sickness", "Syncope", "Vasovagal syncope", "Cardiac syncope",
            "Neurologic syncope", "Depression", "Major depression", "Clinical depression",
            "Persistent depressive disorder", "Postpartum depression", "Seasonal affective disorder",
            "Bipolar disorder", "Bipolar I disorder", "Bipolar II disorder", "Cyclothymic disorder",
            "Anxiety disorder", "Generalized anxiety disorder", "Panic disorder", "Social anxiety disorder",
            "Specific phobia", "Agoraphobia", "Separation anxiety disorder", "Selective mutism",
            "Obsessive-compulsive disorder", "Body dysmorphic disorder", "Hoarding disorder",
            "Trichotillomania", "Excoriation disorder", "Post-traumatic stress disorder",
            "Acute stress disorder", "Adjustment disorder", "Reactive attachment disorder",
            "Disinhibited social engagement disorder", "Dissociative disorders",
            "Dissociative identity disorder", "Dissociative amnesia",
            "Depersonalization-derealization disorder", "Somatic symptom disorder",
            "Illness anxiety disorder", "Conversion disorder", "Factitious disorder",
            "Feeding and eating disorders", "Anorexia nervosa", "Bulimia nervosa", "Binge-eating disorder",
            "Pica", "Rumination disorder", "Avoidant/restrictive food intake disorder",
            "Sleep-wake disorders", "Insomnia disorder", "Hypersomnolence disorder", "Narcolepsy",
            "Obstructive sleep apnea hypopnea", "Central sleep apnea", "Sleep-related hypoventilation",
            "Circadian rhythm sleep-wake disorders", "Parasomnias",
            "Non-rapid eye movement sleep arousal disorders", "Nightmare disorder",
            "Rapid eye movement sleep behavior disorder", "Restless legs syndrome",
            "Substance-related disorders", "Alcohol-related disorders", "Caffeine-related disorders",
            "Cannabis-related disorders", "Hallucinogen-related disorders", "Inhalant-related disorders",
            "Opioid-related disorders", "Sedative-hypnotic-related disorders", "Stimulant-related disorders",
            "Tobacco-related disorders", "Gambling disorder", "Internet gaming disorder",
            "Neurocognitive disorders", "Delirium", "Major neurocognitive disorder",
            "Mild neurocognitive disorder", "Alzheimer's disease",
            "Frontotemporal neurocognitive disorder", "Neurocognitive disorder with Lewy bodies",
            "Vascular neurocognitive disorder",
            "Neurocognitive disorder due to traumatic brain injury",
            "Substance/medication-induced neurocognitive disorder",
            "Neurocognitive disorder due to HIV infection",
            "Neurocognitive disorder due to prion disease",
            "Neurocognitive disorder due to Parkinson's disease",
            "Neurocognitive disorder due to Huntington's disease", "Personality disorders",
            "Paranoid personality disorder", "Schizoid personality disorder",
            "Schizotypal personality disorder", "Antisocial personality disorder",
            "Borderline personality disorder", "Histrionic personality disorder",
            "Narcissistic personality disorder", "Avoidant personality disorder",
            "Dependent personality disorder", "Obsessive-compulsive personality disorder",
            "Paraphilic disorders", "Voyeuristic disorder", "Exhibitionistic disorder",
            "Frotteuristic disorder", "Sexual masochism disorder", "Sexual sadism disorder",
            "Pedophilic disorder", "Fetishistic disorder", "Transvestic disorder", "Gender dysphoria"
        ];

        // Function to handle concern selection
        document.addEventListener('DOMContentLoaded', function() {
            const concernSelect = document.getElementById('concernSelect');
            const otherConcernContainer = document.getElementById('otherConcernContainer');
            const otherConcernInput = document.getElementById('otherConcern');
            const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');
            const consultationForm = document.getElementById('consultationForm');
            const dateInput = document.querySelector('input[name="date"]');
            const timeSelect = document.getElementById('timeSelect');

            let currentFocus = -1;

            concernSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    otherConcernContainer.style.display = 'block';
                    otherConcernInput.required = true;
                    otherConcernInput.focus();
                } else {
                    otherConcernContainer.style.display = 'none';
                    otherConcernInput.required = false;
                    otherConcernInput.value = '';
                    hideSuggestions();
                }
            });

            // Autocomplete functionality
            otherConcernInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                hideSuggestions();
                currentFocus = -1;
                
                if (value.length < 2) return;
                
                const matches = medicalConcerns.filter(concern => 
                    concern.toLowerCase().includes(value)
                ).slice(0, 8); // Limit to 8 suggestions
                
                if (matches.length > 0) {
                    showSuggestions(matches, value);
                }
            });

            otherConcernInput.addEventListener('keydown', function(e) {
                const suggestions = autocompleteSuggestions.getElementsByClassName('autocomplete-suggestion');
                
                if (e.key === 'ArrowDown') {
                    currentFocus++;
                    addActive(suggestions);
                    e.preventDefault();
                } else if (e.key === 'ArrowUp') {
                    currentFocus--;
                    addActive(suggestions);
                    e.preventDefault();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentFocus > -1) {
                        if (suggestions) suggestions[currentFocus].click();
                    }
                } else if (e.key === 'Escape') {
                    hideSuggestions();
                }
            });

            function showSuggestions(matches, searchValue) {
                autocompleteSuggestions.innerHTML = '';
                matches.forEach(match => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-suggestion';
                    // Highlight matching text
                    const highlightedMatch = match.replace(
                        new RegExp(searchValue, 'gi'),
                        match => `<strong>${match}</strong>`
                    );
                    div.innerHTML = highlightedMatch;
                    div.addEventListener('click', function() {
                        otherConcernInput.value = match;
                        hideSuggestions();
                    });
                    autocompleteSuggestions.appendChild(div);
                });
                autocompleteSuggestions.style.display = 'block';
            }

            function addActive(suggestions) {
                if (!suggestions) return false;
                removeActive(suggestions);
                if (currentFocus >= suggestions.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = suggestions.length - 1;
                suggestions[currentFocus].classList.add('active');
            }

            function removeActive(suggestions) {
                for (let i = 0; i < suggestions.length; i++) {
                    suggestions[i].classList.remove('active');
                }
            }

            function hideSuggestions() {
                autocompleteSuggestions.style.display = 'none';
                currentFocus = -1;
            }

            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!otherConcernContainer.contains(e.target)) {
                    hideSuggestions();
                }
            });

            // ✅ VALIDATION: Check if selected time is in the past
            function validateDateTime() {
                const selectedDate = dateInput.value;
                const selectedTime = timeSelect.value;
                
                if (selectedDate && selectedTime) {
                    const selectedDateTime = new Date(selectedDate + 'T' + selectedTime);
                    const currentDateTime = new Date();
                    
                    if (selectedDateTime <= currentDateTime) {
                        alert('You cannot schedule a consultation for a past date/time. Please select a future date and time.');
                        timeSelect.value = '';
                        return false;
                    }
                }
                return true;
            }

            // Add validation when time is selected
            timeSelect.addEventListener('change', validateDateTime);
            
            // Add validation when date is changed
            dateInput.addEventListener('change', function() {
                if (timeSelect.value) {
                    validateDateTime();
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
                
                // Validate date/time on form submission
                if (!validateDateTime()) {
                    e.preventDefault();
                    return;
                }
            });

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

            // ENHANCED INTERACTIONS
            const formContainers = document.querySelectorAll('.consultation-form-container, .consultation-schedule');
            formContainers.forEach(container => {
                container.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                container.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                });
            });

            // FOCUS MANAGEMENT FOR ACCESSIBILITY
            const focusableElements = document.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            focusableElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.classList.add('focus-visible');
                });
                
                element.addEventListener('blur', function() {
                    this.classList.remove('focus-visible');
                });
            });

            // TOUCH DEVICE ENHANCEMENTS
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
                
                // Increase tap targets
                const tapTargets = document.querySelectorAll('.btn-action, .btn-primary, .nav-item');
                tapTargets.forEach(target => {
                    target.style.minHeight = '44px';
                });
            }

            // RESIZE HANDLER
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
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