<?php
session_start();
require_once '../includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success_message'] = 'Announcement deleted successfully!';
        header('Location: announcement_history.php');
        exit;
    } catch (PDOException $e) {
        $error_message = "Error deleting announcement: " . $e->getMessage();
    }
}

// Handle expire action - SET is_active = 0
if (isset($_GET['expire_id'])) {
    $expire_id = $_GET['expire_id'];
    try {
        $stmt = $pdo->prepare("UPDATE announcements SET is_active = 0 WHERE id = ?");
        $stmt->execute([$expire_id]);
        $_SESSION['success_message'] = 'Announcement expired successfully!';
        header('Location: announcement_history.php');
        exit;
    } catch (PDOException $e) {
        $error_message = "Error expiring announcement: " . $e->getMessage();
    }
}

// Handle activate action - SET is_active = 1
if (isset($_GET['activate_id'])) {
    $activate_id = $_GET['activate_id'];
    try {
        $stmt = $pdo->prepare("UPDATE announcements SET is_active = 1 WHERE id = ?");
        $stmt->execute([$activate_id]);
        $_SESSION['success_message'] = 'Announcement activated successfully!';
        header('Location: announcement_history.php');
        exit;
    } catch (PDOException $e) {
        $error_message = "Error activating announcement: " . $e->getMessage();
    }
}

// FIXED: Fetch announcements with safe column checking
$announcements = [];
try {
    // First, let's check what columns actually exist
    $check_stmt = $pdo->query("SHOW COLUMNS FROM announcements");
    $existing_columns = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query with only existing columns
    $select_columns = ["id", "title", "content", "sent_by", "created_at"];
    
    $optional_columns = ['send_email', 'post_on_front', 'recipient_type', 'attachment', 'is_active'];
    foreach ($optional_columns as $column) {
        if (in_array($column, $existing_columns)) {
            $select_columns[] = $column;
        }
    }
    
    $query = "SELECT " . implode(', ', $select_columns) . " FROM announcements ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Error fetching announcements: " . $e->getMessage();
}

// Safe functions with null coalescing
function getAnnouncementStatus($created_at, $is_active = null) {
    $is_active = $is_active ?? 1;
    
    if (!$is_active) {
        return 'Expired';
    }
    
    $created_time = strtotime($created_at);
    $current_time = time();
    $days_diff = ($current_time - $created_time) / (60 * 60 * 24);
    
    if ($days_diff > 30) {
        return 'Expired';
    }
    
    return 'Active';
}

function getAnnouncementType($send_email = null, $post_on_front = null) {
    $send_email = $send_email ?? 0;
    $post_on_front = $post_on_front ?? 0;
    
    $types = [];
    if ($send_email) {
        $types[] = 'Email';
    }
    if ($post_on_front) {
        $types[] = 'Post';
    }
    return implode(' & ', $types) ?: 'None';
}

