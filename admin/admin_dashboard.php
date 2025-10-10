<?php

//<!-- CLIENT-SIDE TECHNOLOGIES -->

//HTML5 - Page structure and semantics
//CSS3 + Bootstrap 5 - Responsive design and styling
//JavaScript - Interactive features and dynamic content
//Font Awesome - Icons for better user interface

// SERVER-SIDE TECHNOLOGIES

//PHP - Server-side processing and logic
//PDO (PHP Data Objects) - Secure database operations
//MySQL - Data storage and retrieval
//Sessions - Admin authentication and state management

// START SESSION TO ACCESS SESSION VARIABLES
session_start();
// CHECK IF ADMIN IS LOGGED IN BY VERIFYING ADMIN_ID SESSION EXISTS
if (!isset($_SESSION['admin_id'])) {
    // IF NOT LOGGED IN, REDIRECT TO LOGIN PAGE
    header("Location: admin_login.php");
    exit(); // TERMINATE SCRIPT EXECUTION
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ASCOT Clinic</title>
    <!-- BOOTSTRAP CSS FOR STYLING COMPONENTS -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- FONT AWESOME FOR ICONS -->
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    <!-- CUSTOM CSS FOR ADMIN DASHBOARD -->
    <link href="../admin/css/admin_dashboard.css" rel="stylesheet">
    <style>
        /* Notification button styles */
        .notification-dropdown {
            position: relative;
        }

        .notification-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #444;
            margin top: 15px;
        }

        .notification-btn:hover {
            color: #007bff;
        }

        .notification-btn .badge {
            position: absolute;
            top: -8px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            border-radius: 50%;
            padding: 3px 6px;
        }

        /* Notification dropdown menu */
        .notification-menu {
            display: none;
            position: absolute;
            top: 40px;
            right: 0;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border-radius: 10px;
            width: 250px;
            z-index: 999;
        }

        .notification-menu.show {
            display: block;
        }

        .notification-menu .notif-title {
            font-weight: bold;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .notification-menu .notif-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 200px;
            overflow-y: auto;
        }

        .notification-menu .notif-list li {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
            color: #333;
        }

        .notification-menu .notif-list li i {
            color: #007bff;
            margin-right: 5px;
        }

        .notification-menu .view-all {
            display: block;
            text-align: center;
            padding: 8px;
            font-size: 0.85rem;
            color: #007bff;
            text-decoration: none;
            border-top: 1px solid #ddd;
        }

        .notification-menu .view-all:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- HEADER SECTION WITH LOGO AND SCHOOL INFORMATION -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content d-flex align-items-center">
                <!-- ASCOT LOGO -->
                <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN DASHBOARD CONTAINER -->
    <div class="dashboard-container">
        <!-- SIDEBAR NAVIGATION MENU -->
        <aside class="sidebar">
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
                        <a href="calendar.php" class="submenu-item">
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
                        <a href="user_management.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            User Management
                        </a>
                        <a href="access_logs.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Access Logs
                        </a>
                    </div>
                </div>

                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <!-- Notification button floating right above quick-actions -->
             <br>
             <br>
             <br>
             <br>
            <div style="display:flex; justify-content:flex-end; margin:4px 20px 0 0;">
                <div class="notification-dropdown">
                    <button class="notification-btn" id="notifBtn" style="top:-4px;">
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
                        <a href="notifications.php" class="view-all">View all</a>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="new_consultation.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Consultation</span>
                </a>
                <a href="search_students.php" class="action-btn">
                    <i class="fas fa-search"></i>
                    <span>Search Students</span>
                </a>
                <a href="reports.php" class="action-btn">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Reports</span>
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
                    </div>
                </div>

                <div class="divider"></div>

                <div class="quick-links">
                    <a href="students.php" class="link-btn">
                        <i class="fas fa-users"></i>
                        View All Students
                    </a>
                    <a href="reports.php" class="link-btn">
                        <i class="fas fa-archive"></i>
                        Reports Archive
                    </a>
                </div>
            </div>
        </main>
    </div>

    <!-- BOOTSTRAP JAVASCRIPT BUNDLE -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // QUICK ACTION BUTTONS FUNCTIONALITY (CURRENTLY SHOWS ALERTS)
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const actionText = this.querySelector('span').textContent;
                alert(`Redirecting to: ${actionText}`);
            });
        });

        // QUICK LINKS FUNCTIONALITY (CURRENTLY SHOWS ALERTS)
        document.querySelectorAll('.link-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const linkText = this.textContent.trim();
                alert(`Navigating to: ${linkText}`);
            });
        });

        // NOTIFICATION DROPDOWN FUNCTIONALITY
        const notifBtn = document.getElementById('notifBtn');
        const notifMenu = document.getElementById('notifMenu');

        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifMenu.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!notifMenu.contains(e.target) && !notifBtn.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
        });
    </script>
</body>
</html>
