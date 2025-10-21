<?php
session_start();
include 'admin_logger.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Log admin access
logAdminAction($_SESSION['admin_name'] ?? 'Admin User', 'Accessed Monthly Summary Report');

// Get selected month and year from GET parameters, default to current month
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month range
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('n');
}

// Sample data - in a real application, this would come from your database
// Based on the selected month and year
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$month = $month_names[$selected_month] . ' ' . $selected_year;

// Sample data that would typically come from database queries
$monthly_data = [
    // January 2025
    '1_2025' => [
        'total_consultations' => 28,
        'departments' => [
            "Information Technology" => 8,
            "Forestry" => 20
        ],
        'diagnostics' => [
            "Flu" => 12,
            "Allergies" => 9,
            "Migraine" => 7
        ],
        'year_levels' => [
            "1st Year" => 25,
            "2nd Year" => 3
        ]
    ],
    // February 2025
    '2_2025' => [
        'total_consultations' => 32,
        'departments' => [
            "Information Technology" => 10,
            "Forestry" => 22
        ],
        'diagnostics' => [
            "Flu" => 14,
            "Allergies" => 11,
            "Migraine" => 7
        ],
        'year_levels' => [
            "1st Year" => 28,
            "2nd Year" => 4
        ]
    ],
    // March 2025
    '3_2025' => [
        'total_consultations' => 30,
        'departments' => [
            "Information Technology" => 9,
            "Forestry" => 21
        ],
        'diagnostics' => [
            "Flu" => 13,
            "Allergies" => 10,
            "Migraine" => 7
        ],
        'year_levels' => [
            "1st Year" => 26,
            "2nd Year" => 4
        ]
    ],
    // April 2025
    '4_2025' => [
        'total_consultations' => 35,
        'departments' => [
            "Information Technology" => 12,
            "Forestry" => 23
        ],
        'diagnostics' => [
            "Flu" => 16,
            "Allergies" => 12,
            "Migraine" => 7
        ],
        'year_levels' => [
            "1st Year" => 30,
            "2nd Year" => 5
        ]
    ],
    // May 2025
    '5_2025' => [
        'total_consultations' => 34,
        'departments' => [
            "Information Technology" => 11,
            "Forestry" => 23
        ],
        'diagnostics' => [
            "Flu" => 15,
            "Allergies" => 10,
            "Migraine" => 9
        ],
        'year_levels' => [
            "1st Year" => 31,
            "2nd Year" => 3
        ]
    ],
    // June 2025
    '6_2025' => [
        'total_consultations' => 29,
        'departments' => [
            "Information Technology" => 10,
            "Forestry" => 19
        ],
        'diagnostics' => [
            "Flu" => 11,
            "Allergies" => 10,
            "Migraine" => 8
        ],
        'year_levels' => [
            "1st Year" => 24,
            "2nd Year" => 5
        ]
    ],
    // July 2025
    '7_2025' => [
        'total_consultations' => 38,
        'departments' => [
            "Information Technology" => 14,
            "Forestry" => 24
        ],
        'diagnostics' => [
            "Flu" => 17,
            "Allergies" => 13,
            "Migraine" => 8
        ],
        'year_levels' => [
            "1st Year" => 33,
            "2nd Year" => 5
        ]
    ],
    // August 2025
    '8_2025' => [
        'total_consultations' => 36,
        'departments' => [
            "Information Technology" => 13,
            "Forestry" => 23
        ],
        'diagnostics' => [
            "Flu" => 16,
            "Allergies" => 12,
            "Migraine" => 8
        ],
        'year_levels' => [
            "1st Year" => 32,
            "2nd Year" => 4
        ]
    ],
    // September 2025
    '9_2025' => [
        'total_consultations' => 31,
        'departments' => [
            "Information Technology" => 10,
            "Forestry" => 21
        ],
        'diagnostics' => [
            "Flu" => 13,
            "Allergies" => 11,
            "Migraine" => 7
        ],
        'year_levels' => [
            "1st Year" => 27,
            "2nd Year" => 4
        ]
    ],
    // October 2025
    '10_2025' => [
        'total_consultations' => 33,
        'departments' => [
            "Information Technology" => 11,
            "Forestry" => 22
        ],
        'diagnostics' => [
            "Flu" => 14,
            "Allergies" => 12,
            "Migraine" => 7
        ],
        'year_levels' => [
            "1st Year" => 29,
            "2nd Year" => 4
        ]
    ],
    // November 2025
    '11_2025' => [
        'total_consultations' => 37,
        'departments' => [
            "Information Technology" => 13,
            "Forestry" => 24
        ],
        'diagnostics' => [
            "Flu" => 16,
            "Allergies" => 13,
            "Migraine" => 8
        ],
        'year_levels' => [
            "1st Year" => 32,
            "2nd Year" => 5
        ]
    ],
    // December 2025
    '12_2025' => [
        'total_consultations' => 27,
        'departments' => [
            "Information Technology" => 9,
            "Forestry" => 18
        ],
        'diagnostics' => [
            "Flu" => 10,
            "Allergies" => 9,
            "Migraine" => 8
        ],
        'year_levels' => [
            "1st Year" => 23,
            "2nd Year" => 4
        ]
    ]
];

