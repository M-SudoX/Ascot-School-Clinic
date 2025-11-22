<?php
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

// ✅ Check kung naka-login ang student
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_number = $_SESSION['student_number'] ?? ($_SESSION['student_id'] ?? 'N/A');

// ✅ NEW: FETCH CONSULTATION STATUS COUNTS FOR NOTIFICATIONS
try {
    $status_counts_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM consultation_requests 
        WHERE student_id = ? 
        AND status IN ('Approved', 'Rejected', 'Rescheduled', 'Cancelled')
        GROUP BY status
    ");
    $status_counts_stmt->execute([$student_id]);
    $status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize counts
    $approved_count = 0;
    $rejected_count = 0;
    $rescheduled_count = 0;
    $cancelled_count = 0;
    $consultation_notifications = 0;
    
    // Process counts
    foreach ($status_counts as $status_count) {
        switch ($status_count['status']) {
            case 'Approved':
                $approved_count = $status_count['count'];
                break;
            case 'Rejected':
                $rejected_count = $status_count['count'];
                break;
            case 'Rescheduled':
                $rescheduled_count = $status_count['count'];
                break;
            case 'Cancelled':
                $cancelled_count = $status_count['count'];
                break;
        }
    }
    
    $consultation_notifications = $approved_count + $rejected_count + $rescheduled_count + $cancelled_count;
    
} catch (PDOException $e) {
    $approved_count = 0;
    $rejected_count = 0;
    $rescheduled_count = 0;
    $cancelled_count = 0;
    $consultation_notifications = 0;
}

// ✅ NEW: FETCH ANNOUNCEMENT COUNTS FOR NOTIFICATIONS
try {
    // COUNT NEW ANNOUNCEMENTS (last 7 days)
    $new_announcements_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM announcements 
        WHERE post_on_front = 1 
        AND is_active = 1
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND (expiry_date IS NULL OR expiry_date > NOW())
    ");
    $new_announcements_stmt->execute();
    $new_announcements_count = $new_announcements_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // COUNT EXPIRED ANNOUNCEMENTS
    $expired_count_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM announcements 
        WHERE post_on_front = 1 
        AND (is_active = 0 OR expiry_date <= NOW())
    ");
    $expired_count_stmt->execute();
    $expired_announcements_count = $expired_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // TOTAL ANNOUNCEMENT NOTIFICATIONS
    $announcement_notifications = $new_announcements_count + $expired_announcements_count;
    
    // TOTAL ALL NOTIFICATIONS
    $total_notifications = $consultation_notifications + $announcement_notifications;
    
} catch (PDOException $e) {
    $new_announcements_count = 0;
    $expired_announcements_count = 0;
    $announcement_notifications = 0;
    $total_notifications = $consultation_notifications;
}

