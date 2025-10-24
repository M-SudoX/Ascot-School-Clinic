<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Logs - ASCOT Clinic</title>
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
            padding-top: 100px; /* Added for fixed header */
        }

        /* Fixed Header Styles */
        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed; /* Made fixed */
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1002; /* Higher z-index */
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
            background-color: white;
            border-radius: 50%;
            padding: 5px;
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
            top: 110px; /* Adjusted for fixed header */
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

        /* Fixed Sidebar Styles */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 0;
            transition: transform 0.3s ease;
            position: fixed; /* Made fixed */
            top: 100px; /* Below the fixed header */
            left: 0;
            height: calc(100vh - 100px); /* Full height minus header */
            overflow-y: auto; /* Scrollable if content is long */
            z-index: 1000;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
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

        /* Main Content - Adjusted for fixed sidebar */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            margin-left: 280px; /* Space for fixed sidebar */
            width: calc(100% - 280px); /* Adjusted width */
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
            margin-bottom: 20px;
        }

        .content-header h2 {
            color: #1a3a5f;
            font-size: 24px;
        }

        /* Search Bar Styles */
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 250px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        .search-btn {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background-color: #5a6fd8;
            transform: translateY(-2px);
        }

        .clear-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .clear-btn:hover {
            background-color: #5a6268;
        }

        /* Bulk Actions Styles */
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .select-all-label {
            font-size: 14px;
            color: #666;
            cursor: pointer;
        }

        .bulk-delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .bulk-delete-btn:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .bulk-delete-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            color: #1a3a5f;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }

        .user-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .user-type.admin {
            background-color: #e7f3ff;
            color: #0066cc;
        }

        .user-type.student {
            background-color: #f0f9ff;
            color: #0c6;
        }

        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background-color: #c82333;
            transform: scale(1.05);
        }

        .row-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pagination-info {
            font-size: 14px;
            color: #666;
        }

        .pagination-controls {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 5px 10px;
            border: 1px solid #ddd;
            background-color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover {
            background-color: #f8f9fa;
        }

        .pagination-btn.active {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
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
            body {
                padding-top: 80px; /* Adjusted for smaller header on mobile */
            }

            .top-header {
                padding: 0.5rem 0; /* Reduced padding on mobile */
            }

            .mobile-menu-toggle {
                display: block;
                top: 85px; /* Adjusted for smaller header */
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 80px; /* Adjusted for smaller header */
                height: calc(100vh - 80px); /* Adjusted height */
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px; /* Fixed width */
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 80px; /* Start below header */
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
                margin-left: 0; /* Remove sidebar margin on mobile */
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

            .search-container {
                flex-direction: column;
            }

            .search-input {
                min-width: 100%;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }

            .pagination {
                flex-direction: column;
                align-items: flex-start;
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

            th, td {
                padding: 8px 10px;
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

    <!-- Fixed Header -->
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
        <!-- Fixed Sidebar -->
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
                    <div class="submenu show" id="adminMenu">
                        <a href="users_logs.php" class="submenu-item active">
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
                
                <a href="logout.php" class="nav-item logout">
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
                    <h2>User Activity Logs</h2>
                </div>
                
                <!-- Search Bar -->
                <div class="search-container">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search by name, student ID, or action...">
                    <button class="search-btn" id="searchBtn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button class="clear-btn" id="clearBtn">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <div class="select-all-container">
                        <input type="checkbox" class="select-all-checkbox" id="selectAll">
                        <label for="selectAll" class="select-all-label">Select All</label>
                    </div>
                    <button class="bulk-delete-btn" id="bulkDeleteBtn" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
                
                <div class="table-container">
                    <table id="userLogsTable">
                        <thead>
                            <tr>
                                <th width="50">Select</th>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>User Type</th>
                                <th>Date & Time</th>
                                <th>Action</th>
                                <th width="100">Delete</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php
                            // Database connection
                            $servername = "127.0.0.1";
                            $username = "root";
                            $password = "";
                            $dbname = "ascot_clinic_db";

                            // Create connection
                            $conn = new mysqli($servername, $username, $password, $dbname);

                            // Check connection
                            if ($conn->connect_error) {
                                die("Connection failed: " . $conn->connect_error);
                            }

                            // CREATE ADMIN LOGS TABLE IF IT DOESN'T EXIST
                            $create_admin_logs_table = "
                                CREATE TABLE IF NOT EXISTS `admin_logs` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `admin_name` varchar(100) DEFAULT NULL,
                                    `action` varchar(255) NOT NULL,
                                    `log_date` datetime NOT NULL DEFAULT current_timestamp(),
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                            ";
                            
                            if (!$conn->query($create_admin_logs_table)) {
                                echo "<!-- Error creating admin_logs table: " . $conn->error . " -->";
                            }

                            // Check if delete action is requested
                            if (isset($_GET['delete_id']) && isset($_GET['type'])) {
                                $delete_id = $_GET['delete_id'];
                                $type = $_GET['type'];
                                
                                if ($type == 'student') {
                                    $delete_sql = "DELETE FROM activity_logs WHERE id = ?";
                                } else {
                                    $delete_sql = "DELETE FROM admin_logs WHERE id = ?";
                                }
                                
                                $stmt = $conn->prepare($delete_sql);
                                $stmt->bind_param("i", $delete_id);
                                if ($stmt->execute()) {
                                    echo "<script>alert('Log deleted successfully');</script>";
                                } else {
                                    echo "<script>alert('Error deleting log');</script>";
                                }
                                $stmt->close();
                                
                                // Redirect to avoid duplicate deletion on refresh
                                echo "<script>window.location.href = 'users_logs.php';</script>";
                                exit();
                            }

                            // Check if bulk delete is requested
                            if (isset($_POST['bulk_delete']) && isset($_POST['selected_logs'])) {
                                $selected_logs = $_POST['selected_logs'];
                                $deleted_count = 0;
                                
                                foreach ($selected_logs as $log) {
                                    list($type, $id) = explode('_', $log);
                                    
                                    if ($type == 'student') {
                                        $delete_sql = "DELETE FROM activity_logs WHERE id = ?";
                                    } else {
                                        $delete_sql = "DELETE FROM admin_logs WHERE id = ?";
                                    }
                                    
                                    $stmt = $conn->prepare($delete_sql);
                                    $stmt->bind_param("i", $id);
                                    if ($stmt->execute()) {
                                        $deleted_count++;
                                    }
                                    $stmt->close();
                                }
                                
                                if ($deleted_count > 0) {
                                    echo "<script>alert('Successfully deleted " . $deleted_count . " logs');</script>";
                                    echo "<script>window.location.href = 'users_logs.php';</script>";
                                    exit();
                                }
                            }

                            // Student logs query - LAHAT NG ACTIONS MALIBAN SA Viewed at Accessed
                            $student_logs_sql = "
                                SELECT 
                                    al.id,
                                    al.log_date as date_time,
                                    u.student_number as student_id,
                                    u.fullname as name,
                                    'student' as user_type,
                                    al.action
                                FROM activity_logs al
                                INNER JOIN users u ON al.student_id = u.id
                                WHERE u.fullname IS NOT NULL 
                                AND u.fullname != ''
                                AND al.action NOT LIKE '%Viewed%'
                                AND al.action NOT LIKE '%Accessed%'
                                ORDER BY al.log_date DESC
                                LIMIT 100
                            ";

                            $student_logs = $conn->query($student_logs_sql);

                            // Admin logs query - LAHAT NG ACTIONS MALIBAN SA Viewed at Accessed
                            $admin_logs_sql = "
                                SELECT 
                                    id,
                                    log_date as date_time,
                                    'N/A' as student_id,
                                    COALESCE(admin_name, 'Admin User') as name,
                                    'admin' as user_type,
                                    action
                                FROM admin_logs 
                                WHERE action NOT LIKE '%Viewed%'
                                AND action NOT LIKE '%Accessed%'
                                ORDER BY log_date DESC
                                LIMIT 100
                            ";

                            $admin_logs = $conn->query($admin_logs_sql);

                            // Combine all logs
                            $all_logs = [];

                            // Process student logs
                            if ($student_logs && $student_logs->num_rows > 0) {
                                while($row = $student_logs->fetch_assoc()) {
                                    $all_logs[] = $row;
                                }
                            }

                            // Process admin logs
                            if ($admin_logs && $admin_logs->num_rows > 0) {
                                while($row = $admin_logs->fetch_assoc()) {
                                    $all_logs[] = $row;
                                }
                            }

                            // Sort by date (newest first)
                            usort($all_logs, function($a, $b) {
                                return strtotime($b['date_time']) - strtotime($a['date_time']);
                            });

                            // Display logs
                            if (empty($all_logs)) {
                                echo '<tr><td colspan="8" class="no-results">No user logs available</td></tr>';
                            } else {
                                foreach ($all_logs as $log) {
                                    $student_id = $log['student_id'];
                                    $user_type = $log['user_type'];
                                    $name = $log['name'];
                                    $action = $log['action'];
                                    $date_time = $log['date_time'];
                                    $id = $log['id'];
                                    $log_type = ($user_type == 'student') ? 'student' : 'admin';
                                    $checkbox_value = $log_type . '_' . $id;
                                    
                                    echo "
                                    <tr>
                                        <td>
                                            <input type='checkbox' class='row-checkbox' name='selected_logs[]' value='{$checkbox_value}'>
                                        </td>
                                        <td>{$id}</td>
                                        <td>{$student_id}</td>
                                        <td>{$name}</td>
                                        <td><span class='user-type {$user_type}'>" . ucfirst($user_type) . "</span></td>
                                        <td>{$date_time}</td>
                                        <td>{$action}</td>
                                        <td>
                                            <button class='delete-btn' onclick=\"deleteLog({$id}, '{$log_type}')\">
                                                <i class='fas fa-trash'></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                    ";
                                }
                            }

                            $total_logs = count($all_logs);
                            $conn->close();
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">
                        Showing <?php echo $total_logs; ?> of <?php echo $total_logs; ?> entries
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="prevBtn">Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn" id="nextBtn">Next</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bulk Delete Form -->
    <form method="POST" id="bulkDeleteForm" style="display: none;">
        <input type="hidden" name="bulk_delete" value="1">
    </form>

    <script>
        function deleteLog(id, type) {
            if (confirm('Are you sure you want to delete this log?')) {
                window.location.href = 'users_logs.php?delete_id=' + id + '&type=' + type;
            }
        }

        function bulkDelete() {
            const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one log to delete.');
                return;
            }

            if (confirm(`Are you sure you want to delete ${selectedCheckboxes.length} selected logs?`)) {
                // Add selected checkboxes to form
                const form = document.getElementById('bulkDeleteForm');
                selectedCheckboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_logs[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
                
                // Submit form
                form.submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchBtn = document.getElementById('searchBtn');
            const clearBtn = document.getElementById('clearBtn');
            const tableBody = document.getElementById('tableBody');
            const rows = tableBody.getElementsByTagName('tr');
            const paginationInfo = document.getElementById('paginationInfo');
            const selectAllCheckbox = document.getElementById('selectAll');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            
            // Select All functionality
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                updateBulkDeleteButton();
            });

            // Update bulk delete button state
            function updateBulkDeleteButton() {
                const selectedCount = document.querySelectorAll('.row-checkbox:checked').length;
                bulkDeleteBtn.disabled = selectedCount === 0;
                if (selectedCount > 0) {
                    bulkDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${selectedCount})`;
                } else {
                    bulkDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected`;
                }
            }

            // Add event listeners to row checkboxes
            document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // If any checkbox is unchecked, uncheck select all
                    if (!this.checked && selectAllCheckbox.checked) {
                        selectAllCheckbox.checked = false;
                    }
                    updateBulkDeleteButton();
                });
            });

            // Bulk delete button event
            bulkDeleteBtn.addEventListener('click', bulkDelete);

            // Search function
            function searchLogs() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.getElementsByTagName('td');
                    let found = false;

                    // Skip the "no results" row
                    if (cells.length === 1 && cells[0].classList.contains('no-results')) {
                        continue;
                    }

                    for (let j = 0; j < cells.length; j++) {
                        const cellText = cells[j].textContent.toLowerCase();
                        if (cellText.includes(searchTerm)) {
                            found = true;
                            break;
                        }
                    }

                    if (found) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }

                // Update pagination info
                paginationInfo.textContent = `Showing ${visibleCount} of ${rows.length} entries`;

                // Show no results message if no rows are visible
                if (visibleCount === 0 && rows.length > 0) {
                    const firstRow = rows[0];
                    if (!firstRow.querySelector('.no-results')) {
                        tableBody.innerHTML = '<tr><td colspan="8" class="no-results">No matching records found</td></tr>';
                    }
                }
            }

            // Clear search
            function clearSearch() {
                searchInput.value = '';
                // Reload the page to reset the table
                window.location.reload();
            }

            // Event listeners
            searchBtn.addEventListener('click', searchLogs);
            clearBtn.addEventListener('click', clearSearch);

            // Search on Enter key
            searchInput.addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    searchLogs();
                }
            });

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

            // Pagination functionality
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const paginationBtns = document.querySelectorAll('.pagination-btn');

            paginationBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!this.classList.contains('active')) {
                        document.querySelector('.pagination-btn.active').classList.remove('active');
                        this.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>
</html>