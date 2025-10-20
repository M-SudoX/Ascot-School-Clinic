<?php
session_start();
require '../includes/db_connect.php';

// ✅ Only logged-in admin can access
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// ✅ FETCH APPROVED CONSULTATION REQUESTS
$query = "
SELECT 
    c.id,
    u.fullname AS student,
    u.student_number AS studentId,
    u.email AS contact,
    c.requested AS purpose,
    DATE_FORMAT(c.date, '%e') AS day,
    DATE_FORMAT(c.date, '%c') AS month,
    DATE_FORMAT(c.date, '%Y') AS year,
    TIME_FORMAT(c.time, '%h:%i %p') AS time,
    c.status
FROM consultation_requests c
LEFT JOIN users u ON c.student_id = u.id
WHERE c.status IN ('Approved', 'Rescheduled')
ORDER BY c.date, c.time ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar View - ASCOT Clinic</title>

    <!-- BOOTSTRAP / FONT AWESOME -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
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

        /* Header Styles - FIXED */
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

        /* Mobile Menu Toggle - FIXED */
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
        }

        /* Dashboard Container - FIXED */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        /* Sidebar Styles - FIXED */
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
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
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
            font-size: 0.9rem;
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

        /* Main Content - FIXED */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            background: linear-gradient(135deg, #ffda6a 0%, #764ba2 100%);
            min-height: calc(100vh - 100px);
            margin-left: 280px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - FIXED */
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

        /* SIMPLIFIED CALENDAR CONTAINER */
        .calendar-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            max-width: 1400px;
            width: 95%;
        }
        
        /* SIMPLIFIED CALENDAR HEADER */
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendar-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .calendar-header h3 i {
            font-size: 1.7rem;
        }
        
        /* SIMPLIFIED CALENDAR CONTROLS */
        .calendar-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .calendar-controls button {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
        }
        
        #monthDisplay {
            font-size: 1.3rem;
            font-weight: 600;
            min-width: 180px;
            text-align: center;
            color: white;
        }

        /* SIMPLIFIED CALENDAR GRID */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        /* SIMPLIFIED DAY HEADERS */
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            color: #667eea;
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 15px 0;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 5px;
            border: 1px solid #e9ecef;
        }

        /* SIMPLIFIED DAY CELLS - NO HOVER */
        .calendar-day {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            min-height: 100px;
            padding: 12px;
            position: relative;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        /* TODAY INDICATOR - SIMPLE */
        .calendar-day.today {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #764ba2;
        }
        
        /* EMPTY DAYS */
        .calendar-day.text-muted {
            opacity: 0.3;
            cursor: default;
            pointer-events: none;
            background: #f8f9fa;
        }
        
        /* DAY NUMBER */
        .calendar-day > div:first-child {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: inherit;
        }

        /* SIMPLIFIED APPOINTMENT BADGE */
        .appointment-count {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 25px;
            border: none;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-header .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-body h5 {
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
            font-size: 1.2rem;
        }

        /* Table Styles */
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .appointments-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .appointments-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .appointments-table td {
            padding: 12px 15px;
            font-size: 0.9rem;
            color: #4b5563;
            border-bottom: 1px solid #e9ecef;
        }

        .appointments-table .time-cell {
            font-weight: 600;
            color: #764ba2;
        }

        .appointments-table .purpose-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* No Appointments Message */
        .no-appointments {
            text-align: center;
            color: #6c757d;
            padding: 40px 20px;
            font-size: 1.1rem;
        }
        
        .no-appointments i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
            display: block;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 1200px) {
            .calendar-container {
                padding: 25px;
            }
            
            .calendar-header {
                padding: 18px 25px;
            }
            
            .calendar-header h3 {
                font-size: 1.4rem;
            }
            
            #monthDisplay {
                font-size: 1.2rem;
            }
            
            .calendar-grid {
                gap: 8px;
            }
            
            .calendar-day {
                min-height: 90px;
                padding: 10px;
            }
        }
        
        @media (max-width: 992px) {
            .calendar-container {
                padding: 20px;
                width: 98%;
            }
            
            .calendar-header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .calendar-header h3 {
                font-size: 1.3rem;
            }
            
            .calendar-controls {
                width: 100%;
                justify-content: center;
            }
            
            #monthDisplay {
                font-size: 1.1rem;
                min-width: 150px;
            }
            
            .calendar-grid {
                gap: 6px;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 8px;
            }
            
            .calendar-day > div:first-child {
                font-size: 1.1rem;
            }
            
            .appointment-count {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
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

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 1rem;
                width: 100%;
                margin-left: 0;
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

            .calendar-container {
                padding: 15px;
                border-radius: 15px;
            }
            
            .calendar-header {
                padding: 12px 15px;
                margin-bottom: 20px;
            }
            
            .calendar-header h3 {
                font-size: 1.1rem;
                gap: 8px;
            }
            
            .calendar-header h3 i {
                font-size: 1.2rem;
            }
            
            .calendar-controls button {
                width: 35px;
                height: 35px;
            }
            
            #monthDisplay {
                font-size: 1rem;
                min-width: 130px;
            }
            
            .calendar-day-header {
                font-size: 0.75rem;
                padding: 12px 0;
            }
            
            .calendar-grid {
                gap: 4px;
            }
            
            .calendar-day {
                min-height: 70px;
                padding: 6px;
            }
            
            .calendar-day > div:first-child {
                font-size: 1rem;
            }
            
            .appointment-count {
                width: 22px;
                height: 22px;
                font-size: 0.7rem;
                top: 6px;
                right: 6px;
            }

            /* Responsive table for mobile */
            .appointments-table-container {
                overflow-x: auto;
            }

            .appointments-table {
                min-width: 600px;
            }
        }
        
        @media (max-width: 576px) {
            .calendar-container {
                padding: 12px;
            }
            
            .calendar-header {
                padding: 10px 12px;
                border-radius: 12px;
            }
            
            .calendar-header h3 {
                font-size: 1rem;
            }
            
            .calendar-controls {
                gap: 10px;
            }
            
            .calendar-controls button {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            #monthDisplay {
                font-size: 0.9rem;
                min-width: 110px;
            }
            
            .calendar-day-header {
                font-size: 0.7rem;
                padding: 10px 0;
            }
            
            .calendar-grid {
                gap: 3px;
            }
            
            .calendar-day {
                min-height: 60px;
                padding: 5px;
                border-radius: 8px;
            }
            
            .calendar-day > div:first-child {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 400px) {
            .calendar-container {
                padding: 10px;
            }
            
            .calendar-header {
                padding: 8px 10px;
            }
            
            .calendar-header h3 {
                font-size: 0.9rem;
            }
            
            .calendar-controls button {
                width: 30px;
                height: 30px;
            }
            
            #monthDisplay {
                font-size: 0.85rem;
                min-width: 100px;
            }
            
            .calendar-day {
                min-height: 55px;
                padding: 4px;
            }
            
            .calendar-day > div:first-child {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - FIXED -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - FIXED -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- HEADER - FIXED -->
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

    <!-- DASHBOARD CONTAINER - FIXED -->
    <div class="dashboard-container">
        <!-- SIDEBAR - FIXED -->
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
                    <button class="nav-item dropdown-btn active" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="appointmentsMenu">
                        <a href="calendar_view.php" class="submenu-item active">
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="calendar-container">
                <div class="calendar-header">
                    <h3><i class="fas fa-calendar-alt"></i> Appointment Calendar</h3>
                    <div class="calendar-controls">
                        <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                        <span id="monthDisplay"></span>
                        <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>

                <div class="calendar-grid">
                    <div class="calendar-day-header">Mon</div>
                    <div class="calendar-day-header">Tue</div>
                    <div class="calendar-day-header">Wed</div>
                    <div class="calendar-day-header">Thu</div>
                    <div class="calendar-day-header">Fri</div>
                    <div class="calendar-day-header">Sat</div>
                    <div class="calendar-day-header">Sun</div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL -->
    <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Appointments</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="appointmentsList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // MOBILE MENU FUNCTIONALITY - FIXED
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

        // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS - FIXED
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

        // CALENDAR FUNCTIONALITY
        const appointments = <?php echo json_encode($appointments); ?>;
        const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        function renderCalendar(month, year) {
            const grid = document.querySelector('.calendar-grid');
            while (grid.children.length > 7) grid.removeChild(grid.lastChild);

            document.getElementById('monthDisplay').textContent = `${monthNames[month]} ${year}`;
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const adjustedFirst = firstDay === 0 ? 6 : firstDay - 1;
            const today = new Date();

            for (let i = 0; i < adjustedFirst; i++) {
                const d = document.createElement('div');
                d.className = 'calendar-day text-muted';
                grid.appendChild(d);
            }

            for (let i = 1; i <= daysInMonth; i++) {
                const d = document.createElement('div');
                d.className = 'calendar-day';
                if (month === today.getMonth() && year === today.getFullYear() && i === today.getDate()) {
                    d.classList.add('today');
                }
                d.innerHTML = `<div>${i}</div>`;

                const dayApps = appointments.filter(a => a.day == i && a.month == month + 1 && a.year == year);
                if (dayApps.length > 0) {
                    const badge = document.createElement('div');
                    badge.className = 'appointment-count';
                    badge.textContent = dayApps.length;
                    d.appendChild(badge);
                    d.addEventListener('click', () => showAppointments(dayApps, i, month, year));
                }
                grid.appendChild(d);
            }
        }

        function showAppointments(dayApps, day, month, year) {
            const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
            const list = document.getElementById('appointmentsList');
            
            list.innerHTML = `
                <h5>${monthNames[month]} ${day}, ${year}</h5>
                <div class="appointments-table-container">
                    ${dayApps.length === 0 ? 
                        `<div class="no-appointments">
                            <i class="fas fa-calendar-times"></i><br>
                            No appointments scheduled for this day
                        </div>` : 
                        `<table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Time</th>
                                    <th>Purpose</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${dayApps.map(app => `
                                    <tr>
                                        <td>${app.student}</td>
                                        <td>${app.studentId}</td>
                                        <td class="time-cell">${app.time}</td>
                                        <td class="purpose-cell">${app.purpose}</td>
                                        <td>${app.contact}</td>
                                        <td>
                                            <span class="badge ${app.status === 'Approved' ? 'bg-success' : 'bg-warning'}">
                                                ${app.status}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>`
                    }
                </div>
            `;
            modal.show();
        }

        document.getElementById('prevMonth').onclick = () => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(currentMonth, currentYear); };
        document.getElementById('nextMonth').onclick = () => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(currentMonth, currentYear); };

        renderCalendar(currentMonth, currentYear);
    </script>
</body>
</html>