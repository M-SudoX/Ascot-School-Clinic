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
logActivity($pdo, $student_id, "Accessed dashboard");

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
        AND action NOT LIKE '%viewed%' 
        AND action NOT LIKE '%accessed%' 
        AND action NOT LIKE '%logged in%' 
        AND action NOT LIKE '%logged out%'
        ORDER BY log_date DESC 
        LIMIT 5
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
    <title>Student Dashboard - ASCOT Online School Clinic</title>
    <!-- CSS FILES FOR STYLING -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">  <!-- BOOTSTRAP FRAMEWORK -->
    <link href="assets/webfonts/all.min.css" rel="stylesheet">   <!-- FONT AWESOME ICONS -->
    <link href="assets/css/student_dashboard.css" rel="stylesheet"> <!-- CUSTOM STYLES -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --info-color: #17a2b8;
            --sidebar-width: 280px;
            --header-height: 80px;
            --card-radius: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: var(--transition);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: #2c3e50;
        }

        /* ========== ENHANCED HEADER DESIGN ========== */
        .header {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: var(--header-height);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.05;
            z-index: -1;
        }

        .header .logo-img {
            height: 80px;
            width: 80px;
            margin-top: -15px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }

        .header .logo-img:hover {
            transform: scale(1.05);
        }

        .header .college-info {
            text-align: center;
        }

        .header .college-info h4 {
            font-size: 1rem;
            margin-bottom: 0.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header .college-info p {
            font-size: 0.85rem;
            margin-bottom: 0;
            color: #7f8c8d;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .header .student-welcome {
            text-align: right;
            color: #2c3e50;
            font-weight: 600;
        }

        .header .student-welcome .student-name {
            color: #3498db;
            font-weight: 700;
        }

        /* ========== ENHANCED SIDEBAR DESIGN ========== */
        .sidebar {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.95) 0%, rgba(52, 73, 94, 0.98) 100%);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.1);
            box-shadow: 8px 0 32px rgba(0,0,0,0.2);
            min-height: calc(100vh - var(--header-height));
            padding: 30px 0;
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            z-index: 999;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .sidebar .nav {
            padding: 0 20px;
        }

        .sidebar .nav-link {
            color: #ecf0f1 !important;
            padding: 15px 20px;
            margin: 8px 0;
            border-radius: 12px;
            border-left: 4px solid transparent;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.2) 0%, rgba(41, 128, 185, 0.2) 100%);
            border-left: 4px solid #3498db;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 15px;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .sidebar .nav-link:hover i {
            transform: scale(1.2);
        }

        .sidebar .nav-link.active i {
            color: #3498db;
        }

        .logout-btn .nav-link {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.2) 0%, rgba(192, 57, 43, 0.2) 100%);
            border: 1px solid rgba(231, 76, 60, 0.3);
            margin-top: 20px;
        }

        .logout-btn .nav-link:hover {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.3) 0%, rgba(192, 57, 43, 0.3) 100%);
            border-left: 4px solid #e74c3c;
            transform: translateX(8px);
        }

        /* ========== MOBILE SIDEBAR ENHANCEMENTS ========== */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1100;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1.3rem;
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 998;
            backdrop-filter: blur(5px);
        }

        /* ========== MAIN CONTENT ENHANCEMENTS ========== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            background: rgba(248, 249, 250, 0.95);
            backdrop-filter: blur(10px);
            min-height: calc(100vh - var(--header-height));
            margin-top: var(--header-height);
        }

        /* ========== WELCOME SECTION ========== */
        .welcome-section {
            background: linear-gradient(135deg, rgba(255, 218, 106, 0.9) 0%, rgba(255, 247, 222, 0.95) 100%);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: var(--card-radius);
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .welcome-content h1 {
            color: #2c3e50;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-content p {
            color: #7f8c8d;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0;
        }

        /* ========== DASHBOARD CARDS ========== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
            backdrop-filter: blur(10px);
            border-radius: var(--card-radius);
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            color: #2c3e50;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            transition: var(--transition);
        }

        .card-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* ========== INFO CARD SPECIFIC STYLES ========== */
        .info-card .card-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(236, 240, 241, 0.8);
            position: relative;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 2px;
            transition: height 0.3s ease;
        }

        .info-row:hover::before {
            height: 80%;
        }

        .info-label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }

        .info-label::before {
            content: '▶';
            margin-right: 10px;
            color: #3498db;
            font-size: 0.8rem;
        }

        .info-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
            text-align: right;
        }

        /* ========== APPOINTMENTS CARD ========== */
        .appointments-card .card-icon {
            background: linear-gradient(135deg, #27ae60, #219a52);
        }

        .appointment-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(236, 240, 241, 0.8);
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-date {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
            min-width: 70px;
        }

        .appointment-date .day {
            font-size: 1.2rem;
            font-weight: 700;
            display: block;
        }

        .appointment-date .month {
            font-size: 0.8rem;
            font-weight: 600;
            display: block;
        }

        .appointment-details {
            flex: 1;
        }

        .appointment-details h6 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .appointment-details p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .appointment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .no-appointments {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }

        .no-appointments i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
            color: #bdc3c7;
        }

        /* ========== ACTIVITIES CARD ========== */
        .activities-card .card-icon {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(236, 240, 241, 0.8);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details p {
            margin: 0;
            color: #2c3e50;
            font-weight: 500;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        /* ========== QUICK ACTIONS ========== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.5);
            color: white;
        }

        .action-btn i {
            font-size: 1.2rem;
        }

        /* ========== CALENDAR MODAL STYLES ========== */
        .calendar-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            backdrop-filter: blur(10px);
        }

        .calendar-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-container {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
            border-radius: var(--card-radius);
            padding: 30px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(52, 152, 219, 0.3);
        }

        .calendar-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .calendar-nav-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .calendar-nav-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }

        .calendar-close {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .calendar-close:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 700;
            color: #3498db;
            padding: 10px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 8px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            border: 2px solid transparent;
        }

        .calendar-day:hover {
            background: rgba(52, 152, 219, 0.1);
            transform: scale(1.05);
        }

        .calendar-day.other-month {
            color: #bdc3c7;
        }

        .calendar-day.today {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: 700;
        }

        .calendar-day.has-appointment::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #e74c3c;
            border-radius: 50%;
        }

        .calendar-appointments {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(52, 152, 219, 0.3);
        }

        .calendar-appointments h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .appointment-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .appointment-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: rgba(52, 152, 219, 0.05);
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .appointment-list-item.pending {
            border-left-color: #f39c12;
        }

        .appointment-list-item.approved {
            border-left-color: #27ae60;
        }

        .appointment-list-item.completed {
            border-left-color: #7f8c8d;
        }

        /* ========== RESPONSIVE BREAKPOINTS ========== */
        @media (max-width: 1199.98px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            
            .welcome-content h1 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 991.98px) {
            .sidebar {
                left: -100%;
                width: 300px;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar.active {
                left: 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header .student-welcome {
                display: none;
            }

            .calendar-container {
                width: 95%;
                padding: 20px;
            }
        }

        @media (max-width: 767.98px) {
            :root {
                --header-height: 70px;
            }

            .header {
                padding: 10px 0;
            }

            .header .logo-img {
                height: 40px;
            }

            .main-content {
                padding: 15px;
                margin-top: 70px;
            }

            .welcome-section {
                padding: 20px;
            }

            .welcome-content h1 {
                font-size: 1.8rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .dashboard-card {
                padding: 20px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .mobile-menu-btn {
                top: 15px;
                left: 15px;
                padding: 10px 14px;
                font-size: 1.2rem;
            }

            .calendar-grid {
                gap: 4px;
            }

            .calendar-day {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 575.98px) {
            .welcome-content h1 {
                font-size: 1.6rem;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .info-value {
                text-align: left;
            }

            .appointment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .appointment-date {
                align-self: flex-start;
            }

            .calendar-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .calendar-nav {
                justify-content: center;
            }
        }

        /* ========== ANIMATIONS ========== */
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

        .stagger-animation > * {
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }

        /* ========== CUSTOM SCROLLBAR ========== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
        }
    </style>
</head>
<body>
    <!-- MOBILE MENU BUTTON -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- CALENDAR MODAL -->
    <div class="calendar-modal" id="calendarModal">
        <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title" id="calendarTitle">October 2024</h2>
                <div class="calendar-nav">
                    <button class="calendar-nav-btn" id="prevMonth">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="calendar-nav-btn" id="todayBtn">Today</button>
                    <button class="calendar-nav-btn" id="nextMonth">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="calendar-close" id="closeCalendar">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
            
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar days will be generated by JavaScript -->
            </div>
            
            <div class="calendar-appointments">
                <h4>Appointments for <span id="selectedDate"></span></h4>
                <div class="appointment-list" id="appointmentList">
                    <!-- Appointments will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- ENHANCED HEADER SECTION -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                </div>
                <div class="col">
                    <div class="college-info">
                        <h4>Republic of the Philippines</h4>
                        <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
                        <p>ONLINE SCHOOL CLINIC</p>
                    </div>
                </div>
                <div class="col-auto d-none d-lg-block">
                    <div class="student-welcome">
                        Welcome, <span class="student-name"><?php echo htmlspecialchars($student_info['fullname']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN LAYOUT CONTAINER -->
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- ENHANCED SIDEBAR NAVIGATION -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <!-- MOBILE SIDEBAR HEADER -->
                <div class="d-block d-md-none text-center mb-4 p-4 border-bottom border-secondary">
                    <img src="img/logo.png" alt="ASCOT Logo" style="height: 50px; margin-bottom: 15px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));">
                    <h6 class="text-white mb-2">Student Portal</h6>
                    <small class="text-light"><?php echo htmlspecialchars($student_info['fullname']); ?></small>
                </div>
                
                <nav class="nav flex-column stagger-animation">
                    <!-- NAVIGATION LINKS -->
                    <a class="nav-link active" href="student_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="update_profile.php">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </a>
                    <a class="nav-link" href="schedule_consultation.php">
                        <i class="fas fa-calendar-alt"></i> Schedule Consultation
                    </a>
                    <a class="nav-link" href="student_report.php">
                        <i class="fas fa-chart-bar"></i> Report
                    </a>
                    <a class="nav-link" href="student_announcement.php">
                        <i class="fas fa-bullhorn"></i> Announcement
                    </a>
                    <a class="nav-link" href="activity_logs.php">
                        <i class="fas fa-clipboard-list"></i> Activity Logs
                    </a>
                </nav>
                <!-- LOGOUT BUTTON -->
                <div class="logout-btn px-3">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- WELCOME SECTION -->
                <div class="welcome-section fade-in">
                    <div class="welcome-content">
                        <h1>Welcome back Long Time No See Ayaw Kol Bata Pako , <?php echo htmlspecialchars(explode(' ', $student_info['fullname'])[0]); ?>! 👋</h1>
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
                                        ? '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Not set</span>'
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
                                        ? '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Not set</span>'
                                        : htmlspecialchars($contact);
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- QUICK ACTIONS -->
                        <div class="quick-actions mt-4">
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
                            <div class="no-appointments">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming appointments</p>
                                <small>Schedule your first consultation</small>
                            </div>
                        <?php endif; ?>

                        <div class="quick-actions mt-4">
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
                                        <div class="activity-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
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
                            <div class="no-appointments">
                                <i class="fas fa-history"></i>
                                <p>No recent activities</p>
                                <small>Your activities will appear here</small>
                            </div>
                        <?php endif; ?>

                        <div class="quick-actions mt-4">
                            <a href="activity_logs.php" class="action-btn">
                                <i class="fas fa-list"></i> View All Activities
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT FILES -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // MOBILE SIDEBAR FUNCTIONALITY
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                
                // UPDATE MENU ICON
                const icon = mobileMenuBtn.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                } else {
                    icon.className = 'fas fa-bars';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
                }
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
            }
            
            if (mobileMenuBtn && sidebar && sidebarOverlay) {
                // TOGGLE SIDEBAR ON BUTTON CLICK
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                
                // CLOSE SIDEBAR WHEN OVERLAY IS CLICKED
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                // CLOSE SIDEBAR WHEN NAV LINK IS CLICKED (ON MOBILE)
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991.98) {
                            closeSidebar();
                        }
                    });
                });
                
                // CLOSE SIDEBAR ON ESC KEY PRESS
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                });
            }
            
            // AUTO-CLOSE SIDEBAR ON WINDOW RESIZE
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
            
            // SWIPE GESTURE SUPPORT FOR MOBILE
            let touchStartX = 0;
            let touchEndX = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                const swipeThreshold = 50;
                const swipeDistance = touchEndX - touchStartX;
                
                // SWIPE RIGHT TO OPEN SIDEBAR
                if (swipeDistance > swipeThreshold && window.innerWidth <= 991.98) {
                    sidebar.classList.add('active');
                    sidebarOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    mobileMenuBtn.querySelector('i').className = 'fas fa-times';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                }
                // SWIPE LEFT TO CLOSE SIDEBAR
                else if (swipeDistance < -swipeThreshold && window.innerWidth <= 991.98) {
                    closeSidebar();
                }
            }

            // ADD LOADING ANIMATIONS
            const staggerElements = document.querySelectorAll('.stagger-animation > *');
            staggerElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
            });

            // REAL-TIME CLOCK
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true 
                });
                const dateString = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                // You can display this in your dashboard if needed
                console.log(`${dateString} - ${timeString}`);
            }
            
            // Update clock every second
            setInterval(updateClock, 1000);
            updateClock(); // Initial call

            // ========== CALENDAR FUNCTIONALITY ==========
            const calendarModal = document.getElementById('calendarModal');
            const calendarIcon = document.getElementById('calendarIcon');
            const closeCalendar = document.getElementById('closeCalendar');
            const calendarGrid = document.getElementById('calendarGrid');
            const calendarTitle = document.getElementById('calendarTitle');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');
            const todayBtn = document.getElementById('todayBtn');
            const selectedDateEl = document.getElementById('selectedDate');
            const appointmentList = document.getElementById('appointmentList');

            let currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();

            // PHP appointments data converted to JavaScript
            const appointments = <?php echo json_encode($calendar_appointments); ?>;

            // OPEN CALENDAR MODAL
            calendarIcon.addEventListener('click', function() {
                calendarModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                generateCalendar(currentMonth, currentYear);
            });

            // CLOSE CALENDAR MODAL
            closeCalendar.addEventListener('click', function() {
                calendarModal.classList.remove('active');
                document.body.style.overflow = '';
            });

            // CLOSE MODAL WHEN CLICKING OUTSIDE
            calendarModal.addEventListener('click', function(e) {
                if (e.target === calendarModal) {
                    calendarModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // CLOSE MODAL ON ESC KEY
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && calendarModal.classList.contains('active')) {
                    calendarModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // NAVIGATE MONTHS
            prevMonthBtn.addEventListener('click', function() {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                generateCalendar(currentMonth, currentYear);
            });

            nextMonthBtn.addEventListener('click', function() {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                generateCalendar(currentMonth, currentYear);
            });

            // GO TO TODAY
            todayBtn.addEventListener('click', function() {
                currentDate = new Date();
                currentMonth = currentDate.getMonth();
                currentYear = currentDate.getFullYear();
                generateCalendar(currentMonth, currentYear);
            });

            // GENERATE CALENDAR
            function generateCalendar(month, year) {
                calendarGrid.innerHTML = '';
                
                // Set calendar title
                const monthNames = ["January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"];
                calendarTitle.textContent = `${monthNames[month]} ${year}`;

                // Add day headers
                const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                dayHeaders.forEach(day => {
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'calendar-day-header';
                    dayHeader.textContent = day;
                    calendarGrid.appendChild(dayHeader);
                });

                // Get first day of month and number of days
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const today = new Date();

                // Add empty cells for days before the first day of the month
                for (let i = 0; i < firstDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'calendar-day other-month';
                    calendarGrid.appendChild(emptyDay);
                }

                // Add days of the month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    dayElement.textContent = day;

                    const currentDay = new Date(year, month, day);
                    
                    // Check if today
                    if (currentDay.toDateString() === today.toDateString()) {
                        dayElement.classList.add('today');
                    }

                    // Check if has appointments
                    const hasAppointment = appointments.some(app => {
                        const appDate = new Date(app.date);
                        return appDate.toDateString() === currentDay.toDateString();
                    });

                    if (hasAppointment) {
                        dayElement.classList.add('has-appointment');
                    }

                    // Add click event
                    dayElement.addEventListener('click', function() {
                        showAppointmentsForDate(currentDay);
                    });

                    calendarGrid.appendChild(dayElement);
                }
            }

            // SHOW APPOINTMENTS FOR SELECTED DATE
            function showAppointmentsForDate(date) {
                const dateString = date.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                selectedDateEl.textContent = dateString;

                const filteredAppointments = appointments.filter(app => {
                    const appDate = new Date(app.date);
                    return appDate.toDateString() === date.toDateString();
                });

                appointmentList.innerHTML = '';

                if (filteredAppointments.length === 0) {
                    appointmentList.innerHTML = '<p class="text-muted text-center">No appointments for this date</p>';
                } else {
                    filteredAppointments.forEach(app => {
                        const appElement = document.createElement('div');
                        appElement.className = `appointment-list-item ${app.status.toLowerCase()}`;
                        
                        const time = new Date(`1970-01-01T${app.time}`).toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });

                        appElement.innerHTML = `
                            <div>
                                <strong>${app.requested}</strong>
                                <div class="text-muted">${time}</div>
                            </div>
                            <span class="appointment-status status-${app.status.toLowerCase()}">
                                ${app.status}
                            </span>
                        `;

                        appointmentList.appendChild(appElement);
                    });
                }
            }

            // INITIALIZE CALENDAR
            generateCalendar(currentMonth, currentYear);
            showAppointmentsForDate(currentDate);
        });
    </script>
</body>
</html>