// Get data for selected month or use default if not available
$data_key = $selected_month . '_' . $selected_year;
if (isset($monthly_data[$data_key])) {
    $data = $monthly_data[$data_key];
} else {
    // Default data if month not in sample data
    $data = [
        'total_consultations' => 30,
        'departments' => [
            "Information Technology" => 10,
            "Forestry" => 20
        ],
        'diagnostics' => [
            "Flu" => 12,
            "Allergies" => 10,
            "Migraine" => 8
        ],
        'year_levels' => [
            "1st Year" => 25,
            "2nd Year" => 5
        ]
    ];
}

$total_consultations = $data['total_consultations'];
$departments = $data['departments'];
$diagnostics = $data['diagnostics'];
$year_levels = $data['year_levels'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Summary - ASCOT Clinic</title>
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
            padding-top: 100px;
        }

        /* Header Styles */
        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 100px;
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
            position: fixed;
            top: 100px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 999;
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
            font-weight: 600;
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
            margin-left: 280px;
            margin-top: 0;
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

        /* Monthly Summary Styles */
        .monthly-summary {
            max-width: 900px;
            margin: 0 auto;
        }

        .month-selector {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .month-btn {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #495057;
        }

        .month-btn:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .month-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .year-selector {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .year-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s ease;
        }

        .year-btn:hover {
            transform: scale(1.2);
        }

        .current-year {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a3a5f;
            min-width: 80px;
            text-align: center;
        }

        .summary-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #1a3a5f;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e9ecef, transparent);
            margin: 2rem 0;
        }

        .report-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-report {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-report:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-report.secondary {
            background: #6c757d;
        }

        .btn-report.secondary:hover {
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
                top: 100px;
                height: calc(100vh - 100px);
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 100px;
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
                margin-left: 0;
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

            .report-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn-report {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }

            .month-selector {
                gap: 0.25rem;
            }

            .month-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 1rem;
            }

            .notification-menu {
                width: 250px;
                right: -20px;
            }

            .summary-title {
                font-size: 1.25rem;
            }

            .month-selector {
                gap: 0.2rem;
            }

            .month-btn {
                padding: 0.35rem 0.7rem;
                font-size: 0.8rem;
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
                    <div class="submenu show" id="reportsMenu">
                        <a href="#" class="submenu-item active">
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

            <div class="content">
                <div class="content-header">
                    <h2><i class="fas fa-file-invoice me-2"></i>Monthly Summary Report</h2>
                </div>

                <div class="monthly-summary">
                    <!-- Year Selector -->
                    <div class="year-selector">
                        <button class
                        ="year-btn" onclick="changeYear(<?php echo $selected_year - 1; ?>)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="current-year"><?php echo $selected_year; ?></div>
                        <button class="year-btn" onclick="changeYear(<?php echo $selected_year + 1; ?>)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Month Selector -->
                    <div class="month-selector">
                        <?php 
                        $month_abbr = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
                        for ($i = 1; $i <= 12; $i++): 
                            $is_active = ($i == $selected_month) ? 'active' : '';
                        ?>
                            <button class="month-btn <?php echo $is_active; ?>" 
                                    onclick="selectMonth(<?php echo $i; ?>, <?php echo $selected_year; ?>)">
                                <?php echo $month_abbr[$i-1]; ?>
                            </button>
                        <?php endfor; ?>
                    </div>

                    <!-- Bar Chart - ALL 12 MONTHS -->
                    <div style="background: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                        <div style="display: flex; align-items: flex-end; justify-content: center; gap: 1rem; height: 200px; margin-bottom: 1rem; overflow-x: auto; padding: 0 1rem;">
                            <?php 
                            // Get all 12 months data for the selected year
                            $all_months_data = [];
                            for ($m = 1; $m <= 12; $m++) {
                                $key = $m . '_' . $selected_year;
                                if (isset($monthly_data[$key])) {
                                    $all_months_data[$m] = $monthly_data[$key]['total_consultations'];
                                } else {
                                    $all_months_data[$m] = 30; // default value
                                }
                            }
                            
                            $max = max($all_months_data);
                            
                            // Display ALL 12 months
                            for ($m = 1; $m <= 12; $m++): 
                                $height = ($all_months_data[$m] / $max) * 150;
                                $is_selected = ($m == $selected_month) ? 'box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);' : '';
                            ?>
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; min-width: 50px;">
                                <div style="width: 50px; height: <?php echo $height; ?>px; background: #f4d35e; border-radius: 4px 4px 0 0; <?php echo $is_selected; ?>"></div>
                                <span style="font-size: 0.7rem; font-weight: 600; color: <?php echo ($m == $selected_month) ? '#667eea' : '#333'; ?>;"><?php echo strtoupper(substr($month_names[$m], 0, 3)); ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <h2 class="summary-title">MONTHLY SUMMARY (<?php echo strtoupper($month); ?>)</h2>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; background: #f8f9fa; padding: 2rem; border-radius: 10px;">
                        <div>
                            <p style="margin-bottom: 0.5rem;"><strong>Total Consultation:</strong> <?php echo $total_consultations; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Top Diagnoses:</strong></p>
                            <?php foreach($diagnostics as $diagnostic => $count): ?>
                            <p style="margin-left: 1rem; margin-bottom: 0.25rem;"><?php echo $diagnostic; ?></p>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <p style="margin-bottom: 0.5rem;"><strong>BY DEPARTMENT</strong></p>
                            <?php foreach($departments as $dept => $count): ?>
                            <p style="margin-left: 1rem; margin-bottom: 0.25rem;"><?php echo $dept; ?>: <?php echo $count; ?></p>
                            <?php endforeach; ?>
                            
                            <p style="margin-bottom: 0.5rem; margin-top: 1rem;"><strong>BY YEAR LEVEL</strong></p>
                            <?php foreach($year_levels as $level => $count): ?>
                            <p style="margin-left: 1rem; margin-bottom: 0.25rem;"><?php echo $level; ?>: <?php echo $count; ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="report-actions">
                        <button class="btn-report" onclick="printReport()">
                            <i class="fas fa-print"></i>
                            Print Report
                        </button>
                        <button class="btn-report secondary" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i>
                            Export to PDF
                        </button>
                        <button class="btn-report secondary" onclick="goBack()">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </button>
                    </div>
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

        // Month and Year selection functions
        function selectMonth(month, year) {
            window.location.href = `?month=${month}&year=${year}`;
        }

        function changeYear(year) {
            window.location.href = `?month=<?php echo $selected_month; ?>&year=${year}`;
        }

        // Report functions
        function printReport() {
            window.print();
        }

        function exportToPDF() {
            alert('PDF export functionality would be implemented here.');
            // In a real application, you would use a library like jsPDF
            // or make an API call to generate a PDF
        }

        function goBack() {
            window.location.href = 'admin_dashboard.php';
        }
    </script>
</body>
</html>