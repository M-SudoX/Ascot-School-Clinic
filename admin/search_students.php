<?php
// START SESSION TO ACCESS ADMIN AUTHENTICATION DATA

//Admin-only access - Ensures only authorized administrators can search student data
session_start();
// CHECK IF ADMIN IS LOGGED IN BY VERIFYING ADMIN_ID SESSION VARIABLE EXISTS

if (!isset($_SESSION['admin_id'])) {
        // IF NOT LOGGED IN, REDIRECT TO ADMIN LOGIN PAGE

    header("Location: admin_login.php");
    exit();// TERMINATE SCRIPT EXECUTION

}

// INCLUDE DATABASE CONNECTION FILE - USES PDO (PHP DATA OBJECTS) FOR DATABASE OPERATIONS
require_once '../includes/db_connect.php';

// INITIALIZE VARIABLES FOR SUCCESS/ERROR MESSAGES AND SEARCH RESULTS

$success = '';
$error = '';

// INITIALIZE SEARCH RESULTS ARRAY AND FILTER VARIABLES
$search_results = [];
$search_query = '';
$year_level_filter = '';
$gender_filter = '';
$status_filter = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_query = trim($_POST['search_query'] ?? '');
    $year_level_filter = trim($_POST['year_level'] ?? '');
    $gender_filter = trim($_POST['gender'] ?? '');
    $status_filter = trim($_POST['status'] ?? '');
    
    try {
        $where_conditions = [];
        $params = [];
        
        // Base search query
        if (!empty($search_query)) {
            $search_term = "%$search_query%";
            $where_conditions[] = "(si.student_number LIKE ? OR si.fullname LIKE ? OR u.email LIKE ? OR si.course_year LIKE ? OR si.cellphone_number LIKE ?)";
            $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        }
        
        // Year level filter
        if (!empty($year_level_filter)) {
            $where_conditions[] = "si.course_year LIKE ?";
            $params[] = "%$year_level_filter%";
        }
        
        // Gender filter
        if (!empty($gender_filter)) {
            $where_conditions[] = "si.sex = ?";
            $params[] = $gender_filter;
        }
        
        // Status filter (assuming active students have complete profiles)
        if (!empty($status_filter)) {
            if ($status_filter === 'Active') {
                $where_conditions[] = "(si.course_year IS NOT NULL AND si.course_year != '' AND si.cellphone_number IS NOT NULL AND si.cellphone_number != '')";
            } elseif ($status_filter === 'Inactive') {
                $where_conditions[] = "(si.course_year IS NULL OR si.course_year = '' OR si.cellphone_number IS NULL OR si.cellphone_number = '')";
            }
        }
        
        // Build final query
        $query = "
            SELECT si.*, u.email 
            FROM student_information si 
            LEFT JOIN users u ON si.student_number = u.student_number 
        ";
        
        if (!empty($where_conditions)) {
            $query .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        $query .= " ORDER BY si.fullname ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($search_results)) {
            $error = "No students found matching your search criteria.";
        } else {
            $success = "Found " . count($search_results) . " student(s) matching your search.";
        }
        
    } catch (PDOException $e) {
        $error = "Search failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Students - ASCOT Clinic (Admin)</title>

    <!-- Bootstrap -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 80px;
            line-height: 1.6;
        }

        /* Header Styles - SAME AS STUDENT */
        .top-header {
            background: 
                linear-gradient(90deg, 
                    #ffda6a 0%, 
                    #ffda6a 30%, 
                    #FFF5CC 70%, 
                    #ffffff 100%);
            color: white;
            padding: 0.75rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 80px;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }

        .logo-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            color: #555;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
            color: #555;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
            color: #555;
        }

        /* Mobile Menu Toggle - SAME AS STUDENT */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 95px;
            left: 20px;
            z-index: 1025;
            background: var(--primary);
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
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        /* Dashboard Container - SAME AS STUDENT */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS STUDENT */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1020;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.25rem;
            color: #555;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #555;
            border-left: 8px solid #ffda6a;
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
            color: #555;
        }

        .nav-item span {
            flex: 1;
            color: #555;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
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
            padding: 0.7rem 1.25rem 0.7rem 3.25rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: var(--primary);
        }

        .submenu-item i {
            width: 18px;
            margin-right: 0.7rem;
            font-size: 0.9rem;
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - SAME AS STUDENT */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS STUDENT */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1019;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Welcome Section - SAME AS STUDENT */
        .welcome-section {
            background: linear-gradient(110deg, #fff7da 50%, #fff7da 50%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(206, 224, 144, 0.2);
            border-left: 10px solid #ffda6a;
        }

        .welcome-content h1 {
            color: #555;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Dashboard Card - SAME AS STUDENT */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title {
            color: #555;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #555;
            background: #fff7da;
            transition: all 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.1);
        }

        /* Search Bar Styles */
        .search-container {
            background: transparent;
            border: none;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        .search-input-group {
            position: relative;
            max-width: 600px;
        }
        .search-input-group .form-control {
            padding-left: 3rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            font-size: 1rem;
            height: 50px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .search-input-group .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }
        .search-input-group .btn {
            border-radius: 8px;
            height: 50px;
            padding: 0 2rem;
            margin-left: 0.5rem;
        }
        .search-stats {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Info Cards Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .info-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .info-card h5 {
            color: #555;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Responsive Design - SAME AS STUDENT */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 992px) {
            .school-name {
                font-size: 1rem;
            }

            .logo-img {
                width: 50px;
                height: 50px;
            }

            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .top-header {
                height: 70px;
                padding: 0.5rem 0;
            }
            
            .mobile-menu-toggle {
                display: block;
                top: 85px;
                left: 20px;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
                z-index: 1020;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                top: 70px;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 2rem 1.25rem 1.25rem;
                width: 100%;
                margin-left: 0;
            }

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.9rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .dashboard-card {
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .search-input-group {
                max-width: 100%;
            }
            
            .search-input-group .form-control {
                height: 45px;
            }
            
            .search-input-group .btn {
                height: 45px;
                padding: 0 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .dashboard-card {
                padding: 1.25rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-content h1 {
                font-size: 1.5rem;
            }

            .main-content {
                padding: 1.75rem 1rem 1rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }

            .info-card {
                padding: 1.25rem;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img {
                width: 40px;
                height: 40px;
            }
            
            .school-name {
                font-size: 0.8rem;
            }
            
            .republic, .clinic-title {
                font-size: 0.6rem;
            }
            
            .mobile-menu-toggle {
                width: 45px;
                height: 45px;
                top: 80px;
                left: 15px;
            }
            
            .main-content {
                padding: 1.5rem 1rem 1rem;
            }
        }

        @media (max-width: 375px) {
            .mobile-menu-toggle {
                top: 75px;
                left: 15px;
                width: 40px;
                height: 40px;
            }
            
            .main-content {
                padding: 1.25rem 0.75rem 0.75rem;
            }
        }

        /* ANIMATIONS - SAME AS STUDENT */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - SAME AS STUDENT -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS STUDENT -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS STUDENT -->
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
        <!-- Sidebar - ADMIN MENU ITEMS WITH STUDENT STYLING -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn active" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="studentMenu">
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="search_students.php" class="submenu-item active">
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
                    <div class="submenu" id="adminMenu">
                        <a href="users_logs.php" class="submenu-item">
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
                
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content - FOLLOWING STUDENT DASHBOARD STRUCTURE -->
        <main class="main-content">
            <!-- WELCOME SECTION -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Search Students 🔍</h1>
                    <p>Find students quickly using various search criteria</p>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Search Students</h3>
                    <div class="card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                
                <div class="search-container">
                    <form method="POST" action="">
                        <div class="d-flex align-items-center">
                            <div class="search-input-group flex-grow-1">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" name="search_query" 
                                       placeholder="Enter student name, student number, email, course, or contact number..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>"
                                       aria-label="Search students">
                            </div>
                            &nbsp;<button type="submit" name="search" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <a href="search_students.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-refresh me-2"></i>Clear
                            </a>
                        </div>
                        <?php if (!empty($search_query)): ?>
                            <div class="search-stats">
                                <i class="fas fa-info-circle me-1"></i>
                                Searching for: "<?php echo htmlspecialchars($search_query); ?>"
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])): ?>
                <?php if (!empty($search_results)): ?>
                    <!-- Results Count -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Search Results</h3>
                            <div class="card-icon">
                                <i class="fas fa-list"></i>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i> 
                            Found <strong><?php echo count($search_results); ?></strong> student(s) matching your search
                        </div>

                        <!-- Results Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student Number</th>
                                        <th>Name</th>
                                        <th>Sex</th>
                                        <th>Year Level</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $student): 
                                        $studentId = $student['id'] ?? 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['fullname'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($student['sex'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['course_year'] ?? 'Not set'); ?></td>
                                            <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['cellphone_number'] ?? 'Not set'); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($studentId && $studentId > 0): ?>
                                                        <a href="view_student.php?id=<?php echo $studentId; ?>" class="btn btn-success btn-sm" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_student.php?id=<?php echo $studentId; ?>" class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="students.php?delete_id=<?php echo $studentId; ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this student?')">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Edit via Students</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Results -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">No Results Found</h3>
                            <div class="card-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No students found</h5>
                            <p class="text-muted">Try searching with different keywords or check your spelling</p>
                            <a href="search_students.php" class="btn btn-primary">
                                <i class="fas fa-refresh"></i> Try Again
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Initial State - Quick Info Cards -->
                <div class="info-grid fade-in">
                    <div class="info-card">
                        <i class="fas fa-id-card text-primary"></i>
                        <h5>Search by Student Number</h5>
                        <p>Find students using their unique ID number</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-user text-success"></i>
                        <h5>Search by Name</h5>
                        <p>Find students by full or partial name</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-envelope text-warning"></i>
                        <h5>Search by Email</h5>
                        <p>Find students using email address</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-graduation-cap text-info"></i>
                        <h5>Search by Course</h5>
                        <p>Find students by course or year level</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // MOBILE MENU FUNCTIONALITY - SAME AS STUDENT
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

            // Focus on search input on page load
            const searchInput = document.querySelector('input[name="search_query"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Enter key search
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }

            // LOADING ANIMATIONS
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
            });
        });
    </script>
</body>
</html>