<?php
// ==================== SESSION AT SECURITY ====================
session_start();

// ✅ SECURITY CHECK: TEMPORARILY DISABLED FOR TESTING
// if (!isset($_SESSION['student_id'])) {
//     header("Location: student_login.php");
//     exit();
// }

require_once 'includes/db_connect.php'; // Adjust path as needed

$student_number = $_SESSION['student_number'] ?? '2021-12345';

// ✅ REAL DATABASE QUERY
try {
    // Get announcements that are posted on front page
    $stmt = $pdo->prepare("
        SELECT * FROM announcements 
        WHERE post_on_front = 1 
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    // Fallback to sample data if database error
    $announcements = [
        [
            'id' => 1,
            'title' => 'Database Connection Issue',
            'content' => 'There was an issue loading announcements. Please try again later.',
            'sent_by' => 'Clinic Administrator',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - ASCOT Online School Clinic</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <link href="assets/css/student_dashboard.css" rel="stylesheet">
    <link href="assets/css/student_announcement.css" rel="stylesheet">
    
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

        /* ========== ORIGINAL ANNOUNCEMENT STYLES (PRESERVED) ========== */
        .page-header {
            background: #ffda6a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h2 {
            color: #333;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .page-header .text-muted {
            color: #666 !important;
            margin: 0;
        }

        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        .announcements-container {
            margin-top: 20px;
        }

        .announcement-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }

        .announcement-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .announcement-icon {
            background: #3498db;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .announcement-meta {
            flex: 1;
        }

        .announcement-meta h4 {
            color: #333;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .announcement-date {
            color: #666;
            font-size: 0.9rem;
        }

        .announcement-date i {
            margin-right: 5px;
        }

        .badge-urgent {
            background: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .announcement-body {
            margin-bottom: 15px;
        }

        .announcement-body p {
            color: #555;
            line-height: 1.6;
            margin: 0;
        }

        .announcement-footer {
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .announcement-category {
            background: #f8f9fa;
            color: #666;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .announcement-category i {
            margin-right: 5px;
        }

        .no-announcements {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        .no-announcements i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }

        .no-announcements h4 {
            color: #666;
            margin-bottom: 10px;
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

            .announcement-header {
                flex-direction: column;
                gap: 10px;
            }

            .announcement-card {
                padding: 20px;
            }

            .mobile-menu-btn {
                top: 15px;
                left: 15px;
                padding: 10px 14px;
                font-size: 1.2rem;
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

    <!-- MAIN LAYOUT CONTAINER -->
    <div class="container-fluid">
        <div class="row">
            <!-- ENHANCED SIDEBAR -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a class="nav-link active" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                    <a class="nav-link" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </nav>

                <!-- LOGOUT BUTTON -->
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- MAIN CONTENT AREA (ORIGINAL DESIGN PRESERVED) -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <h2><i class="fas fa-bullhorn"></i>  Announcements</h2>
                    <p class="text-muted"> Stay updated with the latest clinic announcements and notices</p>
                </div>

                <!-- DEMO MODE BANNER -->
                <div class="alert alert-info mb-3" role="alert">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Live Data:</strong> Showing real announcements from the clinic.
                </div>

                <!-- ANNOUNCEMENTS LIST -->
                <div class="announcements-container">
                    <?php if (empty($announcements)): ?>
                        <div class="no-announcements">
                            <i class="fas fa-inbox"></i>
                            <h4>No Announcements Yet</h4>
                            <p>Check back later for updates from the clinic</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <div class="announcement-icon">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div class="announcement-meta">
                                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                        <span class="announcement-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="announcement-body">
                                    <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                    
                                    <!-- Show attachment if exists -->
                                    <?php if (!empty($announcement['attachment'])): ?>
                                        <div class="mt-3">
                                            <i class="fas fa-paperclip"></i>
                                            <strong>Attachment:</strong> 
                                            <?php
                                            $fileExtension = strtolower(pathinfo($announcement['attachment'], PATHINFO_EXTENSION));
                                            if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                <a href="../uploads/announcements/<?php echo $announcement['attachment']; ?>" target="_blank">
                                                    View Image
                                                </a>
                                            <?php elseif (in_array($fileExtension, ['pdf'])): ?>
                                                <a href="../uploads/announcements/<?php echo $announcement['attachment']; ?>" target="_blank">
                                                    View PDF
                                                </a>
                                            <?php else: ?>
                                                <a href="../uploads/announcements/<?php echo $announcement['attachment']; ?>" download>
                                                    Download File
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="announcement-footer">
                                    <span class="announcement-category">
                                        <i class="fas fa-user"></i>
                                        Sent by: <?php echo htmlspecialchars($announcement['sent_by']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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