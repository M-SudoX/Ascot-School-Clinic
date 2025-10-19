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

// ✅ I-log ang pag-visit sa activity logs page (automatic duplicate prevention na)
logActivity($pdo, $student_id, "Viewed activity logs");

// ✅ Fetch ONLY SPECIFIC ACTION LOGS - hindi kasama ang viewed/accessed/login/logout
try {
    $stmt = $pdo->prepare("
        SELECT id, action, log_date 
        FROM activity_logs 
        WHERE student_id = :student_id 
        AND action NOT LIKE '%viewed%' 
        AND action NOT LIKE '%accessed%' 
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
    <title>Activity Logs - ASCOT Online School Clinic</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <link href="assets/css/student_dashboard.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --sidebar-width: 280px;
            --header-height: 80px;
        }

        * {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
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
            margin-top: -15px;;
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
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
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

        /* ========== ORIGINAL ACTIVITY LOGS STYLES (PRESERVED) ========== */
        .activity-table {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }

        .activity-table h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ffda6a;
        }

        table th {
            background: #ffda6a;
            text-align: center;
            font-weight: bold;
            padding: 15px;
        }

        table td {
            vertical-align: middle;
            padding: 12px;
        }

        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
            border-radius: 8px;
            padding: 15px;
        }
        
        /* Center aligned action cells */
        .action-cell {
            text-align: center;
            padding: 12px;
        }
        
        .action-content {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .action-icon {
            width: 16px;
        }
        
        .text-muted-empty {
            color: #6c757d;
            font-style: italic;
            padding: 40px 0;
            text-align: center;
        }
        
        .table-bordered {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* ========== RESPONSIVE BREAKPOINTS ========== */
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

            .activity-table {
                padding: 20px;
                margin-top: 10px;
            }

            table {
                font-size: 14px;
            }

            .action-content {
                flex-direction: column;
                gap: 4px;
            }

            .mobile-menu-btn {
                top: 15px;
                left: 15px;
                padding: 10px 14px;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 575.98px) {
            .activity-table {
                padding: 15px;
            }
            
            table {
                font-size: 13px;
            }
            
            table th,
            table td {
                padding: 8px 4px;
            }
        }

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

    <!-- ENHANCED HEADER -->
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
            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="container-fluid">
        <div class="row">
            <!-- ENHANCED SIDEBAR -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                    <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                    <a class="nav-link active" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </nav>
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- MAIN CONTENT (ORIGINAL DESIGN PRESERVED) -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="activity-table">
                    <h3>Activity Logs</h3>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-info">
                            <strong>Info:</strong> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <table class="table table-bordered mt-4">
                        <thead>
                            <tr>
                                <th class="text-center">ID</th>
                                <th class="text-center">Action</th>
                                <th class="text-center">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-center"><?php echo htmlspecialchars($log['id']); ?></td>
                                        <td class="action-cell">
                                            <div class="action-content">
                                                <?php 
                                                // Add icons based on action type
                                                $action = htmlspecialchars($log['action']);
                                                $icon = '';
                                                
                                                if (strpos($action, 'profile') !== false) {
                                                    $icon = '<i class="fas fa-user-edit text-primary action-icon"></i>';
                                                } elseif (strpos($action, 'medical') !== false) {
                                                    $icon = '<i class="fas fa-file-medical text-info action-icon"></i>';
                                                } elseif (strpos($action, 'password') !== false) {
                                                    $icon = '<i class="fas fa-key text-warning action-icon"></i>';
                                                } elseif (strpos($action, 'consultation') !== false) {
                                                    if (strpos($action, 'Scheduled') !== false) {
                                                        $icon = '<i class="fas fa-calendar-plus text-success action-icon"></i>';
                                                    } elseif (strpos($action, 'Edited') !== false) {
                                                        $icon = '<i class="fas fa-edit text-primary action-icon"></i>';
                                                    } elseif (strpos($action, 'Cancelled') !== false) {
                                                        $icon = '<i class="fas fa-times-circle text-danger action-icon"></i>';
                                                    } else {
                                                        $icon = '<i class="fas fa-calendar-check text-info action-icon"></i>';
                                                    }
                                                } else {
                                                    $icon = '<i class="fas fa-history text-secondary action-icon"></i>';
                                                }
                                                
                                                echo $icon;
                                                ?>
                                                <span><?php echo $action; ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo date('M d, Y h:i A', strtotime($log['log_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted-empty">
                                        <i class="fas fa-clipboard-list fa-2x mb-3 d-block"></i>
                                        No important activities recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // MOBILE SIDEBAR FUNCTIONALITY
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                
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
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991.98) {
                            closeSidebar();
                        }
                    });
                });
                
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                });
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html>