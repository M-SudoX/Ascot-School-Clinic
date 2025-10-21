<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Announcement - ASCOT Clinic</title>
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
            margin-bottom: 30px;
        }

        .content-header h2 {
            color: #1a3a5f;
            font-size: 24px;
        }

        /* Form Styles */
        .announcement-form {
            max-width: 800px;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .form-section-title {
            font-size: 1.1rem;
            color: #1a3a5f;
            margin-bottom: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section-title i {
            color: #667eea;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .radio-option label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .checkbox-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .checkbox-option label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
        }

        .file-upload-input::file-selector-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 1rem;
            transition: background 0.3s ease;
        }

        .file-upload-input::file-selector-button:hover {
            background: #5a6fd8;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Email Preview Styles */
        .email-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0;
            margin-top: 10px;
            display: none;
            overflow: hidden;
        }

        .email-preview.active {
            display: block;
        }

        .preview-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            text-align: center;
            margin: 0;
        }

        .preview-content {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .recipient-count {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            border-left: 4px solid #667eea;
        }

        .email-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        /* Student Selection Styles */
        .student-selection {
            display: none;
            margin-top: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .student-selection.active {
            display: block;
        }
        
        .student-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background: white;
            margin-top: 10px;
        }
        
        .student-checkbox {
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }
        
        .student-checkbox:hover {
            background-color: #f8f9fa;
        }
        
        .student-checkbox:last-child {
            border-bottom: none;
        }
        
        .student-checkbox label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        
        .select-all {
            background: #e9ecef;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selected-count {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .student-name {
            font-weight: 600;
            color: #1a3a5f;
        }
        
        .student-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Time Picker Styles */
        .expiry-time-container {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .expiry-date-group, .expiry-time-group {
            flex: 1;
        }

        .time-presets {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .time-preset-btn {
            padding: 4px 8px;
            font-size: 0.8rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .time-preset-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .expiry-preview {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #667eea;
            font-size: 0.9rem;
        }

        .expiry-preview .label {
            font-weight: 600;
            color: #1a3a5f;
        }

        .expiry-preview .value {
            color: #495057;
        }

        .expiry-preview .time-remaining {
            color: #e67e22;
            font-weight: 500;
            margin-top: 5px;
        }

        /* Student Search */
        .student-search {
            margin-bottom: 15px;
        }

        /* Email Recipient Selection */
        .email-recipient-selection {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }

        .email-recipient-selection.active {
            display: block;
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

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .content-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .student-list {
                max-height: 200px;
            }

            .expiry-time-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .expiry-date-group, .expiry-time-group {
                width: 100%;
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

            .radio-group, .checkbox-group {
                gap: 0.75rem;
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
                        <a href="new_announcement.php" class="submenu-item active">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="announcement_history.php" class="submenu-item">
                            <i class="fas fa-history"></i>
                            History
                        </a>
                    </div>
                </div>
                
                <a href="../logout.php" class="nav-item logout">
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
                    <h2>Create Announcement</h2>
                    <button type="button" class="btn btn-outline-primary" id="previewEmailBtn">
                        <i class="fas fa-eye"></i> Preview Email
                    </button>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 2rem;">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom: 2rem;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form class="announcement-form" id="announcementForm" method="POST" enctype="multipart/form-data">
                    <!-- Sent By Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-user"></i>
                            Sent By
                        </h3>
                        <div class="form-group">
                            <select class="form-select" id="sentBy" name="sentBy" required>
                                <option value="">Select sender...</option>
                                <option value="Admin User">Admin User</option>
                                <option value="Dr. James Smith">Dr. James Smith</option>
                                <option value="Nurse Maria Santos">Nurse Maria Santos</option>
                                <option value="Clinic Administrator">Clinic Administrator</option>
                            </select>
                        </div>
                    </div>

                    <!-- Type of Announcement Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-bullhorn"></i>
                            Type of Announcement
                        </h3>
                        <div class="checkbox-group">
                            <div class="checkbox-option">
                                <input type="checkbox" id="sendEmail" name="announcementType[]" value="email">
                                <label for="sendEmail">Send an Email</label>
                            </div>
                            <div class="checkbox-option">
                                <input type="checkbox" id="postFront" name="announcementType[]" value="front" checked>
                                <label for="postFront">Post on Front Page</label>
                            </div>
                        </div>
                        
                        <!-- Email Recipient Selection (Only shows when Send Email is checked) -->
                        <div class="email-recipient-selection" id="emailRecipientSelection">
                            <h4 style="color: #1a3a5f; margin-bottom: 15px; font-size: 1rem;">
                                <i class="fas fa-users me-2"></i>Email Recipients
                            </h4>
                            
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" id="emailAllStudents" name="emailRecipientType" value="all" checked>
                                    <label for="emailAllStudents">All Students</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" id="emailSpecificStudents" name="emailRecipientType" value="specific">
                                    <label for="emailSpecificStudents">Specific Students</label>
                                </div>
                            </div>
                            
                            <!-- Specific Students Selection for Email -->
                            <div class="student-selection" id="emailSpecificStudentsSelection">
                                <div class="student-search">
                                    <input type="text" class="form-control" id="emailStudentSearch" placeholder="Search students by name, ID, or course...">
                                </div>
                                
                                <div class="select-all">
                                    <input type="checkbox" id="selectEmailAllStudents">
                                    <label for="selectEmailAllStudents">Select All Students</label>
                                    <span class="selected-count" id="emailSelectedCount">0 selected</span>
                                </div>
                                
                                <div class="student-list">
                                    <?php if (!empty($students)): ?>
                                        <?php foreach ($students as $student): ?>
                                            <div class="student-checkbox">
                                                <input type="checkbox" name="emailSpecificStudents[]" value="<?php echo $student['id']; ?>" 
                                                       id="email_student_<?php echo $student['id']; ?>" class="email-student-checkbox-input">
                                                <label for="email_student_<?php echo $student['id']; ?>">
                                                    <div class="student-info">
                                                        <span class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></span>
                                                        <span class="student-details">
                                                            <?php echo htmlspecialchars($student['student_number']); ?> - 
                                                            <?php echo htmlspecialchars($student['course_year']); ?>
                                                            <?php if (!empty($student['email'])): ?>
                                                                <br><small class="text-success">✓ Has email: <?php echo htmlspecialchars($student['email']); ?></small>
                                                            <?php else: ?>
                                                                <br><small class="text-danger">✗ No email address</small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            No students found in the database. 
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="recipient-count" id="emailRecipientCount" style="margin-top: 15px;">
                                <i class="fas fa-users me-2"></i>Email recipients: 0 students
                            </div>
                        </div>
                        
                        <div class="email-warning" id="emailWarning" style="display: none; margin-top: 15px;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Sending emails may take several minutes depending on the number of recipients.
                        </div>
                    </div>

                    <!-- Subject Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-heading"></i>
                            Subject
                        </h3>
                        <div class="form-group">
                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Enter announcement subject..." required>
                        </div>
                    </div>

                    <!-- Content Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-file-alt"></i>
                            Content
                        </h3>
                        <div class="form-group">
                            <textarea class="form-control" id="content" name="content" placeholder="Enter announcement content..." required rows="6"></textarea>
                        </div>
                    </div>

                    <!-- Expiry Date & Time Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-clock"></i>
                            Expiry Settings
                        </h3>
                        <div class="form-group">
                            <label class="form-label">Announcement Expiry Date & Time (Optional)</label>
                            
                            <div class="expiry-time-container">
                                <div class="expiry-date-group">
                                    <label class="form-label small">Date</label>
                                    <input type="date" class="form-control" id="expiryDate" name="expiryDate" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="expiry-time-group">
                                    <label class="form-label small">Time</label>
                                    <input type="time" class="form-control" id="expiryTime" name="expiryTime" value="23:59">
                                    
                                    <div class="time-presets">
                                        <button type="button" class="time-preset-btn" data-time="08:00">8:00 AM</button>
                                        <button type="button" class="time-preset-btn" data-time="12:00">12:00 PM</button>
                                        <button type="button" class="time-preset-btn" data-time="17:00">5:00 PM</button>
                                        <button type="button" class="time-preset-btn" data-time="23:59">End of Day</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="expiry-preview" id="expiryPreview" style="display: none;">
                                <div>
                                    <span class="label">Expires on:</span>
                                    <span class="value" id="expiryPreviewText"></span>
                                </div>
                                <div class="time-remaining" id="timeRemaining"></div>
                            </div>
                            
                            <small class="form-text text-muted">
                                Leave empty if announcement should not expire. Minimum date is today.
                            </small>
                        </div>
                    </div>

                    <!-- File Upload Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-paperclip"></i>
                            Upload File or Image/Video
                        </h3>
                        <div class="form-group">
                            <input type="file" class="file-upload-input" id="attachment" name="attachment" 
                                   accept="image/*,video/*,.pdf,.doc,.docx">
                            <small class="form-text text-muted">Accepted formats: Images (JPG, PNG, GIF, WEBP), Videos (MP4, AVI, MOV, WMV, WEBM), Documents (PDF, DOC, DOCX). Max size: 10MB</small>
                        </div>
                    </div>

                    <!-- Email Preview -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-envelope"></i>
                            Email Preview
                        </h3>
                        <div class="email-preview" id="emailPreview">
                            <div class="preview-header">
                                <h4 class="mb-1">Email Preview</h4>
                                <small class="opacity-75">This is how your email will look to recipients</small>
                            </div>
                            <div class="preview-content" id="previewContent">
                                <p class="text-muted">Fill out the subject and content to see preview...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelBtn">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i> Send Announcement
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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

        // NEW FUNCTIONS FOR EMAIL RECIPIENT SELECTION
        // Toggle email recipient selection based on send email checkbox
        function toggleEmailRecipientSelection() {
            const sendEmailCheckbox = document.getElementById('sendEmail');
            const emailRecipientSelection = document.getElementById('emailRecipientSelection');
            const emailWarning = document.getElementById('emailWarning');
            
            if (sendEmailCheckbox.checked) {
                emailRecipientSelection.classList.add('active');
                emailWarning.style.display = 'block';
                updateEmailRecipientCount();
                toggleEmailSpecificStudentSelection();
            } else {
                emailRecipientSelection.classList.remove('active');
                emailWarning.style.display = 'none';
            }
        }

        // Toggle specific student selection for email
        function toggleEmailSpecificStudentSelection() {
            const emailRecipientType = document.querySelector('input[name="emailRecipientType"]:checked').value;
            const emailSpecificSelection = document.getElementById('emailSpecificStudentsSelection');
            
            if (emailRecipientType === 'specific') {
                emailSpecificSelection.classList.add('active');
                updateEmailSelectedCount();
                updateEmailRecipientCount();
            } else {
                emailSpecificSelection.classList.remove('active');
                updateEmailRecipientCount();
            }
        }

        // Update email selected count
        function updateEmailSelectedCount() {
            const selectedCount = document.querySelectorAll('.email-student-checkbox-input:checked').length;
            document.getElementById('emailSelectedCount').textContent = selectedCount + ' selected';
            
            const totalStudents = document.querySelectorAll('.email-student-checkbox-input').length;
            const selectAll = document.getElementById('selectEmailAllStudents');
            
            if (selectedCount === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (selectedCount === totalStudents) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        }

        // Setup select all for email students
        function setupEmailSelectAll() {
            const selectAll = document.getElementById('selectEmailAllStudents');
            const studentCheckboxes = document.querySelectorAll('.email-student-checkbox-input');
            
            selectAll.addEventListener('change', function() {
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateEmailSelectedCount();
                if (document.querySelector('input[name="emailRecipientType"]:checked').value === 'specific') {
                    updateEmailRecipientCount();
                }
            });
        }

        // Update email recipient count
        function updateEmailRecipientCount() {
            const sendEmailChecked = document.getElementById('sendEmail').checked;
            
            if (!sendEmailChecked) {
                document.getElementById('emailRecipientCount').innerHTML = 
                    `<i class="fas fa-users me-2"></i>Email recipients: 0 students (email not selected)`;
                return;
            }
            
            const emailRecipientType = document.querySelector('input[name="emailRecipientType"]:checked').value;
            
            if (emailRecipientType === 'specific') {
                const selectedStudents = Array.from(document.querySelectorAll('.email-student-checkbox-input:checked'))
                    .map(checkbox => checkbox.value);
                const selectedCount = selectedStudents.length;
                
                if (selectedCount > 0) {
                    document.getElementById('emailRecipientCount').innerHTML = 
                        `<i class="fas fa-users me-2"></i>Email recipients: ${selectedCount} students`;
                    
                    // Fetch actual count with valid emails
                    fetch(`get_recipient_count.php?type=specific&specific_students=${selectedStudents.join(',')}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('emailRecipientCount').innerHTML = 
                                    `<i class="fas fa-users me-2"></i>Email recipients: ${data.count} students (with valid email)`;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching recipient count:', error);
                        });
                } else {
                    document.getElementById('emailRecipientCount').innerHTML = 
                        `<i class="fas fa-users me-2"></i>Email recipients: 0 students`;
                }
            } else {
                document.getElementById('emailRecipientCount').innerHTML = 
                    `<i class="fas fa-spinner fa-spin me-2"></i>Loading email recipient count...`;
                
                fetch(`get_recipient_count.php?type=${emailRecipientType}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('emailRecipientCount').innerHTML = 
                                `<i class="fas fa-users me-2"></i>Email recipients: ${data.count} students`;
                        } else {
                            document.getElementById('emailRecipientCount').innerHTML = 
                                `<i class="fas fa-users me-2"></i>Email recipients: Unable to load count`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching recipient count:', error);
                        document.getElementById('emailRecipientCount').innerHTML = 
                            `<i class="fas fa-users me-2"></i>Email recipients: Error loading count`;
                    });
            }
        }

        // Email student search functionality
        function initEmailStudentSearch() {
            const searchInput = document.getElementById('emailStudentSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const studentCheckboxes = document.querySelectorAll('.student-checkbox');
                
                studentCheckboxes.forEach(checkbox => {
                    const studentName = checkbox.querySelector('.student-name').textContent.toLowerCase();
                    const studentDetails = checkbox.querySelector('.student-details').textContent.toLowerCase();
                    const isVisible = studentName.includes(searchTerm) || studentDetails.includes(searchTerm);
                    
                    checkbox.style.display = isVisible ? 'block' : 'none';
                });
            });
        }

        // Update email preview
        function updateEmailPreview() {
            const subject = document.getElementById('subject').value || 'Announcement Subject';
            const content = document.getElementById('content').value || 'Announcement content will appear here...';
            const sentBy = document.getElementById('sentBy').value || 'Administrator';
            
            const previewHTML = `
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 100%;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center;">
                        <h2 style="margin: 0; font-size: 20px;">AURORA STATE COLLEGE OF TECHNOLOGY</h2>
                        <p style="margin: 5px 0 0 0; opacity: 0.9;">Online School Clinic</p>
                    </div>
                    <div style="padding: 20px; background: #f8f9fa;">
                        <h3 style="color: #1a3a5f; font-size: 18px; margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">${subject}</h3>
                        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; margin-bottom: 15px;">
                            ${content.replace(/\n/g, '<br>')}
                        </div>
                        <div style="background: #e9ecef; padding: 12px; border-radius: 8px; font-size: 14px;">
                            <p style="margin: 0;"><strong>Sent by:</strong> ${sentBy}</p>
                            <p style="margin: 5px 0 0 0;"><strong>Date:</strong> ${new Date().toLocaleString()}</p>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 15px; color: #666; font-size: 12px; background: white; border-top: 1px solid #e9ecef;">
                        <p style="margin: 0;">This is an automated message from ASCOT Online Clinic System.</p>
                        <p style="margin: 5px 0 0 0;">Please do not reply to this email.</p>
                    </div>
                </div>
            `;
            
            document.getElementById('previewContent').innerHTML = previewHTML;
        }

        // NEW FUNCTIONS FOR EXPIRY TIME
        function updateExpiryPreview() {
            const dateInput = document.getElementById('expiryDate');
            const timeInput = document.getElementById('expiryTime');
            const preview = document.getElementById('expiryPreview');
            const previewText = document.getElementById('expiryPreviewText');
            const timeRemaining = document.getElementById('timeRemaining');

            if (dateInput.value && timeInput.value) {
                const expiryDateTime = new Date(dateInput.value + 'T' + timeInput.value);
                const now = new Date();
                
                // Format the date for display
                const formattedDate = expiryDateTime.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                previewText.textContent = formattedDate;
                
                // Calculate time remaining
                const timeDiff = expiryDateTime - now;
                if (timeDiff > 0) {
                    const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    
                    let remainingText = '';
                    if (days > 0) {
                        remainingText = `${days} day${days > 1 ? 's' : ''} and ${hours} hour${hours > 1 ? 's' : ''} from now`;
                    } else if (hours > 0) {
                        remainingText = `${hours} hour${hours > 1 ? 's' : ''} from now`;
                    } else {
                        const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                        remainingText = `${minutes} minute${minutes > 1 ? 's' : ''} from now`;
                    }
                    
                    timeRemaining.textContent = `⏰ ${remainingText}`;
                    timeRemaining.style.color = '#e67e22';
                } else {
                    timeRemaining.textContent = '⚠️ This time has already passed';
                    timeRemaining.style.color = '#e74c3c';
                }
                
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        function setupTimePresets() {
            const presetButtons = document.querySelectorAll('.time-preset-btn');
            const timeInput = document.getElementById('expiryTime');
            
            presetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    timeInput.value = this.getAttribute('data-time');
                    updateExpiryPreview();
                });
            });
        }

        function setMinimumDate() {
            const dateInput = document.getElementById('expiryDate');
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            
            // Set default date to tomorrow if empty
            if (!dateInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                dateInput.value = tomorrow.toISOString().split('T')[0];
            }
        }

        // Event listeners for email functionality
        const sendEmailCheckbox = document.getElementById('sendEmail');
        sendEmailCheckbox.addEventListener('change', toggleEmailRecipientSelection);

        // Email recipient type change
        document.querySelectorAll('input[name="emailRecipientType"]').forEach(radio => {
            radio.addEventListener('change', function() {
                toggleEmailSpecificStudentSelection();
                updateEmailRecipientCount();
            });
        });

        // Email student checkbox changes
        document.querySelectorAll('.email-student-checkbox-input').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateEmailSelectedCount();
                if (document.querySelector('input[name="emailRecipientType"]:checked').value === 'specific') {
                    updateEmailRecipientCount();
                }
            });
        });

        // Email functionality
        const previewEmailBtn = document.getElementById('previewEmailBtn');
        const emailPreview = document.getElementById('emailPreview');
        const subjectInput = document.getElementById('subject');
        const contentInput = document.getElementById('content');
        const sentBySelect = document.getElementById('sentBy');

        subjectInput.addEventListener('input', updateEmailPreview);
        contentInput.addEventListener('input', updateEmailPreview);
        sentBySelect.addEventListener('change', updateEmailPreview);

        previewEmailBtn.addEventListener('click', function() {
            emailPreview.classList.toggle('active');
            if (emailPreview.classList.contains('active')) {
                updateEmailPreview();
                this.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Preview';
            } else {
                this.innerHTML = '<i class="fas fa-eye"></i> Preview Email';
            }
        });

        // Form submission
        const announcementForm = document.getElementById('announcementForm');
        const cancelBtn = document.getElementById('cancelBtn');

        announcementForm.addEventListener('submit', function(e) {
            // Client-side validation
            const sentBy = document.getElementById('sentBy').value;
            const subject = document.getElementById('subject').value;
            const content = document.getElementById('content').value;
            const sendEmail = document.getElementById('sendEmail').checked;
            const postFront = document.getElementById('postFront').checked;
            
            // Check if at least one announcement type is selected
            if (!sendEmail && !postFront) {
                e.preventDefault();
                alert('Please select at least one announcement type (Send Email or Post on Front Page)');
                return;
            }
            
            if (!sentBy) {
                e.preventDefault();
                alert('Please select a sender');
                return;
            }
            
            if (!subject) {
                e.preventDefault();
                alert('Please enter a subject');
                return;
            }
            
            if (!content) {
                e.preventDefault();
                alert('Please enter announcement content');
                return;
            }
            
            // Validate specific email students selection
            if (sendEmail) {
                const emailRecipientType = document.querySelector('input[name="emailRecipientType"]:checked').value;
                
                if (emailRecipientType === 'specific') {
                    const selectedStudents = document.querySelectorAll('.email-student-checkbox-input:checked').length;
                    if (selectedStudents === 0) {
                        e.preventDefault();
                        alert('Please select at least one student for email announcement');
                        return;
                    }
                }
                
                const emailRecipientCountText = document.querySelector('#emailRecipientCount').textContent;
                
                if (!confirm(`This will send emails to all selected recipients.\n${emailRecipientCountText}\n\nThis may take several minutes. Do you want to continue?`)) {
                    e.preventDefault();
                    return;
                }
            }
            
            // Validate expiry date if provided
            const expiryDate = document.getElementById('expiryDate').value;
            const expiryTime = document.getElementById('expiryTime').value;
            if (expiryDate) {
                const expiryDateTime = new Date(expiryDate + 'T' + (expiryTime || '23:59'));
                const now = new Date();
                
                if (expiryDateTime <= now) {
                    e.preventDefault();
                    alert('Please select a future date and time for expiry');
                    return;
                }
            }
            
            // File validation
            const fileInput = document.getElementById('attachment');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const fileSize = file.size / 1024 / 1024; // MB
                const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'avi', 'mov', 'wmv', 'webm', 'pdf', 'doc', 'docx'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (!allowedExtensions.includes(fileExtension)) {
                    e.preventDefault();
                    alert('Please select a valid file type (images, videos, PDF, or documents)');
                    return;
                }
                
                if (fileSize > 10) {
                    e.preventDefault();
                    alert('File size must be less than 10MB');
                    return;
                }
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;
        });

        // Cancel button
        cancelBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
                window.location.href = 'admin_dashboard.php';
            }
        });

        // File upload preview
        const fileInput = document.getElementById('attachment');
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                if (fileSize > 10) {
                    alert('File size should be less than 10MB');
                    this.value = '';
                }
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize email functionality
            toggleEmailRecipientSelection();
            toggleEmailSpecificStudentSelection();
            setupEmailSelectAll();
            initEmailStudentSearch();
            updateEmailSelectedCount();
            updateEmailRecipientCount();
            
            // Initialize expiry time functionality
            setMinimumDate();
            setupTimePresets();
            
            const dateInput = document.getElementById('expiryDate');
            const timeInput = document.getElementById('expiryTime');
            
            dateInput.addEventListener('change', updateExpiryPreview);
            timeInput.addEventListener('change', updateExpiryPreview);
            
            // Initial preview update
            updateExpiryPreview();
        });
    </script>
</body>
</html>