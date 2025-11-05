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

    // ✅ VALIDATION: Check if the selected date/time is in the past
    $selected_datetime = strtotime($date . ' ' . $time);
    $current_datetime = time();
    
    if ($selected_datetime <= $current_datetime) {
        $_SESSION['error_message'] = 'You cannot schedule a consultation for a past date/time. Please select a future date and time.';
        header("Location: schedule_consultation.php");
        exit();
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

    // ✅ VALIDATION: Check if the selected date/time is in the past
    $selected_datetime = strtotime($date . ' ' . $time);
    $current_datetime = time();
    
    if ($selected_datetime <= $current_datetime) {
        $_SESSION['error_message'] = 'You cannot schedule a consultation for a past date/time. Please select a future date and time.';
        header("Location: schedule_consultation.php");
        exit();
    }

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
            </div>

            <!-- Consultation Form - ENHANCED -->
            <div class="consultation-form-container fade-in">
                <h4><i class="fas fa-calendar-plus me-2"></i>New Consultation Request</h4>
                <form method="POST" action="" id="consultationForm">
                    <input type="hidden" name="action" value="create">
                    
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
                        
                        <!-- Other Concern Textbox (Hidden by default) -->
                        <div id="otherConcernContainer" class="mt-3" style="display: none;">
                            <label class="form-label"><strong>Please specify your concern:</strong></label>
                            <input type="text" name="other_concern" id="otherConcern" class="form-control" 
                                   placeholder="Please describe your specific concern...">
                            <small class="form-text">Type your specific reason for consultation</small>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label"><strong>Additional Notes (Optional):</strong></label>
                        <textarea name="notes" class="form-control" rows="4" 
                                  placeholder="Please provide any additional information about your condition or concerns..."></textarea>
                        <small class="form-text">Any details that might help the medical staff</small>
                    </div>
                    
                    <div class="form-actions text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> SUBMIT CONSULTATION REQUEST
                        </button>
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

        // Function to handle concern selection
        document.addEventListener('DOMContentLoaded', function() {
            const concernSelect = document.getElementById('concernSelect');
            const otherConcernContainer = document.getElementById('otherConcernContainer');
            const otherConcernInput = document.getElementById('otherConcern');
            const consultationForm = document.getElementById('consultationForm');
            const dateInput = document.querySelector('input[name="date"]');
            const timeSelect = document.getElementById('timeSelect');

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