function getRecipientDisplay($recipient_type = null) {
    if (empty($recipient_type) || $recipient_type === null) {
        return 'All Students';
    }
    
    switch ($recipient_type) {
        case 'all':
            return 'All Students';
        case 'specific':
            return 'Specific Students';
        case 'attendees':
            return 'Attendees';
        default:
            return 'All Students';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement History - ASCOT Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        /* Header Styles */
        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .school-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0.2rem 0;
        }

        .clinic-title {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 100px;
            left: 20px;
            z-index: 1001;
            background: #667eea;
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
            transform: scale(1.1);
            background: #764ba2;
        }

        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 0;
            transition: transform 0.3s ease;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #667eea;
            border-left: 4px solid #667eea;
        }

        .nav-item i {
            width: 25px;
            margin-right: 1rem;
        }

        .nav-item span {
            flex: 1;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
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
            padding: 0.75rem 1.5rem 0.75rem 3.5rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: #667eea;
        }

        .submenu-item.active {
            color: #667eea;
            font-weight: 500;
        }

        .submenu-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        .nav-item.logout {
            color: #dc3545;
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
        }

        /* Notification Styles */
        .notification-dropdown {
            position: relative;
        }

        .notification-btn {
            background: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #444;
            padding: 0.5rem 1rem;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            color: #667eea;
            transform: scale(1.1);
        }

        .notification-btn .badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            border-radius: 50%;
            padding: 3px 6px;
            min-width: 20px;
        }

        .notification-menu {
            display: none;
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 10px;
            width: 300px;
            z-index: 999;
        }

        .notification-menu.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-menu .notif-title {
            font-weight: bold;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .notification-menu .notif-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 250px;
            overflow-y: auto;
        }

        .notification-menu .notif-list li {
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            font-size: 0.9rem;
            color: #333;
            transition: background 0.3s ease;
        }

        .notification-menu .notif-list li:hover {
            background: #f8f9fa;
        }

        .notification-menu .notif-list li i {
            color: #667eea;
            margin-right: 0.5rem;
        }

        .notification-menu .view-all {
            display: block;
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
            color: #667eea;
            text-decoration: none;
            border-top: 1px solid #e9ecef;
            font-weight: 500;
        }

        .notification-menu .view-all:hover {
            background: #f8f9fa;
        }

        /* Content Section */
        .content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-top: 1rem;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .content-header h2 {
            color: #1a3a5f;
            font-size: 24px;
        }

        /* Table Styles */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .history-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .history-table tr:hover {
            background: #f8f9fa;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .btn-edit:hover {
            background: #e0a800;
        }

        .btn-expire {
            background: #dc3545;
            color: white;
        }

        .btn-expire:hover {
            background: #c82333;
        }

        .btn-activate {
            background: #28a745;
            color: white;
        }

        .btn-activate:hover {
            background: #218838;
        }

        .btn-delete {
            background: #6c757d;
            color: white;
        }

        .btn-delete:hover {
            background: #5a6268;
        }

        /* Responsive Design */
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
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 1rem;
                width: 100%;
            }

            .notification-menu {
                width: 280px;
                right: -40px;
            }

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.85rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .history-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .notification-menu {
                width: 250px;
                right: -20px;
            }

            .content {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header -->
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
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item">
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
                        <a href="#" class="submenu-item">
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
                        <a href="#" class="submenu-item">
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
                        <a href="#" class="submenu-item">
                            <i class="fas fa-calendar-alt"></i>
                            Calendar View
                        </a>
                        <a href="#" class="submenu-item">
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
                        <a href="#" class="submenu-item">
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
                        <a href="#" class="submenu-item">
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
                    <div class="submenu show" id="announcementMenu">
                        <a href="new_announcement.php" class="submenu-item">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="announcement_history.php" class="submenu-item active">
                            <i class="fas fa-history"></i>
                            History
                        </a>
                    </div>
                </div>
                
                <a href="#" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div style="display:flex; justify-content:flex-end; margin-bottom: 1.5rem;">
                <div class="notification-dropdown">
                    <button class="notification-btn" id="notifBtn">
                        <i class="fas fa-bell"></i>
                        <span class="badge" id="notifCount">3</span>
                    </button>
                    <div class="notification-menu" id="notifMenu">
                        <p class="notif-title">Notifications</p>
                        <ul class="notif-list">
                            <li><i class="fas fa-user-plus"></i> New student registered</li>
                            <li><i class="fas fa-calendar-check"></i> Appointment pending approval</li>
                            <li><i class="fas fa-stethoscope"></i> Consultation completed</li>
                        </ul>
                        <a href="#" class="view-all">View all</a>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="content-header">
                    <h2><i class="fas fa-history me-2"></i>Announcement History</h2>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Date Sent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: #dee2e6; margin-bottom: 1rem;"></i>
                                        <p>No announcements found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($announcement['id']); ?></td>
                                        <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                        <td><?php echo getRecipientDisplay($announcement['recipient_type'] ?? 'all'); ?></td>
                                        <td><?php echo getAnnouncementType($announcement['send_email'] ?? 0, $announcement['post_on_front'] ?? 0); ?></td>
                                        <td><?php echo date('m-d-Y', strtotime($announcement['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $status = getAnnouncementStatus($announcement['created_at'], $announcement['is_active'] ?? 1);
                                            $status_class = 'status-' . strtolower($status);
                                            ?>
                                            <span class="<?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" 
                                                        title="View Details" 
                                                        onclick="viewAnnouncement(<?php echo $announcement['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-edit" 
                                                        title="Edit Announcement"
                                                        onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <!-- EXPIRED/ACTIVATE BUTTON -->
                                                <?php if (($announcement['is_active'] ?? 1)): ?>
                                                    <button class="btn-action btn-expire" 
                                                            title="Mark as Expired"
                                                            onclick="expireAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-action btn-activate" 
                                                            title="Activate Announcement"
                                                            onclick="activateAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn-action btn-delete" 
                                                        title="Delete Announcement"
                                                        onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu functionality
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

        // Notification dropdown
        const notifBtn = document.getElementById('notifBtn');
        const notifMenu = document.getElementById('notifMenu');

        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifMenu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!notifMenu.contains(e.target) && !notifBtn.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
        });

        // View Announcement Function
        function viewAnnouncement(id) {
            alert('Viewing announcement ID: ' + id);
            // You can implement modal view here
        }

        // Edit Announcement Function  
        function editAnnouncement(id) {
            alert('Edit feature coming soon! Announcement ID: ' + id);
        }

        // EXPIRE Announcement Function - SET is_active = 0
        function expireAnnouncement(id, title) {
            if (confirm(`Are you sure you want to mark "${title}" as EXPIRED?\n\nThis will remove it from student view.`)) {
                window.location.href = `announcement_history.php?expire_id=${id}`;
            }
        }

        // ACTIVATE Announcement Function - SET is_active = 1  
        function activateAnnouncement(id, title) {
            if (confirm(`Are you sure you want to ACTIVATE "${title}"?\n\nThis will make it visible to students again.`)) {
                window.location.href = `announcement_history.php?activate_id=${id}`;
            }
        }

        // DELETE Announcement Function
        function deleteAnnouncement(id, title) {
            if (confirm(`Are you sure you want to PERMANENTLY DELETE "${title}"?\n\nThis action cannot be undone!`)) {
                window.location.href = `announcement_history.php?delete_id=${id}`;
            }
        }

        // Dropdown functionality
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
    </script>
</body>
</html>