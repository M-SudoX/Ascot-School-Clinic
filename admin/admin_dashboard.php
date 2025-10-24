<?php
session_start();
require_once '../includes/db_connect.php';

// Check if this is the first time accessing dashboard after login
if (!isset($_SESSION['dashboard_accessed'])) {
    // Log admin access only once per session
    $admin_name = $_SESSION['admin_name'] ?? 'Admin User';
    $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_name, action) VALUES (?, 'Logged into system')");
    $log_stmt->execute([$admin_name]);
    
    // Mark dashboard as accessed to prevent duplicate logging
    $_SESSION['dashboard_accessed'] = true;
}

// Fetch recent activities (excluding viewed/accessed actions)
$activity_sql = "
    (SELECT 
        log_date, 
        admin_name AS user_name, 
        action, 
        'admin' AS user_type
    FROM admin_logs 
    WHERE action NOT LIKE '%viewed%' AND action NOT LIKE '%accessed%'
    ORDER BY log_date DESC 
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 
        log_date, 
        user_name, 
        action, 
        user_type
    FROM activity_logs 
    WHERE action NOT LIKE '%viewed%' AND action NOT LIKE '%accessed%'
    ORDER BY log_date DESC 
    LIMIT 10)
    
    ORDER BY log_date DESC 
    LIMIT 10
";

$activity_stmt = $pdo->prepare($activity_sql);
$activity_stmt->execute();
$recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM consultation_requests WHERE status = 'Pending') as pending_requests,
        (SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()) as today_consultations,
        (SELECT COUNT(*) FROM announcements WHERE status = 'active' AND is_archived = 0) as active_announcements,
        (SELECT COUNT(*) FROM certificates WHERE DATE(created_at) = CURDATE()) as today_certificates
";

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Function to format activity time
function formatActivityTime($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 3600) { // Less than 1 hour
        $minutes = round($diff / 60);
        return $minutes <= 1 ? 'Just now' : $minutes . ' min ago';
    } elseif ($diff < 86400) { // Less than 24 hours
        $hours = round($diff / 3600);
        return $hours . ' hr ago';
    } else {
        return date('M j, g:i A', $time);
    }
}

