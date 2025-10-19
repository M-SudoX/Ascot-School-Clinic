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

        .action-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .action-type.create {
            background-color: #e6f7ee;
            color: #0c6;
        }

        .action-type.update {
            background-color: #fff4e6;
            color: #f90;
        }

        .action-type.delete {
            background-color: #ffe6e6;
            color: #f00;
        }

        .action-type.view {
            background-color: #e6f3ff;
            color: #06c;
        }

        .action-type.login {
            background-color: #f0e6ff;
            color: #90f;
        }

        .action-type.logout {
            background-color: #f5f5f5;
            color: #666;
        }

        .action-type.cancel {
            background-color: #fff0f0;
            color: #c00;
        }

        .export-btn {
            background-color: #667eea;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            background-color: #5a6fd8;
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

            .search-container {
                flex-direction: column;
            }

            .search-input {
                min-width: 100%;
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
                    <div class="submenu show" id="adminMenu">
                        <a href="users_logs.php" class="submenu-item active">
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
                        <a href="#" class="submenu-item">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="#" class="submenu-item">
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
                    <h2>User Activity Logs</h2>
                </div>
                
                <!-- Search Bar -->
                <div class="search-container">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search by name, user ID, or action...">
                    <button class="search-btn" id="searchBtn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <button class="clear-btn" id="clearBtn">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
                
                <div class="table-container">
                    <table id="userLogsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>User Type</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Table will be empty initially -->
                            <tr>
                                <td colspan="7" class="no-results">No user logs available</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <button class="export-btn">Export to CSV</button>
                
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">
                        Showing 0 of 0 entries
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="prevBtn">Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">4</button>
                        <button class="pagination-btn">5</button>
                        <button class="pagination-btn" id="nextBtn">Next</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Empty data for user logs - ready to be populated from backend
        const userLogsData = [];

        // DOM elements
        const tableBody = document.getElementById('tableBody');
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const clearBtn = document.getElementById('clearBtn');
        const paginationInfo = document.getElementById('paginationInfo');

        // Action type mapping
        const actionTypes = {
            'create': { text: 'Create', class: 'create' },
            'update': { text: 'Update', class: 'update' },
            'delete': { text: 'Delete', class: 'delete' },
            'view': { text: 'View', class: 'view' },
            'login': { text: 'Login', class: 'login' },
            'logout': { text: 'Logout', class: 'logout' },
            'cancel': { text: 'Cancel', class: 'cancel' },
            'change_password': { text: 'Change Password', class: 'update' }
        };

        // Initialize the table with empty data
        function initializeTable() {
            renderTable(userLogsData);
            updatePaginationInfo(userLogsData.length, userLogsData.length);
        }

        // Render table with provided data
        function renderTable(data) {
            tableBody.innerHTML = '';
            
            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="no-results">No user logs available</td></tr>';
                return;
            }
            
            data.forEach(log => {
                const row = document.createElement('tr');
                const actionInfo = actionTypes[log.action] || { text: log.action, class: 'view' };
                
                row.innerHTML = `
                    <td>${log.id}</td>
                    <td>${log.date}</td>
                    <td>${log.userId}</td>
                    <td>${log.name}</td>
                    <td><span class="user-type ${log.userType}">${log.userType.charAt(0).toUpperCase() + log.userType.slice(1)}</span></td>
                    <td><span class="action-type ${actionInfo.class}">${actionInfo.text}</span></td>
                    <td>${log.details}</td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Search function
        function searchLogs() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            
            if (searchTerm === '') {
                renderTable(userLogsData);
                updatePaginationInfo(userLogsData.length, userLogsData.length);
                return;
            }
            
            const filteredData = userLogsData.filter(log => 
                log.name.toLowerCase().includes(searchTerm) || 
                log.userId.toLowerCase().includes(searchTerm) ||
                log.details.toLowerCase().includes(searchTerm) ||
                (actionTypes[log.action] && actionTypes[log.action].text.toLowerCase().includes(searchTerm)) ||
                log.userType.toLowerCase().includes(searchTerm)
            );
            
            renderTable(filteredData);
            updatePaginationInfo(filteredData.length, userLogsData.length);
        }

        // Update pagination info
        function updatePaginationInfo(displayed, total) {
            paginationInfo.textContent = `Showing ${displayed} of ${total} entries`;
        }

        // Clear search
        function clearSearch() {
            searchInput.value = '';
            renderTable(userLogsData);
            updatePaginationInfo(userLogsData.length, userLogsData.length);
        }

        // Event listeners
        searchBtn.addEventListener('click', searchLogs);
        clearBtn.addEventListener('click', clearSearch);

        // Search on Enter key
        searchInput.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') {
                searchLogs();
            }
        });

        // Initialize the table when page loads
        document.addEventListener('DOMContentLoaded', initializeTable);

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

        // Export button functionality
        document.querySelector('.export-btn').addEventListener('click', function() {
            alert('Exporting user logs to CSV file...');
        });

        // Function to add a new log entry (for backend integration)
        function addLogEntry(logData) {
            userLogsData.push(logData);
            renderTable(userLogsData);
            updatePaginationInfo(userLogsData.length, userLogsData.length);
        }

        // Example of how to add a log entry (for testing)
        // addLogEntry({
        //     id: 1,
        //     date: "2025-01-15 10:30:45",
        //     userId: "22-01-0087",
        //     name: "Maria Janell De Padua",
        //     userType: "student",
        //     action: "login",
        //     details: "Logged into the system"
        // });
    </script>
</body>
</html>