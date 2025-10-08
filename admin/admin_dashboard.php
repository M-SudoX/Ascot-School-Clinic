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








// i use session start check if yo are admin and authorize administrator can be access the dashboard 

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
</head>
<body>
    <!-- HEADER SECTION WITH LOGO AND SCHOOL INFORMATION -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
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
                <!-- DASHBOARD LINK - ACTIVE STATE -->
                <a href="admin_dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <!-- STUDENT MANAGEMENT DROPDOWN MENU -->
                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="studentMenu">
                        <!-- STUDENTS PROFILE LINK -->
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <!-- SEARCH STUDENTS LINK -->
                        <a href="search_students.php" class="submenu-item">
                            <i class="fas fa-search"></i>
                            Search Students
                        </a>
                    </div>
                </div>

                <!-- CONSULTATION DROPDOWN MENU -->
                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="consultationMenu">
                        <!-- VIEW RECORDS LINK -->
                        <a href="view_records.php" class="submenu-item">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                    </div>
                </div>

                <!-- APPOINTMENTS DROPDOWN MENU -->
                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="appointmentsMenu">
                        <!-- CALENDAR VIEW LINK -->
                        <a href="calendar.php" class="submenu-item">
                            <i class="fas fa-calendar-alt"></i>
                            Calendar View
                        </a>
                        <!-- APPROVALS LINK -->
                        <a href="approvals.php" class="submenu-item">
                            <i class="fas fa-check-circle"></i>
                            Approvals
                        </a>
                    </div>
                </div>

                <!-- REPORTS DROPDOWN MENU -->
                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="reportsMenu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="reportsMenu">
                        <!-- MONTHLY SUMMARY LINK -->
                        <a href="monthly_summary.php" class="submenu-item">
                            <i class="fas fa-file-invoice"></i>
                            Monthly Summary
                        </a>
                    </div>
                </div>

                <!-- ADMIN TOOLS DROPDOWN MENU -->
                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="adminMenu">
                        <!-- USER MANAGEMENT LINK -->
                        <a href="user_management.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            User Management
                        </a>
                        <!-- ACCESS LOGS LINK -->
                        <a href="access_logs.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Access Logs
                        </a>
                    </div>
                </div>

                <!-- LOGOUT LINK -->
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <!-- QUICK ACTION BUTTONS SECTION -->
            <div class="quick-actions">
                <!-- NEW CONSULTATION BUTTON -->
                <a href="new_consultation.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Consultation</span>
                </a>
                <!-- SEARCH STUDENTS BUTTON -->
                <a href="search_students.php" class="action-btn">
                    <i class="fas fa-search"></i>
                    <span>Search Students</span>
                </a>
                <!-- PENDING APPROVALS BUTTON WITH COUNTER -->
                <a href="approvals.php" class="action-btn">
                    <i class="fas fa-clock"></i>
                    <span>Pending: (3)</span>
                </a>
                <!-- GENERATE REPORTS BUTTON -->
                <a href="reports.php" class="action-btn">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Reports</span>
                </a>
            </div>

            <!-- DASHBOARD STATISTICS CARD -->
            <div class="dashboard-card">
                <!-- STATS ROW WITH TODAY'S COUNT AND ALERTS -->
                <div class="stats-row">
                    <div class="stat-item">
                        <i class="fas fa-calendar-day"></i>
                        <div>
                            <div class="stat-label">Today:</div>
                            <div class="stat-value">5 Consults</div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- DIVIDER LINE -->
                <div class="divider"></div>

                <!-- RECENT ACTIVITY SECTION -->
                <div class="activity-section">
                    <h3 class="section-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </h3>
                    <div class="activity-list">
                        <!-- ACTIVITY ITEM 1 -->
                        <div class="activity-item">
                            <i class="fas fa-user-md"></i>
                            <span>Dr. James added consult (3:00 pm)</span>
                        </div>
                        <!-- ACTIVITY ITEM 2 -->
                        <div class="activity-item">
                            <i class="fas fa-clipboard-check"></i>
                            <span>New appointment request received (2:30 pm)</span>
                        </div>
                        <!-- ACTIVITY ITEM 3 -->
                        <div class="activity-item">
                            <i class="fas fa-user-check"></i>
                            <span>Student profile updated (1:15 pm)</span>
                        </div>
                    </div>
                </div>

                <!-- DIVIDER LINE -->
                <div class="divider"></div>

                <!-- QUICK LINKS SECTION -->
                <div class="quick-links">
                    <!-- VIEW ALL STUDENTS LINK -->
                    <a href="students.php" class="link-btn">
                        <i class="fas fa-users"></i>
                        View All Students
                    </a>
                    <!-- REPORTS ARCHIVE LINK -->
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
                
                // CLOSE OTHER SUBMENUS WHEN OPENING A NEW ONE
                document.querySelectorAll('.submenu').forEach(menu => {
                    if (menu.id !== targetId && menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                        if (otherBtn) {
                            otherBtn.querySelector('.arrow').classList.remove('rotate');
                        }
                    }
                });
                
                // TOGGLE CURRENT SUBMENU VISIBILITY
                submenu.classList.toggle('show');
                arrow.classList.toggle('rotate');
            });
        });

        // QUICK ACTION BUTTONS FUNCTIONALITY (CURRENTLY SHOWS ALERTS)
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); // PREVENT DEFAULT LINK BEHAVIOR
                const actionText = this.querySelector('span').textContent;
                alert(`Redirecting to: ${actionText}`);
                // UNCOMMENT BELOW FOR ACTUAL NAVIGATION:
                // window.location.href = this.href;
            });
        });

        // QUICK LINKS FUNCTIONALITY (CURRENTLY SHOWS ALERTS)
        document.querySelectorAll('.link-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); // PREVENT DEFAULT LINK BEHAVIOR
                const linkText = this.textContent.trim();
                alert(`Navigating to: ${linkText}`);
                // UNCOMMENT BELOW FOR ACTUAL NAVIGATION:
                // window.location.href = this.href;
            });
        });
    </script>
</body>
</html>

<!-- 
THE PHP CODE ONLY HANDLES SESSION VERIFICATION AT THE BEGINNING.
-->