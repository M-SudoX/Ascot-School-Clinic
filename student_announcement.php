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
$student_number = $_SESSION['student_number'] ?? '';

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

// ==================== ANNOUNCEMENT SPECIFIC LOGIC ====================

// ✅ NEW: AUTO-EXPIRE ANNOUNCEMENTS ON PAGE LOAD
function expireAnnouncements($pdo) {
    try {
        $currentDateTime = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET is_active = 0, 
                status = 'inactive',
                updated_at = NOW()
            WHERE expiry_date IS NOT NULL 
            AND expiry_date <= ? 
            AND is_active = 1
            AND status = 'active'
        ");
        $stmt->execute([$currentDateTime]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error expiring announcements: " . $e->getMessage());
        return 0;
    }
}

// ✅ RUN AUTO-EXPIRATION ON EVERY PAGE LOAD
expireAnnouncements($pdo);

// Function to check file paths
function checkMediaFile($filename) {
    if (empty($filename)) return ['exists' => false, 'path' => ''];
    
    $possible_paths = [
        '../uploads/announcements/' . $filename,
        'uploads/announcements/' . $filename,
        '../admin/uploads/announcements/' . $filename,
        'admin/uploads/announcements/' . $filename,
        'uploads/' . $filename,
        '../uploads/' . $filename
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return ['exists' => true, 'path' => $path];
        }
    }
    
    return ['exists' => false, 'path' => '../uploads/announcements/' . $filename];
}

// Function to calculate time remaining until expiry
function getTimeRemaining($expiry_date) {
    if (empty($expiry_date)) return null;
    
    $now = new DateTime();
    $expiry = new DateTime($expiry_date);
    
    if ($expiry <= $now) {
        return 'expired';
    }
    
    $interval = $now->diff($expiry);
    
    if ($interval->days > 0) {
        return $interval->days . ' day' . ($interval->days > 1 ? 's' : '') . ' remaining';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' remaining';
    } else {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' remaining';
    }
}

// Function to check if announcement is expired
function isExpired($expiry_date) {
    if (empty($expiry_date)) return false;
    $now = new DateTime();
    $expiry = new DateTime($expiry_date);
    return $expiry <= $now;
}

