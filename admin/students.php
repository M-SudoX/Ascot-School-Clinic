<?php
//i use session authentication for admin only
//PDO for - SQL injection protection




// START SESSION TO ACCESS SESSION VARIABLES AND MAINTAIN USER STATE
session_start();

// CHECK IF STUDENT IS LOGGED IN BY VERIFYING STUDENT_ID EXISTS IN SESSION
if (!isset($_SESSION['admin_id'])) {
    // IF NOT LOGGED IN, REDIRECT TO STUDENT LOGIN PAGE
    header("Location: admin_login.php");
    exit(); // TERMINATE SCRIPT EXECUTION IMMEDIATELY
}

// INCLUDE DATABASE CONNECTION FILE TO ESTABLISH DATABASE CONNECTION
require_once '../includes/db_connect.php';

// Handle form submissions
$success = '';
$error = '';

// DELETE STUDENT
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];





    //PDO for Secure Data Access - Protected database operations for sensitive student information
    
    try {
        // First get student_number before deleting
        $get_stmt = $pdo->prepare("SELECT student_number FROM student_information WHERE id = ?");
        $get_stmt->execute([$delete_id]);
        $student = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $student_number = $student['student_number'];
            
            // Delete from student_information table
            $delete_stmt = $pdo->prepare("DELETE FROM student_information WHERE id = ?");
            $delete_success = $delete_stmt->execute([$delete_id]);
            
            // Also delete from users table
            if ($delete_success) {
                $delete_user_stmt = $pdo->prepare("DELETE FROM users WHERE student_number = ?");
                $delete_user_stmt->execute([$student_number]);
                
                $success = "Student deleted successfully!";
            }
        } else {
            $error = "Student not found!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';
$department_filter = $_GET['department'] ?? 'all';

// Fetch all students with medical history check
$students = [];
$where_conditions = [];
$params = [];

try {
    // Base query with LEFT JOIN for medical_history
    $query = "
        SELECT 
            si.*, 
            u.email, 
            u.created_at as user_created,
            mh.medical_attention,
            mh.medical_conditions,
            mh.other_conditions,
            mh.previous_hospitalization,
            mh.hosp_year,
            mh.surgery,
            mh.surgery_details,
            mh.food_allergies,
            mh.medicine_allergies
        FROM users u 
        LEFT JOIN student_information si ON u.student_number = si.student_number 
        LEFT JOIN medical_history mh ON u.student_number = mh.student_number
        WHERE 1=1
    ";

    // Apply status filter
    if ($filter === 'incomplete') {
        $query .= " AND (
            si.course_year IS NULL OR si.course_year = '' OR 
            si.cellphone_number IS NULL OR si.cellphone_number = '' OR
            si.fullname IS NULL OR si.fullname = '' OR
            si.address IS NULL OR si.address = '' OR
            si.age IS NULL OR si.age = '' OR
            si.sex IS NULL OR si.sex = '' OR
            mh.medical_attention IS NULL OR mh.medical_attention = '' OR
            mh.medical_conditions IS NULL OR mh.medical_conditions = '' OR
            mh.previous_hospitalization IS NULL OR mh.previous_hospitalization = '' OR
            mh.surgery IS NULL OR mh.surgery = ''
        )";
    }

    // Apply department filter
    if ($department_filter !== 'all') {
        $query .= " AND si.course_year LIKE ?";
        $params[] = "%$department_filter%";
    }

    $query .= " ORDER BY COALESCE(si.created_at, u.created_at) DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Failed to fetch students: " . $e->getMessage();
    error_log("Student fetch error: " . $e->getMessage());
}

// Get unique departments for filter dropdown
$departments = [];
try {
    $dept_stmt = $pdo->query("
        SELECT DISTINCT course_year 
        FROM student_information 
        WHERE course_year IS NOT NULL AND course_year != '' 
        ORDER BY course_year
    ");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Department fetch error: " . $e->getMessage());
}

// Calculate statistics - UPDATED TO INCLUDE PART 2
$total_students = count($students);
$complete_profiles = 0;
$incomplete_profiles = 0;

foreach ($students as $student) {
    if (isProfileComplete($student)) {
        $complete_profiles++;
    } else {
        $incomplete_profiles++;
    }
}

// FUNCTION TO CHECK IF PROFILE IS COMPLETE (BOTH PART 1 AND PART 2)
function isProfileComplete($student) {
    // PART 1: Student Information Requirements
    $part1_complete = !empty($student['fullname']) && 
                     !empty($student['address']) && 
                     !empty($student['age']) && 
                     !empty($student['sex']) && 
                     !empty($student['course_year']) && 
                     !empty($student['cellphone_number']);
    
    // PART 2: Medical History Requirements
    $part2_complete = !empty($student['medical_attention']) && 
                     !empty($student['previous_hospitalization']) && 
                     !empty($student['surgery']);
    
    return $part1_complete && $part2_complete;
}

