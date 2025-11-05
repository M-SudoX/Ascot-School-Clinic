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

// Use PDO - Secure database access
// PDO was used to PROTECT the student information

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ASCOT Clinic</title>
    
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
                <a href="student_dashboard.php" class="nav-item active">
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
            <!-- WELCOME SECTION -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $student_info['fullname'])[0]); ?>! 👋</h1>
                    <p>Here's what's happening with your health consultations today</p>
                </div>
            </div>

            <!-- DASHBOARD GRID -->
            <div class="dashboard-grid">
                <!-- STUDENT INFORMATION CARD -->
                <div class="dashboard-card info-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Student Information</h3>
                        <div class="card-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    
                    <div class="stagger-animation">
                        <!-- DISPLAY STUDENT FULL NAME -->
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['fullname']); ?></span>
                        </div>
                        
                        <!-- DISPLAY STUDENT ID NUMBER -->
                        <div class="info-row">
                            <span class="info-label">ID Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['student_number']); ?></span>
                        </div>
                        
                        <!-- DISPLAY COURSE AND YEAR -->
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
                        
                        <!-- DISPLAY CONTACT NUMBER -->
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

                    <!-- QUICK ACTIONS -->
                    <div class="quick-actions">
                        <a href="update_profile.php" class="action-btn">
                            <i class="fas fa-edit"></i> Update Profile
                        </a>
                    </div>
                </div>

                <!-- UPCOMING APPOINTMENTS CARD -->
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

                <!-- RECENT ACTIVITIES CARD -->
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
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
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
                const tapTargets = document.querySelectorAll('.nav-item, .action-btn, .appointment-item, .activity-item');
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

            // ADD SMOOTH SCROLLING
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>