// ✅ FETCH ANNOUNCEMENTS
try {
    // Get ALL active announcements that should be shown to students
    $stmt = $pdo->prepare("
        SELECT a.* FROM announcements a
        WHERE a.post_on_front = 1 
        AND a.is_active = 1
        AND (a.expiry_date IS NULL OR a.expiry_date > NOW())
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get expired announcements
    $expired_stmt = $pdo->prepare("
        SELECT a.* FROM announcements a
        WHERE a.post_on_front = 1 
        AND (a.is_active = 0 OR a.expiry_date <= NOW())
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $expired_stmt->execute();
    $expired_announcements = $expired_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $announcements = [];
    $expired_announcements = [];
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

// ✅ UPDATED: FETCH ANNOUNCEMENT COUNTS (Active & New Only)
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
    
    $announcement_notifications = $new_announcements_count;
    $total_notifications = $consultation_notifications + $announcement_notifications;
    
} catch (PDOException $e) {
    $announcement_notifications = 0;
    $total_notifications = $consultation_notifications;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - ASCOT Clinic</title>
    
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

        /* ANNOUNCEMENT TABS */
        .announcement-tabs {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.3);
            transition: var(--transition);
        }

        .announcement-tabs:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .nav-tabs {
            border-bottom: 1px solid rgba(233, 236, 239, 0.8);
            padding: 0;
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.9) 0%, rgba(233, 236, 239, 0.95) 100%);
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--text-light);
            font-weight: 600;
            padding: 1.5rem 2rem;
            margin-bottom: -1px;
            transition: var(--transition);
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .nav-tabs .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            transition: var(--transition);
            transform: translateX(-50%);
        }

        .nav-tabs .nav-link.active {
            color: var(--text-dark);
            background: transparent;
        }

        .nav-tabs .nav-link.active::before { width: 100%; }

        .nav-tabs .nav-link:hover {
            color: var(--text-dark);
            background: rgba(255,255,255,0.5);
        }

        .tab-content { padding: 2.5rem; }

        /* Announcement Cards */
        .announcement-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border-left: 6px solid var(--accent);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .announcement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .announcement-card.expired {
            border-left: 6px solid var(--gray);
            background: rgba(248, 249, 250, 0.9);
            opacity: 0.8;
        }

        .announcement-card.expiring-soon {
            border-left: 6px solid var(--warning);
            background: rgba(255, 251, 240, 0.9);
        }

        .announcement-header {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .announcement-icon {
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            color: var(--text-dark);
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(255,218,106,0.4);
            transition: var(--transition);
        }

        .announcement-icon.expired { background: linear-gradient(135deg, var(--gray), #6c757d); }
        .announcement-icon.expiring-soon { background: linear-gradient(135deg, var(--warning), #e0a800); }

        .announcement-meta { flex: 1; }

        .announcement-meta h4 {
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 1.3rem;
        }

        .announcement-date {
            color: var(--text-light);
            font-size: 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .expiry-info {
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            border-left: 4px solid;
            transition: var(--transition);
        }

        .expiry-info.expiring {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border-left-color: var(--warning);
        }

        .expiry-info.expired {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-left-color: var(--danger);
        }

        .expiry-info.no-expiry {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left-color: var(--success);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success { background: rgba(40, 167, 69, 0.1); color: #155724; border: 1px solid rgba(40, 167, 69, 0.2); }
        .badge-warning { background: rgba(255, 193, 7, 0.1); color: #856404; border: 1px solid rgba(255, 193, 7, 0.2); }
        .badge-danger { background: rgba(220, 53, 69, 0.1); color: var(--danger); border: 1px solid rgba(220, 53, 69, 0.2); }
        .badge-info { background: rgba(23, 162, 184, 0.1); color: #0c5460; border: 1px solid rgba(23, 162, 184, 0.2); }
        .badge-secondary { background: rgba(108, 117, 125, 0.1); color: var(--gray); border: 1px solid rgba(108, 117, 125, 0.2); }
        .badge-primary { background: rgba(255, 218, 106, 0.2); color: var(--text-dark); border: 1px solid rgba(255, 218, 106, 0.3); }

        .announcement-body { margin-bottom: 1.5rem; }
        .announcement-body p { color: var(--text-dark); line-height: 1.7; margin: 0; font-size: 1rem; }

        .announcement-footer {
            border-top: 1px solid rgba(233, 236, 239, 0.8);
            padding-top: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .announcement-category {
            background: rgba(248, 249, 250, 0.8);
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .announcement-category:hover { background: rgba(255, 218, 106, 0.2); color: var(--text-dark); }

        .status-active { color: var(--success); font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .status-expired { color: var(--danger); font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }

        .no-announcements { text-align: center; padding: 4rem 2rem; color: var(--text-light); }
        .no-announcements i { font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.7; }
        .no-announcements h4 { margin-bottom: 1rem; font-weight: 600; font-size: 1.5rem; }

        /* Media Styles */
        .announcement-media {
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: var(--transition);
        }
        .announcement-media:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .announcement-image { width: 100%; height: auto; max-height: 400px; object-fit: contain; cursor: pointer; transition: transform 0.3s ease; background: #f8f9fa; }
        .announcement-image:hover { transform: scale(1.02); }
        .announcement-video { width: 100%; height: auto; max-height: 400px; background: #000; border-radius: 8px; }

        .announcement-pdf-preview {
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            color: var(--text-dark);
            padding: 2.5rem;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1.5rem;
            box-shadow: 0 4px 15px rgba(255,218,106,0.4);
        }
        .announcement-pdf-preview:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(255,218,106,0.6); }
        .announcement-pdf-preview i { font-size: 3rem; margin-bottom: 1rem; display: block; }

        /* Modal */
        .modal-content { border-radius: var(--border-radius); border: none; box-shadow: var(--shadow); backdrop-filter: blur(20px); background: rgba(255,255,255,0.95); }
        .modal-header { background: linear-gradient(135deg, var(--accent), #ffd24a); color: var(--text-dark); border-bottom: none; padding: 1.5rem 2rem; }

        /* Countdown */
        .countdown-timer { display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem; }
        .countdown-expiring { background: #fff3cd; color: #856404; animation: pulse 2s infinite; border: 1px solid #ffeaa7; }
        .countdown-expired { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

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
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu"><i class="fas fa-bars"></i></button>
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
                                        <h6><i class="fas fa-bullhorn me-2"></i> Announcement Updates</h6>
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

    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Preview" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>

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
                        <div class="notification-badge total"><?= $consultation_notifications ?></div>
                    <?php endif; ?>
                </a>
                <a href="student_report.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i><span>Report</span>
                </a>
                <a href="student_announcement.php" class="nav-item active">
                    <i class="fas fa-bullhorn"></i><span>Announcement</span>
                    <?php if ($announcement_notifications > 0): ?>
                        <div class="notification-badge announcement"><?= $announcement_notifications ?></div>
                    <?php endif; ?>
                </a>
                <a href="activity_logs.php" class="nav-item">
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
                    <h1>Announcements 📢</h1>
                    <p>Stay updated with the latest clinic announcements and notices</p>
                </div>
            </div>

            <div class="announcement-tabs fade-in">
                <ul class="nav nav-tabs" id="announcementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                            <i class="fas fa-bell me-2"></i> Active Announcements
                            <span class="badge badge-primary ms-2"><?php echo count($announcements); ?></span>
                            <?php if ($new_announcements_count > 0): ?>
                                <span class="badge badge-success ms-1"><?= $new_announcements_count ?> new</span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="archive-tab" data-bs-toggle="tab" data-bs-target="#archive" type="button" role="tab">
                            <i class="fas fa-archive me-2"></i> Expired Announcements
                            <span class="badge badge-secondary ms-2"><?php echo count($expired_announcements); ?></span>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="announcementTabsContent">
                    <div class="tab-pane fade show active" id="active" role="tabpanel">
                        <?php if (empty($announcements)): ?>
                            <div class="no-announcements">
                                <i class="fas fa-inbox"></i>
                                <h4>No Active Announcements</h4>
                                <p>Check back later for updates from the clinic</p>
                            </div>
                        <?php else: ?>
                            <div class="stagger-animation">
                                <?php foreach ($announcements as $announcement): 
                                    $timeRemaining = getTimeRemaining($announcement['expiry_date']);
                                    $isExpiringSoon = $timeRemaining && $timeRemaining !== 'expired' && strpos($timeRemaining, 'day') === false;
                                    $cardClass = $isExpiringSoon ? 'expiring-soon' : '';
                                    $isNew = strtotime($announcement['created_at']) >= strtotime('-7 days');
                                ?>
                                    <div class="announcement-card <?php echo $cardClass; ?>" data-announcement-id="<?php echo $announcement['id']; ?>" data-expiry="<?php echo $announcement['expiry_date']; ?>">
                                        <div class="announcement-header">
                                            <div class="announcement-icon <?php echo $cardClass; ?>"><i class="fas fa-bullhorn"></i></div>
                                            <div class="announcement-meta">
                                                <h4>
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                    <?php if ($isNew): ?><span class="badge badge-success"><i class="fas fa-star me-1"></i> New</span><?php endif; ?>
                                                    <?php if ($isExpiringSoon): ?><span class="badge badge-warning"><i class="fas fa-clock me-1"></i> Expiring Soon</span><?php endif; ?>
                                                </h4>
                                                <div class="announcement-date">
                                                    <span><i class="fas fa-calendar-alt me-1"></i> Posted: <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                                    <div class="expiry-info <?php echo empty($announcement['expiry_date']) ? 'no-expiry' : ($isExpiringSoon ? 'expiring' : ''); ?>">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php if (empty($announcement['expiry_date'])): ?> No expiry date
                                                        <?php else: ?> Expires: <?php echo date('F j, Y g:i A', strtotime($announcement['expiry_date'])); ?>
                                                            <?php if ($timeRemaining && $timeRemaining !== 'expired'): ?>
                                                                - <span class="countdown-timer <?php echo $isExpiringSoon ? 'countdown-expiring' : ''; ?>" data-expiry="<?php echo $announcement['expiry_date']; ?>"><i class="fas fa-hourglass-half me-1"></i> <?php echo $timeRemaining; ?></span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="announcement-body">
                                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            
                                            <?php if (!empty($announcement['attachment'])): 
                                                $fileCheck = checkMediaFile($announcement['attachment']);
                                                $actualPath = $fileCheck['path'];
                                                $fileExtension = strtolower(pathinfo($announcement['attachment'], PATHINFO_EXTENSION));
                                                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'webm'];
                                                $pdfExtensions = ['pdf'];
                                            ?>
                                                <?php if (in_array($fileExtension, $imageExtensions)): ?>
                                                    <div class="announcement-media">
                                                        <img src="<?php echo $actualPath; ?>" alt="Announcement Image" class="announcement-image" onclick="openImageModal('<?php echo $actualPath; ?>')">
                                                    </div>
                                                <?php elseif (in_array($fileExtension, $videoExtensions)): ?>
                                                    <div class="announcement-media">
                                                        <video class="announcement-video" controls>
                                                            <source src="<?php echo $actualPath; ?>" type="video/<?php echo $fileExtension; ?>">
                                                        </video>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')">
                                                        <i class="fas fa-file<?php echo in_array($fileExtension, $pdfExtensions) ? '-pdf' : ''; ?>"></i>
                                                        <h5>Document File</h5><p>Click to view</p>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="announcement-footer">
                                            <span class="announcement-category"><i class="fas fa-user me-1"></i> Sent by: <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Admin'); ?></span>
                                            <span class="status-active"><i class="fas fa-circle me-1"></i> Active</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="archive" role="tabpanel">
                        <?php if (empty($expired_announcements)): ?>
                            <div class="no-announcements">
                                <i class="fas fa-archive"></i>
                                <h4>No Expired Announcements</h4>
                                <p>No expired announcements found</p>
                            </div>
                        <?php else: ?>
                            <div class="stagger-animation">
                                <?php foreach ($expired_announcements as $announcement): ?>
                                    <div class="announcement-card expired">
                                        <div class="announcement-header">
                                            <div class="announcement-icon expired"><i class="fas fa-archive"></i></div>
                                            <div class="announcement-meta">
                                                <h4><?php echo htmlspecialchars($announcement['title']); ?> <span class="badge badge-danger"><i class="fas fa-ban me-1"></i> Expired</span></h4>
                                                <div class="announcement-date">
                                                    <span><i class="fas fa-calendar-alt me-1"></i> Posted: <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                                    <div class="expiry-info expired">
                                                        <i class="fas fa-clock me-1"></i> Expired: <?php echo !empty($announcement['expiry_date']) ? date('F j, Y g:i A', strtotime($announcement['expiry_date'])) : 'Manually deactivated'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="announcement-body">
                                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                        </div>
                                        <div class="announcement-footer">
                                            <span class="announcement-category"><i class="fas fa-user me-1"></i> Sent by: <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Admin'); ?></span>
                                            <span class="status-expired"><i class="fas fa-circle me-1"></i> Expired</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // BELL NOTIFICATION - Updated Logic for Persistence
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const bellBadge = document.getElementById('bellBadge');
            const notificationCount = document.getElementById('notificationCount'); // Ensure this ID exists in HTML

            if (notificationBell) {
                notificationBell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('active');
                    
                    this.style.transform = 'scale(1.1)';
                    setTimeout(() => { this.style.transform = 'scale(1)'; }, 200);
                    
                    // Hide Badge Visually
                    if (bellBadge) {
                        bellBadge.style.display = 'none';
                    }
                    if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }

                    // Send AJAX Request to Update DB & Session
                    const formData = new FormData();
                    formData.append('action', 'mark_read');

                    fetch('student_announcement.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // console.log(data); // For debugging
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

            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            sidebarOverlay.addEventListener('click', toggleMobileMenu);

            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                    });
                });
            }

            // IMAGE MODAL
            window.openImageModal = function(filePath) {
                const modalImage = document.getElementById('modalImage');
                modalImage.src = filePath;
                const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
                imageModal.show();
            }

            // TAB PERSISTENCE
            const announcementTabs = document.getElementById('announcementTabs');
            if (announcementTabs) {
                announcementTabs.addEventListener('click', function(e) {
                    if (e.target.tagName === 'BUTTON') {
                        const tabId = e.target.getAttribute('data-bs-target').replace('#', '');
                        localStorage.setItem('activeAnnouncementTab', tabId);
                    }
                });
                const activeTab = localStorage.getItem('activeAnnouncementTab') || 'active';
                const tabButton = document.querySelector(`[data-bs-target="#${activeTab}"]`);
                if (tabButton) {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }

            // REAL-TIME EXPIRATION CHECK
            function checkAnnouncementExpirations() {
                const activeAnnouncements = document.querySelectorAll('.announcement-card:not(.expired)');
                const now = new Date();
                
                activeAnnouncements.forEach(card => {
                    const expiryDate = card.getAttribute('data-expiry');
                    if (expiryDate && expiryDate !== 'null') {
                        const expiry = new Date(expiryDate);
                        if (expiry <= now) {
                            window.location.reload(); // Reload to update status
                        }
                    }
                });
            }
            
            setInterval(checkAnnouncementExpirations, 30000);

            // ANIMATIONS
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>