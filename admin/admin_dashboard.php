<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ASCOT Clinic</title>
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

        /* Header Styles */
        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed; /* Added */
            top: 0; /* Added */
            left: 0; /* Added */
            right: 0; /* Added */
            z-index: 1000; /* Added */
            height: 100px; /* Added */
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
            position: fixed; /* Added */
            top: 100px; /* Added */
            left: 0; /* Added */
            bottom: 0; /* Added */
            overflow-y: auto; /* Added */
            z-index: 999; /* Added */
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%; /* Added */
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
            margin-left: 280px; /* Added for sidebar space */
            margin-top: 0; /* Added */
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

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: white;
            border-radius: 10px;
            text-decoration: none;
            color: #444;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.3);
            color: #667eea;
        }

        .action-btn i {
            font-size: 1.5rem;
            color: #667eea;
        }

        /* Dashboard Card */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
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
            font-size: 1.5rem;
            font-weight: bold;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e9ecef, transparent);
            margin: 2rem 0;
        }

        .section-title {
            font-size: 1.2rem;
            color: #444;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #667eea;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .activity-item i {
            color: #667eea;
            font-size: 1.2rem;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .link-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #444;
            transition: all 0.3s ease;
        }

        .link-btn:hover {
            background: #667eea;
            color: white;
            transform: translateX(5px);
        }

        .link-btn i {
            color: #667eea;
            transition: color 0.3s ease;
        }

        .link-btn:hover i {
            color: white;
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

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 100px; /* Adjusted for fixed header */
                height: calc(100vh - 100px); /* Adjusted for fixed header */
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px; /* Added */
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 100px; /* Adjusted for fixed header */
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
                margin-left: 0; /* Reset margin for mobile */
            }

            .quick-actions {
                grid-template-columns: 1fr;
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
        }

        @media (max-width: 480px) {
            .action-btn {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .dashboard-card {
                padding: 1rem;
            }

            .stat-item {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.2rem;
            }

            .notification-menu {
                width: 250px;
                right: -20px;
            }

            .quick-links {
                grid-template-columns: 1fr;
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
                <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img"> <!-- SCHOOL LOGO -->
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
                
                <a href="admin_login.php" class="nav-item logout">
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

            <div class="quick-actions">
                <a href="#" class="action-btn">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Consultation</span>
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-search"></i>
                    <span>Search Students</span>
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Reports</span>
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-bullhorn"></i>
                    <span>New Announcement</span>
                </a>
            </div>

            <div class="dashboard-card">
                <div class="stats-row">
                    <div class="stat-item">
                        <i class="fas fa-calendar-day"></i>
                        <div>
                            <div class="stat-label">Today:</div>
                            <div class="stat-value">5 Consults</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-bullhorn"></i>
                        <div>
                            <div class="stat-label">Active:</div>
                            <div class="stat-value">3 Announcements</div>
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
                        <div class="activity-item">
                            <i class="fas fa-user-md"></i>
                            <span>Dr. James added consult (3:00 pm)</span>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-clipboard-check"></i>
                            <span>New appointment request received (2:30 pm)</span>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-user-check"></i>
                            <span>Student profile updated (1:15 pm)</span>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-bullhorn"></i>
                            <span>New announcement posted (12:45 pm)</span>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="quick-links">
                    <a href="#" class="link-btn">
                        <i class="fas fa-users"></i>
                        View All Students
                    </a>
                    <a href="#" class="link-btn">
                        <i class="fas fa-archive"></i>
                        Reports Archive
                    </a>
                    <a href="#" class="link-btn">
                        <i class="fas fa-bullhorn"></i>
                        Announcement History
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
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
    </script>
</body>
</html>