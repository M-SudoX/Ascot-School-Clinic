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
            background: #f8f9fa;
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

        /* Consultation History Specific Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #667eea;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
        }

        .back-btn, .new-consultation-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn {
            background: #6c757d;
            color: white;
        }

        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .new-consultation-btn {
            background: #28a745;
            color: white;
        }

        .new-consultation-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .student-info {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .student-info h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .student-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 1rem;
        }

        .student-details span {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .contact-info p {
            margin-bottom: 0.5rem;
            color: #6c757d;
        }

        .consultation-table-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .table-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .republic-banner {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.4;
            margin-bottom: 1rem;
        }

        .table-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .consultation-table {
            width: 100%;
            border-collapse: collapse;
        }

        .consultation-table th {
            background: #2c3e50;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .consultation-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .consultation-table tr:hover {
            background: #f8f9fa;
        }

        /* Action Buttons Styles */
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .view-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
        }

        .view-btn:hover {
            background: #0056b3;
            transform: scale(1.05);
        }

        .archive-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
        }

        .archive-btn:hover {
            background: #545b62;
            transform: scale(1.05);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }

        .modal-header .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 2rem;
        }

        .consultation-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .detail-row {
            display: flex;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 150px;
        }

        .detail-row span {
            color: #6c757d;
            flex: 1;
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 2rem;
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

            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-buttons {
                flex-direction: column;
                width: 100%;
            }

            .student-info, .consultation-table-container {
                padding: 1rem;
            }

            .student-details {
                flex-direction: column;
                gap: 10px;
            }

            .consultation-table {
                font-size: 0.8rem;
            }

            .consultation-table th,
            .consultation-table td {
                padding: 10px 8px;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .detail-row label {
                min-width: auto;
            }

            /* Responsive adjustments for action buttons */
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .view-btn, .archive-btn {
                width: 100%;
                justify-content: center;
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.4rem;
            }

            .table-title {
                font-size: 1.2rem;
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
                        <a href="search_students.php" class="submenu-item">
                            <i class="fas fa-search"></i>
                            Search Students
                        </a>
                        <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="submenu-item active">
                            <i class="fas fa-file-medical"></i>
                            Consultation History
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-file-medical"></i> Consultation History</h1>
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
                    
                    <div class="table-responsive">
                        <table class="consultation-table">
                            <thead>
                                <tr>
                                    <th width="25%">Date</th>
                                    <th width="50%">Diagnose</th>
                                    <th width="25%">Actions</th>
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
                                                <div class="action-buttons">
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
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="archive-btn" 
                                                            onclick="archiveConsultation(<?php echo $consultation['id']; ?>)">
                                                        <i class="fas fa-archive"></i> Archive
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h4>Student Not Found</h4>
                    <p>The requested student record could not be found.</p>
                    <a href="students.php" class="btn btn-primary">Back to Students</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Consultation Details Modal -->
    <div class="modal fade" id="consultationModal" tabindex="-1" aria-labelledby="consultationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="consultationModalLabel">
                        <i class="fas fa-file-medical me-2"></i>Consultation Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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

        // Consultation Modal functionality
        const consultationModal = document.getElementById('consultationModal');
        if (consultationModal) {
            consultationModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('modalDate').textContent = button.getAttribute('data-date') || 'Not specified';
                document.getElementById('modalDiagnosis').textContent = button.getAttribute('data-diagnosis') || 'No diagnosis';
                document.getElementById('modalSymptoms').textContent = button.getAttribute('data-symptoms') || 'No symptoms recorded';
                document.getElementById('modalTemperature').textContent = button.getAttribute('data-temperature') || 'Not recorded';
                document.getElementById('modalBloodPressure').textContent = button.getAttribute('data-blood-pressure') || 'Not recorded';
                document.getElementById('modalTreatment').textContent = button.getAttribute('data-treatment') || 'No treatment provided';
                document.getElementById('modalHeartRate').textContent = button.getAttribute('data-heart-rate') || 'Not recorded';
                document.getElementById('modalStaff').textContent = button.getAttribute('data-staff') || 'Not specified';
                document.getElementById('modalNotes').textContent = button.getAttribute('data-notes') || 'No notes provided';
            });
        }

        // Archive Consultation Function
        function archiveConsultation(consultationId) {
            if (confirm('Are you sure you want to archive this consultation record?')) {
                // You can implement the archive functionality here
                // This could be an AJAX call to update the record status
                fetch(`archive_consultation.php?id=${consultationId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Consultation record archived successfully!');
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert('Error archiving consultation record: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error archiving consultation record.');
                });
                
                // For now, just show a confirmation message
                // alert('Archive functionality would be implemented here for consultation ID: ' + consultationId);
            }
        }
    </script>
</body>
</html>