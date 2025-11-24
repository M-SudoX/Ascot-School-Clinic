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
    $stmt = $pdo->prepare("SELECT student_number FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_number = $stmt->fetchColumn();
    $_SESSION['student_number'] = $student_number;
}

if (!$student_number) {
    die("Student record not found.");
}

// ==================== REPORT LOGIC (CHARTS) ====================

// ✅ Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');

// ✅ Get start and end of the current month
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// ✅ Fetch consultations from consultations table (added by admin)
$query = "
    SELECT consultation_date 
    FROM consultations
    WHERE student_number = :student_number
      AND consultation_date BETWEEN :start AND :end
";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'student_number' => $student_number,
    'start' => $monthStart,
    'end' => $monthEnd
]);
$consultations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ✅ Determine how many weeks are in this month
$firstDay = new DateTime($monthStart);
$lastDay = new DateTime($monthEnd);
$numWeeks = ceil(($lastDay->format('d') + $firstDay->format('N') - 1) / 7);

// ✅ Initialize week data dynamically
$consultationData = array_fill(0, $numWeeks, 0);

// ✅ Count consultations per week
foreach ($consultations as $consultDate) {
    $day = (int)date('j', strtotime($consultDate));
    $weekNum = (int)ceil(($day + date('N', strtotime(date('Y-m-01')))) / 7);
    if ($weekNum >= 1 && $weekNum <= $numWeeks) {
        $consultationData[$weekNum - 1]++;
    }
}

$totalConsults = array_sum($consultationData);

// ✅ Generate week labels
$weekLabels = [];
for ($i = 1; $i <= $numWeeks; $i++) {
    $weekLabels[] = "Week $i";
}

// ==================== NOTIFICATION LOGIC (UPDATED) ====================

// ✅ FETCH CONSULTATION STATUS COUNTS (UNVIEWED ONLY)
try {
    $status_counts_stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM consultation_requests 
        WHERE student_id = ? 
        AND status IN ('Approved', 'Disapproved', 'Rescheduled', 'Cancelled', 'No Show')
        AND is_viewed = FALSE
        GROUP BY status
    ");
    $status_counts_stmt->execute([$student_id]);
    $status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize counts
    $approved_count = 0;
    $disapproved_count = 0;
    $rescheduled_count = 0;
    $cancelled_count = 0;
    $no_show_count = 0;
    
    // Process counts
    foreach ($status_counts as $status_count) {
        switch ($status_count['status']) {
            case 'Approved': $approved_count = $status_count['count']; break;
            case 'Disapproved': $disapproved_count = $status_count['count']; break;
            case 'Rescheduled': $rescheduled_count = $status_count['count']; break;
            case 'Cancelled': $cancelled_count = $status_count['count']; break;
            case 'No Show': $no_show_count = $status_count['count']; break;
        }
    }
    
    $consultation_notifications = $approved_count + $disapproved_count + $rescheduled_count + $cancelled_count + $no_show_count;
    
} catch (PDOException $e) {
    $consultation_notifications = 0;
}

