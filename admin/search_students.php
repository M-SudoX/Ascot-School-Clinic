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
    <!-- Custom CSS -->
    <link href="../admin/css/admin_dashboard.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    <link href="../admin/css/search_students.css" rel="stylesheet">
    
    <style>
        /* Minimal additional styles for search page */
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
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                </div>
                <div class="col">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Layout -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar position-relative">
                <nav class="nav flex-column">
                    <!-- Dashboard -->
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>

                    <!-- Student Management -->
                    <a class="nav-link d-flex justify-content-between align-items-center"
                       data-bs-toggle="collapse" href="#studentMenu" role="button"
                       aria-expanded="true" aria-controls="studentMenu">
                        <span><i class="fas fa-users"></i> Student Management</span>
                        <i class="fas fa-caret-down rotate-icon"></i>
                    </a>
                    <div class="collapse show" id="studentMenu">
                        <ul class="list-unstyled ms-3">
                            <li><a href="students.php" class="nav-link">• Students</a></li>
                            <li><a href="search_students.php" class="nav-link active">• Search Students</a></li>
                        </ul>
                    </div>

                    <!-- Other links -->
                    <a class="nav-link" href="#"><i class="fas fa-notes-medical"></i> Consultation</a>
                    <a class="nav-link" href="#"><i class="fas fa-calendar-alt"></i> Appointments</a>
                    <a class="nav-link" href="#"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a class="nav-link" href="#"><i class="fas fa-tools"></i> Admin Tools</a>
                </nav>

                <!-- Logout -->
                <div class="logout-btn">
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
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
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const collapseLinks = document.querySelectorAll('[data-bs-toggle="collapse"]');
            collapseLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const icon = this.querySelector('.rotate-icon');
                    icon.style.transition = 'transform 0.3s ease';
                    icon.style.transform = icon.style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
                });
            });

            // Focus on search input on page load
            const searchInput = document.querySelector('input[name="search_query"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Enter key search
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        });
    </script>
</body>
</html>

<!-- 
SUMMARY OF HOW THIS CODE WORKS:

1. AUTHENTICATION & SECURITY:
   - USES SESSION MANAGEMENT TO VERIFY ADMIN LOGIN
   - REDIRECTS UNAUTHENTICATED USERS TO LOGIN PAGE
   - USES PDO PREPARED STATEMENTS TO PREVENT SQL INJECTION
   - USES htmlspecialchars() TO PREVENT XSS ATTACKS

2. DATABASE OPERATIONS (USES PDO - PHP DATA OBJECTS):
   - require_once FOR DATABASE CONNECTION
   - PDO PREPARED STATEMENTS WITH PARAMETER BINDING
   - DYNAMIC QUERY BUILDING WITH WHERE CONDITIONS
   - LEFT JOIN BETWEEN student_information AND users TABLES

3. SEARCH FUNCTIONALITY:
   - MULTI-FIELD SEARCH (STUDENT NUMBER, NAME, EMAIL, COURSE, PHONE)
   - ADVANCED FILTERING BY YEAR LEVEL, GENDER, AND STATUS
   - DYNAMIC SQL QUERY BUILDING BASED ON USER INPUT
   - RESULTS COUNT DISPLAY AND PAGINATION-READY STRUCTURE

4. USER INTERFACE:
   - RESPONSIVE BOOTSTRAP LAYOUT WITH SIDEBAR NAVIGATION
   - SUCCESS/ERROR MESSAGE DISPLAY
   - INTERACTIVE TABLE WITH ACTION BUTTONS (VIEW, EDIT, DELETE)
   - CLEAR FILTERS FUNCTIONALITY
   - EMPTY STATE HANDLING WITH HELPFUL MESSAGES

5. JAVASCRIPT ENHANCEMENTS:
   - AUTO-FOCUS ON SEARCH INPUT
   - ENTER KEY SUBMISSION
   - COLLAPSIBLE MENU ANIMATIONS
   - DELETE CONFIRMATION DIALOGS

NOTE: THIS CODE USES PDO (PHP DATA OBJECTS) FOR DATABASE OPERATIONS, 
WHICH PROVIDES BETTER SECURITY AND FEATURES COMPARED TO MYSQLI.
THE SEARCH SYSTEM IS DESIGNED TO BE FLEXIBLE AND USER-FRIENDLY FOR ADMINISTRATORS.
-->
