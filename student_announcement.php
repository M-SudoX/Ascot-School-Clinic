<?php
// ==================== SESSION AT SECURITY ====================
session_start();

// ✅ SECURITY CHECK: TEMPORARILY DISABLED FOR TESTING
// if (!isset($_SESSION['student_id'])) {
//     header("Location: student_login.php");
//     exit();
// }

require_once 'includes/db_connect.php';

$student_number = $_SESSION['student_number'] ?? '2021-12345';
$student_id = $_SESSION['student_id'] ?? 1;

// DEBUG: Check what's in the database - COMMENTED OUT FOR NORMAL VIEW
/*
echo "<div style='background: #f8d7da; padding: 15px; margin: 10px; border-radius: 5px;'>";
echo "<h3>🔍 DEBUG INFORMATION - STUDENT ANNOUNCEMENTS</h3>";

try {
    // Check total announcements in database
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM announcements");
    $total_count = $total_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total announcements in database:</strong> " . $total_count['total'] . "</p>";
    
    // Check active announcements with post_on_front = 1
    $active_stmt = $pdo->query("SELECT COUNT(*) as active FROM announcements WHERE post_on_front = 1 AND is_active = 1");
    $active_count = $active_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Active announcements (post_on_front=1, is_active=1):</strong> " . $active_count['active'] . "</p>";
    
    // Check inactive announcements
    $inactive_stmt = $pdo->query("SELECT COUNT(*) as inactive FROM announcements WHERE is_active = 0");
    $inactive_count = $inactive_stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Inactive announcements (is_active=0):</strong> " . $inactive_count['inactive'] . "</p>";
    
    // Show ALL announcements data - FIXED: removed recipient_type
    $all_stmt = $pdo->query("SELECT id, title, post_on_front, is_active, created_at FROM announcements ORDER BY created_at DESC");
    $all_data = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>ALL ANNOUNCEMENTS IN DATABASE:</strong></p>";
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background: #e9ecef;'><th>ID</th><th>Title</th><th>Post Front</th><th>Active</th><th>Created</th></tr>";
    foreach ($all_data as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . $row['post_on_front'] . "</td>";
        echo "<td>" . $row['is_active'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . $e->getMessage() . "</p>";
}
echo "</div>";
*/

// Function to get announcement badge - FIXED: added default value
function getAnnouncementBadge($recipient_type = 'all') {
    switch ($recipient_type) {
        case 'specific':
            return '<span class="badge bg-warning ms-2"><i class="fas fa-user-friends"></i> Specific</span>';
        case 'attendees':
            return '<span class="badge bg-info ms-2"><i class="fas fa-calendar-check"></i> Recent Visitors</span>';
        case 'all':
        default:
            return '<span class="badge bg-success ms-2"><i class="fas fa-users"></i> All Students</span>';
    }
}

// Function to get announcement card class - FIXED: added default value
function getAnnouncementCardClass($recipient_type = 'all', $is_expiring_soon = false) {
    $classes = [];
    
    if ($is_expiring_soon) {
        $classes[] = 'expiring';
    }
    
    switch ($recipient_type) {
        case 'specific':
            $classes[] = 'specific';
            break;
        case 'attendees':
            $classes[] = 'attendees';
            break;
    }
    
    return implode(' ', $classes);
}

// ✅ SIMPLIFIED DATABASE QUERY - FIXED: removed recipient_type references
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
    
    // COMMENTED OUT DEBUG INFORMATION
    /*
    echo "<div style='background: #d1ecf1; padding: 15px; margin: 10px; border-radius: 5px;'>";
    echo "<p><strong>ACTIVE ANNOUNCEMENTS FOUND FOR STUDENT VIEW:</strong> " . count($announcements) . "</p>";
    if (!empty($announcements)) {
        echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='background: #e9ecef;'><th>ID</th><th>Title</th><th>Post Front</th><th>Active</th><th>Created</th></tr>";
        foreach ($announcements as $ann) {
            echo "<tr>";
            echo "<td>" . $ann['id'] . "</td>";
            echo "<td>" . htmlspecialchars($ann['title']) . "</td>";
            echo "<td>" . $ann['post_on_front'] . "</td>";
            echo "<td>" . $ann['is_active'] . "</td>";
            echo "<td>" . $ann['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No announcements found with post_on_front = 1 AND is_active = 1</p>";
    }
    echo "</div>";
    */
    
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
    // COMMENTED OUT ERROR DISPLAY
    /*
    echo "<div style='background: #f8d7da; padding: 15px; margin: 10px; border-radius: 5px;'>";
    echo "<p style='color: red;'><strong>QUERY ERROR:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
    */
}

