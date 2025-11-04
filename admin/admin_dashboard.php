<?php
session_start();
require_once '../includes/db_connect.php';

// Check if this is the first time accessing dashboard after login
if (!isset($_SESSION['dashboard_accessed'])) {
    // Mark dashboard as accessed to prevent duplicate logging
    $_SESSION['dashboard_accessed'] = true;
}

// Fetch recent activities (admin activities only, excluding viewed/accessed/logged in actions)
$activity_sql = "
    SELECT 
        log_date, 
        admin_name AS user_name, 
        action, 
        'admin' AS user_type
    FROM admin_logs 
    WHERE action NOT LIKE '%viewed%' 
    AND action NOT LIKE '%accessed%'
    AND action NOT LIKE '%logged into system%'
    ORDER BY log_date DESC 
    LIMIT 5
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

        /* Header Styles - SAME AS STUDENT */
        .top-header {
            background: 
                linear-gradient(90deg, 
                    #ffda6a 0%, 
                    #ffda6a 30%, 
                    #FFF5CC 70%, 
                    #ffffff 100%);
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
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            color: #555;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
            color: #555;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
            color: #555;
        }

        /* Mobile Menu Toggle - SAME AS STUDENT */
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        /* Dashboard Container - SAME AS STUDENT */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS STUDENT */
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
            color: #555;
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
            color: #555;
            border-left: 8px solid #ffda6a;
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
            color: #555;
        }

        .nav-item span {
            flex: 1;
            color: #555;
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

        /* Main Content - SAME AS STUDENT */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS STUDENT */
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

        /* Welcome Section - SAME AS STUDENT */
        .welcome-section {
            background: linear-gradient(110deg, #fff7da 50%, #fff7da 50%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(206, 224, 144, 0.2);
            border-left: 10px solid #ffda6a;
        }

        .welcome-content h1 {
            color: #555;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Dashboard Grid - SAME AS STUDENT */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title {
            color: #555;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #555;
            background: #fff7da;
            transition: all 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.1);
        }

        /* STATS CARD STYLES - ADAPTED FROM STUDENT INFO CARD */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .stat-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            border-left: 4px solid #ffda6a;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #555;
            display: block;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* ACTIVITIES CARD - SAME AS STUDENT */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
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
            color: #555;
            background: #fff7da;
            flex-shrink: 0;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details p {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
            font-weight: 500;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
            display: block;
        }

        /* QUICK ACTIONS - SAME AS STUDENT */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .action-btn {
            background: #fff59d;
            color: #555;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        /* Responsive Design - SAME AS STUDENT */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
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
                padding: 2rem 1.25rem 1.25rem;
                width: 100%;
                margin-left: 0;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                margin-top: 1.5rem;
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

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-content h1 {
                font-size: 1.5rem;
            }

            .main-content {
                padding: 1.75rem 1rem 1rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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

        /* ANIMATIONS - SAME AS STUDENT */
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
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - SAME AS STUDENT -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS STUDENT -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS STUDENT -->
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
        <!-- Sidebar - ADMIN MENU ITEMS WITH STUDENT STYLING -->
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
                    <div class="submenu" id="studentMenu">
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

        <!-- Main Content - FOLLOWING STUDENT DASHBOARD STRUCTURE -->
        <main class="main-content">
            <!-- WELCOME SECTION -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Welcome, Admin! 👋</h1>
                    <p>Here's an overview of the clinic management system</p>
                </div>
            </div>

            <!-- DASHBOARD GRID -->
            <div class="dashboard-grid">
                <!-- SYSTEM STATISTICS CARD -->
                <div class="dashboard-card stats-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">System Statistics</h3>
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    
                    <div class="stats-grid stagger-animation">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['today_consultations']; ?></span>
                            <span class="stat-label">Today's Consultations</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['pending_requests']; ?></span>
                            <span class="stat-label">Pending Requests</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['active_announcements']; ?></span>
                            <span class="stat-label">Active Announcements</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['today_certificates']; ?></span>
                            <span class="stat-label">Today's Certificates</span>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="quick-actions">
                        <a href="monthly_summary.php" class="action-btn">
                            <i class="fas fa-file-invoice"></i> View Reports
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
                                <?php 
                                $activity_style = getActivityStyle($activity['action']);
                                $icon = $activity_style[0];
                                $color = $activity_style[1];
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p><?php echo htmlspecialchars($activity['action']); ?></p>
                                        <span class="activity-time">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatActivityTime($activity['log_date']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                            <small>System activities will appear here</small>
                        </div>
                    <?php endif; ?>

                    <div class="quick-actions">
                        <a href="users_logs.php" class="action-btn">
                            <i class="fas fa-list"></i> View All Logs
                        </a>
                    </div>
                </div>

                <!-- QUICK ACTIONS CARD -->
                <div class="dashboard-card quick-actions-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                        <div class="card-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                    </div>
                    
                    <div class="quick-actions">
                        <a href="search_students.php" class="action-btn">
                            <i class="fas fa-search"></i> Search Students
                        </a>
                        <a href="new_announcement.php" class="action-btn">
                            <i class="fas fa-bullhorn"></i> New Announcement
                        </a>
                        <a href="approvals.php" class="action-btn">
                            <i class="fas fa-check-circle"></i> Manage Requests
                        </a>
                        <a href="students.php" class="action-btn">
                            <i class="fas fa-users"></i> View All Students
                        </a>
                        <!-- ADDED: Manage Certificate Settings Button -->
                        <a href="manage_physician.php" class="action-btn">
                            <i class="fas fa-file-medical-alt"></i> Certificate Settings
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS
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

            // MOBILE MENU FUNCTIONALITY - SAME AS STUDENT
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

            // LOADING ANIMATIONS - SAME AS STUDENT
            const staggerElements = document.querySelectorAll('.stagger-animation > *');
            staggerElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
            });
        });
    </script>
</body>
</html>