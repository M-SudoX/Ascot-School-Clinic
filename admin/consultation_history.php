<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student information and consultations
$student = [];
$consultations = [];

try {
    // Get student details
    $student_stmt = $pdo->prepare("
        SELECT si.*, u.email, u.student_number 
        FROM student_information si 
        JOIN users u ON si.student_number = u.student_number 
        WHERE si.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        // Get consultation history
        $consult_stmt = $pdo->prepare("
            SELECT * FROM consultations 
            WHERE student_number = ? 
            ORDER BY consultation_date DESC, created_at DESC
        ");
        $consult_stmt->execute([$student['student_number']]);
        $consultations = $consult_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Consultation history error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation History - ASCOT Clinic</title>
    
    <!-- Bootstrap & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../admin/css/consultation_history.css" rel="stylesheet">
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
                       aria-expanded="false" aria-controls="studentMenu">
                        <span><i class="fas fa-users"></i> Student Management</span>
                        <i class="fas fa-caret-down rotate-icon"></i>
                    </a>
                    <div class="collapse show" id="studentMenu">
                        <ul class="list-unstyled ms-3">
                            <li><a href="students.php" class="nav-link">• Students</a></li>
                            <li><a href="consultation_history.php" class="nav-link active">• Consultation History</a></li>
                        </ul>
                    </div>

                    <!-- Other links -->
                    <a class="nav-link" href="#"><i class="fas fa-calendar-alt"></i> Appointments</a>
                    <a class="nav-link" href="#"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a class="nav-link" href="#"><i class="fas fa-tools"></i> Admin Tools</a>
                </nav>

                <!-- Logout -->
                <div class="logout-btn">
                    <a class="nav-link text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-file-medical"></i> CONSULTATION HISTORY</h1>
                    <div class="header-buttons">
                        <a href="students.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
                        <a href="add_consultation.php?id=<?php echo $student_id; ?>" class="new-consultation-btn">
                            <i class="fas fa-plus"></i> Add New Consultation
                        </a>
                    </div>
                </div>

                <?php if ($student): ?>
                    <!-- Student Information -->
                    <div class="student-info">
                        <div class="row">
                            <div class="col-md-8">
                                <h3><?php echo htmlspecialchars($student['fullname']); ?></h3>
                                <div class="student-details">
                                    <span><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_number']); ?></span>
                                    <span><strong>Course/Year:</strong> <?php echo htmlspecialchars($student['course_year']); ?></span>
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="contact-info">
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($student['cellphone_number']); ?></p>
                                    <p><strong>Age/Sex:</strong> <?php echo htmlspecialchars($student['age']); ?> / <?php echo htmlspecialchars($student['sex']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Consultation History Table -->
                    <div class="consultation-table-container">
                        <div class="table-header">
                            <div class="republic-banner">
                                Republic of the Philippines<br>
                                AURORA STATE COLLEGE OF TECHNOLOGY<br>
                                Zabali, Baler, Aurora - Philippines
                            </div>
                            <div class="table-title">
                                <span class="title-center">CONSULTATION HISTORY</span>
                            </div>
                        </div>
                        
                        <table class="consultation-table">
                            <thead>
                                <tr>
                                    <th width="25%">Date</th>
                                    <th width="55%">Diagnose</th>
                                    <th width="20%">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($consultations)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <i class="fas fa-file-medical-alt fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">No consultation records found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($consultations as $consultation): ?>
                                        <tr>
                                            <td><?php echo date('F j, Y', strtotime($consultation['consultation_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($consultation['diagnosis'] ?? 'No diagnosis'); ?></td>
                                            <td>
                                                <button class="view-btn" data-bs-toggle="modal" data-bs-target="#consultationModal"
                                                        data-date="<?php echo date('F j, Y', strtotime($consultation['consultation_date'])); ?>"
                                                        data-diagnosis="<?php echo htmlspecialchars($consultation['diagnosis'] ?? ''); ?>"
                                                        data-symptoms="<?php echo htmlspecialchars($consultation['symptoms'] ?? ''); ?>"
                                                        data-temperature="<?php echo htmlspecialchars($consultation['temperature'] ?? ''); ?>"
                                                        data-blood-pressure="<?php echo htmlspecialchars($consultation['blood_pressure'] ?? ''); ?>"
                                                        data-treatment="<?php echo htmlspecialchars($consultation['treatment'] ?? ''); ?>"
                                                        data-heart-rate="<?php echo htmlspecialchars($consultation['heart_rate'] ?? ''); ?>"
                                                        data-staff="<?php echo htmlspecialchars($consultation['attending_staff'] ?? ''); ?>"
                                                        data-notes="<?php echo htmlspecialchars($consultation['physician_notes'] ?? ''); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <!-- Empty rows for spacing -->
                                <?php for ($i = count($consultations); $i < 7; $i++): ?>
                                    <tr><td>&nbsp;</td><td></td><td></td></tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h4>Student Not Found</h4>
                        <p>The requested student record could not be found.</p>
                        <a href="students.php" class="btn btn-primary">Back to Students</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Consultation Details Modal -->
    <div class="modal fade" id="consultationModal" tabindex="-1" aria-labelledby="consultationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="consultationModalLabel">Consultation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="consultation-details">
                        <div class="detail-row">
                            <label>Date:</label>
                            <span id="modalDate"></span>
                        </div>
                        <div class="detail-row">
                            <label>Diagnosis:</label>
                            <span id="modalDiagnosis"></span>
                        </div>
                        <div class="detail-row">
                            <label>Symptoms:</label>
                            <span id="modalSymptoms"></span>
                        </div>
                        <div class="detail-row">
                            <label>Temperature:</label>
                            <span id="modalTemperature"></span>
                        </div>
                        <div class="detail-row">
                            <label>Blood Pressure:</label>
                            <span id="modalBloodPressure"></span>
                        </div>
                        <div class="detail-row">
                            <label>Treatment:</label>
                            <span id="modalTreatment"></span>
                        </div>
                        <div class="detail-row">
                            <label>Heart Rate:</label>
                            <span id="modalHeartRate"></span>
                        </div>
                        <div class="detail-row">
                            <label>Attending Staff:</label>
                            <span id="modalStaff"></span>
                        </div>
                        <div class="detail-row">
                            <label>Physician's Notes:</label>
                            <span id="modalNotes"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../admin/js/consultation_history.js"></script>
</body>
</html>