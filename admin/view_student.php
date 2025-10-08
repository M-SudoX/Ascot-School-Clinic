<?php

//to prevent SQL Injection - cannot hack the database using malicious inputs.


session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Get student ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

// Fetch student information
try {
    // Get student information from student_information table
    $stmt = $pdo->prepare("
        SELECT si.*, u.email, u.created_at as user_created 
        FROM student_information si 
        LEFT JOIN users u ON si.student_number = u.student_number 
        WHERE si.id = ?
    ");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student_info) {
        header("Location: students.php?error=Student not found");
        exit();
    }

    // Get medical history
    $medical_stmt = $pdo->prepare("
        SELECT * FROM medical_history 
        WHERE student_number = ?
    ");
    $medical_stmt->execute([$student_info['student_number']]);
    $medical_info = $medical_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// FUNCTION TO CHECK IF PROFILE IS COMPLETE (BOTH PART 1 AND PART 2)
function isProfileComplete($student_info, $medical_info) {
    // PART 1: Student Information Requirements
    $part1_complete = !empty($student_info['fullname']) && 
                     !empty($student_info['address']) && 
                     !empty($student_info['age']) && 
                     !empty($student_info['sex']) && 
                     !empty($student_info['course_year']) && 
                     !empty($student_info['cellphone_number']);
    
    // PART 2: Medical History Requirements
    $part2_complete = !empty($medical_info['medical_attention']) && 
                     !empty($medical_info['previous_hospitalization']) && 
                     !empty($medical_info['surgery']);
    
    return $part1_complete && $part2_complete;
}

// FUNCTION TO GET COMPLETION STATUS WITH DETAILS
function getProfileStatus($student_info, $medical_info) {
    $missing_fields = [];
    
    // Check Part 1 fields
    if (empty($student_info['fullname'])) $missing_fields[] = 'Full Name';
    if (empty($student_info['address'])) $missing_fields[] = 'Address';
    if (empty($student_info['age'])) $missing_fields[] = 'Age';
    if (empty($student_info['sex'])) $missing_fields[] = 'Sex';
    if (empty($student_info['course_year'])) $missing_fields[] = 'Course/Year';
    if (empty($student_info['cellphone_number'])) $missing_fields[] = 'Cellphone Number';
    
    // Check Part 2 fields
    if (empty($medical_info['medical_attention'])) $missing_fields[] = 'Medical Attention';
    if (empty($medical_info['previous_hospitalization'])) $missing_fields[] = 'Previous Hospitalization';
    if (empty($medical_info['surgery'])) $missing_fields[] = 'Surgery Information';
    
    return [
        'is_complete' => empty($missing_fields),
        'missing_fields' => $missing_fields,
        'part1_complete' => !empty($student_info['fullname']) && !empty($student_info['address']) && !empty($student_info['age']) && !empty($student_info['sex']) && !empty($student_info['course_year']) && !empty($student_info['cellphone_number']),
        'part2_complete' => !empty($medical_info['medical_attention']) && !empty($medical_info['previous_hospitalization']) && !empty($medical_info['surgery'])
    ];
}

// Get profile status
$profile_status = getProfileStatus($student_info, $medical_info);
$isComplete = $profile_status['is_complete'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - ASCOT Clinic (Admin)</title>

    <!-- Bootstrap & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <!-- Admin CSS -->
    <link href="css/view_student.css" rel="stylesheet">
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
                            <li><a href="students.php" class="nav-link">• Students</a></li>
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
                    <h2><i class="fas fa-user-graduate"></i> Student Profile</h2>
                    <a href="students.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Students
                    </a>
                </div>

                <!-- Student Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert <?php echo $isComplete ? 'alert-success' : 'alert-warning'; ?> d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Student Number:</strong> <?php echo htmlspecialchars($student_info['student_number']); ?>
                                <span class="mx-2">|</span>
                                <strong>Status:</strong> 
                                <?php if ($isComplete): ?>
                                    <span class="badge bg-success status-badge">Complete Profile</span>
                                <?php else: ?>
                                    <span class="badge bg-warning status-badge">Incomplete Profile</span>
                                <?php endif; ?>
                                
                                <!-- Completion Details -->
                                <div class="completion-details mt-2">
                                    <div>
                                        <span class="part-status <?php echo $profile_status['part1_complete'] ? 'part-complete' : 'part-incomplete'; ?>"></span>
                                        <strong>Part 1:</strong> <?php echo $profile_status['part1_complete'] ? 'Complete' : 'Incomplete'; ?>
                                        (Student Information)
                                    </div>
                                    <div>
                                        <span class="part-status <?php echo $profile_status['part2_complete'] ? 'part-complete' : 'part-incomplete'; ?>"></span>
                                        <strong>Part 2:</strong> <?php echo $profile_status['part2_complete'] ? 'Complete' : 'Incomplete'; ?>
                                        (Medical History)
                                    </div>
                                    <?php if (!$isComplete && !empty($profile_status['missing_fields'])): ?>
                                        <div class="missing-fields-list">
                                            <strong><i class="fas fa-exclamation-circle"></i> Missing Fields:</strong> 
                                            <?php echo implode(', ', $profile_status['missing_fields']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PART I: STUDENT INFORMATION -->
                <div class="form-section">
                    <div class="section-title">PART I: STUDENT INFORMATION</div>
                    
                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group <?php echo empty($student_info['fullname']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Full Name</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($student_info['fullname'] ?? 'Not set'); ?>
                                    <?php if (empty($student_info['fullname'])): ?>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group <?php echo empty($student_info['address']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Address</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($student_info['address'] ?? 'Not set'); ?>
                                    <?php if (empty($student_info['address'])): ?>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-group <?php echo empty($student_info['age']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Age</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($student_info['age'] ?? 'Not set'); ?>
                                    <?php if (empty($student_info['age'])): ?>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-group <?php echo empty($student_info['sex']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Sex</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($student_info['sex'] ?? 'Not set'); ?>
                                    <?php if (empty($student_info['sex'])): ?>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-group">
                                <div class="info-label">Civil Status</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['civil_status'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-group">
                                <div class="info-label">Blood Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['blood_type'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Parent/Guardian Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['father_name'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-group">
                                <div class="info-label">Date Registered</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['date'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-group">
                                <div class="info-label">School Year</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['school_year'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group <?php echo empty($student_info['course_year']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Course/Year</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($student_info['course_year'] ?? 'Not set'); ?>
                                    <?php if (empty($student_info['course_year'])): ?>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group <?php echo empty($student_info['cellphone_number']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Cellphone Number</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($student_info['cellphone_number'] ?? 'Not set'); ?>
                                    <?php if (empty($student_info['cellphone_number'])): ?>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['email'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Account Created</div>
                                <div class="info-value"><?php echo htmlspecialchars($student_info['user_created'] ?? 'Not set'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PART II: MEDICAL HISTORY -->
                <div class="form-section">
                    <div class="section-title">PART II: MEDICAL HISTORY</div>
                    
                    <!-- Medical Attention -->
                    <div class="info-group <?php echo empty($medical_info['medical_attention']) ? 'missing-field' : ''; ?>">
                        <div class="info-label">1. Do you need medical attention or has known medical illness?</div>
                        <div class="info-value">
                            <?php if (!empty($medical_info['medical_attention'])): ?>
                                <span class="badge <?php echo $medical_info['medical_attention'] == 'Yes' ? 'bg-warning' : 'bg-success'; ?>">
                                    <?php echo htmlspecialchars($medical_info['medical_attention']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Not specified</span>
                                <small class="text-danger">(Required)</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Medical Conditions -->
                    <?php if (!empty($medical_info['medical_conditions'])): ?>
                    <div class="info-group">
                        <div class="info-label">Medical Conditions</div>
                        <div class="conditions-grid">
                            <?php
                            $conditions = explode(',', $medical_info['medical_conditions']);
                            foreach ($conditions as $condition):
                                if (!empty(trim($condition))):
                            ?>
                                <div class="condition-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <?php echo htmlspecialchars(trim($condition)); ?>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Other Conditions -->
                    <?php if (!empty($medical_info['other_conditions'])): ?>
                    <div class="info-group">
                        <div class="info-label">Other Conditions</div>
                        <div class="info-value"><?php echo htmlspecialchars($medical_info['other_conditions']); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Previous Hospitalization -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group <?php echo empty($medical_info['previous_hospitalization']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Previous Hospitalization</div>
                                <div class="info-value">
                                    <?php if (!empty($medical_info['previous_hospitalization'])): ?>
                                        <span class="badge <?php echo $medical_info['previous_hospitalization'] == 'Yes' ? 'bg-warning' : 'bg-success'; ?>">
                                            <?php echo htmlspecialchars($medical_info['previous_hospitalization']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if (!empty($medical_info['hosp_year'])): ?>
                            <div class="info-group">
                                <div class="info-label">Year of Hospitalization</div>
                                <div class="info-value"><?php echo htmlspecialchars($medical_info['hosp_year']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Surgery Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group <?php echo empty($medical_info['surgery']) ? 'missing-field' : ''; ?>">
                                <div class="info-label">Operation/Surgery</div>
                                <div class="info-value">
                                    <?php if (!empty($medical_info['surgery'])): ?>
                                        <span class="badge <?php echo $medical_info['surgery'] == 'Yes' ? 'bg-warning' : 'bg-success'; ?>">
                                            <?php echo htmlspecialchars($medical_info['surgery']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                        <small class="text-danger">(Required)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if (!empty($medical_info['surgery_details'])): ?>
                            <div class="info-group">
                                <div class="info-label">Surgery Details</div>
                                <div class="info-value"><?php echo htmlspecialchars($medical_info['surgery_details']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Allergies -->
                    <div class="info-group">
                        <div class="info-label">2. Additional Information - Allergies</div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="info-label">Food Allergies</div>
                                <div class="info-value">
                                    <?php echo !empty($medical_info['food_allergies']) ? htmlspecialchars($medical_info['food_allergies']) : '<span class="text-muted">None specified</span>'; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Medicine Allergies</div>
                                <div class="info-value">
                                    <?php echo !empty($medical_info['medicine_allergies']) ? htmlspecialchars($medical_info['medicine_allergies']) : '<span class="text-muted">None specified</span>'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Student Information
                    </a>
                    <a href="students.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Students List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin_scripts.js"></script>
</body>
</html>