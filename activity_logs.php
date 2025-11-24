<?php
// ==================== SESSION AND SECURITY ====================
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

// ✅ SECURITY CHECK: Ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// ==================== 🟢 AJAX HANDLER (FIX: UPDATE DB & SESSION) ====================
// Ito ang sasalo ng signal galing sa JavaScript para i-update ang status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    try {
        // 1. Update Consultation Requests sa Database (Gawin viewed = TRUE)
        $updateStmt = $pdo->prepare("UPDATE consultation_requests SET is_viewed = TRUE WHERE student_id = ?");
        $updateStmt->execute([$student_id]);

        // 2. Update Announcements sa Session (Gamitin ang Database Time para accurate)
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

$student_number = $_SESSION['student_number'] ?? '';

// ✅ FETCH USER INFO IF NOT IN SESSION (Fallback)
if (empty($student_number)) {
    $stmt = $pdo->prepare("SELECT student_number, fullname FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $student_number = $user['student_number'] ?? '';
    if(isset($user['fullname'])) $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['student_number'] = $student_number;
}

if (!$student_number) {
    die("Student record not found.");
}

// ==================== NOTIFICATION LOGIC (CONSISTENT) ====================

// ✅ FETCH CONSULTATION STATUS COUNTS (UNVIEWED ONLY)
try {
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
    
    // Process counts
    foreach ($status_counts as $status_count) {
        switch ($status_count['status']) {
            case 'Approved': $approved_count = $status_count['count']; break;
            case 'Disapproved': $disapproved_count = $status_count['count']; break;
            case 'Rejected': $rejected_count = $status_count['count']; break;
            case 'Rescheduled': $rescheduled_count = $status_count['count']; break;
            case 'Cancelled': $cancelled_count = $status_count['count']; break;
            case 'No Show': $no_show_count = $status_count['count']; break;
        }
    }
    
    $consultation_notifications = $approved_count + $disapproved_count + $rejected_count + $rescheduled_count + $cancelled_count + $no_show_count;
    
} catch (PDOException $e) {
    $consultation_notifications = 0;
}

// ✅ UPDATED: FETCH ANNOUNCEMENT COUNTS (WITH SESSION CHECK)
try {
    // First, run expiration check to ensure data is fresh
    $currentDateTime = date('Y-m-d H:i:s');
    $expire_stmt = $pdo->prepare("
        UPDATE announcements 
        SET is_active = 0, status = 'inactive'
        WHERE expiry_date IS NOT NULL 
        AND expiry_date <= ? 
        AND is_active = 1
    ");
    $expire_stmt->execute([$currentDateTime]);

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
    
    $announcement_notifications = $new_announcements_count;
    $total_notifications = $consultation_notifications + $announcement_notifications;
    
} catch (PDOException $e) {
    $announcement_notifications = 0;
    $total_notifications = $consultation_notifications;
}

// ==================== ACTIVITY LOGS LOGIC ====================

// ✅ Fetch ONLY SPECIFIC ACTION LOGS - exclude login/logout for cleaner view
try {
    $stmt = $pdo->prepare("
        SELECT id, action, log_date 
        FROM activity_logs 
        WHERE student_id = :student_id 
        AND action NOT LIKE '%logged in%' 
        AND action NOT LIKE '%logged out%'
        ORDER BY log_date DESC
    ");
    $stmt->execute([':student_id' => $student_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Activity logs table not found. Please contact administrator.";
    $logs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - ASCOT Clinic</title>
    
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
        }

        /* Header Styles */
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

        /* Bell Notification Styles */
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
            0% { transform: scale(1); box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4); }
            50% { transform: scale(1.1); box-shadow: 0 4px 12px rgba(220, 53, 69, 0.6); }
            100% { transform: scale(1); box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4); }
        }

        /* Notification Dropdown */
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

        .notification-section { margin-bottom: 1.5rem; }
        .notification-section:last-child { margin-bottom: 0; }

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
            cursor: pointer;
        }

        .notification-item:hover {
            background: rgba(248,249,250,0.8);
            transform: translateX(5px);
        }

        .notification-item.approved { border-left-color: var(--success); background: rgba(40, 167, 69, 0.05); }
        .notification-item.disapproved { border-left-color: var(--danger); background: rgba(220, 53, 69, 0.05); }
        .notification-item.rejected { border-left-color: var(--danger); background: rgba(220, 53, 69, 0.05); }
        .notification-item.rescheduled { border-left-color: var(--warning); background: rgba(255, 193, 7, 0.05); }
        .notification-item.cancelled { border-left-color: var(--secondary); background: rgba(118, 75, 162, 0.05); }
        .notification-item.no-show { border-left-color: #6c757d; background: rgba(108, 117, 125, 0.05); }
        .notification-item.new-announcement { border-left-color: var(--success); background: rgba(40, 167, 69, 0.05); }

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

        .notification-icon.approved { background: var(--success); }
        .notification-icon.disapproved { background: var(--danger); }
        .notification-icon.rejected { background: var(--danger); }
        .notification-icon.rescheduled { background: var(--warning); }
        .notification-icon.cancelled { background: var(--secondary); }
        .notification-icon.no-show { background: #6c757d; }
        .notification-icon.new-announcement { background: var(--success); }

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

        /* Mobile Menu Toggle */
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
        }

        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles */
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
            width: 100%;
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

        .nav-item:hover::before { width: 100%; }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(255,218,106,0.15) 0%, transparent 100%);
            color: var(--text-dark);
            border-left: 6px solid var(--accent);
        }

        .nav-item i {
            width: 24px;
            margin-right: 1rem;
            font-size: 1.2rem;
            color: inherit;
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

        /* Sidebar Notification Badges */
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
        
        .notification-badge.announcement { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .notification-badge.total { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }

        .nav-item:hover .notification-badge {
            transform: scale(1.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            margin-left: 280px;
        }

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

        .sidebar-overlay.active { display: block; }

        /* Welcome Section */
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

        .welcome-content h1 {
            color: var(--text-dark);
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 0;
            font-weight: 500;
        }

        /* Activity Card Styles */
        .activity-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
            margin-bottom: 2rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .activity-card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(0,0,0,0.15); }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(233, 236, 239, 0.8);
        }

        .card-title {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            box-shadow: 0 4px 15px rgba(255,218,106,0.4);
        }

        /* Table Styles */
        .activity-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(255,255,255,0.8);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .activity-table th {
            background: linear-gradient(135deg, rgba(255,218,106,0.3), rgba(255,218,106,0.1));
            color: var(--text-dark);
            font-weight: 700;
            padding: 1.25rem;
            text-align: left;
            border-bottom: 2px solid rgba(255,218,106,0.3);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .activity-table td {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(233, 236, 239, 0.8);
            vertical-align: middle;
            color: var(--text-dark);
            font-weight: 500;
        }

        .activity-table tr:last-child td { border-bottom: none; }
        .activity-table tr:hover { background: rgba(255, 218, 106, 0.08); }

        .action-cell { display: flex; align-items: center; gap: 1rem; }

        .action-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .icon-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .icon-success { background: linear-gradient(135deg, var(--success), #218838); }
        .icon-info { background: linear-gradient(135deg, var(--info), #138496); }
        .icon-warning { background: linear-gradient(135deg, var(--warning), #e0a800); }
        .icon-danger { background: linear-gradient(135deg, var(--danger), #c82333); }
        .icon-secondary { background: linear-gradient(135deg, var(--gray), #5a6268); }

        .no-data { text-align: center; padding: 4rem 2rem; color: var(--text-light); }
        .no-data i { font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.7; }
        .no-data h4 { margin-bottom: 1rem; font-weight: 600; font-size: 1.5rem; }

        /* Badge */
        .badge { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .badge-primary { background: rgba(255, 218, 106, 0.2); color: var(--text-dark); border: 1px solid rgba(255, 218, 106, 0.3); }

        /* Responsive */
        @media (max-width: 1200px) { .sidebar { width: 260px; } .main-content { margin-left: 260px; } }
        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .top-header { height: 70px; padding: 0.5rem 0; }
            .mobile-menu-toggle { display: flex; top: 85px; left: 20px; }
            .sidebar { left: 0; top: 70px; height: calc(100vh - 70px); transform: translateX(-100%); width: 300px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { padding: 1.5rem; margin-left: 0; }
            .notification-dropdown { width: 320px; right: -50px; }
            .notification-dropdown::before { right: 60px; }
            .activity-table { font-size: 0.9rem; }
            .activity-table th, .activity-table td { padding: 1rem 0.75rem; }
        }
        @media (max-width: 576px) {
            .activity-table { display: block; overflow-x: auto; white-space: nowrap; }
            .notification-dropdown { width: 280px; right: -30px; }
            .notification-dropdown::before { right: 40px; }
        }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeInUp 0.8s ease-out; }
        .stagger-animation > * { opacity: 0; animation: fadeInUp 0.6s ease-out forwards; }
        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
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
                            <div class="bell-badge" id="bellBadge"><?= $total_notifications ?></div>
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
                                            <div class="notification-icon approved"><i class="fas fa-check-circle"></i></div>
                                            <div class="notification-content"><p><?= $approved_count ?> Approved</p><small>Request approved</small></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($disapproved_count > 0): ?>
                                        <div class="notification-item disapproved">
                                            <div class="notification-icon disapproved"><i class="fas fa-times-circle"></i></div>
                                            <div class="notification-content"><p><?= $disapproved_count ?> Disapproved</p><small>Request disapproved</small></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rejected_count > 0): ?>
                                        <div class="notification-item rejected">
                                            <div class="notification-icon rejected"><i class="fas fa-times-circle"></i></div>
                                            <div class="notification-content"><p><?= $rejected_count ?> Rejected</p><small>Request rejected</small></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rescheduled_count > 0): ?>
                                        <div class="notification-item rescheduled">
                                            <div class="notification-icon rescheduled"><i class="fas fa-calendar-alt"></i></div>
                                            <div class="notification-content"><p><?= $rescheduled_count ?> Rescheduled</p><small>Consultation rescheduled</small></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($cancelled_count > 0): ?>
                                        <div class="notification-item cancelled">
                                            <div class="notification-icon cancelled"><i class="fas fa-ban"></i></div>
                                            <div class="notification-content"><p><?= $cancelled_count ?> Cancelled</p><small>Consultation cancelled</small></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($no_show_count > 0): ?>
                                        <div class="notification-item no-show">
                                            <div class="notification-icon no-show"><i class="fas fa-user-times"></i></div>
                                            <div class="notification-content"><p><?= $no_show_count ?> No Show</p><small>Marked as no show</small></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($announcement_notifications > 0): ?>
                                <div class="notification-section">
                                    <div class="notification-section-header">
                                        <h6><i class="fas fa-bullhorn me-2"></i> Announcements</h6>
                                        <span class="notification-section-count"><?= $announcement_notifications ?></span>
                                    </div>
                                    <?php if ($new_announcements_count > 0): ?>
                                        <div class="notification-item new-announcement">
                                            <div class="notification-icon new-announcement"><i class="fas fa-bell"></i></div>
                                            <div class="notification-content"><p><?= $new_announcements_count ?> New Announcement<?= $new_announcements_count > 1 ? 's' : '' ?></p><small>Posted recently</small></div>
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
                                <a href="schedule_consultation.php" class="btn btn-primary btn-sm me-2"><i class="fas fa-calendar me-1"></i> Consultations</a>
                                <a href="student_announcement.php" class="btn btn-success btn-sm"><i class="fas fa-bullhorn me-1"></i> Announcements</a>
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
                <a href="student_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i><span>Dashboard</span>
                </a>
                <a href="update_profile.php" class="nav-item">
                    <i class="fas fa-user-edit"></i><span>Update Profile</span>
                </a>
                <a href="schedule_consultation.php" class="nav-item">
                    <i class="fas fa-calendar-plus"></i><span>Schedule Consultation</span>
                    <?php if ($consultation_notifications > 0): ?>
                        <div class="notification-badge total" title="Consultation updates: <?= $consultation_notifications ?>">
                            <?= $consultation_notifications ?>
                        </div>
                    <?php endif; ?>
                </a>
                <a href="student_report.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i><span>Report</span>
                </a>
                <a href="student_announcement.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i><span>Announcement</span>
                    <?php if ($announcement_notifications > 0): ?>
                        <div class="notification-badge announcement" title="Announcement updates: <?= $announcement_notifications ?>">
                            <?= $announcement_notifications ?>
                        </div>
                    <?php endif; ?>
                </a>
                <a href="activity_logs.php" class="nav-item active">
                    <i class="fas fa-clipboard-list"></i><span>Activity Logs</span>
                </a>
                <a href="logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Activity Logs 📋</h1>
                    <p>Track your important activities and actions in the system</p>
                </div>
            </div>

            <div class="activity-card fade-in">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history me-2"></i> Activity History <span class="badge badge-primary ms-2"><?php echo count($logs); ?> Records</span></h3>
                    <div class="card-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-info"><strong><i class="fas fa-info-circle me-2"></i>Info:</strong> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($logs)): ?>
                    <div class="table-responsive">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="stagger-animation">
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="action-cell">
                                                <?php 
                                                $action = htmlspecialchars($log['action']);
                                                $iconClass = 'icon-secondary';
                                                $icon = 'fas fa-history';
                                                
                                                if (strpos($action, 'profile') !== false) {
                                                    $iconClass = 'icon-primary'; $icon = 'fas fa-user-edit';
                                                } elseif (strpos($action, 'medical') !== false) {
                                                    $iconClass = 'icon-info'; $icon = 'fas fa-file-medical';
                                                } elseif (strpos($action, 'password') !== false) {
                                                    $iconClass = 'icon-warning'; $icon = 'fas fa-key';
                                                } elseif (strpos($action, 'consultation') !== false) {
                                                    if (strpos($action, 'Scheduled') !== false) {
                                                        $iconClass = 'icon-success'; $icon = 'fas fa-calendar-plus';
                                                    } elseif (strpos($action, 'Edited') !== false) {
                                                        $iconClass = 'icon-primary'; $icon = 'fas fa-edit';
                                                    } elseif (strpos($action, 'Cancelled') !== false) {
                                                        $iconClass = 'icon-danger'; $icon = 'fas fa-times-circle';
                                                    } else {
                                                        $iconClass = 'icon-info'; $icon = 'fas fa-calendar-check';
                                                    }
                                                }
                                                ?>
                                                <div class="action-icon <?php echo $iconClass; ?>"><i class="<?php echo $icon; ?>"></i></div>
                                                <span><?php echo $action; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted"><i class="fas fa-clock me-2"></i><?php echo date('M d, Y h:i A', strtotime($log['log_date'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-clipboard-list"></i>
                        <h4>No Activities Recorded</h4>
                        <p>Your important activities will appear here once you perform actions in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ✅ BELL NOTIFICATION (WITH PERSISTENCE FIX)
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const bellBadge = document.getElementById('bellBadge');
            const notificationCount = document.getElementById('notificationCount');

            if (notificationBell) {
                notificationBell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('active');
                    
                    // Animation
                    this.style.transform = 'scale(1.1)';
                    setTimeout(() => { this.style.transform = 'scale(1)'; }, 200);
                    
                    // Hide Badge Visually
                    if (bellBadge) {
                        bellBadge.style.display = 'none';
                    }
                    if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }

                    // Send AJAX Request
                    const formData = new FormData();
                    formData.append('action', 'mark_read');

                    fetch('activity_logs.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // console.log('Marked as read:', data);
                    })
                    .catch(error => console.error('Error:', error));
                });
            }

            document.addEventListener('click', function(e) {
                if (notificationBell && !notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('active');
                }
            });

            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (this.classList.contains('new-announcement')) {
                        window.location.href = 'student_announcement.php';
                    } else {
                        window.location.href = 'schedule_consultation.php';
                    }
                });
                item.style.cursor = 'pointer';
            });

            // MOBILE MENU
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleMobileMenu() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }

            if(mobileMenuToggle) {
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

            // AUTO REFRESH (Optional, kept from previous code)
            setTimeout(() => { location.reload(); }, 120000);

            // ANIMATIONS
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>