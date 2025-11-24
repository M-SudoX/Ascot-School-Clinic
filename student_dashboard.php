<?php
// ==================== SESSION AT SECURITY ====================
session_start();  // SIMULIN ANG SESSION PARA MA-ACCESS ANG USER DATA
require 'includes/db_connect.php';
require 'includes/activity_logger.php';  // IKONEK SA DATABASE GAMIT ANG PDO

// ✅ SECURITY CHECK: TINITIGNAN KUNG NAKA-LOGIN ANG USER
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");  // KUNG HINDI NAKA-LOGIN, BALIK SA LOGIN PAGE
    exit();  // ITIGIL ANG EXECUTION
}

$student_id = $_SESSION['student_id'];

// ==================== 🟢 AJAX HANDLER (FIXED: USE DB TIME SYNC) ====================
// Ito ang sasalo ng signal galing sa JavaScript para i-update ang status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    try {
        // 1. Update Consultation Requests sa Database (Gawin viewed = TRUE)
        $updateStmt = $pdo->prepare("UPDATE consultation_requests SET is_viewed = TRUE WHERE student_id = ?");
        $updateStmt->execute([$student_id]);

        // 2. Update Announcements sa Session (Gamitin ang Database Time para accurate)
        // FIX: Kumuha ng oras galing sa database (NOW()) para pareho sila ng timezone ng created_at
        $timeStmt = $pdo->query("SELECT NOW()");
        $dbCurrentTime = $timeStmt->fetchColumn();

        $_SESSION['announcements_last_viewed'] = $dbCurrentTime;
        
        echo "success";
    } catch (Exception $e) {
        echo "error";
    }
    exit; // Tigil dito para hindi mag-load ang buong HTML
}
// ====================================================================================

$student_number = $_SESSION['student_number'] ?? ($_SESSION['student_id'] ?? 'N/A');

// ✅ I-LOG ANG PAG-ACCESS SA DASHBOARD (automatic duplicate prevention na)

