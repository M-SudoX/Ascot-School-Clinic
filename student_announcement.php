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

// ✅ FETCH ANNOUNCEMENTS
try {
    // Get ALL active announcements that should be shown to students
    $stmt = $pdo->prepare("
        SELECT a.* 
        FROM announcements a
        WHERE a.post_on_front = 1 
        AND a.is_active = 1
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inactive announcements
    $archive_stmt = $pdo->prepare("
        SELECT a.* 
        FROM announcements a
        WHERE a.is_active = 0
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $archive_stmt->execute();
    $archived_announcements = $archive_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    $announcements = [];
    $archived_announcements = [];
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
            --primary: #ffda6a;
            --primary-dark: #e6c45f;
            --secondary: #ffda6a;
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

        /* Header Styles - SAME AS DASHBOARD */
        .top-header {
            background: 
            linear-gradient(90deg, 
                #ffda6a 0%, 
                #ffda6a 30%, 
                #FFF5CC 70%, 
                #ffffff 100%);
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

        /* Mobile Menu Toggle - SAME AS DASHBOARD */
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

        /* Dashboard Container - SAME AS DASHBOARD */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS DASHBOARD */
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
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(255,218,106,0.1) 0%, transparent 100%);
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
            
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
        }
        .bg-primary {
            background-color: rgba(var(--bs-primary-rgb), var(--bs-bg-opacity)) !important;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - SAME AS DASHBOARD */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS DASHBOARD */
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

        /* WELCOME SECTION - SAME AS DASHBOARD */
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

        /* KEEPING YOUR ORIGINAL ANNOUNCEMENT STYLES */
        .announcement-tabs {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .nav-tabs {
            border-bottom: 1px solid #e9ecef;
            padding: 0;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 1.25rem 2rem;
            margin-bottom: -1px;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            color: #555;
            background: transparent;
            border-bottom: 3px solid #ffda6a;

        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: #555;
            background: rgba(255,218,106,0.05);
        }

        .tab-content {
            padding: 2rem;
        }

        /* Announcement Cards */
        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .announcement-card.archived {
            border-left: 4px solid var(--gray);
            background: #f8f9fa;
            opacity: 0.8;
        }

        .announcement-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .announcement-icon {
            background: #ffda6a;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        

        .announcement-icon.archived {
            background: var(--gray);
        }

        .announcement-meta {
            flex: 1;
        }

        .announcement-meta h4 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .announcement-date {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .announcement-date i {
            margin-right: 0.5rem;
        }

        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            
            background: #ffda6a;
        }

        .badge-success {
            background: rgba(40, 167, 69, 0.1);
            color: #555;
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .badge-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }

        .badge-secondary {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .announcement-body {
            margin-bottom: 1.5rem;
        }

        .announcement-body p {
            color: #555;
            line-height: 1.6;
            margin: 0;
        }

        .announcement-footer {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .announcement-category {
            background: #f8f9fa;
            color: var(--gray);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .announcement-category i {
            margin-right: 0.4rem;
        }

        .status-active {
            color: var(--success);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--gray);
            font-weight: 600;
        }

        .no-announcements {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }

        .no-announcements i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
            display: block;
        }

        .no-announcements h4 {
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        /* Media Styles */
        .announcement-media {
            margin-top: 1.5rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            transform: scale(1.01);
        }

        .announcement-video {
            width: 100%;
            height: auto;
            max-height: 400px;
            background: #000;
            border-radius: 8px;
        }
        .badge bg-primary ms-1{
            color: #ffda6a;
        }

        .announcement-pdf-preview {
            background: linear-gradient(135deg, #ffda6a 0%, #ffda6a 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .announcement-pdf-preview:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .announcement-pdf-preview i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .announcement-pdf-preview h5 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .announcement-pdf-preview p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        /* Modal enhancements */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #ffda6a 0%, #ffda6a 100%);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        .modal-header .btn-close:hover {
            opacity: 1;
        }

        /* Responsive Design - COMBINED FROM BOTH */
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
                gap: 0.75rem;
            }
            
            .announcement-icon {
                align-self: flex-start;
            }
            
            .announcement-footer {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }

            .announcement-meta h4 {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .tab-content {
                padding: 1.5rem;
            }
            
            .nav-tabs .nav-link {
                padding: 1rem 1.25rem;
                font-size: 0.9rem;
            }

            .announcement-card {
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

        /* ANIMATIONS */
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
    <!-- Mobile Menu Toggle Button - SAME AS DASHBOARD -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS DASHBOARD -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS DASHBOARD -->
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
        <!-- Sidebar - SAME AS DASHBOARD -->
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
            <!-- WELCOME SECTION - SAME AS DASHBOARD -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Announcements 📢</h1>
                    <p>Stay updated with the latest clinic announcements and notices</p>
                </div>
            </div>

            <!-- ANNOUNCEMENT TABS -->
            <div class="announcement-tabs fade-in">
                <ul class="nav nav-tabs" id="announcementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                            <i class="fas fa-bell"></i> Active Announcements
                            <span class="badge bg-primary ms-1"><?php echo count($announcements); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="archive-tab" data-bs-toggle="tab" data-bs-target="#archive" type="button" role="tab">
                            <i class="fas fa-archive"></i> Archive
                            <span class="badge bg-secondary ms-1"><?php echo count($archived_announcements); ?></span>
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
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="announcement-card">
                                        <div class="announcement-header">
                                            <div class="announcement-icon">
                                                <i class="fas fa-bullhorn"></i>
                                            </div>
                                            <div class="announcement-meta">
                                                <h4>
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-users"></i> All Students
                                                    </span>
                                                </h4>
                                                <span class="announcement-date">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                </span>
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
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, #ffda6a, #ffda6a);">
                                                        <i class="fas fa-file"></i>
                                                        <h5>Document File</h5>
                                                        <p>Click to view the file</p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                            <?php endif; ?>
                                        </div>
                                        <div class="announcement-footer">
                                            <span class="announcement-category">
                                                <i class="fas fa-user"></i>
                                                Sent by: <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Admin'); ?>
                                            </span>
                                            <span class="status-active">
                                                <i class="fas fa-circle"></i> Active
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ARCHIVE TAB -->
                    <div class="tab-pane fade" id="archive" role="tabpanel">
                        <?php if (empty($archived_announcements)): ?>
                            <div class="no-announcements">
                                <i class="fas fa-archive"></i>
                                <h4>Archive is Empty</h4>
                                <p>No expired or archived announcements found</p>
                            </div>
                        <?php else: ?>
                            <div class="stagger-animation">
                                <?php foreach ($archived_announcements as $announcement): ?>
                                    <div class="announcement-card archived">
                                        <div class="announcement-header">
                                            <div class="announcement-icon archived">
                                                <i class="fas fa-archive"></i>
                                            </div>
                                            <div class="announcement-meta">
                                                <h4>
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-users"></i> All Students
                                                    </span>
                                                </h4>
                                                <span class="announcement-date">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    Posted: <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                </span>
                                            </div>
                                            
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-ban"></i> Inactive
                                            </span>
                                        </div>
                                        <div class="announcement-body">
                                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            
                                            <!-- MEDIA DISPLAY FOR ARCHIVED -->
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
                                                             alt="Archived Announcement Image" 
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
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, #ffda6a, #ffda6a); opacity: 0.8;">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <h5>PDF Document</h5>
                                                        <p>Click to view the document</p>
                                                    </div>
                                                    
                                                <?php else: ?>
                                                    <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, #ffda6a, #ffda6a); opacity: 0.8;">
                                                        <i class="fas fa-file"></i>
                                                        <h5>Document File</h5>
                                                        <p>Click to view the file</p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                            <?php endif; ?>
                                        </div>
                                        <div class="announcement-footer">
                                            <span class="announcement-category">
                                                <i class="fas fa-user"></i>
                                                Sent by: <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Admin'); ?>
                                            </span>
                                            <span class="status-inactive">
                                                <i class="fas fa-archive"></i> Inactive
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
            // MOBILE MENU FUNCTIONALITY - SAME AS DASHBOARD
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

            // LOADING ANIMATIONS
            const staggerElements = document.querySelectorAll('.stagger-animation > *');
            staggerElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
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