// Function to get activity icon and color
function getActivityStyle($action) {
    $action = strtolower($action);
    
    if (strpos($action, 'consultation') !== false) {
        if (strpos($action, 'scheduled') !== false || strpos($action, 'created') !== false) {
            return ['fas fa-calendar-plus', '#28a745'];
        } else {
            return ['fas fa-stethoscope', '#17a2b8'];
        }
    } 
    elseif (strpos($action, 'certificate') !== false) {
        return ['fas fa-file-certificate', '#ffc107'];
    }
    elseif (strpos($action, 'approve') !== false) {
        return ['fas fa-check-circle', '#28a745'];
    }
    elseif (strpos($action, 'reject') !== false) {
        return ['fas fa-times-circle', '#dc3545'];
    }
    elseif (strpos($action, 'reschedule') !== false) {
        return ['fas fa-calendar-alt', '#17a2b8'];
    }
    elseif (strpos($action, 'delete') !== false) {
        return ['fas fa-trash', '#dc3545'];
    }
    elseif (strpos($action, 'announce') !== false) {
        return ['fas fa-bullhorn', '#6f42c1'];
    }
    elseif (strpos($action, 'logged') !== false) {
        return ['fas fa-sign-in-alt', '#20c997'];
    }
    elseif (strpos($action, 'profile') !== false) {
        return ['fas fa-user-edit', '#fd7e14'];
    }
    elseif (strpos($action, 'medical') !== false) {
        return ['fas fa-file-medical', '#e83e8c'];
    }
    else {
        return ['fas fa-info-circle', '#6c757d'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ASCOT Clinic</title>
    
    <!-- Bootstrap -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 80px;
            line-height: 1.6;
        }

        /* Header Styles - IMPROVED */
        .top-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 0.75rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 80px;
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
            filter: brightness(0) invert(1);
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Mobile Menu Toggle - COMPLETELY FIXED POSITION */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 95px; /* MAS MALAYO SA HEADER */
            left: 20px; /* MAS MALAYO SA GILID */
            z-index: 1025;
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        /* Dashboard Container - IMPROVED */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - IMPROVED */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1020;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.25rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
        }

        .nav-item span {
            flex: 1;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }

        .nav-item .arrow.rotate {
            transform: rotate(180deg);
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #f8f9fa;
        }

        .submenu.show {
            max-height: 500px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1.25rem 0.7rem 3.25rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: var(--primary);
        }

        .submenu-item i {
            width: 18px;
            margin-right: 0.7rem;
            font-size: 0.9rem;
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - IMPROVED */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - IMPROVED */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1019;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Quick Actions - IMPROVED */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            margin-top: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 1.25rem 1.5rem;
            background: white;
            border-radius: 12px;
            text-decoration: none;
            color: #444;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.2);
            color: var(--primary);
            border-color: var(--primary);
            text-decoration: none;
        }

        .action-btn i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        /* Dashboard Card - IMPROVED */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(102,126,234,0.3);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item i {
            font-size: 2rem;
            opacity: 0.9;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e9ecef, transparent);
            margin: 2rem 0;
        }

        .section-title {
            font-size: 1.3rem;
            color: #444;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .section-title i {
            color: var(--primary);
        }

        /* Enhanced Activity Styles - IMPROVED */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .activity-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: rgba(102, 126, 234, 0.1);
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.35rem;
            gap: 1rem;
        }

        .activity-user {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .activity-time {
            color: var(--gray);
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .activity-action {
            color: #495057;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .activity-empty {
            text-align: center;
            padding: 2.5rem;
            color: var(--gray);
        }

        .activity-empty i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .link-btn {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 1.25rem;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #444;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .link-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateX(5px);
            text-decoration: none;
        }

        .link-btn i {
            color: var(--primary);
            transition: color 0.3s ease;
        }

        .link-btn:hover i {
            color: white;
        }

        /* Responsive Design - COMPLETELY FIXED MOBILE SPACING */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
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

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-links {
                grid-template-columns: repeat(2, 1fr);
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
                display: block;
                top: 85px; /* MAS MALAYO SA HEADER */
                left: 20px; /* MAS MALAYO SA GILID */
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
                z-index: 1020;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
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
                padding: 2rem 1.25rem 1.25rem; /* MAS MALAKING PADDING SA ITAAS */
                width: 100%;
                margin-left: 0;
            }

            .quick-actions {
                grid-template-columns: 1fr;
                margin-top: 1.5rem; /* MAS MALAKING SPACE SA ITAAS NG MGA BUTTON */
            }
            
            .stats-row {
                grid-template-columns: 1fr;
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

            .activity-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .activity-time {
                align-self: flex-start;
            }
            
            .dashboard-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .action-btn {
                padding: 1rem 1.25rem;
                font-size: 0.9rem;
            }

            .dashboard-card {
                padding: 1.25rem;
            }

            .stat-item {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .quick-links {
                grid-template-columns: 1fr;
            }

            .activity-item {
                padding: 1rem;
            }

            .activity-icon {
                width: 38px;
                height: 38px;
                font-size: 1rem;
            }
            
            .main-content {
                padding: 1.75rem 1rem 1rem; /* ADJUSTED PADDING */
            }
            
            .quick-actions {
                margin-top: 1rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
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
                padding: 1.5rem 1rem 1rem;
            }
            
            .quick-actions {
                margin-top: 0.75rem;
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
                padding: 1.25rem 0.75rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - COMPLETELY FIXED POSITION -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - IMPROVED -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - IMPROVED -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Sidebar - IMPROVED -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="studentMenu">
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="search_students.php" class="submenu-item">
                            <i class="fas fa-search"></i>
                            Search Students
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="appointmentsMenu">
                        <a href="calendar_view.php" class="submenu-item">
                            <i class="fas fa-calendar-alt"></i>
                            Calendar View
                        </a>
                        <a href="approvals.php" class="submenu-item">
                            <i class="fas fa-check-circle"></i>
                            Approvals
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="reportsMenu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="reportsMenu">
                        <a href="monthly_summary.php" class="submenu-item">
                            <i class="fas fa-file-invoice"></i>
                            Monthly Summary
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="adminMenu">
                        <a href="users_logs.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            Users Logs
                        </a>
                        <a href="backup_restore.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Back up & Restore
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="announcementMenu">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcement</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="announcementMenu">
                        <a href="new_announcement.php" class="submenu-item">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="announcement_history.php" class="submenu-item">
                            <i class="fas fa-history"></i>
                            History
                        </a>
                    </div>
                </div>
                
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Quick Actions - MAY SPACE NA SA ITAAS -->
            <div class="quick-actions">
                <a href="search_students.php" class="action-btn">
                    <i class="fas fa-search"></i>
                    <span>Search Students</span>
                </a>
                <a href="new_announcement.php" class="action-btn">
                    <i class="fas fa-bullhorn"></i>
                    <span>New Announcement</span>
                </a>
                <a href="approvals.php" class="action-btn">
                    <i class="fas fa-check-circle"></i>
                    <span>Manage Requests</span>
                </a>
                <a href="monthly_summary.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <span>View Reports</span>
                </a>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-card">
                <div class="stats-row">
                    <div class="stat-item">
                        <i class="fas fa-calendar-day"></i>
                        <div>
                            <div class="stat-label">Today's Consultations</div>
                            <div class="stat-value"><?php echo $stats['today_consultations']; ?></div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="stat-label">Pending Requests</div>
                            <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-bullhorn"></i>
                        <div>
                            <div class="stat-label">Active Announcements</div>
                            <div class="stat-value"><?php echo $stats['active_announcements']; ?></div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-file-certificate"></i>
                        <div>
                            <div class="stat-label">Today's Certificates</div>
                            <div class="stat-value"><?php echo $stats['today_certificates']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="activity-section">
                    <h3 class="section-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </h3>
                    <div class="activity-list">
                        <?php if (empty($recent_activities)): ?>
                            <div class="activity-empty">
                                <i class="fas fa-stream"></i>
                                <div>No recent activity</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <?php 
                                $activity_style = getActivityStyle($activity['action']);
                                $icon = $activity_style[0];
                                $color = $activity_style[1];
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon" style="background: rgba(<?php echo hex2rgb($color); ?>, 0.1); color: <?php echo $color; ?>;">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-header">
                                            <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                            <span class="activity-time"><?php echo formatActivityTime($activity['log_date']); ?></span>
                                        </div>
                                        <div class="activity-action">
                                            <?php echo htmlspecialchars($activity['action']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="quick-links">
                    <a href="students.php" class="link-btn">
                        <i class="fas fa-users"></i>
                        View All Students
                    </a>
                    <a href="view_records.php" class="link-btn">
                        <i class="fas fa-folder-open"></i>
                        Consultation Records
                    </a>
                    <a href="announcement_history.php" class="link-btn">
                        <i class="fas fa-bullhorn"></i>
                        Announcement History
                    </a>
                    <a href="users_logs.php" class="link-btn">
                        <i class="fas fa-clipboard-list"></i>
                        System Logs
                    </a>
                </div>
            </div>
        </main>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS - IMPROVED
            document.querySelectorAll('.dropdown-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const submenu = document.getElementById(targetId);
                    const arrow = this.querySelector('.arrow');

                    document.querySelectorAll('.submenu').forEach(menu => {
                        if (menu.id !== targetId && menu.classList.contains('show')) {
                            menu.classList.remove('show');
                            const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                            if (otherBtn) {
                                otherBtn.querySelector('.arrow').classList.remove('rotate');
                            }
                        }
                    });

                    submenu.classList.toggle('show');
                    arrow.classList.toggle('rotate');
                });
            });

            // MOBILE MENU FUNCTIONALITY - IMPROVED
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
            });

            // Close sidebar when clicking submenu items on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.submenu-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                    });
                });
            }
        });
    </script>

    <?php
    // PHP helper function to convert hex to rgb
    function hex2rgb($hex) {
        $hex = str_replace("#", "", $hex);
        
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return "$r, $g, $b";
    }
    ?>
</body>
</html>