// FUNCTION TO GET COMPLETION STATUS WITH DETAILS
function getProfileStatus($student) {
    $missing_fields = [];
    
    // Check Part 1 fields
    if (empty($student['fullname'])) $missing_fields[] = 'Full Name';
    if (empty($student['address'])) $missing_fields[] = 'Address';
    if (empty($student['age'])) $missing_fields[] = 'Age';
    if (empty($student['sex'])) $missing_fields[] = 'Sex';
    if (empty($student['course_year'])) $missing_fields[] = 'Course/Year';
    if (empty($student['cellphone_number'])) $missing_fields[] = 'Cellphone Number';
    
    // Check Part 2 fields
    if (empty($student['medical_attention'])) $missing_fields[] = 'Medical Attention';
    if (empty($student['previous_hospitalization'])) $missing_fields[] = 'Previous Hospitalization';
    if (empty($student['surgery'])) $missing_fields[] = 'Surgery Information';
    
    return [
        'is_complete' => empty($missing_fields),
        'missing_fields' => $missing_fields,
        'part1_complete' => !empty($student['fullname']) && !empty($student['address']) && !empty($student['age']) && !empty($student['sex']) && !empty($student['course_year']) && !empty($student['cellphone_number']),
        'part2_complete' => !empty($student['medical_attention']) && !empty($student['previous_hospitalization']) && !empty($student['surgery'])
    ];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - ASCOT Clinic (Admin)</title>

    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../admin/css/students.css" rel="stylesheet">
    
    <style>
        .incomplete-profile {
            background-color: #fff3cd !important;
        }
        .status-badge {
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .filter-active {
            background-color: #0d6efd !important;
            color: white !important;
        }
        .department-filter {
            max-width: 200px;
        }
        .filter-badge {
            font-size: 0.75em;
            margin-left: 5px;
        }
        .completion-details {
            font-size: 0.8em;
            color: #6c757d;
        }
        .part-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .part-complete {
            background-color: #28a745;
        }
        .part-incomplete {
            background-color: #dc3545;
        }
        .missing-fields-tooltip {
            cursor: help;
            border-bottom: 1px dotted #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../img/logo.png" alt="Logo" class="logo-img">
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
                    <a class="nav-link d-flex justify-content-between align-items-center active"
                       data-bs-toggle="collapse" href="#studentMenu" role="button"
                       aria-expanded="true" aria-controls="studentMenu">
                        <span><i class="fas fa-users"></i> Student Management</span>
                        <i class="fas fa-caret-down rotate-icon"></i>
                    </a>
                    <div class="collapse show" id="studentMenu">
                        <ul class="list-unstyled ms-3">
                            <li><a href="students.php" class="nav-link active">• Students</a></li>
                            <li><a href="search_students.php" class="nav-link">• Search Students</a></li>
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
                    <a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users"></i> Student Management</h2>
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

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <a href="students.php?filter=all&department=<?php echo $department_filter; ?>" 
                               class="btn btn-outline-primary <?php echo $filter === 'all' ? 'filter-active' : ''; ?>">
                                All Students
                                <span class="badge bg-secondary filter-badge"><?php echo $total_students; ?></span>
                            </a>
                            <a href="students.php?filter=incomplete&department=<?php echo $department_filter; ?>" 
                               class="btn btn-outline-warning <?php echo $filter === 'incomplete' ? 'filter-active' : ''; ?>">
                                Incomplete Profiles
                                <span class="badge bg-warning filter-badge"><?php echo $incomplete_profiles; ?></span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                    </div>
                </div>

                <!-- Student Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Students</h5>
                                <h3><?php echo $total_students; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Complete Profiles</h5>
                                <h3><?php echo $complete_profiles; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Incomplete Profiles</h5>
                                <h3><?php echo $incomplete_profiles; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Completion Rate</h5>
                                <h3><?php echo $total_students > 0 ? round(($complete_profiles / $total_students) * 100) : 0; ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table">
                    <div class="table">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Course/Year</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Completion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                                <p class="text-muted">No students found with current filters.</p>
                                                <a href="students.php" class="btn btn-primary">
                                                    <i class="fas fa-refresh"></i> Reset Filters
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): 
                                            $status = getProfileStatus($student);
                                            $isComplete = $status['is_complete'];
                                            $rowClass = $isComplete ? '' : 'incomplete-profile';
                                            $studentId = $student['id'] ?? $student['user_id'] ?? 0;
                                        ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['fullname'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($student['course_year'] ?? 'Not set'); ?></td>
                                                <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['cellphone_number'] ?? 'Not set'); ?></td>
                                                <td>
                                                    <?php if ($isComplete): ?>
                                                        <span class="badge bg-success status-badge">Complete</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning status-badge">Incomplete</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="completion-details">
                                                        <div>
                                                            <span class="part-status <?php echo $status['part1_complete'] ? 'part-complete' : 'part-incomplete'; ?>"></span>
                                                            Part 1: <?php echo $status['part1_complete'] ? 'Complete' : 'Incomplete'; ?>
                                                        </div>
                                                        <div>
                                                            <span class="part-status <?php echo $status['part2_complete'] ? 'part-complete' : 'part-incomplete'; ?>"></span>
                                                            Part 2: <?php echo $status['part2_complete'] ? 'Complete' : 'Incomplete'; ?>
                                                        </div>
                                                        <?php if (!$isComplete && !empty($status['missing_fields'])): ?>
                                                            <small class="text-danger missing-fields-tooltip" title="Missing: <?php echo implode(', ', $status['missing_fields']); ?>">
                                                                Missing <?php echo count($status['missing_fields']); ?> fields
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($studentId && $studentId > 0): ?>
                                                            <a href="view_student.php?id=<?php echo $studentId; ?>" class="btn btn-success btn-sm" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="consultation_history.php?id=<?php echo $studentId; ?>" class="btn btn-info btn-sm" title="Consultation History">
                                                                <i class="fas fa-file-medical"></i>
                                                            </a>
                                                            <button class="btn btn-danger btn-sm delete-student" 
                                                                    data-id="<?php echo $studentId; ?>" 
                                                                    data-name="<?php echo htmlspecialchars($student['fullname']); ?>"
                                                                    title="Delete">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Edit via Search</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteStudentModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete student: <strong id="studentNameToDelete"></strong>?</p>
                    <p class="text-danger">This action cannot be undone and will remove both user account and student records.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmDelete">Delete</a>
                </div>
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
            
            // Delete student confirmation
            const deleteButtons = document.querySelectorAll('.delete-student');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));
            const studentNameElement = document.getElementById('studentNameToDelete');
            const confirmDeleteButton = document.getElementById('confirmDelete');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    studentNameElement.textContent = studentName;
                    confirmDeleteButton.href = 'students.php?delete_id=' + studentId;
                    deleteModal.show();
                });
            });

            // Department filter change
            const departmentFilter = document.getElementById('departmentFilter');
            if (departmentFilter) {
                departmentFilter.addEventListener('change', function() {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('department', this.value);
                    window.location.href = currentUrl.toString();
                });
            }

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>

