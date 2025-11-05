<?php
// ==================== SESSION AT SECURITY ====================
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

// ✅ SECURITY CHECK: TINITIGNAN KUNG NAKA-LOGIN ANG USER
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_number = $_SESSION['student_number'] ?? ($_SESSION['student_id'] ?? 'N/A');

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
        SELECT a.* 
        FROM announcements a
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
        SELECT a.* 
        FROM announcements a
        WHERE a.post_on_front = 1 
        AND (a.is_active = 0 OR a.expiry_date <= NOW())
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $expired_stmt->execute();
    $expired_announcements = $expired_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    $announcements = [];
    $expired_announcements = [];
}

// Use PDO - Secure database access
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - ASCOT Clinic</title>
    
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

        /* ANNOUNCEMENT TABS - ENHANCED */
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

        .nav-tabs .nav-link.active::before {
            width: 100%;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: var(--text-dark);
            background: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }

        .tab-content {
            padding: 2.5rem;
        }

        /* Announcement Cards - ENHANCED */
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

        .announcement-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .announcement-card:hover::before {
            left: 100%;
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

        .announcement-card:hover .announcement-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .announcement-icon.expired {
            background: linear-gradient(135deg, var(--gray), #6c757d);
        }

        .announcement-icon.expiring-soon {
            background: linear-gradient(135deg, var(--warning), #e0a800);
        }

        .announcement-meta {
            flex: 1;
        }

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

        .announcement-date i {
            margin-right: 0.5rem;
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

        .badge-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .badge-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .badge-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border: 1px solid rgba(23, 162, 184, 0.2);
        }

        .badge-secondary {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
            border: 1px solid rgba(108, 117, 125, 0.2);
        }

        .badge-primary {
            background: rgba(255, 218, 106, 0.2);
            color: var(--text-dark);
            border: 1px solid rgba(255, 218, 106, 0.3);
        }

        .announcement-body {
            margin-bottom: 1.5rem;
        }

        .announcement-body p {
            color: var(--text-dark);
            line-height: 1.7;
            margin: 0;
            font-size: 1rem;
        }

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

        .announcement-category:hover {
            background: rgba(255, 218, 106, 0.2);
            color: var(--text-dark);
        }

        .announcement-category i {
            margin-right: 0.5rem;
        }

        .status-active {
            color: var(--success);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-inactive {
            color: var(--gray);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-expired {
            color: var(--danger);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .no-announcements {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .no-announcements i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #dee2e6;
            display: block;
            opacity: 0.7;
        }

        .no-announcements h4 {
            color: var(--text-light);
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .no-announcements p {
            color: #999;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Media Styles - ENHANCED */
        .announcement-media {
            margin-top: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: var(--transition);
        }

        .announcement-media:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .announcement-image {
            width: 100%;
            height: auto;
            max-height: 400px;
            object-fit: contain;
            cursor: pointer;
            transition: transform 0.3s ease;
            background: #f8f9fa;
        }

        .announcement-image:hover {
            transform: scale(1.02);
        }

        .announcement-video {
            width: 100%;
            height: auto;
            max-height: 400px;
            background: #000;
            border-radius: 8px;
        }

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
            position: relative;
            overflow: hidden;
        }

        .announcement-pdf-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .announcement-pdf-preview:hover::before {
            left: 100%;
        }

        .announcement-pdf-preview:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255,218,106,0.6);
        }

        .announcement-pdf-preview i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .announcement-pdf-preview h5 {
            margin-bottom: 0.5rem;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .announcement-pdf-preview p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        /* Modal enhancements - ENHANCED */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
            background: rgba(255,255,255,0.95);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--accent), #ffd24a);
            color: var(--text-dark);
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 1.5rem 2rem;
        }

        .modal-header .btn-close {
            filter: invert(0.3);
            opacity: 0.8;
            transition: var(--transition);
        }

        .modal-header .btn-close:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Countdown timer styles - ENHANCED */
        .countdown-timer {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .countdown-expiring {
            background: #fff3cd;
            color: #856404;
            animation: pulse 2s infinite;
            border: 1px solid #ffeaa7;
        }

        .countdown-expired {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes pulse {
            0% { background-color: #fff3cd; }
            50% { background-color: #ffeaa7; }
            100% { background-color: #fff3cd; }
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

            .tab-content {
                padding: 2rem;
            }

            .announcement-header {
                gap: 1.25rem;
            }

            .announcement-icon {
                width: 55px;
                height: 55px;
                font-size: 1.3rem;
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

            .announcement-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .announcement-icon {
                align-self: flex-start;
            }
            
            .announcement-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .announcement-meta h4 {
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-tabs .nav-link {
                padding: 1.25rem 1.5rem;
                font-size: 0.95rem;
            }

            .tab-content {
                padding: 1.5rem;
            }

            .welcome-section {
                padding: 2rem;
            }

            .welcome-content h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 576px) {
            .tab-content {
                padding: 1.25rem;
            }
            
            .nav-tabs .nav-link {
                padding: 1rem 1.25rem;
                font-size: 0.9rem;
            }

            .announcement-card {
                padding: 1.5rem;
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

            .announcement-pdf-preview {
                padding: 2rem;
            }

            .announcement-pdf-preview i {
                font-size: 2.5rem;
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

            .announcement-card {
                padding: 1.25rem;
            }

            .welcome-section {
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

            .announcement-card {
                padding: 1rem;
            }

            .tab-content {
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

        /* Touch Device Improvements */
        .touch-device .announcement-card {
            padding: 1.5rem;
        }

        .touch-device .announcement-icon {
            width: 55px;
            height: 55px;
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

    <!-- Image Modal -->
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
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule Consultation</span>
                </a>

                <a href="student_report.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Report</span>
                </a>

                <a href="student_announcement.php" class="nav-item active">
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
            <!-- WELCOME SECTION - ENHANCED -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Announcements 📢</h1>
                    <p>Stay updated with the latest clinic announcements and notices</p>
                </div>
            </div>

            <!-- ANNOUNCEMENT TABS - ENHANCED -->
            <div class="announcement-tabs fade-in">
                <ul class="nav nav-tabs" id="announcementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                            <i class="fas fa-bell me-2"></i> Active Announcements
                            <span class="badge badge-primary ms-2"><?php echo count($announcements); ?></span>
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
                    <!-- ACTIVE ANNOUNCEMENTS TAB -->
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
                                ?>
                                    <div class="announcement-card <?php echo $cardClass; ?>">
                                        <div class="announcement-header">
                                            <div class="announcement-icon <?php echo $cardClass; ?>">
                                                <i class="fas fa-bullhorn"></i>
                                            </div>
                                            <div class="announcement-meta">
                                                <h4>
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-users me-1"></i> All Students
                                                    </span>
                                                    <?php if ($isExpiringSoon): ?>
                                                        <span class="badge badge-warning">
                                                            <i class="fas fa-clock me-1"></i> Expiring Soon
                                                        </span>
                                                    <?php endif; ?>
                                                </h4>
                                                <div class="announcement-date">
                                                    <span>
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        Posted: <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                    </span>
                                                    
                                                    <!-- EXPIRY INFORMATION -->
                                                    <div class="expiry-info <?php echo empty($announcement['expiry_date']) ? 'no-expiry' : ($isExpiringSoon ? 'expiring' : ''); ?>">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php if (empty($announcement['expiry_date'])): ?>
                                                            No expiry date
                                                        <?php else: ?>
                                                            Expires: <?php echo date('F j, Y g:i A', strtotime($announcement['expiry_date'])); ?>
                                                            <?php if ($timeRemaining && $timeRemaining !== 'expired'): ?>
                                                                - <span class="countdown-timer <?php echo $isExpiringSoon ? 'countdown-expiring' : ''; ?>" 
                                                                       data-expiry="<?php echo $announcement['expiry_date']; ?>">
                                                                    <i class="fas fa-hourglass-half me-1"></i>
                                                                    <?php echo $timeRemaining; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="announcement-body">
                                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            
                                            <!-- MEDIA DISPLAY -->
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
                                                        <img src="<?php echo $actualPath; ?>" 
                                                             alt="Announcement Image" 
                                                             class="announcement-image"
                                                             onclick="openImageModal('<?php echo $actualPath; ?>')">
                                                    </div>
                                                    
                                                <?php elseif (in_array($fileExtension, $videoExtensions)): ?>
                                                    <div class="announcement-media">
                                                        <video class="announcement-video" controls>
                                                            <source src="<?php echo $actualPath; ?>" type="video/<?php echo $fileExtension; ?>">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    </div>
                                                    
                                                <?php elseif (in_array($fileExtension, $pdfExtensions)): ?>
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <h5>PDF Document</h5>
                                                        <p>Click to view the document</p>
                                                    </div>
                                                    
                                                <?php else: ?>
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, var(--accent), #ffd24a);">
                                                        <i class="fas fa-file"></i>
                                                        <h5>Document File</h5>
                                                        <p>Click to view the file</p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                            <?php endif; ?>
                                        </div>
                                        <div class="announcement-footer">
                                            <span class="announcement-category">
                                                <i class="fas fa-user me-1"></i>
                                                Sent by: <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Admin'); ?>
                                            </span>
                                            <span class="status-active">
                                                <i class="fas fa-circle me-1"></i> Active
                                                <?php if ($isExpiringSoon): ?>
                                                    <span class="badge badge-warning ms-2">Expiring Soon</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- EXPIRED ANNOUNCEMENTS TAB -->
                    <div class="tab-pane fade" id="archive" role="tabpanel">
                        <?php if (empty($expired_announcements)): ?>
                            <div class="no-announcements">
                                <i class="fas fa-archive"></i>
                                <h4>No Expired Announcements</h4>
                                <p>No expired announcements found</p>
                            </div>
                        <?php else: ?>
                            <div class="stagger-animation">
                                <?php foreach ($expired_announcements as $announcement): 
                                    $isActuallyExpired = isExpired($announcement['expiry_date']);
                                ?>
                                    <div class="announcement-card expired">
                                        <div class="announcement-header">
                                            <div class="announcement-icon expired">
                                                <i class="fas fa-archive"></i>
                                            </div>
                                            <div class="announcement-meta">
                                                <h4>
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-users me-1"></i> All Students
                                                    </span>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-ban me-1"></i> Expired
                                                    </span>
                                                </h4>
                                                <div class="announcement-date">
                                                    <span>
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        Posted: <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                    </span>
                                                    
                                                    <!-- EXPIRY INFORMATION FOR EXPIRED ANNOUNCEMENTS -->
                                                    <?php if (!empty($announcement['expiry_date'])): ?>
                                                        <div class="expiry-info expired">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Expired: <?php echo date('F j, Y g:i A', strtotime($announcement['expiry_date'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="expiry-info expired">
                                                            <i class="fas fa-ban me-1"></i>
                                                            Manually deactivated
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="announcement-body">
                                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            
                                            <!-- MEDIA DISPLAY FOR EXPIRED ANNOUNCEMENTS -->
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
                                                        <img src="<?php echo $actualPath; ?>" 
                                                             alt="Expired Announcement Image" 
                                                             class="announcement-image"
                                                             onclick="openImageModal('<?php echo $actualPath; ?>')"
                                                             style="opacity: 0.7;">
                                                    </div>
                                                    
                                                <?php elseif (in_array($fileExtension, $videoExtensions)): ?>
                                                    <div class="announcement-media">
                                                        <video class="announcement-video" controls style="opacity: 0.7;">
                                                            <source src="<?php echo $actualPath; ?>" type="video/<?php echo $fileExtension; ?>">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    </div>
                                                    
                                                <?php elseif (in_array($fileExtension, $pdfExtensions)): ?>
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, var(--accent), #ffd24a); opacity: 0.8;">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <h5>PDF Document</h5>
                                                        <p>Click to view the document</p>
                                                    </div>
                                                    
                                                <?php else: ?>
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, var(--accent), #ffd24a); opacity: 0.8;">
                                                        <i class="fas fa-file"></i>
                                                        <h5>Document File</h5>
                                                        <p>Click to view the file</p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                            <?php endif; ?>
                                        </div>
                                        <div class="announcement-footer">
                                            <span class="announcement-category">
                                                <i class="fas fa-user me-1"></i>
                                                Sent by: <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Admin'); ?>
                                            </span>
                                            <span class="status-expired">
                                                <i class="fas fa-circle me-1"></i> Expired
                                            </span>
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

    <!-- JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
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

            // Image Modal Functionality
            window.openImageModal = function(filePath) {
                const modalImage = document.getElementById('modalImage');
                modalImage.src = filePath;
                
                const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
                imageModal.show();
            }

            // Tab persistence
            const announcementTabs = document.getElementById('announcementTabs');
            if (announcementTabs) {
                announcementTabs.addEventListener('click', function(e) {
                    if (e.target.tagName === 'BUTTON') {
                        const tabId = e.target.getAttribute('data-bs-target').replace('#', '');
                        localStorage.setItem('activeAnnouncementTab', tabId);
                    }
                });

                // Restore active tab
                const activeTab = localStorage.getItem('activeAnnouncementTab') || 'active';
                const tabButton = document.querySelector(`[data-bs-target="#${activeTab}"]`);
                if (tabButton) {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }

            // Real-time countdown update for expiring announcements
            function updateCountdownTimers() {
                const timers = document.querySelectorAll('.countdown-timer');
                
                timers.forEach(timer => {
                    const expiryDate = new Date(timer.getAttribute('data-expiry'));
                    const now = new Date();
                    
                    if (expiryDate <= now) {
                        timer.innerHTML = '<i class="fas fa-ban me-1"></i> Expired';
                        timer.className = 'countdown-timer countdown-expired';
                        return;
                    }
                    
                    const timeDiff = expiryDate - now;
                    const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                    
                    let remainingText = '';
                    if (days > 0) {
                        remainingText = `${days} day${days > 1 ? 's' : ''} remaining`;
                    } else if (hours > 0) {
                        remainingText = `${hours} hour${hours > 1 ? 's' : ''} remaining`;
                    } else {
                        remainingText = `${minutes} minute${minutes > 1 ? 's' : ''} remaining`;
                    }
                    
                    timer.innerHTML = `<i class="fas fa-hourglass-half me-1"></i> ${remainingText}`;
                    
                    // Update class if it's expiring soon (less than 1 day)
                    if (days === 0) {
                        timer.className = 'countdown-timer countdown-expiring';
                    }
                });
            }

            // Update countdown every minute
            updateCountdownTimers();
            setInterval(updateCountdownTimers, 60000);

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
            const announcementCards = document.querySelectorAll('.announcement-card');
            announcementCards.forEach(card => {
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
                const tapTargets = document.querySelectorAll('.nav-item, .announcement-card, .announcement-pdf-preview');
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

            // Auto-refresh announcements every 5 minutes
            function autoRefreshAnnouncements() {
                setTimeout(() => {
                    location.reload();
                }, 300000); // 5 minutes
            }

            // Initialize auto-refresh
            autoRefreshAnnouncements();
        });

        // Keyboard navigation for modal
        document.addEventListener('keydown', function(e) {
            const imageModal = document.getElementById('imageModal');
            if (imageModal.classList.contains('show') && e.key === 'Escape') {
                const modal = bootstrap.Modal.getInstance(imageModal);
                if (modal) {
                    modal.hide();
                }
            }
        });
    </script>
</body>
</html>