$stmt = $pdo->prepare("SELECT fullname, student_number, course_year, cellphone_number 
                       FROM student_information 
                       WHERE student_number = :student_number LIMIT 1");
$stmt->execute([':student_number' => $student_number]);
$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ ERROR HANDLING: BACKUP SYSTEM KUNG WALANG MAKUHA SA DATABASE
if (!$student_info) {
    $student_info = [
        'fullname' => $_SESSION['fullname'] ?? 'N/A',
        'student_number' => $student_number,
        'course_year' => 'Not set',
        'cellphone_number' => 'Not set'
    ];
} else {
    $_SESSION['fullname'] = $student_info['fullname'];
    $_SESSION['student_number'] = $student_info['student_number'];
}

// ✅ Fetch ONLY SPECIFIC ACTION LOGS - hindi kasama ang viewed/accessed/login/logout
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

        /* ✅ NEW: BELL NOTIFICATION STYLES */
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

        /* ✅ NEW: NOTIFICATION DROPDOWN */
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

        .notification-item.new-announcement {
            border-left-color: var(--success);
            background: rgba(40, 167, 69, 0.05);
        }

        .notification-item.expired-announcement {
            border-left-color: var(--danger);
            background: rgba(220, 53, 69, 0.05);
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

        .notification-icon.rejected {
            background: var(--danger);
        }

        .notification-icon.rescheduled {
            background: var(--warning);
        }

        .notification-icon.cancelled {
            background: var(--secondary);
        }

        .notification-icon.new-announcement {
            background: var(--success);
        }

        .notification-icon.expired-announcement {
            background: var(--danger);
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

        /* ✅ NEW: SIDEBAR NOTIFICATION BADGES */
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

        .notification-badge.approved {
            background: linear-gradient(135deg, var(--success), #218838);
        }

        .notification-badge.rejected {
            background: linear-gradient(135deg, var(--danger), #c82333);
        }

        .notification-badge.rescheduled {
            background: linear-gradient(135deg, var(--warning), #e0a800);
        }

        .notification-badge.cancelled {
            background: linear-gradient(135deg, var(--secondary), #6f42c1);
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

        /* WELCOME SECTION - ENHANCED */
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

        /* ACTIVITY LOGS CARD - ENHANCED */
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

        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .activity-card:hover::before {
            left: 100%;
        }

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
            transition: var(--transition);
        }

        .activity-card:hover .card-icon {
            transform: scale(1.1) rotate(5deg);
        }

        /* Table Styles - ENHANCED */
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

        .activity-table tr:last-child td {
            border-bottom: none;
        }

        .activity-table tr {
            transition: var(--transition);
        }

        .activity-table tr:hover {
            background: rgba(255, 218, 106, 0.08);
            transform: translateX(5px);
        }

        .action-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

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
            transition: var(--transition);
        }

        .action-cell:hover .action-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .icon-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .icon-success { background: linear-gradient(135deg, var(--success), #218838); }
        .icon-info { background: linear-gradient(135deg, var(--info), #138496); }
        .icon-warning { background: linear-gradient(135deg, var(--warning), #e0a800); }
        .icon-danger { background: linear-gradient(135deg, var(--danger), #c82333); }
        .icon-secondary { background: linear-gradient(135deg, var(--gray), #5a6268); }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #dee2e6;
            display: block;
            opacity: 0.7;
        }

        .no-data h4 {
            color: var(--text-light);
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .no-data p {
            color: #999;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }

        .alert-info {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.2);
            color: #0c5460;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        /* Badge Styles - ENHANCED */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-primary {
            background: rgba(255, 218, 106, 0.2);
            color: var(--text-dark);
            border: 1px solid rgba(255, 218, 106, 0.3);
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

            .activity-card {
                padding: 2rem;
            }

            .card-title {
                font-size: 1.3rem;
            }

            .card-icon {
                width: 55px;
                height: 55px;
                font-size: 1.3rem;
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

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.9rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .activity-card {
                padding: 1.5rem;
            }

            .activity-table {
                font-size: 0.9rem;
            }

            .activity-table th,
            .activity-table td {
                padding: 1rem 0.75rem;
            }

            .action-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .welcome-section {
                padding: 2rem;
            }

            .welcome-content h1 {
                font-size: 1.8rem;
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
            .activity-card {
                padding: 1.25rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-content h1 {
                font-size: 1.5rem;
            }

            .main-content {
                padding: 1.25rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }

            .activity-table {
                font-size: 0.85rem;
            }

            .activity-table th,
            .activity-table td {
                padding: 0.75rem 0.5rem;
            }

            .action-icon {
                width: 40px;
                height: 40px;
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

            .activity-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .activity-card {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.25rem;
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

            .activity-card {
                padding: 0.75rem;
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

        /* Touch Device Improvements */
        .touch-device .activity-card {
            padding: 1.5rem;
        }

        .touch-device .action-icon {
            width: 50px;
            height: 50px;
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
                <div class="header-left">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                    <div class="school-info">
                        <div class="republic">Republic of the Philippines</div>
                        <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                        <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                    </div>
                </div>

                <!-- ✅ NEW: BELL NOTIFICATION -->
                <div class="notification-wrapper" style="position: relative;">
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if ($total_notifications > 0): ?>
                            <div class="bell-badge" id="bellBadge">
                                <?= $total_notifications ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ✅ NEW: NOTIFICATION DROPDOWN -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h5><i class="fas fa-bell"></i> Notifications</h5>
                            <?php if ($total_notifications > 0): ?>
                                <span class="notification-count"><?= $total_notifications ?> new</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-items">
                            <?php if ($total_notifications > 0): ?>
                                <!-- Consultation Notifications Section -->
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
                                </div>
                                <?php endif; ?>

                                <!-- Announcement Notifications Section -->
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
                                    
                                    <?php if ($expired_announcements_count > 0): ?>
                                        <div class="notification-item expired-announcement">
                                            <div class="notification-icon expired-announcement">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?= $expired_announcements_count ?> Expired Announcement<?= $expired_announcements_count > 1 ? 's' : '' ?></p>
                                                <small>No longer active</small>
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

                <a href="schedule_consultation.php" class="nav-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Schedule Consultation</span>
                    <!-- ✅ NEW: NOTIFICATION BADGES -->
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
                    <!-- ✅ NEW: ANNOUNCEMENT NOTIFICATION BADGE IN SIDEBAR -->
                    <?php if ($announcement_notifications > 0): ?>
                        <div class="notification-badge" title="Announcement updates: <?= $announcement_notifications ?>">
                            <?= $announcement_notifications ?>
                        </div>
                    <?php endif; ?>
                </a>

                <a href="activity_logs.php" class="nav-item active">
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
            <!-- WELCOME SECTION - ENHANCED -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Activity Logs 📋</h1>
                    <p>Track your important activities and actions in the system</p>
                </div>
            </div>

            <!-- ACTIVITY LOGS CARD - ENHANCED -->
            <div class="activity-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history me-2"></i>
                        Activity History
                        <span class="badge badge-primary ms-2"><?php echo count($logs); ?> Records</span>
                    </h3>
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-info">
                        <strong><i class="fas fa-info-circle me-2"></i>Info:</strong> <?php echo $error; ?>
                    </div>
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
                                                // Add icons based on action type
                                                $action = htmlspecialchars($log['action']);
                                                $iconClass = '';
                                                $icon = '';
                                                
                                                if (strpos($action, 'profile') !== false) {
                                                    $iconClass = 'icon-primary';
                                                    $icon = 'fas fa-user-edit';
                                                } elseif (strpos($action, 'medical') !== false) {
                                                    $iconClass = 'icon-info';
                                                    $icon = 'fas fa-file-medical';
                                                } elseif (strpos($action, 'password') !== false) {
                                                    $iconClass = 'icon-warning';
                                                    $icon = 'fas fa-key';
                                                } elseif (strpos($action, 'consultation') !== false) {
                                                    if (strpos($action, 'Scheduled') !== false) {
                                                        $iconClass = 'icon-success';
                                                        $icon = 'fas fa-calendar-plus';
                                                    } elseif (strpos($action, 'Edited') !== false) {
                                                        $iconClass = 'icon-primary';
                                                        $icon = 'fas fa-edit';
                                                    } elseif (strpos($action, 'Cancelled') !== false) {
                                                        $iconClass = 'icon-danger';
                                                        $icon = 'fas fa-times-circle';
                                                    } else {
                                                        $iconClass = 'icon-info';
                                                        $icon = 'fas fa-calendar-check';
                                                    }
                                                } else {
                                                    $iconClass = 'icon-secondary';
                                                    $icon = 'fas fa-history';
                                                }
                                                ?>
                                                <div class="action-icon <?php echo $iconClass; ?>">
                                                    <i class="<?php echo $icon; ?>"></i>
                                                </div>
                                                <span><?php echo $action; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted">
                                                <i class="fas fa-clock me-2"></i>
                                                <?php echo date('M d, Y h:i A', strtotime($log['log_date'])); ?>
                                            </span>
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

    <!-- JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ✅ NEW: BELL NOTIFICATION FUNCTIONALITY
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const bellBadge = document.getElementById('bellBadge');

            // Toggle notification dropdown
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
                
                // Add animation to bell when clicked
                this.style.transform = 'scale(1.1) rotate(15deg)';
                setTimeout(() => {
                    this.style.transform = 'scale(1.1) rotate(-5deg)';
                }, 150);
                setTimeout(() => {
                    this.style.transform = 'scale(1.1) rotate(0deg)';
                }, 300);
                
                // Remove pulse animation when clicked
                if (bellBadge) {
                    bellBadge.style.animation = 'none';
                    setTimeout(() => {
                        bellBadge.style.animation = 'pulse 2s infinite';
                    }, 100);
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('active');
                }
            });

            // Close dropdown when pressing Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    notificationDropdown.classList.remove('active');
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

            // LOADING ANIMATIONS
            const staggerElements = document.querySelectorAll('.stagger-animation > *');
            staggerElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.15}s`;
            });

            // ENHANCED INTERACTIONS
            const activityCards = document.querySelectorAll('.activity-card');
            activityCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-5px)';
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
                const tapTargets = document.querySelectorAll('.nav-item, .activity-card, .notification-bell');
                tapTargets.forEach(target => {
                    target.style.minHeight = '44px';
                });
            }

            // RESIZE HANDLER
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
                
                // Close notification dropdown on mobile when resizing
                if (window.innerWidth <= 768) {
                    notificationDropdown.classList.remove('active');
                }
            });

            // ✅ NEW: NOTIFICATION ITEM INTERACTIONS
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (this.classList.contains('new-announcement') || this.classList.contains('expired-announcement')) {
                        window.location.href = 'student_announcement.php';
                    } else {
                        window.location.href = 'schedule_consultation.php';
                    }
                });
                
                item.style.cursor = 'pointer';
            });

            // Auto-refresh activity logs every 2 minutes
            function autoRefreshLogs() {
                setTimeout(() => {
                    location.reload();
                }, 120000); // 2 minutes
            }

            // Initialize auto-refresh
            autoRefreshLogs();
        });
    </script>
</body>
</html>