// ✅ UPDATED: FETCH ANNOUNCEMENT COUNTS (WITH SESSION CHECK)
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
    
    // TOTAL ANNOUNCEMENT NOTIFICATIONS
    $announcement_notifications = $new_announcements_count;
    
    // TOTAL ALL NOTIFICATIONS
    $total_notifications = $consultation_notifications + $announcement_notifications;
    
} catch (PDOException $e) {
    $new_announcements_count = 0;
    $announcement_notifications = 0;
    $total_notifications = $consultation_notifications;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report - ASCOT Clinic</title>
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Stats Card */
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .stats-card:hover { transform: translateY(-3px); }

        .stats-icon {
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(255,218,106,0.4);
        }

        .stats-icon i {
            font-size: 2.5rem;
            color: var(--text-dark);
        }

        .stats-info h5 {
            color: var(--text-light);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .stats-info h2 {
            color: var(--text-dark);
            font-weight: 800;
            font-size: 3rem;
            margin: 0;
            background: linear-gradient(135deg, var(--text-dark), #495057);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Chart Container */
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,0.3);
            width: 100%;
            max-width: 800px;
            height: 450px;
            margin: 0 auto;
            position: relative;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .chart-title {
            color: var(--text-dark);
            font-weight: 800;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-month {
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            color: var(--text-dark);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(255,218,106,0.4);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar { width: 260px; }
            .main-content { margin-left: 260px; }
        }

        @media (max-width: 992px) {
            .school-name { font-size: 1rem; }
            .logo-img { width: 50px; height: 50px; }
            .notification-dropdown { width: 350px; }
        }

        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .top-header { height: 70px; padding: 0.5rem 0; }
            .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; top: 85px; left: 20px; }
            .sidebar { position: fixed; left: 0; top: 70px; height: calc(100vh - 70px); z-index: 1020; transform: translateX(-100%); width: 300px; }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay { top: 70px; }
            .main-content { padding: 1.5rem; width: 100%; margin-left: 0; }
            .header-content { padding: 0 1rem; }
            .school-name { font-size: 0.9rem; }
            .republic, .clinic-title { font-size: 0.65rem; }
            .notification-dropdown { width: 320px; right: -50px; }
            .notification-dropdown::before { right: 60px; }
        }

        @media (max-width: 576px) {
            .main-content { padding: 1.25rem; }
            .notification-dropdown { width: 280px; right: -30px; }
            .notification-dropdown::before { right: 40px; }
            .mobile-menu-toggle { top: 80px; width: 45px; height: 45px; }
            .welcome-content h1 { font-size: 1.4rem; }
            .chart-container { padding: 1.5rem; height: 350px; }
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeInUp 0.8s ease-out; }
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
                                            <div class="notification-icon approved"><i class="fas fa-check-circle"></i></div>
                                            <div class="notification-content">
                                                <p><?= $approved_count ?> Consultation<?= $approved_count > 1 ? 's' : '' ?> Approved</p>
                                                <small>Your consultation request has been approved</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($disapproved_count > 0): ?>
                                        <div class="notification-item disapproved">
                                            <div class="notification-icon disapproved"><i class="fas fa-times-circle"></i></div>
                                            <div class="notification-content">
                                                <p><?= $disapproved_count ?> Consultation<?= $disapproved_count > 1 ? 's' : '' ?> Disapproved</p>
                                                <small>Your consultation request has been disapproved</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rescheduled_count > 0): ?>
                                        <div class="notification-item rescheduled">
                                            <div class="notification-icon rescheduled"><i class="fas fa-calendar-alt"></i></div>
                                            <div class="notification-content">
                                                <p><?= $rescheduled_count ?> Consultation<?= $rescheduled_count > 1 ? 's' : '' ?> Rescheduled</p>
                                                <small>Your consultation has been rescheduled</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($cancelled_count > 0): ?>
                                        <div class="notification-item cancelled">
                                            <div class="notification-icon cancelled"><i class="fas fa-ban"></i></div>
                                            <div class="notification-content">
                                                <p><?= $cancelled_count ?> Consultation<?= $cancelled_count > 1 ? 's' : '' ?> Cancelled</p>
                                                <small>Your consultation has been cancelled</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($no_show_count > 0): ?>
                                        <div class="notification-item no-show">
                                            <div class="notification-icon no-show"><i class="fas fa-user-times"></i></div>
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
                                            <div class="notification-icon new-announcement"><i class="fas fa-bell"></i></div>
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
                    <?php if ($consultation_notifications > 0): ?>
                        <div class="notification-badge total" title="Consultation updates: <?= $consultation_notifications ?>">
                            <?= $consultation_notifications ?>
                        </div>
                    <?php endif; ?>
                </a>

                <a href="student_report.php" class="nav-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
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
                    <h1>Your Consultation Reports 📊</h1>
                    <p>Track your monthly consultation activity and statistics</p>
                </div>
            </div>

            <div class="stats-card fade-in">
                <div class="stats-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stats-info">
                    <h5>Total Consultations This Month</h5>
                    <h2><?php echo $totalConsults; ?></h2>
                </div>
            </div>

            <div class="chart-container fade-in">
                <div class="chart-header">
                    <h3 class="chart-title"><i class="fas fa-chart-line me-2"></i>Weekly Consultation History</h3>
                    <div class="chart-month">
                        <i class="fas fa-calendar-alt"></i>
                        <strong><?php echo date('F Y'); ?></strong>
                    </div>
                </div>
                <canvas id="consultChart"></canvas>
            </div>
        </main>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ✅ BELL NOTIFICATION FUNCTIONALITY (WITH DB UPDATE)
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const bellBadge = document.getElementById('bellBadge');
            const notificationCount = document.getElementById('notificationCount');

            // Toggle notification dropdown
            if (notificationBell) {
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
                    
                    // ✅ FIX: HIDE BADGE AUTOMATICALLY (VISUAL ONLY)
                    if (bellBadge) {
                        bellBadge.style.transition = 'opacity 0.3s ease';
                        bellBadge.style.opacity = '0';
                        setTimeout(() => {
                            bellBadge.style.display = 'none';
                        }, 300);
                    }
                    if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }

                    // ✅ SEND AJAX REQUEST TO UPDATE DATABASE (Persistence)
                    const formData = new FormData();
                    formData.append('action', 'mark_read');

                    fetch('student_report.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // console.log('Notifications marked as read:', data); 
                    })
                    .catch(error => console.error('Error marking notifications as read:', error));
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
            }

            // ✅ NOTIFICATION ITEM INTERACTIONS
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

            // MOBILE MENU FUNCTIONALITY
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

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
                sidebarOverlay.addEventListener('click', toggleMobileMenu);
            }

            // Close sidebar when clicking nav items on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        if (mobileMenuToggle) {
                            mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                        }
                    });
                });
            }

            // ADD SMOOTH SCROLLING
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // CHART CONFIGURATION
            const consultationData = <?php echo json_encode($consultationData); ?>;
            const weekLabels = <?php echo json_encode($weekLabels); ?>;

            const ctx = document.getElementById('consultChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: weekLabels,
                    datasets: [{
                        label: 'Number of Consultations',
                        data: consultationData,
                        backgroundColor: 'rgba(255, 218, 106, 0.8)',
                        borderColor: '#ffda6a',
                        borderWidth: 2,
                        borderRadius: 8,
                        hoverBackgroundColor: '#ffd24a',
                        hoverBorderColor: '#ffc107',
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            display: false 
                        },
                        tooltip: {
                            backgroundColor: 'rgba(44, 62, 80, 0.95)',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            borderColor: '#ffda6a',
                            borderWidth: 2,
                            cornerRadius: 8,
                            callbacks: {
                                label: context => `Consultations: ${context.parsed.y}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Consultations',
                                font: { size: 14, weight: 'bold' },
                                color: '#2c3e50'
                            },
                            ticks: { 
                                stepSize: 1, 
                                font: { size: 12 }, 
                                color: '#6c757d' 
                            },
                            grid: { 
                                color: 'rgba(108, 117, 125, 0.1)', 
                                drawBorder: false 
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Weeks',
                                font: { size: 14, weight: 'bold' },
                                color: '#2c3e50'
                            },
                            ticks: { 
                                font: { size: 12 }, 
                                color: '#6c757d' 
                            },
                            grid: { 
                                display: false 
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });

            // RESIZE HANDLER
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
                
                // Close notification dropdown on mobile when resizing
                if (window.innerWidth <= 768) {
                    if (notificationDropdown) {
                        notificationDropdown.classList.remove('active');
                    }
                }
            });
        });
    </script>
</body>
</html>