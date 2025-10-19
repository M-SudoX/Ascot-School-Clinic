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

        /* Header Styles - FIXED */
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

        /* Mobile Menu Toggle - FIXED */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 100px; /* Adjusted for fixed header */
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

        /* Main Content - FIXED */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            margin-left: 280px; /* Added for sidebar space */
            margin-top: 0; /* Added */
        }

        /* Sidebar Overlay for Mobile - FIXED */
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

        /* Search Page Specific Styles */
        .search-highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }
        
        .advanced-search {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: none;
        }

        .card .card-body {
            padding: 0;
        }

        /* Responsive Design - FIXED */
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

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 1rem;
                width: 100%;
                margin-left: 0; /* Reset margin for mobile */
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

            /* Table responsive adjustments */
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .btn-group .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }

            .card {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .quick-links {
                grid-template-columns: 1fr;
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

    <!-- Header - FIXED -->
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
        <!-- Sidebar - FIXED -->
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

                <!-- ANNOUNCEMENT SECTION -->
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-search"></i> Search Students</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary">
                    </button>
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

            <!-- Search Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search_query" 
                                           placeholder="Search by name, student number, or course..." 
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" name="search" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                            </div>
                            <div class="col-md-3">
                                <a href="search_students.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-refresh"></i> Clear Filters
                                </a>
                            </div>
                        </div>

                        <!-- Advanced Search -->
                        <div class="advanced-search">                              
                            <div class="row g-3">
                                <div class="col-md-4">
                                </div>
                                <div class="col-md-4">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="search" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])): ?>
                <?php if (!empty($search_results)): ?>
                    <!-- Results Count -->
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i> 
                        Showing <strong><?php echo count($search_results); ?></strong> result(s) matching your search
                    </div>

                    <!-- Table -->
                    <div class="card">
                        <div class="card-body">
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
                                                    <div class="btn-group" role="group">
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
                    </div>
                <?php else: ?>
                    <!-- No Results -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No students found</h5>
                            <p class="text-muted">Try searching with different keywords or filters</p>
                            <a href="search_students.php" class="btn btn-primary">
                                <i class="fas fa-refresh"></i> Try Again
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Initial State - Quick Info -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-id-card fa-2x text-primary mb-3"></i>
                                <h5>Search by Student Number</h5>
                                <p class="text-muted">Find students using their unique ID</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user fa-2x text-success mb-3"></i>
                                <h5>Search by Name</h5>
                                <p class="text-muted">Find students by full or partial name</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-envelope fa-2x text-warning mb-3"></i>
                                <h5>Search by Email</h5>
                                <p class="text-muted">Find students using email address</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>