<!-- 
SUMMARY OF HOW THIS CODE WORKS:

1. AUTHENTICATION & SESSION MANAGEMENT:
   - session_start() INITIATES OR RESUMES EXISTING SESSION
   - CHECKS IF student_id EXISTS IN SESSION TO VERIFY LOGIN STATUS
   - REDIRECTS TO LOGIN PAGE IF USER IS NOT AUTHENTICATED

2. DATABASE OPERATIONS (USING MYSQLI - NOT PDO):
   - INCLUDES DATABASE CONNECTION FILE
   - EXECUTES MULTIPLE SQL QUERIES TO FETCH STUDENT DATA AND APPOINTMENT COUNTS
   - USES mysqli_query() TO EXECUTE QUERIES
   - USES mysqli_fetch_assoc() TO RETRIEVE RESULTS AS ASSOCIATIVE ARRAYS
   - CLOSES DATABASE CONNECTION AFTER USE

3. DATA FLOW:
   - GETS student_id FROM SESSION
   - FETCHES STUDENT PROFILE INFORMATION FROM DATABASE
   - COUNTS TOTAL, PENDING, AND COMPLETED APPOINTMENTS
   - DISPLAYS PERSONALIZED WELCOME MESSAGE WITH STUDENT NAME

4. FRONTEND STRUCTURE:
   - RESPONSIVE DASHBOARD WITH SIDEBAR NAVIGATION
   - QUICK ACTION BUTTONS FOR FREQUENT TASKS
   - STATISTICS CARDS SHOWING APPOINTMENT METRICS
   - RECENT ACTIVITY FEED

5. SECURITY FEATURES:
   - SESSION-BASED AUTHENTICATION
   - INPUT SANITIZATION USING htmlspecialchars() WHEN OUTPUTTING DATA
   - PROPER REDIRECTION FOR UNAUTHENTICATED USERS

6. JAVASCRIPT FUNCTIONALITY:
   - DROPDOWN MENU TOGGLE FOR SIDEBAR NAVIGATION
   - INTERACTIVE QUICK ACTION BUTTONS

NOTE: THIS IS A STUDENT DASHBOARD THAT PROVIDES PERSONALIZED ACCESS TO 
MEDICAL SERVICES AND APPOINTMENT MANAGEMENT WITHIN THE SCHOOL CLINIC SYSTEM.
-->