// Add this function to check file paths
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

        /* ========== ANNOUNCEMENT STYLES ========== */
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
            transition: all 0.3s ease;
        }

        .announcement-card.archived {
            border-left: 4px solid #6c757d;
            background: #f8f9fa;
            opacity: 0.8;
        }

        .announcement-card.expiring {
            border-left: 4px solid #f39c12;
            background: linear-gradient(135deg, #fff 0%, #fff9e6 100%);
        }

        .announcement-card.specific {
            border-left: 4px solid #ffc107;
            background: linear-gradient(135deg, #fff 0%, #fffbf0 100%);
        }

        .announcement-card.attendees {
            border-left: 4px solid #17a2b8;
            background: linear-gradient(135deg, #fff 0%, #f0fdff 100%);
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

        .announcement-icon.archived {
            background: #6c757d;
        }

        .announcement-icon.expiring {
            background: #f39c12;
        }

        .announcement-icon.specific {
            background: #ffc107;
        }

        .announcement-icon.attendees {
            background: #17a2b8;
        }

        .announcement-meta {
            flex: 1;
        }

        .announcement-meta h4 {
            color: #333;
            margin-bottom: 5px;
            font-weight: bold;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
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

        .badge-expired {
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-expiring {
            background: #f39c12;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .archive-status {
            font-size: 0.8rem;
            color: #e74c3c;
            font-weight: 500;
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

        /* ========== TAB STYLES ========== */
        .announcement-tabs {
            background: white;
            border-radius: 10px;
            padding: 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            padding: 0 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 15px 25px;
            margin-bottom: -1px;
        }

        .nav-tabs .nav-link.active {
            color: #3498db;
            background: transparent;
            border-bottom: 3px solid #3498db;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: #3498db;
        }

        .tab-content {
            padding: 20px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* ========== ENHANCED MEDIA STYLES ========== */
        .announcement-media {
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .announcement-image {
            width: 100%;
            height: auto;
            max-height: 500px;
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
            max-height: 500px;
            background: #000;
            border-radius: 8px;
        }

        .announcement-pdf-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .announcement-pdf-preview:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .announcement-pdf-preview i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        .announcement-pdf-preview h5 {
            margin-bottom: 10px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        /* Auto-play video styling */
        .video-autoplay {
            width: 100%;
            height: auto;
            max-height: 500px;
            background: #000;
            border-radius: 10px;
        }

        /* Responsive media */
        @media (max-width: 768px) {
            .announcement-image,
            .announcement-video,
            .video-autoplay {
                max-height: 300px !important;
            }
            
            .announcement-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .announcement-icon {
                align-self: flex-start;
            }
            
            .announcement-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .announcement-meta h4 {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .announcement-image,
            .announcement-video,
            .video-autoplay {
                max-height: 250px !important;
            }
            
            .nav-tabs .nav-link {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
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

            <!-- MAIN CONTENT AREA -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
                    <p class="text-muted">Stay updated with the latest clinic announcements and notices</p>
                </div>

                <!-- REMOVED DEBUG MODE BANNER -->

                <!-- ANNOUNCEMENT TABS -->
                <div class="announcement-tabs">
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
                            <div class="announcements-container">
                                <?php if (empty($announcements)): ?>
                                    <div class="no-announcements">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Active Announcements</h4>
                                        <p>Check back later for updates from the clinic</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="announcement-card <?php echo getAnnouncementCardClass($announcement['recipient_type'] ?? 'all'); ?>">
                                            <div class="announcement-header">
                                                <div class="announcement-icon <?php echo ($announcement['recipient_type'] ?? 'all') === 'specific' ? 'specific' : (($announcement['recipient_type'] ?? 'all') === 'attendees' ? 'attendees' : ''); ?>">
                                                    <i class="fas fa-bullhorn"></i>
                                                </div>
                                                <div class="announcement-meta">
                                                    <h4>
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                        <?php echo getAnnouncementBadge($announcement['recipient_type'] ?? 'all'); ?>
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
                                                        <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, #27ae60, #219a52);">
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
                                                <span class="text-success">
                                                    <i class="fas fa-circle"></i> Active
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- ARCHIVE TAB -->
                        <div class="tab-pane fade" id="archive" role="tabpanel">
                            <div class="announcements-container">
                                <?php if (empty($archived_announcements)): ?>
                                    <div class="no-announcements">
                                        <i class="fas fa-archive"></i>
                                        <h4>Archive is Empty</h4>
                                        <p>No expired or archived announcements found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($archived_announcements as $announcement): ?>
                                        <div class="announcement-card archived">
                                            <div class="announcement-header">
                                                <div class="announcement-icon archived">
                                                    <i class="fas fa-archive"></i>
                                                </div>
                                                <div class="announcement-meta">
                                                    <h4>
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                        <?php echo getAnnouncementBadge($announcement['recipient_type'] ?? 'all'); ?>
                                                    </h4>
                                                    <span class="announcement-date">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        Posted: <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                    </span>
                                                </div>
                                                
                                                <span class="badge-expired">
                                                    <i class="fas fa-ban"></i>
                                                    Inactive
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
                                                        <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, #6c757d, #5a6268); opacity: 0.8;">
                                                            <i class="fas fa-file-pdf"></i>
                                                            <h5>PDF Document</h5>
                                                            <p>Click to view the document</p>
                                                        </div>
                                                        
                                                    <?php else: ?>
                                                        <div class="announcement-pdf-preview" onclick="window.open('<?php echo $actualPath; ?>', '_blank')" style="background: linear-gradient(135deg, #6c757d, #5a6268); opacity: 0.8;">
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
                                                <span class="archive-status">
                                                    <i class="fas fa-archive"></i> Inactive
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
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

            // Image Modal Functionality
            window.openImageModal = function(filePath) {
                const modalImage = document.getElementById('modalImage');
                modalImage.src = filePath;
                
                const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
                imageModal.show();
            }

            // Auto-refresh announcements every 5 minutes
            function autoRefreshAnnouncements() {
                setTimeout(() => {
                    location.reload();
                }, 300000); // 5 minutes
            }

            // Initialize auto-refresh
            autoRefreshAnnouncements();

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