$stmt = $pdo->prepare("SELECT fullname, student_number, course_year, cellphone_number 
                        FROM student_information 
                        WHERE student_number = :student_number LIMIT 1");

$stmt->execute([':student_number' => $student_number]);

$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ ERROR HANDLING: BACKUP SYSTEM KUNG WALANG MAKUHA SA DATABASE
if (!$student_info) {
    // GUMAMIT NG SESSION DATA KUNG WALANG RECORD SA DATABASE
    $student_info = [
        'fullname' => $_SESSION['fullname'] ?? 'N/A',
        'student_number' => $student_number,
        'course_year' => 'Not set',
        'cellphone_number' => 'Not set'
    ];
} else {
    // ✅ UPDATE ANG SESSION DATA PARA CONSISTENT ANG INFORMATION
    $_SESSION['fullname'] = $student_info['fullname'];
    $_SESSION['student_number'] = $student_info['student_number']; // SIGURADUHING NA-SET
}

// ✅ FETCH UPCOMING APPOINTMENTS
try {
    $appointment_stmt = $pdo->prepare("
        SELECT date, time, requested, status 
        FROM consultation_requests 
        WHERE student_id = ? AND date >= CURDATE() AND status IN ('Pending', 'Approved')
        ORDER BY date ASC, time ASC 
        LIMIT 3
    ");
    $appointment_stmt->execute([$student_id]);
    $upcoming_appointments = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $upcoming_appointments = [];
}

// ✅ FETCH APPOINTMENTS FOR CALENDAR
try {
    $calendar_stmt = $pdo->prepare("
        SELECT date, time, requested, status 
        FROM consultation_requests 
        WHERE student_id = ? AND status IN ('Pending', 'Approved', 'Completed')
        ORDER BY date ASC
    ");
    $calendar_stmt->execute([$student_id]);
    $calendar_appointments = $calendar_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $calendar_appointments = [];
}

// ✅ FETCH RECENT ACTIVITIES - SAME FILTER AS ACTIVITY_LOGS.PHP
try {
    $activity_stmt = $pdo->prepare("
        SELECT action, log_date 
        FROM activity_logs 
        WHERE student_id = ?
        AND action NOT LIKE '%logged in%' 
        AND action NOT LIKE '%logged out%'
        ORDER BY log_date DESC 
        LIMIT 3
    ");
    $activity_stmt->execute([$student_id]);
    $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_activities = [];
}

// ✅ UPDATED: FETCH CONSULTATION STATUS COUNTS FOR NOTIFICATIONS (UNVIEWED ONLY)
try {
    // Added 'Rejected' to the list of statuses
    $status_counts_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM consultation_requests 
        WHERE student_id = ? 
        AND status IN ('Approved', 'Disapproved', 'Rescheduled', 'Cancelled', 'No Show', 'Rejected')
        AND is_viewed = FALSE
        GROUP BY status
    ");
    $status_counts_stmt->execute([$student_id]);
    $status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize counts
    $approved_count = 0;
    $disapproved_count = 0;
    $rejected_count = 0; // Added rejected count
    $rescheduled_count = 0;
    $cancelled_count = 0;
    $no_show_count = 0;
    $consultation_notifications = 0;
    
    // Process counts
    foreach ($status_counts as $status_count) {
        switch ($status_count['status']) {
            case 'Approved':
                $approved_count = $status_count['count'];
                break;
            case 'Disapproved':
                $disapproved_count = $status_count['count'];
                break;
            case 'Rejected': // Added case for Rejected
                $rejected_count = $status_count['count'];
                break;
            case 'Rescheduled':
                $rescheduled_count = $status_count['count'];
                break;
            case 'Cancelled':
                $cancelled_count = $status_count['count'];
                break;
            case 'No Show':
                $no_show_count = $status_count['count'];
                break;
        }
    }
    
    // Added rejected_count to total notifications
    $consultation_notifications = $approved_count + $disapproved_count + $rejected_count + $rescheduled_count + $cancelled_count + $no_show_count;
    
} catch (PDOException $e) {
    $approved_count = 0;
    $disapproved_count = 0;
    $rejected_count = 0;
    $rescheduled_count = 0;
    $cancelled_count = 0;
    $no_show_count = 0;
    $consultation_notifications = 0;
}

// ✅ UPDATED: FETCH ANNOUNCEMENT COUNTS FOR NOTIFICATIONS (EXPIRED ANNOUNCEMENTS ARE NOT COUNTED)
try {
    // Build query base
    $sql = "SELECT COUNT(*) as count 
            FROM announcements 
            WHERE post_on_front = 1 
            AND is_active = 1
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND (expiry_date IS NULL OR expiry_date > NOW())";
            
    // FIX: Check session timestamp para hindi na mag-notify kung na-view na
    if (isset($_SESSION['announcements_last_viewed'])) {
        $sql .= " AND created_at > :last_viewed";
        $new_announcements_stmt = $pdo->prepare($sql);
        $new_announcements_stmt->execute([':last_viewed' => $_SESSION['announcements_last_viewed']]);
    } else {
        $new_announcements_stmt = $pdo->prepare($sql);
        $new_announcements_stmt->execute();
    }

    $new_announcements_count = $new_announcements_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // TOTAL ANNOUNCEMENT NOTIFICATIONS (only new announcements)
    $announcement_notifications = $new_announcements_count;
    
    // TOTAL ALL NOTIFICATIONS
    $total_notifications = $consultation_notifications + $announcement_notifications;
    
} catch (PDOException $e) {
    $new_announcements_count = 0;
    $announcement_notifications = 0;
    $total_notifications = $consultation_notifications;
}

// Use PDO - Secure database access
// PDO was used to PROTECT the student information

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ASCOT Clinic</title>
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
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
            justify-content: space-between;
            gap: 1rem;
            height: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
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

        /* ✅ ENHANCED: BELL NOTIFICATION STYLES */
        .notification-bell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .notification-bell:hover {
            transform: scale(1.1) rotate(10deg);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .notification-bell i {
            font-size: 1.4rem;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .notification-bell:hover i {
            color: var(--primary);
        }

        .bell-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, var(--danger), #c82333);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);
            border: 2px solid white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.6);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);
            }
        }

        /* ✅ ENHANCED: NOTIFICATION DROPDOWN */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 1.5rem;
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
        }

        .notification-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(10px);
        }

        .notification-dropdown::before {
            content: '';
            position: absolute;
            top: -10px;
            right: 20px;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.98);
            transform: rotate(45deg);
            border-left: 1px solid rgba(255,255,255,0.3);
            border-top: 1px solid rgba(255,255,255,0.3);
        }

        .notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(248,249,250,0.8);
        }

        .notification-header h5 {
            color: var(--text-dark);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-count {
            background: var(--primary);
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .notification-items {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-section {
            margin-bottom: 1.5rem;
        }

        .notification-section:last-child {
            margin-bottom: 0;
        }

        .notification-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(248,249,250,0.8);
        }

        .notification-section-header h6 {
            color: var(--text-dark);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-section-count {
            background: var(--primary);
            color: white;
            border-radius: 20px;
            padding: 0.2rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            transition: var(--transition);
            border-left: 4px solid;
        }

        .notification-item:hover {
            background: rgba(248,249,250,0.8);
            transform: translateX(5px);
        }

        .notification-item.approved {
            border-left-color: var(--success);
            background: rgba(40, 167, 69, 0.05);
        }

        .notification-item.disapproved {
            border-left-color: var(--danger);
            background: rgba(220, 53, 69, 0.05);
        }

        /* Added Rejected Style - Same as Disapproved */
        .notification-item.rejected {
            border-left-color: var(--danger);
            background: rgba(220, 53, 69, 0.05);
        }

        .notification-item.rescheduled {
            border-left-color: var(--warning);
            background: rgba(255, 193, 7, 0.05);
        }

        .notification-item.cancelled {
            border-left-color: var(--secondary);
            background: rgba(118, 75, 162, 0.05);
        }

        .notification-item.no-show {
            border-left-color: #6c757d;
            background: rgba(108, 117, 125, 0.05);
        }

        .notification-item.new-announcement {
            border-left-color: var(--success);
            background: rgba(40, 167, 69, 0.05);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
        }

        .notification-icon.approved {
            background: var(--success);
        }

        .notification-icon.disapproved {
            background: var(--danger);
        }

        /* Added Rejected Icon Style */
        .notification-icon.rejected {
            background: var(--danger);
        }

        .notification-icon.rescheduled {
            background: var(--warning);
        }

        .notification-icon.cancelled {
            background: var(--secondary);
        }

        .notification-icon.no-show {
            background: #6c757d;
        }

        .notification-icon.new-announcement {
            background: var(--success);
        }

        .notification-content {
            flex: 1;
        }

        .notification-content p {
            margin: 0;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .notification-content small {
            color: var(--text-light);
            font-size: 0.8rem;
        }

        .notification-empty {
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
        }

        .notification-empty i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .notification-empty p {
            margin: 0;
            font-weight: 600;
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

        /* ✅ ENHANCED: SIDEBAR NOTIFICATION BADGES */
        .notification-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            transition: var(--transition);
        }

        .notification-badge.announcement {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .notification-badge.approved {
            background: linear-gradient(135deg, var(--success), #218838);
        }

        .notification-badge.disapproved {
            background: linear-gradient(135deg, var(--danger), #c82333);
        }

        .notification-badge.rescheduled {
            background: linear-gradient(135deg, var(--warning), #e0a800);
        }

        .notification-badge.cancelled {
            background: linear-gradient(135deg, var(--secondary), #6f42c1);
        }

        .notification-badge.no-show {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }

        .notification-badge.total {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            font-size: 0.75rem;
            min-width: 24px;
            height: 24px;
        }

        .nav-item:hover .notification-badge {
            transform: scale(1.1);
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

        /* Welcome Section - ENHANCED */
        .welcome-section {
            background: linear-gradient(135deg, var(--accent-light) 0%, rgba(255,247,218,0.9) 100%);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,218,106,0.3);
            border-left: 8px solid var(--accent);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
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

        .welcome-content h1 {
            color: var(--text-dark);
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .welcome-content p {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 0;
            font-weight: 500;
        }

        /* ✅ ENHANCED: NOTIFICATION BAR STYLES */
        .notification-bar {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.95) 0%, rgba(118, 75, 162, 0.95) 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border-left: 6px solid var(--accent);
            backdrop-filter: blur(10px);
        }

        .notification-bar h5 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }

        .notification-badges-container {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .notification-badge-large {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
        }

        .notification-badge-large:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .notification-badge-large .badge-count {
            background: var(--accent);
            color: var(--text-dark);
            border-radius: 20px;
            padding: 0.5rem 0.75rem;
            font-weight: 800;
            font-size: 0.9rem;
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .notification-badge-large .badge-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
        }

        /* Dashboard Grid - ENHANCED */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .dashboard-card:hover::before {
            left: 100%;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(248,249,250,0.8);
        }

        .card-title {
            color: var(--text-dark);
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--text-dark);
            background: var(--accent-light);
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(255,218,106,0.3);
        }

        .card-icon:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(255,218,106,0.4);
        }

        /* INFO CARD STYLES - ENHANCED */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid rgba(241,243,244,0.8);
            transition: var(--transition);
        }

        .info-row:hover {
            background: rgba(248,249,250,0.5);
            border-radius: 8px;
            padding-left: 1rem;
            padding-right: 1rem;
            margin: 0 -1rem;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 700;
            color: var(--text-light);
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .info-label::before {
            content: '•';
            color: var(--primary);
            margin-right: 0.75rem;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .info-value {
            font-weight: 700;
            color: var(--text-dark);
            text-align: right;
            font-size: 0.95rem;
        }

        /* APPOINTMENTS CARD - ENHANCED */
        .appointment-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid rgba(241,243,244,0.8);
            transition: var(--transition);
        }

        .appointment-item:hover {
            transform: translateX(5px);
            background: rgba(248,249,250,0.5);
            border-radius: 12px;
            padding-left: 1rem;
            padding-right: 1rem;
            margin: 0 -1rem;
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-date {
            background: var(--accent-light);
            color: var(--text-dark);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            min-width: 80px;
            box-shadow: 0 4px 15px rgba(255,218,106,0.3);
            transition: var(--transition);
        }

        .appointment-date:hover {
            transform: scale(1.05);
        }

        .appointment-date .day {
            font-size: 1.5rem;
            font-weight: 800;
            display: block;
            line-height: 1;
        }

        .appointment-date .month {
            font-size: 0.85rem;
            font-weight: 700;
            display: block;
            opacity: 0.8;
        }

        .appointment-details {
            flex: 1;
        }

        .appointment-details h6 {
            margin: 0 0 0.5rem 0;
            color: var(--text-dark);
            font-weight: 700;
            font-size: 1rem;
        }

        .appointment-details p {
            margin: 0;
            color: var(--text-light);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-disapproved {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-rescheduled {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-cancelled {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        .status-no-show {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-light);
        }

        .no-data i {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: #dee2e6;
            display: block;
            opacity: 0.7;
        }

        .no-data p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .no-data small {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* ACTIVITIES CARD - ENHANCED */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid rgba(241,243,244,0.8);
            transition: var(--transition);
        }

        .activity-item:hover {
            transform: translateX(5px);
            background: rgba(248,249,250,0.5);
            border-radius: 12px;
            padding-left: 1rem;
            padding-right: 1rem;
            margin: 0 -1rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--text-dark);
            background: var(--accent-light);
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(255,218,106,0.3);
            transition: var(--transition);
        }

        .activity-item:hover .activity-icon {
            transform: scale(1.1);
        }

        .activity-details {
            flex: 1;
        }

        .activity-details p {
            margin: 0 0 0.5rem 0;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .activity-time {
            font-size: 0.85rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* QUICK ACTIONS - ENHANCED */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--accent) 0%, #ffd24a 100%);
            color: var(--text-dark);
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-weight: 700;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(255,218,106,0.3);
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255,218,106,0.4);
            color: var(--text-dark);
            text-decoration: none;
        }

        .action-btn i {
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .action-btn:hover i {
            transform: scale(1.1);
        }

        /* Responsive Design - ENHANCED */
        @media (max-width: 1200px) {
            .sidebar {
                width: 260px;
            }
            
            .main-content {
                margin-left: 260px;
            }
            
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
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

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-content h1 {
                font-size: 1.8rem;
            }

            .notification-badges-container {
                gap: 1rem;
            }

            .notification-badge-large {
                padding: 0.6rem 1rem;
            }

            .notification-dropdown {
                width: 350px;
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

            .dashboard-grid {
                grid-template-columns: 1fr;
                margin-top: 1.5rem;
                gap: 1.5rem;
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
                padding: 2rem;
            }
            
            .welcome-section {
                padding: 2rem;
            }
            
            .welcome-content h1 {
                font-size: 1.6rem;
            }

            .notification-bar {
                padding: 1rem 1.5rem;
            }

            .notification-badges-container {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .notification-badge-large {
                justify-content: space-between;
            }

            .notification-dropdown {
                width: 320px;
                right: -50px;
            }

            .notification-dropdown::before {
                right: 60px;
            }
        }

        @media (max-width: 576px) {
            .action-btn {
                padding: 1.25rem 1.5rem;
                font-size: 1rem;
            }

            .dashboard-card {
                padding: 1.5rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-content h1 {
                font-size: 1.4rem;
            }

            .main-content {
                padding: 1.25rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }
            
            .card-title {
                font-size: 1.2rem;
            }
            
            .card-icon {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }

            .notification-bar {
                padding: 1rem;
            }

            .notification-bar h5 {
                font-size: 1rem;
            }

            .notification-dropdown {
                width: 280px;
                right: -30px;
            }

            .notification-dropdown::before {
                right: 40px;
            }

            .notification-bell {
                width: 45px;
                height: 45px;
            }

            .notification-bell i {
                font-size: 1.2rem;
            }

            .bell-badge {
                width: 20px;
                height: 20px;
                font-size: 0.7rem;
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
                padding: 1.25rem;
            }
            
            .welcome-section {
                padding: 1.25rem;
            }
            
            .welcome-content h1 {
                font-size: 1.3rem;
            }

            .notification-dropdown {
                width: 250px;
                right: -20px;
            }

            .notification-dropdown::before {
                right: 30px;
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
            
            .dashboard-grid {
                gap: 1rem;
            }

            .notification-dropdown {
                width: 220px;
                right: -10px;
            }

            .notification-dropdown::before {
                right: 20px;
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

        .stagger-animation > * {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }

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
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <div class="header-left">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                    <div class="school-info">
                        <div class="republic">Republic of the Philippines</div>
                        <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                        <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                    </div>
                </div>

                <div class="notification-wrapper" style="position: relative;">
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($total_notifications > 0): ?>
                            <div class="bell-badge" id="bellBadge">
                                <?= $total_notifications ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h5><i class="fas fa-bell"></i> Notifications</h5>
                            <?php if ($total_notifications > 0): ?>
                                <span class="notification-count" id="notificationCount"><?= $total_notifications ?> new</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-items">
                            <?php if ($total_notifications > 0): ?>
                                <?php if ($consultation_notifications > 0): ?>
                                <div class="notification-section">
                                    <div class="notification-section-header">
                                        <h6><i class="fas fa-calendar-check me-2"></i> Consultation Updates</h6>
                                        <span class="notification-section-count"><?= $consultation_notifications ?></span>
                                    </div>
                                    
                                    <?php if ($approved_count > 0): ?>
                                        <div class="notification-item approved">
                                            <div class="notification-icon approved">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $approved_count ?> Consultation<?= $approved_count > 1 ? 's' : '' ?> Approved</p>
                                                <small>Your consultation request has been approved</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($rejected_count > 0): ?>
                                        <div class="notification-item rejected">
                                            <div class="notification-icon rejected">
                                                <i class="fas fa-times-circle"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $rejected_count ?> Consultation<?= $rejected_count > 1 ? 's' : '' ?> Rejected</p>
                                                <small>Your consultation request has been rejected</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($disapproved_count > 0): ?>
                                        <div class="notification-item disapproved">
                                            <div class="notification-icon disapproved">
                                                <i class="fas fa-times-circle"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $disapproved_count ?> Consultation<?= $disapproved_count > 1 ? 's' : '' ?> Disapproved</p>
                                                <small>Your consultation request has been disapproved</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rescheduled_count > 0): ?>
                                        <div class="notification-item rescheduled">
                                            <div class="notification-icon rescheduled">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $rescheduled_count ?> Consultation<?= $rescheduled_count > 1 ? 's' : '' ?> Rescheduled</p>
                                                <small>Your consultation has been rescheduled</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($cancelled_count > 0): ?>
                                        <div class="notification-item cancelled">
                                            <div class="notification-icon cancelled">
                                                <i class="fas fa-ban"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $cancelled_count ?> Consultation<?= $cancelled_count > 1 ? 's' : '' ?> Cancelled</p>
                                                <small>Your consultation has been cancelled</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($no_show_count > 0): ?>
                                        <div class="notification-item no-show">
                                            <div class="notification-icon no-show">
                                                <i class="fas fa-user-times"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $no_show_count ?> Consultation<?= $no_show_count > 1 ? 's' : '' ?> Marked as No Show</p>
                                                <small>You missed your scheduled consultation</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($announcement_notifications > 0): ?>
                                <div class="notification-section">
                                    <div class="notification-section-header">
                                        <h6><i class="fas fa-bullhorn me-2"></i> Announcement Updates</h6>
                                        <span class="notification-section-count"><?= $announcement_notifications ?></span>
                                    </div>
                                    
                                    <?php if ($new_announcements_count > 0): ?>
                                        <div class="notification-item new-announcement">
                                            <div class="notification-icon new-announcement">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $new_announcements_count ?> New Announcement<?= $new_announcements_count > 1 ? 's' : '' ?></p>
                                                <small>Posted in the last 7 days</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($total_notifications > 0): ?>
                            <div class="text-center mt-3">
                                <a href="schedule_consultation.php" class="btn btn-primary btn-sm me-2">
                                    <i class="fas fa-calendar me-1"></i> Consultations
                                </a>
                                <a href="student_announcement.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-bullhorn me-1"></i> Announcements
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="student_dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <a href="update_profile.php" class="nav-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>

                <a href="schedule_consultation.php" class="nav-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Schedule Consultation</span>
                    <?php if ($consultation_notifications > 0): ?>
                        <div class="notification-badge total" title="Consultation updates: <?= $consultation_notifications ?>">
                            <?= $consultation_notifications ?>
                        </div>
                    <?php endif; ?>
                </a>

                <a href="student_report.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Report</span>
                </a>

                <a href="student_announcement.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcement</span>
                    <?php if ($announcement_notifications > 0): ?>
                        <div class="notification-badge announcement" title="Announcement updates: <?= $announcement_notifications ?>">
                            <?= $announcement_notifications ?>
                        </div>
                    <?php endif; ?>
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

        <main class="main-content">
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $student_info['fullname'])[0]); ?>! 👋</h1>
                    <p>Here's what's happening with your health consultations today</p>
                </div>
            </div>

            <?php if ($total_notifications > 0): ?>
            <div class="notification-bar fade-in">
                <h5><i class="fas fa-bell"></i> Consultation & Announcement Updates</h5>
                <div class="notification-badges-container">
                    <?php if ($consultation_notifications > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $consultation_notifications ?></div>
                            <div class="badge-label">Consultation Update<?= $consultation_notifications > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($announcement_notifications > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $announcement_notifications ?></div>
                            <div class="badge-label">Announcement Update<?= $announcement_notifications > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($approved_count > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $approved_count ?></div>
                            <div class="badge-label">Approved Consultation<?= $approved_count > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($rejected_count > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count" style="background: var(--danger); color: white;"><?= $rejected_count ?></div>
                            <div class="badge-label">Rejected Consultation<?= $rejected_count > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($disapproved_count > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $disapproved_count ?></div>
                            <div class="badge-label">Disapproved Consultation<?= $disapproved_count > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($rescheduled_count > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $rescheduled_count ?></div>
                            <div class="badge-label">Rescheduled Consultation<?= $rescheduled_count > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($cancelled_count > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $cancelled_count ?></div>
                            <div class="badge-label">Cancelled Consultation<?= $cancelled_count > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($no_show_count > 0): ?>
                        <div class="notification-badge-large">
                            <div class="badge-count"><?= $no_show_count ?></div>
                            <div class="badge-label">No Show Consultation<?= $no_show_count > 1 ? 's' : '' ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="dashboard-card info-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Student Information</h3>
                        <div class="card-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    
                    <div class="stagger-animation">
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['fullname']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">ID Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['student_number']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Course/Year:</span>
                            <span class="info-value">
                                <?php 
                                $course_year = $student_info['course_year'] ?? 'Not set';
                                echo (empty($course_year) || $course_year === 'Not set') 
                                    ? '<span style="color: #e74c3c;"><i class="fas fa-exclamation-triangle me-1"></i>Not set</span>'
                                    : htmlspecialchars($course_year);
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Contact No:</span>
                            <span class="info-value">
                                <?php 
                                $contact = $student_info['cellphone_number'] ?? 'Not set';
                                echo (empty($contact) || $contact === 'Not set') 
                                    ? '<span style="color: #e74c3c;"><i class="fas fa-exclamation-triangle me-1"></i>Not set</span>'
                                    : htmlspecialchars($contact);
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <a href="update_profile.php" class="action-btn">
                            <i class="fas fa-edit"></i> Update Profile
                        </a>
                    </div>
                </div>

                <div class="dashboard-card appointments-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Appointments</h3>
                        <div class="card-icon" id="calendarIcon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="stagger-animation">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="appointment-date">
                                        <span class="day"><?php echo date('d', strtotime($appointment['date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($appointment['date'])); ?></span>
                                    </div>
                                    <div class="appointment-details">
                                        <h6><?php echo htmlspecialchars($appointment['requested']); ?></h6>
                                        <p><i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($appointment['time'])); ?></p>
                                    </div>
                                    <span class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <p>No upcoming appointments</p>
                            <small>Schedule your first consultation</small>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <a href="schedule_consultation.php" class="action-btn">
                            <i class="fas fa-plus"></i> New Appointment
                        </a>
                    </div>
                </div>

                <div class="dashboard-card activities-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activities</h3>
                        <div class="card-icon">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($recent_activities)): ?>
                        <div class="stagger-animation">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo htmlspecialchars($activity['action']); ?></p>
                                        <span class="activity-time">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($activity['log_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                            <small>Your activities will appear here</small>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <a href="activity_logs.php" class="action-btn">
                            <i class="fas fa-list"></i> View All Activities
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            if(mobileMenuToggle){
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
                sidebarOverlay.addEventListener('click', toggleMobileMenu);
            }

            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                    });
                });
            }

            // ✅ UPDATED: BELL NOTIFICATION (PERSISTENT FIX)
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const bellBadge = document.getElementById('bellBadge');
            const notificationCount = document.getElementById('notificationCount'); // The label inside

            if(notificationBell){
                notificationBell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('active');
                    
                    this.style.transform = 'scale(1.1) rotate(15deg)';
                    setTimeout(() => { this.style.transform = 'scale(1.1) rotate(-5deg)'; }, 150);
                    setTimeout(() => { this.style.transform = 'scale(1.1) rotate(0deg)'; }, 300);
                    
                    // HIDE BADGE VISUALLY
                    if (bellBadge) {
                        bellBadge.style.display = 'none';
                    }
                    // HIDE "New" LABEL inside dropdown (optional but cleaner)
                    if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }

                    // SEND REQUEST TO UPDATE DB & SESSION
                    const formData = new FormData();
                    formData.append('action', 'mark_read');

                    fetch('student_dashboard.php', {
                        method: 'POST',
                        body: formData
                    }).catch(error => console.error('Error:', error));
                });
            }

            document.addEventListener('click', function(e) {
                if (notificationBell && !notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('active');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { notificationDropdown.classList.remove('active'); }
            });

            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (this.classList.contains('new-announcement')) { window.location.href = 'student_announcement.php'; } 
                    else { window.location.href = 'schedule_consultation.php'; }
                });
                item.style.cursor = 'pointer';
            });

            const notificationBadges = document.querySelectorAll('.notification-badge-large');
            notificationBadges.forEach(badge => {
                badge.addEventListener('click', function() {
                    if (this.querySelector('.badge-label').textContent.includes('Announcement')) { window.location.href = 'student_announcement.php'; } 
                    else { window.location.href = 'schedule_consultation.php'; }
                });
                badge.style.cursor = 'pointer';
            });

            const staggerElements = document.querySelectorAll('.stagger-animation > *');
            staggerElements.forEach((element, index) => { element.style.animationDelay = `${index * 0.1}s`; });
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => { element.style.animationDelay = `${index * 0.15}s`; });

            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() { this.style.transform = 'translateY(-8px)'; });
                card.addEventListener('mouseleave', function() { this.style.transform = 'translateY(-5px)'; });
            });

            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
                document.querySelectorAll('.nav-item, .action-btn, .appointment-item, .activity-item, .notification-bell, .notification-badge-large').forEach(target => {
                    target.style.minHeight = '44px';
                });
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('active')) { toggleMobileMenu(); }
                if (window.innerWidth <= 768) { notificationDropdown.classList.remove('active'); }
            });
        });
    </script>
</body>
</html>