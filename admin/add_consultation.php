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

// Fetch student information
$student = [];
try {
    $student_stmt = $pdo->prepare("
        SELECT si.*, u.email 
        FROM student_information si 
        JOIN users u ON si.student_number = u.student_number 
        WHERE si.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Student fetch error: " . $e->getMessage());
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $symptoms = trim($_POST['symptoms'] ?? '');
        $temperature = trim($_POST['temperature'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $blood_pressure = trim($_POST['blood_pressure'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $heart_rate = trim($_POST['heart_rate'] ?? '');
        $attending_staff = trim($_POST['attending_staff'] ?? '');
        $consultation_date = trim($_POST['consultation_date'] ?? date('Y-m-d'));
        $physician_notes = trim($_POST['physician_notes'] ?? '');
        
        // Validate required fields
        if (empty($symptoms) || empty($diagnosis) || empty($attending_staff)) {
            throw new Exception("Please fill in all required fields: Symptoms, Diagnosis, and Attending Staff.");
        }
        
        if (!$student) {
            throw new Exception("Student information not found.");
        }
        
        // Insert consultation record
        $insert_stmt = $pdo->prepare("
            INSERT INTO consultations (
                student_number, symptoms, temperature, diagnosis, 
                blood_pressure, treatment, heart_rate, attending_staff, 
                consultation_date, physician_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insert_stmt->execute([
            $student['student_number'],
            $symptoms,
            $temperature,
            $diagnosis,
            $blood_pressure,
            $treatment,
            $heart_rate,
            $attending_staff,
            $consultation_date,
            $physician_notes
        ]);
        
        $success = "Consultation record saved successfully!";
        
        // Clear form after successful submission
        $_POST = array();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Consultation save error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Consultation - ASCOT Clinic</title>
    
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

        /* Add Consultation Specific Styles */
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

        .back-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #6c757d;
            color: white;
        }

        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .student-info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }

        .student-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .student-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .student-details span {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .consultation-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .form-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e9ecef, transparent);
            margin: 1rem 0;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .save-btn, .reset-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .save-btn {
            background: #28a745;
            color: white;
        }

        .save-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .reset-btn {
            background: #6c757d;
            color: white;
        }

        .reset-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Success Alert */
        .success-alert {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border-left: 4px solid #28a745;
            animation: slideIn 0.5s ease-out;
        }

        .success-alert i {
            color: #28a745;
            margin-right: 0.5rem;
        }

        .success-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .success-btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .success-btn.primary {
            background: #28a745;
            color: white;
        }

        .success-btn.secondary {
            background: #6c757d;
            color: white;
        }

        .success-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

            .student-info-card, .consultation-form {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .student-details {
                flex-direction: column;
                gap: 10px;
            }

            .form-actions {
                flex-direction: column;
            }

            .save-btn, .reset-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.4rem;
            }

            .success-actions {
                flex-direction: column;
            }

            .success-btn {
                justify-content: center;
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
                        <?php if ($student): ?>
                            <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="submenu-item">
                                <i class="fas fa-file-medical"></i>
                                Consultation History
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn active" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                        <?php if ($student): ?>
                            <a href="add_consultation.php?id=<?php echo $student_id; ?>" class="submenu-item active">
                                <i class="fas fa-plus-circle"></i>
                                Add Consultation
                            </a>
                        <?php endif; ?>
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
                <h1><i class="fas fa-plus-circle"></i> Add New Consultation</h1>
                <div class="header-buttons">
                    <?php if ($student): ?>
                        <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Consultation History
                        </a>
                    <?php else: ?>
                        <a href="students.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="success-alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <div>
                            <h5 class="mb-1" style="color: #155724;">Success!</h5>
                            <p class="mb-2"><?php echo $success; ?></p>
                            <div class="success-actions">
                                <?php if ($student): ?>
                                    <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="success-btn primary">
                                        <i class="fas fa-history"></i> View Consultation History
                                    </a>
                                    <a href="add_consultation.php?id=<?php echo $student_id; ?>" class="success-btn secondary">
                                        <i class="fas fa-plus"></i> Add Another Consultation
                                    </a>
                                <?php endif; ?>
                                <a href="students.php" class="success-btn secondary">
                                    <i class="fas fa-users"></i> Back to Students
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Error:</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($student): ?>
                <!-- Student Information -->
                <div class="student-info-card">
                    <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                    <div class="student-details">
                        <span>ID: <?php echo htmlspecialchars($student['student_number']); ?></span>
                        <span>Course: <?php echo htmlspecialchars($student['course_year']); ?></span>
                        <span>Age/Sex: <?php echo htmlspecialchars($student['age']); ?>/<?php echo htmlspecialchars($student['sex']); ?></span>
                    </div>
                </div>

                <!-- Consultation Form -->
                <form method="POST" class="consultation-form" id="consultationForm">
                    <div class="form-container">
                        <!-- Medical Information Section -->
                        <div class="medical-info-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="symptoms" class="required-field">Symptoms:</label>
                                    <input type="text" id="symptoms" name="symptoms" class="form-control" 
                                           placeholder="Enter symptoms" 
                                           value="<?php echo htmlspecialchars($_POST['symptoms'] ?? ''); ?>" 
                                           required>
                                    <div class="error-message" id="symptomsError">Please enter symptoms</div>
                                </div>
                                <div class="form-group">
                                    <label for="temperature">Temperature:</label>
                                    <input type="text" id="temperature" name="temperature" class="form-control" 
                                           placeholder="e.g., 36.5°C" 
                                           value="<?php echo htmlspecialchars($_POST['temperature'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="diagnosis" class="required-field">Diagnosis:</label>
                                    <input type="text" id="diagnosis" name="diagnosis" class="form-control" 
                                           placeholder="Enter diagnosis" 
                                           value="<?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?>" 
                                           required>
                                    <div class="error-message" id="diagnosisError">Please enter diagnosis</div>
                                </div>
                                <div class="form-group">
                                    <label for="blood_pressure">Blood Pressure:</label>
                                    <input type="text" id="blood_pressure" name="blood_pressure" class="form-control" 
                                           placeholder="e.g., 120/80 mmHg" 
                                           value="<?php echo htmlspecialchars($_POST['blood_pressure'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="treatment">Treatment Given:</label>
                                    <input type="text" id="treatment" name="treatment" class="form-control" 
                                           placeholder="Enter treatment" 
                                           value="<?php echo htmlspecialchars($_POST['treatment'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="heart_rate">Heart Rate:</label>
                                    <input type="text" id="heart_rate" name="heart_rate" class="form-control" 
                                           placeholder="e.g., 72 bpm" 
                                           value="<?php echo htmlspecialchars($_POST['heart_rate'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="divider"></div>

                        <!-- Staff and Date Section -->
                        <div class="staff-date-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="attending_staff" class="required-field">Attending Staff:</label>
                                    <input type="text" id="attending_staff" name="attending_staff" class="form-control" 
                                           placeholder="Enter staff name" 
                                           value="<?php echo htmlspecialchars($_POST['attending_staff'] ?? ''); ?>" 
                                           required>
                                    <div class="error-message" id="staffError">Please enter attending staff name</div>
                                </div>
                                <div class="form-group">
                                    <label for="consultation_date" class="required-field">Consultation Date:</label>
                                    <input type="date" id="consultation_date" name="consultation_date" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['consultation_date'] ?? date('Y-m-d')); ?>" 
                                           required>
                                    <div class="error-message" id="dateError">Please select consultation date</div>
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="divider"></div>

                        <!-- Physician's Notes -->
                        <div class="physician-notes-section">
                            <div class="form-group">
                                <label for="physician_notes">Physician's notes:</label>
                                <textarea id="physician_notes" name="physician_notes" class="form-control" rows="4" 
                                          placeholder="Enter additional notes..."><?php echo htmlspecialchars($_POST['physician_notes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="form-actions">
                            <button type="submit" class="save-btn" id="submitBtn">
                                <i class="fas fa-save"></i> Save Consultation
                            </button>
                            <button type="reset" class="reset-btn">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </div>
                </form>

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

        // Form Validation and Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('consultationForm');
            const submitBtn = document.getElementById('submitBtn');
            const requiredFields = form.querySelectorAll('[required]');
            
            // Real-time validation
            requiredFields.forEach(field => {
                field.addEventListener('input', function() {
                    validateField(this);
                });
                
                field.addEventListener('blur', function() {
                    validateField(this);
                });
            });
            
            function validateField(field) {
                const errorElement = document.getElementById(field.id + 'Error');
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    if (errorElement) errorElement.style.display = 'block';
                    return false;
                } else {
                    field.classList.remove('is-invalid');
                    if (errorElement) errorElement.style.display = 'none';
                    return true;
                }
            }
            
            // Form submission
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
                
                return true;
            });
            
            // Auto-format inputs
            const tempInput = document.getElementById('temperature');
            if (tempInput) {
                tempInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && !value.includes('°')) {
                        value = value.replace(/[CF]$/i, '').trim();
                        this.value = value + '°C';
                    }
                });
            }
            
            const bpInput = document.getElementById('blood_pressure');
            if (bpInput) {
                bpInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && !value.toLowerCase().includes('mmhg')) {
                        value = value.replace(/[^0-9\/]/g, '');
                        if (value) this.value = value + ' mmHg';
                    }
                });
            }
            
            const hrInput = document.getElementById('heart_rate');
            if (hrInput) {
                hrInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && !value.toLowerCase().includes('bpm')) {
                        value = value.replace(/\D/g, '');
                        if (value) this.value = value + ' bpm';
                    }
                });
            }

            // Auto-hide success message after 10 seconds
            const successAlert = document.querySelector('.success-alert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 10000);
            }
        });
    </script>
</body>
</html>