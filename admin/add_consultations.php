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
$consultation_request = [];
try {
    $student_stmt = $pdo->prepare("
        SELECT si.*, u.email 
        FROM student_information si 
        JOIN users u ON si.student_number = u.student_number 
        WHERE si.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch the latest consultation request for this student
    if ($student) {
        $consultation_stmt = $pdo->prepare("
            SELECT cr.id, cr.requested, cr.notes, cr.date, cr.time, cr.status
            FROM consultation_requests cr
            JOIN users u ON cr.student_id = u.id
            JOIN student_information si ON u.student_number = si.student_number
            WHERE si.id = ? AND cr.status = 'Approved'
            ORDER BY cr.created_at DESC
            LIMIT 1
        ");
        $consultation_stmt->execute([$student_id]);
        $consultation_request = $consultation_stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Student fetch error: " . $e->getMessage());
}

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i');

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
        $consultation_date = trim($_POST['consultation_date'] ?? $current_date);
        $consultation_time = trim($_POST['consultation_time'] ?? $current_time);
        $physician_notes = trim($_POST['physician_notes'] ?? '');
        
        // Validate ALL fields are required
        if (empty($symptoms) || empty($temperature) || empty($diagnosis) || 
            empty($blood_pressure) || empty($treatment) || empty($heart_rate) || 
            empty($attending_staff) || empty($consultation_date) || empty($consultation_time) ||
            empty($physician_notes)) {
            throw new Exception("Please fill in ALL fields. All fields are required.");
        }
        
        if (!$student) {
            throw new Exception("Student information not found.");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert consultation record
        $insert_stmt = $pdo->prepare("
            INSERT INTO consultations (
                student_number, symptoms, temperature, diagnosis, 
                blood_pressure, treatment, heart_rate, attending_staff, 
                consultation_date, consultation_time, physician_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $consultation_time,
            $physician_notes
        ]);
        
        // Update consultation request status to 'Completed' if it exists
        if ($consultation_request && isset($consultation_request['id'])) {
            $update_stmt = $pdo->prepare("
                UPDATE consultation_requests 
                SET status = 'Completed' 
                WHERE id = ?
            ");
            $update_stmt->execute([$consultation_request['id']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Store success message in session and redirect
        $_SESSION['success_message'] = "Consultation record saved successfully and consultation marked as completed!";
        
        // Redirect to prevent form resubmission
        header("Location: add_consultation.php?id=" . $student_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
        error_log("Consultation save error: " . $e->getMessage());
    }
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
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
            background: 
                linear-gradient(90deg, 
                    #ffda6a 0%, 
                    #ffda6a 30%, 
                    #FFF5CC 70%, 
                    #ffffff 100%);
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
            color: #555;
        }

        .school-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0.2rem 0;
            color: #555;
        }

        .clinic-title {
            font-size: 0.85rem;
            opacity: 0.9;
            color: #555;
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

        /* Student Information Card - UPDATED */
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
            margin-bottom: 1.5rem;
        }

        .student-details span {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #6c757d;
        }

        /* Consultation Request Info - NEW */
        .consultation-request-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            border-left: 4px solid #28a745;
        }

        .consultation-request-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .request-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .request-item {
            display: flex;
            flex-direction: column;
        }

        .request-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .request-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .notes-container {
            grid-column: 1 / -1;
            margin-top: 1rem;
        }

        .notes-content {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 8px;
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

        /* Custom styles for datalist dropdown */
        .staff-datalist-container {
            position: relative;
        }

        .staff-datalist-container input {
            width: 100%;
        }

        .staff-datalist-container::after {
            content: '\f0d7';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
        }

        /* Symptoms & Diagnosis Autocomplete Styles */
        .symptoms-autocomplete, .diagnosis-autocomplete, .treatment-autocomplete {
            position: relative;
        }

        .symptoms-suggestions, .diagnosis-suggestions, .treatment-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            display: none;
        }

        .symptoms-suggestions.active, .diagnosis-suggestions.active, .treatment-suggestions.active {
            display: block;
        }

        .suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .suggestion-item:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .symptom-english, .diagnosis-english, .treatment-english {
            font-weight: 600;
            color: #2c3e50;
        }

        .symptom-tagalog, .diagnosis-tagalog, .treatment-tagalog {
            font-size: 0.85rem;
            color: #6c757d;
            font-style: italic;
        }

        .symptom-category, .diagnosis-category, .treatment-category {
            font-size: 0.75rem;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        .language-indicator {
            font-size: 0.7rem;
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            padding: 2px 6px;
            border-radius: 8px;
            margin-left: 5px;
        }

        /* Treatment Suggestions Styles */
        .treatment-suggestions {
            z-index: 1001;
        }

        .treatment-suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .treatment-suggestion-item:hover {
            background: rgba(40, 167, 69, 0.1);
        }

        .treatment-suggestion-item:last-child {
            border-bottom: none;
        }

        .treatment-suggestion-header {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .treatment-suggestion-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .treatment-category-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
        }

        /* Auto-filled Treatment Notice */
        .auto-treatment-notice {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #0066cc;
            display: none;
        }

        .auto-treatment-notice i {
            color: #28a745;
            margin-right: 5px;
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

            .request-details {
                grid-template-columns: 1fr;
                gap: 0.75rem;
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
                        <a href="approved_consultations.php" class="submenu-item">
                            <i class="fas fa-check-circle"></i>
                            Approved Consultations
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
                        <a href="approved_consultations.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Approved Consultations
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

                    <!-- Consultation Request Information - NEW -->
                    <?php if ($consultation_request): ?>
                        <div class="consultation-request-info">
                            <div class="consultation-request-title">
                                <i class="fas fa-info-circle"></i>
                                Student's Consultation Request
                            </div>
                            <div class="request-details">
                                <div class="request-item">
                                    <span class="request-label">Reason/Concern:</span>
                                    <span class="request-value"><?php echo htmlspecialchars($consultation_request['requested']); ?></span>
                                </div>
                                <div class="request-item">
                                    <span class="request-label">Scheduled Date:</span>
                                    <span class="request-value"><?php echo date('M d, Y', strtotime($consultation_request['date'])); ?></span>
                                </div>
                                <div class="request-item">
                                    <span class="request-label">Scheduled Time:</span>
                                    <span class="request-value"><?php echo date('g:i A', strtotime($consultation_request['time'])); ?></span>
                                </div>
                                <div class="request-item">
                                    <span class="request-label">Status:</span>
                                    <span class="request-value badge bg-success"><?php echo htmlspecialchars($consultation_request['status']); ?></span>
                                </div>
                                <?php if (!empty($consultation_request['notes'])): ?>
                                    <div class="request-item notes-container">
                                        <span class="request-label">Additional Notes:</span>
                                        <div class="notes-content">
                                            <?php echo htmlspecialchars($consultation_request['notes']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No approved consultation request found for this student.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Consultation Form -->
                <form method="POST" class="consultation-form" id="consultationForm">
                    <div class="form-container">
                        <!-- Medical Information Section -->
                        <div class="medical-info-section">
                            <div class="form-row">
                                <div class="form-group symptoms-autocomplete">
                                    <label for="symptoms" class="required-field">Symptoms:</label>
                                    <input type="text" id="symptoms" name="symptoms" class="form-control" 
                                           placeholder="Type symptoms (English or Tagalog)" 
                                           value="" 
                                           autocomplete="off"
                                           required>
                                    <div class="symptoms-suggestions" id="symptomsSuggestions"></div>
                                    <div class="error-message" id="symptomsError">Please enter symptoms</div>
                                </div>
                                <div class="form-group">
                                    <label for="temperature" class="required-field">Temperature:</label>
                                    <input type="text" id="temperature" name="temperature" class="form-control" 
                                           placeholder="e.g., 36.5°C" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="temperatureError">Please enter temperature</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group diagnosis-autocomplete">
                                    <label for="diagnosis" class="required-field">Diagnosis:</label>
                                    <input type="text" id="diagnosis" name="diagnosis" class="form-control" 
                                           placeholder="Type diagnosis (English or Tagalog)" 
                                           value="" 
                                           autocomplete="off"
                                           required>
                                    <div class="diagnosis-suggestions" id="diagnosisSuggestions"></div>
                                    <div class="error-message" id="diagnosisError">Please enter diagnosis</div>
                                </div>
                                <div class="form-group">
                                    <label for="blood_pressure" class="required-field">Blood Pressure:</label>
                                    <input type="text" id="blood_pressure" name="blood_pressure" class="form-control" 
                                           placeholder="e.g., 120/80 mmHg" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="bloodPressureError">Please enter blood pressure</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group treatment-autocomplete">
                                    <label for="treatment" class="required-field">Treatment Given:</label>
                                    <input type="text" id="treatment" name="treatment" class="form-control" 
                                           placeholder="Treatment will be auto-filled when diagnosis is selected" 
                                           value="" 
                                           autocomplete="off"
                                           required
                                           style="background-color: #f8f9fa;">
                                    <div class="auto-treatment-notice" id="autoTreatmentNotice">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Treatment auto-filled based on diagnosis. You can modify if needed.</span>
                                    </div>
                                    <div class="treatment-suggestions" id="treatmentSuggestions"></div>
                                    <div class="error-message" id="treatmentError">Please enter treatment given</div>
                                </div>
                                <div class="form-group">
                                    <label for="heart_rate" class="required-field">Heart Rate:</label>
                                    <input type="text" id="heart_rate" name="heart_rate" class="form-control" 
                                           placeholder="e.g., 72 bpm" 
                                           value="" 
                                           required>
                                    <div class="error-message" id="heartRateError">Please enter heart rate</div>
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
                                    <div class="staff-datalist-container">
                                        <input type="text" id="attending_staff" name="attending_staff" class="form-control" 
                                               list="staff_list" 
                                               placeholder="Select or type staff name" 
                                               value="Mary Rose Valencerina" 
                                               required>
                                        <datalist id="staff_list">
                                            <option value="Mary Rose Valencerina">
                                        </datalist>
                                    </div>
                                    <div class="error-message" id="staffError">Please enter attending staff name</div>
                                </div>
                                <div class="form-group">
                                    <label for="consultation_date" class="required-field">Consultation Date:</label>
                                    <input type="date" id="consultation_date" name="consultation_date" class="form-control" 
                                           value="<?php echo $current_date; ?>" 
                                           required>
                                    <div class="error-message" id="dateError">Please select consultation date</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="consultation_time" class="required-field">Consultation Time:</label>
                                    <input type="time" id="consultation_time" name="consultation_time" class="form-control" 
                                           value="<?php echo $current_time; ?>" 
                                           required>
                                    <div class="error-message" id="timeError">Please select consultation time</div>
                                </div>
                                <div class="form-group">
                                    <!-- Spacer to maintain grid layout -->
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="divider"></div>

                        <!-- Physician's Notes -->
                        <div class="physician-notes-section">
                            <div class="form-group">
                                <label for="physician_notes" class="required-field">Physician's notes:</label>
                                <textarea id="physician_notes" name="physician_notes" class="form-control" rows="4" 
                                          placeholder="Enter additional notes..." required></textarea>
                                <div class="error-message" id="physicianNotesError">Please enter physician's notes</div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="form-actions">
                            <button type="submit" class="save-btn" id="submitBtn">
                                <i class="fas fa-save"></i> Save Consultation
                            </button>
                            <button type="reset" class="reset-btn" id="resetBtn">
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
        // COMPREHENSIVE SYMPTOMS DICTIONARY
        const symptomsDictionary = [
            // General Symptoms
            { english: "Fever", tagalog: "Lagnat", category: "General" },
   { english: "Fever", tagalog: "Lagnat", category: "General" },
    { english: "Fatigue", tagalog: "Pagod", category: "General" },
    { english: "Weakness", tagalog: "Panghihina", category: "General" },
    { english: "Dizziness", tagalog: "Pagkahilo", category: "General" },
    { english: "Chills", tagalog: "Panlalamig", category: "General" },
    { english: "Sweating", tagalog: "Pawis", category: "General" },
    { english: "Weight loss", tagalog: "Pagbawas ng timbang", category: "General" },
    { english: "Weight gain", tagalog: "Pagdagdag ng timbang", category: "General" },
    { english: "Loss of appetite", tagalog: "Kawalan ng gana kumain", category: "General" },
    { english: "Fever", tagalog: "Lagnat", category: "General" },
    { english: "Headache", tagalog: "Sakit ng ulo", category: "General" },
    { english: "Fatigue", tagalog: "Pagod", category: "General" },
    { english: "Weakness", tagalog: "Panghihina", category: "General" },
    { english: "Dizziness", tagalog: "Pagkahilo", category: "General" },
    { english: "Sweating", tagalog: "Pawis", category: "General" },
    { english: "Weight loss", tagalog: "Pagbawas ng timbang", category: "General" },
    { english: "Weight gain", tagalog: "Pagdagdag ng timbang", category: "General" },
    { english: "Loss of appetite", tagalog: "Kawalan ng gana kumain", category: "General" },
    { english: "Increased appetite", tagalog: "Pagdagdag ng gana kumain", category: "General" },
    { english: "Dehydration", tagalog: "Dehydration", category: "General" },
    { english: "Swelling", tagalog: "Pamamaga", category: "General" },
    { english: "Malaise", tagalog: "Panghihina ng katawan", category: "General" },
    { english: "Lethargy", tagalog: "Pagkaantok", category: "General" },
    { english: "Insomnia", tagalog: "Kawalan ng tulog", category: "General" },
    { english: "Excessive sleepiness", tagalog: "Sobrang antok", category: "General" },
    { english: "Night sweats", tagalog: "Pawis sa gabi", category: "General" },
    { english: "Hot flashes", tagalog: "Biglaang init ng katawan", category: "General" },
    { english: "Body aches", tagalog: "Pananakit ng katawan", category: "General" },

     // ITCHING & SKIN-RELATED SYMPTOMS (30+ symptoms)
    { english: "Itching", tagalog: "Pangangati", category: "Skin" },
    { english: "Itchy skin", tagalog: "Makating balat", category: "Skin" },
    { english: "Severe itching", tagalog: "Malubhang pangangati", category: "Skin" },
    { english: "Generalized itching", tagalog: "Pangangati sa buong katawan", category: "Skin" },
    { english: "Localized itching", tagalog: "Pangangati sa iisang parte", category: "Skin" },
    { english: "Itchy scalp", tagalog: "Makating anit", category: "Skin" },
    { english: "Itchy face", tagalog: "Makating mukha", category: "Skin" },
    { english: "Itchy eyes", tagalog: "Makating mata", category: "Skin" },
    { english: "Itchy nose", tagalog: "Makating ilong", category: "Skin" },
    { english: "Itchy ears", tagalog: "Makating tainga", category: "Skin" },
    { english: "Itchy throat", tagalog: "Makating lalamunan", category: "Skin" },
    { english: "Itchy hands", tagalog: "Makating kamay", category: "Skin" },
    { english: "Itchy feet", tagalog: "Makating paa", category: "Skin" },
    { english: "Itchy legs", tagalog: "Makating binti", category: "Skin" },
    { english: "Itchy arms", tagalog: "Makating braso", category: "Skin" },
    { english: "Itchy back", tagalog: "Makating likod", category: "Skin" },
    { english: "Itchy chest", tagalog: "Makating dibdib", category: "Skin" },
    { english: "Itchy abdomen", tagalog: "Makating tiyan", category: "Skin" },
    { english: "Itchy groin", tagalog: "Makating singit", category: "Skin" },
    { english: "Itchy private parts", tagalog: "Makating ari", category: "Skin" },
    { english: "Itchy anus", tagalog: "Makating puwit", category: "Skin" },
    { english: "Nighttime itching", tagalog: "Pangangati sa gabi", category: "Skin" },
    { english: "Itching after shower", tagalog: "Pangangati pagkatapos maligo", category: "Skin" },
    { english: "Itching with rash", tagalog: "Pangangati na may pantal", category: "Skin" },
    { english: "Itching without rash", tagalog: "Pangangati na walang pantal", category: "Skin" },
    { english: "Itching with redness", tagalog: "Pangangati na may pamumula", category: "Skin" },
    { english: "Itching with bumps", tagalog: "Pangangati na may mga bukol", category: "Skin" },
    { english: "Itching with dryness", tagalog: "Pangangati na may panunuyo", category: "Skin" },
    { english: "Itching that worsens at night", tagalog: "Pangangati na lumalala sa gabi", category: "Skin" },
    { english: "Uncontrollable itching", tagalog: "Hindi mapigil na pangangati", category: "Skin" },

    // Skin Symptoms (additional)
    { english: "Rash", tagalog: "Pantal", category: "Skin" },
    { english: "Hives", tagalog: "Pamamantal", category: "Skin" },
    { english: "Redness", tagalog: "Pamamula", category: "Skin" },
    { english: "Bruising", tagalog: "Pasa", category: "Skin" },
    { english: "Dry skin", tagalog: "Tuyong balat", category: "Skin" },
    { english: "Oily skin", tagalog: "Madulas na balat", category: "Skin" },
    { english: "Acne", tagalog: "Pimples", category: "Skin" },
    { english: "Pimples", tagalog: "Pimples", category: "Skin" },
    { english: "Boils", tagalog: "Pigsa", category: "Skin" },
    { english: "Blister", tagalog: "Paltos", category: "Skin" },
    { english: "Bumps", tagalog: "Mga bukol", category: "Skin" },
    { english: "Lesions", tagalog: "Sugat sa balat", category: "Skin" },
    { english: "Skin peeling", tagalog: "Nagluluop na balat", category: "Skin" },
    { english: "Skin discoloration", tagalog: "Pagbabago ng kulay ng balat", category: "Skin" },
    { english: "Dark spots", tagalog: "Maitim na spots", category: "Skin" },
    { english: "Moles", tagalog: "Nunal", category: "Skin" },
    { english: "Skin irritation", tagalog: "Pangangati ng balat", category: "Skin" },
    { english: "Skin sensitivity", tagalog: "Sensitibong balat", category: "Skin" },
    { english: "Flaking skin", tagalog: "Naglulupang balat", category: "Skin" },
    { english: "Scaling skin", tagalog: "Nagkakaliskis na balat", category: "Skin" },
    { english: "Cracked skin", tagalog: "Bitak-bitak na balat", category: "Skin" },

    // Respiratory Symptoms (40)
    { english: "Cough", tagalog: "Ubo", category: "Respiratory" },
    { english: "Dry cough", tagalog: "Tuyong ubo", category: "Respiratory" },
    { english: "Productive cough", tagalog: "Ubo na may plema", category: "Respiratory" },
    { english: "Shortness of breath", tagalog: "Hirap sa paghinga", category: "Respiratory" },
    { english: "Difficulty breathing", tagalog: "Hirap huminga", category: "Respiratory" },
    { english: "Chest pain", tagalog: "Pananakit ng dibdib", category: "Respiratory" },
    { english: "Chest tightness", tagalog: "Paninikip ng dibdib", category: "Respiratory" },
    { english: "Wheezing", tagalog: "Hingal", category: "Respiratory" },
    { english: "Sneezing", tagalog: "Pagbahing", category: "Respiratory" },
    { english: "Runny nose", tagalog: "Sipon", category: "Respiratory" },
    { english: "Stuffy nose", tagalog: "Baradong ilong", category: "Respiratory" },
    { english: "Nasal congestion", tagalog: "Baradong ilong", category: "Respiratory" },
    { english: "Sore throat", tagalog: "Masakit na lalamunan", category: "Respiratory" },
    { english: "Hoarse voice", tagalog: "Pamamalat", category: "Respiratory" },
    { english: "Loss of voice", tagalog: "Pamamalat ng boses", category: "Respiratory" },
    { english: "Difficulty swallowing", tagalog: "Hirap lumunok", category: "Respiratory" },
    { english: "Rapid breathing", tagalog: "Mabilis na paghinga", category: "Respiratory" },
    { english: "Shallow breathing", tagalog: "Mababaw na paghinga", category: "Respiratory" },

    // Gastrointestinal Symptoms (50)
    { english: "Abdominal pain", tagalog: "Pananakit ng tiyan", category: "Gastrointestinal" },
    { english: "Stomach ache", tagalog: "Pananakit ng tiyan", category: "Gastrointestinal" },
    { english: "Nausea", tagalog: "Pagduduwal", category: "Gastrointestinal" },
    { english: "Vomiting", tagalog: "Pagsusuka", category: "Gastrointestinal" },
    { english: "Diarrhea", tagalog: "Pagtatae", category: "Gastrointestinal" },
    { english: "Constipation", tagalog: "Pagtitibi", category: "Gastrointestinal" },
    { english: "Bloating", tagalog: "Kabag", category: "Gastrointestinal" },
    { english: "Gas", tagalog: "Kabag", category: "Gastrointestinal" },
    { english: "Indigestion", tagalog: "Indigestion", category: "Gastrointestinal" },
    { english: "Heartburn", tagalog: "Kabag sa dibdib", category: "Gastrointestinal" },
    { english: "Acid reflux", tagalog: "Acid reflux", category: "Gastrointestinal" },
    { english: "Loss of appetite", tagalog: "Kawalan ng gana kumain", category: "Gastrointestinal" },
    { english: "Excessive thirst", tagalog: "Labis na uhaw", category: "Gastrointestinal" },
    { english: "Excessive hunger", tagalog: "Labis na gutom", category: "Gastrointestinal" },
    { english: "Blood in stool", tagalog: "Dugo sa dumi", category: "Gastrointestinal" },
    { english: "Black stools", tagalog: "Itim na dumi", category: "Gastrointestinal" },
    { english: "Pale stools", tagalog: "Maputlang dumi", category: "Gastrointestinal" },
    { english: "Mucus in stool", tagalog: "Plema sa dumi", category: "Gastrointestinal" },
    { english: "Difficulty swallowing", tagalog: "Hirap lumunok", category: "Gastrointestinal" },
    { english: "Painful swallowing", tagalog: "Masakit lumunok", category: "Gastrointestinal" },
    
    // Musculoskeletal Symptoms (40)
    { english: "Joint pain", tagalog: "Pananakit ng kasukasuan", category: "Musculoskeletal" },
    { english: "Arthritis", tagalog: "Arthritis", category: "Musculoskeletal" },
    { english: "Back pain", tagalog: "Pananakit ng likod", category: "Musculoskeletal" },
    { english: "Lower back pain", tagalog: "Pananakit ng balakang", category: "Musculoskeletal" },
    { english: "Neck pain", tagalog: "Pananakit ng leeg", category: "Musculoskeletal" },
    { english: "Shoulder pain", tagalog: "Pananakit ng balikat", category: "Musculoskeletal" },
    { english: "Muscle pain", tagalog: "Pananakit ng kalamnan", category: "Musculoskeletal" },
    { english: "Muscle weakness", tagalog: "Panghihina ng kalamnan", category: "Musculoskeletal" },
    { english: "Muscle cramps", tagalog: "Pulikat", category: "Musculoskeletal" },
    { english: "Muscle spasms", tagalog: "Pulikat", category: "Musculoskeletal" },
    { english: "Stiffness", tagalog: "Paninigas", category: "Musculoskeletal" },
    { english: "Swelling", tagalog: "Pamamaga", category: "Musculoskeletal" },
    { english: "Limited movement", tagalog: "Limitadong galaw", category: "Musculoskeletal" },
    { english: "Tenderness", tagalog: "Pananakit kapag dinidiin", category: "Musculoskeletal" },
    { english: "Bone pain", tagalog: "Pananakit ng buto", category: "Musculoskeletal" },

    // Neurological Symptoms (40)
    { english: "Dizziness", tagalog: "Pagkahilo", category: "Neurological" },
    { english: "Vertigo", tagalog: "Pagkahilo", category: "Neurological" },
    { english: "Fainting", tagalog: "Pagkahimatay", category: "Neurological" },
    { english: "Confusion", tagalog: "Pagkalito", category: "Neurological" },
    { english: "Memory loss", tagalog: "Pagkawala ng memorya", category: "Neurological" },
    { english: "Difficulty concentrating", tagalog: "Hirap mag-concentrate", category: "Neurological" },
    { english: "Numbness", tagalog: "Pamanhid", category: "Neurological" },
    { english: "Tingling", tagalog: "Pangiki", category: "Neurological" },
    { english: "Burning sensation", tagalog: "Pangangapoy", category: "Neurological" },
    { english: "Seizures", tagalog: "Pangingisay", category: "Neurological" },
    { english: "Tremors", tagalog: "Panginginig", category: "Neurological" },
    { english: "Twitching", tagalog: "Pamimilipit", category: "Neurological" },
    { english: "Loss of balance", tagalog: "Kawalan ng balanse", category: "Neurological" },
    { english: "Coordination problems", tagalog: "Problema sa koordinasyon", category: "Neurological" },
    { english: "Speech difficulties", tagalog: "Hirap magsalita", category: "Neurological" },
    { english: "Slurred speech", tagalog: "Malabong pagsasalita", category: "Neurological" },

    // Eye Symptoms (30)
    { english: "Blurred vision", tagalog: "Malabong paningin", category: "Eye" },
    { english: "Double vision", tagalog: "Dobleng paningin", category: "Eye" },
    { english: "Eye pain", tagalog: "Pananakit ng mata", category: "Eye" },
    { english: "Red eyes", tagalog: "Pamamula ng mata", category: "Eye" },
    { english: "Itchy eyes", tagalog: "Makating mata", category: "Eye" },
    { english: "Watery eyes", tagalog: "Matubig na mata", category: "Eye" },
    { english: "Dry eyes", tagalog: "Tuyong mata", category: "Eye" },
    { english: "Sensitivity to light", tagalog: "Sensitibo sa liwanag", category: "Eye" },
    { english: "Eye discharge", tagalog: "Nana sa mata", category: "Eye" },
    { english: "Swollen eyes", tagalog: "Namamagang mata", category: "Eye" },
    { english: "Dark circles", tagalog: "Maitim na bilog sa mata", category: "Eye" },
    { english: "Floaters", tagalog: "Mga lumulutang sa paningin", category: "Eye" },
    { english: "Flashing lights", tagalog: "Kumikislap na ilaw", category: "Eye" },

    // Ear Symptoms (25)
    { english: "Ear pain", tagalog: "Pananakit ng tainga", category: "Ear" },
    { english: "Earache", tagalog: "Pananakit ng tainga", category: "Ear" },
    { english: "Ear discharge", tagalog: "Dugo o nana sa tainga", category: "Ear" },
    { english: "Ringing in ears", tagalog: "Pag-ingay sa tainga", category: "Ear" },
    { english: "Hearing loss", tagalog: "Pagbaba ng pandinig", category: "Ear" },
    { english: "Decreased hearing", tagalog: "Pagbaba ng pandinig", category: "Ear" },
    { english: "Ear fullness", tagalog: "Parang may baradong tainga", category: "Ear" },
    { english: "Itchy ears", tagalog: "Makating tainga", category: "Ear" },
    { english: "Dizziness", tagalog: "Pagkahilo", category: "Ear" },
    { english: "Vertigo", tagalog: "Pagkahilo", category: "Ear" },

    // Mental Health Symptoms (30)
    { english: "Anxiety", tagalog: "Pagkabalisa", category: "Mental Health" },
    { english: "Depression", tagalog: "Depression", category: "Mental Health" },
    { english: "Stress", tagalog: "Stress", category: "Mental Health" },
    { english: "Irritability", tagalog: "Pagkairita", category: "Mental Health" },
    { english: "Mood swings", tagalog: "Pagbabago-bago ng mood", category: "Mental Health" },
    { english: "Anger", tagalog: "Galit", category: "Mental Health" },
    { english: "Sadness", tagalog: "Kalungkutan", category: "Mental Health" },
    { english: "Crying spells", tagalog: "Biglaang pag-iyak", category: "Mental Health" },
    { english: "Lack of motivation", tagalog: "Kawalan ng motibasyon", category: "Mental Health" },
    { english: "Loss of interest", tagalog: "Kawalan ng interes", category: "Mental Health" },
    { english: "Social withdrawal", tagalog: "Pag-iwas sa pakikisalamuha", category: "Mental Health" },
    { english: "Panic attacks", tagalog: "Atake ng sindak", category: "Mental Health" },
    { english: "Phobias", tagalog: "Takot", category: "Mental Health" },

    // Urinary Symptoms (25)
    { english: "Frequent urination", tagalog: "Madalas na pag-ihi", category: "Urinary" },
    { english: "Painful urination", tagalog: "Masakit na pag-ihi", category: "Urinary" },
    { english: "Burning urination", tagalog: "Mainit na pag-ihi", category: "Urinary" },
    { english: "Blood in urine", tagalog: "Dugo sa ihi", category: "Urinary" },
    { english: "Cloudy urine", tagalog: "Malabong ihi", category: "Urinary" },
    { english: "Strong urine odor", tagalog: "Mabahong ihi", category: "Urinary" },
    { english: "Urinary incontinence", tagalog: "Hindi mapigilang pag-ihi", category: "Urinary" },
    { english: "Difficulty urinating", tagalog: "Hirap umihi", category: "Urinary" },
    { english: "Urgency", tagalog: "Biglaang pangangailangan umihi", category: "Urinary" },
    { english: "Nocturia", tagalog: "Madalas na pag-ihi sa gabi", category: "Urinary" },

    // Women's Health (30)
    { english: "Menstrual cramps", tagalog: "Pananakit ng regla", category: "Women's Health" },
    { english: "Irregular periods", tagalog: "Iregular na regla", category: "Women's Health" },
    { english: "Heavy bleeding", tagalog: "Malakas na pagdurugo", category: "Women's Health" },
    { english: "Light bleeding", tagalog: "Mahinang pagdurugo", category: "Women's Health" },
    { english: "Missed period", tagalog: "Hindi pagdating ng regla", category: "Women's Health" },
    { english: "Vaginal discharge", tagalog: "Dugo o nana sa ari", category: "Women's Health" },
    { english: "Vaginal itching", tagalog: "Pangangati sa ari", category: "Women's Health" },
    { english: "Vaginal burning", tagalog: "Pangangapoy sa ari", category: "Women's Health" },
    { english: "Pain during intercourse", tagalog: "Pananakit sa pagtatalik", category: "Women's Health" },
    { english: "Breast pain", tagalog: "Pananakit ng suso", category: "Women's Health" },
    { english: "Breast lumps", tagalog: "Bukol sa suso", category: "Women's Health" },
    { english: "Nipple discharge", tagalog: "Dugo sa utong", category: "Women's Health" },

    // Men's Health (20)
    { english: "Erectile dysfunction", tagalog: "Kawalan ng paninigas", category: "Men's Health" },
    { english: "Premature ejaculation", tagalog: "Maagang pagputok", category: "Men's Health" },
    { english: "Testicular pain", tagalog: "Pananakit ng bayag", category: "Men's Health" },
    { english: "Testicular swelling", tagalog: "Pamamaga ng bayag", category: "Men's Health" },
    { english: "Penile discharge", tagalog: "Dugo sa ari ng lalaki", category: "Men's Health" },
    { english: "Painful ejaculation", tagalog: "Masakit na pagputok", category: "Men's Health" },

    // Cardiovascular Symptoms (20)
    { english: "Chest pain", tagalog: "Pananakit ng dibdib", category: "Cardiovascular" },
    { english: "Palpitations", tagalog: "Mabilis na tibok ng puso", category: "Cardiovascular" },
    { english: "Rapid heartbeat", tagalog: "Mabilis na pagtibok ng puso", category: "Cardiovascular" },
    { english: "Irregular heartbeat", tagalog: "Iregular na pagtibok ng puso", category: "Cardiovascular" },
    { english: "Slow heartbeat", tagalog: "Mabagal na pagtibok ng puso", category: "Cardiovascular" },
    { english: "High blood pressure", tagalog: "Mataas na presyon ng dugo", category: "Cardiovascular" },
    { english: "Low blood pressure", tagalog: "Mababang presyon ng dugo", category: "Cardiovascular" },
    { english: "Swelling of legs", tagalog: "Pamamaga ng paa", category: "Cardiovascular" },
    { english: "Swelling of ankles", tagalog: "Pamamaga ng bukong-bukong", category: "Cardiovascular" },

    // Common Illness Symptoms (20)
    { english: "Flu-like symptoms", tagalog: "Sintomas ng trangkaso", category: "Common Illness" },
    { english: "Cold symptoms", tagalog: "Sintomas ng sipon", category: "Common Illness" },
    { english: "Allergy symptoms", tagalog: "Sintomas ng allergy", category: "Common Illness" },
    { english: "COVID-19 symptoms", tagalog: "Sintomas ng COVID-19", category: "Common Illness" },
    { english: "Dengue symptoms", tagalog: "Sintomas ng dengue", category: "Common Illness" },
    { english: "Typhoid symptoms", tagalog: "Sintomas ng tipus", category: "Common Illness" },
    { english: "Malaria symptoms", tagalog: "Sintomas ng malaria", category: "Common Illness" }
        ];

        // DIAGNOSIS DICTIONARY - EXACTLY AS YOU PROVIDED
        const diagnosisDictionary = [
            // Infectious Diseases
            { english: "Common Cold", tagalog: "Sipon", category: "Infectious Diseases" },
            { english: "Influenza", tagalog: "Trangkaso", category: "Infectious Diseases" },
            { english: "COVID-19", tagalog: "COVID-19", category: "Infectious Diseases" },
            { english: "Dengue Fever", tagalog: "Dengue", category: "Infectious Diseases" },
            { english: "Typhoid Fever", tagalog: "Tipus", category: "Infectious Diseases" },
            { english: "Urinary Tract Infection", tagalog: "Impeksyon sa ihi", category: "Infectious Diseases" },
            { english: "Upper Respiratory Infection", tagalog: "Impeksyon sa baga", category: "Infectious Diseases" },
            { english: "Pneumonia", tagalog: "Pulmonya", category: "Infectious Diseases" },
            { english: "Bronchitis", tagalog: "Bronkitis", category: "Infectious Diseases" },
            { english: "Tuberculosis", tagalog: "Tuberkulosis", category: "Infectious Diseases" },
            { english: "Gastroenteritis", tagalog: "Tigyawat ng tiyan", category: "Infectious Diseases" },
            { english: "Food Poisoning", tagalog: "Pagkakaroon ng lason sa kinain", category: "Infectious Diseases" },
            { english: "Chickenpox", tagalog: "Bulutong-tubig", category: "Infectious Diseases" },
            { english: "Measles", tagalog: "Tigdas", category: "Infectious Diseases" },
            { english: "Mumps", tagalog: "Beke", category: "Infectious Diseases" },

            // Respiratory Conditions
            { english: "Asthma", tagalog: "Hika", category: "Respiratory" },
            { english: "Allergic Rhinitis", tagalog: "Allergy sa ilong", category: "Respiratory" },
            { english: "Chronic Obstructive Pulmonary Disease", tagalog: "Malalang sakit sa baga", category: "Respiratory" },
            { english: "Sinusitis", tagalog: "Impeksyon sa sinus", category: "Respiratory" },
            { english: "Tonsillitis", tagalog: "Impeksyon sa tonsil", category: "Respiratory" },
            { english: "Pharyngitis", tagalog: "Impeksyon sa lalamunan", category: "Respiratory" },
            { english: "Laryngitis", tagalog: "Pamamaga ng lalamunan", category: "Respiratory" },

            // Gastrointestinal Conditions
            { english: "Gastritis", tagalog: "Pamamaga ng sikmura", category: "Gastrointestinal" },
            { english: "Gastroesophageal Reflux Disease", tagalog: "Acid reflux", category: "Gastrointestinal" },
            { english: "Peptic Ulcer", tagalog: "Ulser sa sikmura", category: "Gastrointestinal" },
            { english: "Irritable Bowel Syndrome", tagalog: "Sindroma ng madaling magalit na bituka", category: "Gastrointestinal" },
            { english: "Constipation", tagalog: "Pagtitibi", category: "Gastrointestinal" },
            { english: "Diarrhea", tagalog: "Pagtatae", category: "Gastrointestinal" },
            { english: "Hemorrhoids", tagalog: "Almoranas", category: "Gastrointestinal" },
            { english: "Appendicitis", tagalog: "Pamamaga ng appendix", category: "Gastrointestinal" },

            // Musculoskeletal Conditions
            { english: "Arthritis", tagalog: "Artritis", category: "Musculoskeletal" },
            { english: "Osteoarthritis", tagalog: "Osteoarthritis", category: "Musculoskeletal" },
            { english: "Rheumatoid Arthritis", tagalog: "Rayumatikong artritis", category: "Musculoskeletal" },
            { english: "Osteoporosis", tagalog: "Osteoporosis", category: "Musculoskeletal" },
            { english: "Back Pain", tagalog: "Sakit sa likod", category: "Musculoskeletal" },
            { english: "Cervical Spondylosis", tagalog: "Sakit sa leeg", category: "Musculoskeletal" },
            { english: "Muscle Strain", tagalog: "Pilay sa kalamnan", category: "Musculoskeletal" },
            { english: "Sprain", tagalog: "Pilay", category: "Musculoskeletal" },
            { english: "Tendinitis", tagalog: "Pamamaga ng litid", category: "Musculoskeletal" },
            { english: "Carpal Tunnel Syndrome", tagalog: "Sindroma ng carpal tunnel", category: "Musculoskeletal" },

            // Neurological Conditions
            { english: "Migraine", tagalog: "Migraine", category: "Neurological" },
            { english: "Tension Headache", tagalog: "Tension headache", category: "Neurological" },
            { english: "Epilepsy", tagalog: "Epilepsy", category: "Neurological" },
            { english: "Vertigo", tagalog: "Vertigo", category: "Neurological" },
            { english: "Peripheral Neuropathy", tagalog: "Sakit sa ugat", category: "Neurological" },

            // Cardiovascular Conditions
            { english: "Hypertension", tagalog: "Mataas na presyon", category: "Cardiovascular" },
            { english: "Coronary Artery Disease", tagalog: "Sakit sa puso", category: "Cardiovascular" },
            { english: "Heart Failure", tagalog: "Pagkabigo ng puso", category: "Cardiovascular" },
            { english: "Arrhythmia", tagalog: "Iregular na tibok ng puso", category: "Cardiovascular" },
            { english: "Anemia", tagalog: "Anemia", category: "Cardiovascular" },
            { english: "Hyperlipidemia", tagalog: "Mataas na kolesterol", category: "Cardiovascular" },
            
            // Dermatological Conditions
            { english: "Acne Vulgaris", tagalog: "Pimples", category: "Dermatological" },
            { english: "Eczema", tagalog: "Eksema", category: "Dermatological" },
            { english: "Psoriasis", tagalog: "Psoriasis", category: "Dermatological" },
            { english: "Contact Dermatitis", tagalog: "Dermatitis", category: "Dermatological" },
            { english: "Urticaria", tagalog: "Pantal", category: "Dermatological" },
            { english: "Fungal Infection", tagalog: "Impeksyon ng halamang-singaw", category: "Dermatological" },
            { english: "Bacterial Skin Infection", tagalog: "Impeksyon ng balat", category: "Dermatological" },
            { english: "Viral Warts", tagalog: "Kulugo", category: "Dermatological" },

            // Mental Health Conditions
            { english: "Anxiety Disorder", tagalog: "Sakit sa pagkabalisa", category: "Mental Health" },
            { english: "Depression", tagalog: "Depression", category: "Mental Health" },
            { english: "Stress Reaction", tagalog: "Reaksyon sa stress", category: "Mental Health" },
            { english: "Insomnia", tagalog: "Kawalan ng tulog", category: "Mental Health" },
            { english: "Adjustment Disorder", tagalog: "Sakit sa pag-aadjust", category: "Mental Health" },

            // Endocrine/Metabolic Conditions
            { english: "Diabetes Mellitus", tagalog: "Diabetes", category: "Endocrine" },
            { english: "Hypothyroidism", tagalog: "Mababang thyroid", category: "Endocrine" },
            { english: "Hyperthyroidism", tagalog: "Mataas na thyroid", category: "Endocrine" },
            { english: "Obesity", tagalog: "Obesity", category: "Endocrine" },
            { english: "Metabolic Syndrome", tagalog: "Sindroma ng metaboliko", category: "Endocrine" },

            // Genitourinary Conditions
            { english: "Kidney Stones", tagalog: "Bato sa bato", category: "Genitourinary" },
            { english: "Urinary Tract Infection", tagalog: "Impeksyon sa ihi", category: "Genitourinary" },
            { english: "Benign Prostatic Hyperplasia", tagalog: "Pamamaga ng prostate", category: "Genitourinary" },
            { english: "Vaginitis", tagalog: "Impeksyon sa ari ng babae", category: "Genitourinary" },
            { english: "Dysmenorrhea", tagalog: "Masakit na regla", category: "Genitourinary" },

            // General Medical Conditions
            { english: "Dehydration", tagalog: "Dehydration", category: "General Medical" },
            { english: "Malnutrition", tagalog: "Malnutrisyon", category: "General Medical" },
            { english: "Vitamin Deficiency", tagalog: "Kakulangan sa bitamina", category: "General Medical" },
            { english: "Fatigue Syndrome", tagalog: "Sindroma ng pagkapagod", category: "General Medical" },
            { english: "Heat Stroke", tagalog: "Heat stroke", category: "General Medical" },
            { english: "Motion Sickness", tagalog: "Motion sickness", category: "General Medical" }
        ];

        // DIAGNOSIS TO TREATMENT MAPPING
        const diagnosisTreatmentMap = {
            // Infectious Diseases
            "Common Cold": "Paracetamol 500mg every 6 hours, Vitamin C 500mg once daily, Increase fluid intake, Bed rest",
            "Influenza": "Paracetamol 500mg every 6 hours, Ibuprofen 400mg every 8 hours, Bed rest, Increase fluid intake",
            "COVID-19": "Paracetamol 500mg every 6 hours, Vitamin C 500mg twice daily, Bed rest, Monitor oxygen saturation",
            "Dengue Fever": "Paracetamol 500mg every 6 hours (avoid NSAIDs), Increase fluid intake, Bed rest, Refer to hospital if severe",
            "Typhoid Fever": "Antibiotics as prescribed, Paracetamol for fever, Increase fluid intake, Bed rest",
            "Urinary Tract Infection": "Amoxicillin 500mg three times daily for 7 days, Increase fluid intake, Cranberry juice",
            "Upper Respiratory Infection": "Amoxicillin 500mg three times daily for 7 days, Paracetamol as needed, Steam inhalation",
            "Pneumonia": "Antibiotics as prescribed, Paracetamol for fever, Steam inhalation, Bed rest",
            "Bronchitis": "Bronchodilators if needed, Expectorants, Steam inhalation, Increase fluid intake",
            "Tuberculosis": "Refer to specialist for TB treatment, Nutritional support, Follow-up monitoring",
            "Gastroenteritis": "Oral rehydration solution, Avoid solid foods initially, Rest, Gradual diet reintroduction",
            "Food Poisoning": "Oral rehydration, Rest, Avoid dairy and fatty foods, Gradual diet reintroduction",
            "Chickenpox": "Calamine lotion for itching, Paracetamol for fever, Antihistamines for itching, Oatmeal baths",
            "Measles": "Paracetamol for fever, Vitamin A supplementation, Increase fluid intake, Rest",
            "Mumps": "Paracetamol for pain and fever, Cold compress for swelling, Soft foods, Rest",

            // Respiratory Conditions
            "Asthma": "Salbutamol inhaler 2 puffs every 4-6 hours as needed, Refer to specialist for long-term management",
            "Allergic Rhinitis": "Antihistamine once daily, Nasal saline spray as needed, Avoid allergens",
            "Chronic Obstructive Pulmonary Disease": "Bronchodilators, Oxygen therapy if needed, Pulmonary rehabilitation",
            "Sinusitis": "Nasal decongestants, Steam inhalation, Pain relievers, Increase fluid intake",
            "Tonsillitis": "Antibiotics if bacterial, Salt water gargle, Pain relievers, Soft foods",
            "Pharyngitis": "Salt water gargle, Lozenges, Pain relievers, Increase fluid intake",
            "Laryngitis": "Voice rest, Steam inhalation, Increase fluid intake, Avoid irritants",

            // Gastrointestinal Conditions
            "Gastritis": "Antacids after meals, Avoid spicy foods, Small frequent meals, Stress management",
            "Gastroesophageal Reflux Disease": "Antacids, Avoid trigger foods, Elevate head during sleep, Small meals",
            "Peptic Ulcer": "Acid reducers, Antibiotics if H. pylori, Avoid NSAIDs, Small frequent meals",
            "Irritable Bowel Syndrome": "Fiber supplements, Antispasmodics, Stress management, Dietary modifications",
            "Constipation": "Increase fiber intake, Increase water consumption, Stool softeners, Exercise",
            "Diarrhea": "Oral rehydration solution, BRAT diet, Avoid dairy, Rest",
            "Hemorrhoids": "Sitz baths, Topical creams, Stool softeners, Increase fiber intake",
            "Appendicitis": "Refer to hospital immediately for surgical evaluation, NPO, Pain management",

            // Musculoskeletal Conditions
            "Arthritis": "Pain relievers, Anti-inflammatory medications, Physical therapy, Joint protection",
            "Osteoarthritis": "Pain management, Physical therapy, Weight management, Assistive devices",
            "Rheumatoid Arthritis": "Refer to rheumatologist, Anti-inflammatory medications, Physical therapy",
            "Osteoporosis": "Calcium and Vitamin D supplementation, Weight-bearing exercise, Fall prevention",
            "Back Pain": "Pain relievers, Heat/cold therapy, Physical therapy, Proper lifting techniques",
            "Cervical Spondylosis": "Pain management, Neck exercises, Posture correction, Physical therapy",
            "Muscle Strain": "Rest, Ice, Compression, Elevation (RICE), Pain relievers, Gradual stretching",
            "Sprain": "RICE protocol, Pain management, Gradual mobilization, Physical therapy if severe",
            "Tendinitis": "Rest, Ice, Anti-inflammatory medications, Physical therapy, Gradual return to activity",
            "Carpal Tunnel Syndrome": "Wrist splinting, Anti-inflammatory medications, Ergonomic adjustments, Refer to specialist",

            // Neurological Conditions
            "Migraine": "Rest in dark room, Pain relievers, Triptans if prescribed, Avoid triggers",
            "Tension Headache": "Pain relievers, Stress management, Relaxation techniques, Proper posture",
            "Epilepsy": "Refer to neurologist, Anti-epileptic medications, Seizure precautions, Regular follow-up",
            "Vertigo": "Vestibular rehabilitation, Anti-vertigo medications, Positional maneuvers, Fall prevention",
            "Peripheral Neuropathy": "Pain management, Physical therapy, Safety precautions, Refer to neurologist",

            // Cardiovascular Conditions
            "Hypertension": "Lifestyle modifications, Regular monitoring, Medication adherence, Low-sodium diet",
            "Coronary Artery Disease": "Lifestyle changes, Medication management, Regular follow-up, Cardiac rehabilitation",
            "Heart Failure": "Medication management, Fluid restriction, Low-sodium diet, Regular monitoring",
            "Arrhythmia": "Refer to cardiologist, Medication management, Lifestyle modifications, Regular monitoring",
            "Anemia": "Iron supplementation, Vitamin B12 if deficient, Dietary modifications, Follow-up testing",
            "Hyperlipidemia": "Dietary modifications, Exercise, Medication if needed, Regular monitoring",

            // Dermatological Conditions
            "Acne Vulgaris": "Topical treatments, Proper skincare, Avoid picking, Refer to dermatologist if severe",
            "Eczema": "Moisturizers, Topical steroids, Avoid triggers, Gentle skincare",
            "Psoriasis": "Topical treatments, Moisturizers, Phototherapy, Refer to dermatologist",
            "Contact Dermatitis": "Avoid allergen, Topical steroids, Cool compresses, Antihistamines",
            "Urticaria": "Antihistamines, Avoid triggers, Cool compresses, Identify cause",
            "Fungal Infection": "Antifungal creams, Keep area dry, Good hygiene, Antifungal powder",
            "Bacterial Skin Infection": "Antibiotic creams, Keep clean and dry, Warm compresses, Oral antibiotics if severe",
            "Viral Warts": "Salicylic acid, Cryotherapy, Cover to prevent spread, Refer to dermatologist",

            // Mental Health Conditions
            "Anxiety Disorder": "Refer to mental health professional, Therapy, Relaxation techniques, Stress management",
            "Depression": "Refer to mental health professional, Therapy, Support system, Regular follow-up",
            "Stress Reaction": "Stress management techniques, Relaxation exercises, Adequate sleep, Support system",
            "Insomnia": "Sleep hygiene, Relaxation techniques, Regular sleep schedule, Avoid caffeine",
            "Adjustment Disorder": "Counseling, Support system, Stress management, Coping strategies",

            // Endocrine/Metabolic Conditions
            "Diabetes Mellitus": "Blood glucose monitoring, Dietary management, Medication adherence, Regular follow-up",
            "Hypothyroidism": "Thyroid hormone replacement, Regular monitoring, Medication adherence, Follow-up testing",
            "Hyperthyroidism": "Refer to endocrinologist, Anti-thyroid medications, Symptom management, Regular monitoring",
            "Obesity": "Dietary counseling, Exercise program, Behavioral modifications, Regular monitoring",
            "Metabolic Syndrome": "Lifestyle modifications, Weight management, Regular exercise, Medical monitoring",

            // Genitourinary Conditions
            "Kidney Stones": "Pain management, Increase fluid intake, Strain urine, Refer to urologist",
            "Urinary Tract Infection": "Antibiotics as prescribed, Increase fluid intake, Urinary analgesics, Follow-up",
            "Benign Prostatic Hyperplasia": "Medication management, Limit fluids before bed, Refer to urologist",
            "Vaginitis": "Appropriate antimicrobial treatment, Good hygiene, Avoid irritants, Follow-up",
            "Dysmenorrhea": "Pain relievers, Heat therapy, Regular exercise, Hormonal contraception if appropriate",

            // General Medical Conditions
            "Dehydration": "Oral rehydration solution, Increase fluid intake, Rest, Electrolyte replacement",
            "Malnutrition": "Nutritional counseling, Dietary supplementation, Regular monitoring, Multivitamins",
            "Vitamin Deficiency": "Specific vitamin supplementation, Dietary modifications, Follow-up testing",
            "Fatigue Syndrome": "Adequate rest, Balanced diet, Stress management, Gradual increase in activity",
            "Heat Stroke": "Cooling measures, Fluid replacement, Rest, Medical monitoring",
            "Motion Sickness": "Anti-emetics, Acupressure bands, Avoid reading in vehicle, Fresh air"
        };

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

        // LANGUAGE DETECTION FUNCTION
        function detectLanguage(text) {
            const tagalogWords = ['ng', 'sa', 'ang', 'mga', 'na', 'at', 'ay', 'si', 'ni', 'kay'];
            const tagalogCharacters = /[ñÑ]/;
            
            const words = text.toLowerCase().split(' ');
            const hasTagalogWord = words.some(word => tagalogWords.includes(word));
            const hasTagalogChar = tagalogCharacters.test(text);
            
            if (hasTagalogWord || hasTagalogChar) {
                return 'tagalog';
            }
            return 'english';
        }

        // AUTOCOMPLETE FUNCTIONALITY FOR SYMPTOMS AND DIAGNOSIS
        function setupAutocomplete(inputElement, suggestionsContainer, dictionary, fieldName) {
            inputElement.addEventListener('input', function() {
                const input = this.value.toLowerCase().trim();
                suggestionsContainer.innerHTML = '';
                
                if (input.length < 2) {
                    suggestionsContainer.classList.remove('active');
                    return;
                }

                const detectedLanguage = detectLanguage(input);
                let filteredItems = [];

                if (detectedLanguage === 'tagalog') {
                    filteredItems = dictionary.filter(item => 
                        item.tagalog.toLowerCase().includes(input)
                    );
                } else {
                    filteredItems = dictionary.filter(item => 
                        item.english.toLowerCase().includes(input)
                    );
                }

                if (filteredItems.length > 0) {
                    filteredItems.forEach(item => {
                        const suggestionItem = document.createElement('div');
                        suggestionItem.className = 'suggestion-item';
                        
                        if (detectedLanguage === 'tagalog') {
                            suggestionItem.innerHTML = `
                                <div>
                                    <span class="${fieldName}-tagalog">${item.tagalog}</span>
                                    <span class="${fieldName}-english">(${item.english})</span>
                                    <span class="language-indicator">Tagalog</span>
                                </div>
                                <span class="${fieldName}-category">${item.category}</span>
                            `;
                            
                            suggestionItem.addEventListener('click', function() {
                                inputElement.value = item.tagalog;
                                suggestionsContainer.classList.remove('active');
                                
                                // If this is diagnosis field, automatically fill treatment
                                if (fieldName === 'diagnosis') {
                                    autoFillTreatmentForDiagnosis(item.english);
                                }
                            });
                        } else {
                            suggestionItem.innerHTML = `
                                <div>
                                    <span class="${fieldName}-english">${item.english}</span>
                                    <span class="${fieldName}-tagalog">(${item.tagalog})</span>
                                    <span class="language-indicator">English</span>
                                </div>
                                <span class="${fieldName}-category">${item.category}</span>
                            `;
                            
                            suggestionItem.addEventListener('click', function() {
                                inputElement.value = item.english;
                                suggestionsContainer.classList.remove('active');
                                
                                // If this is diagnosis field, automatically fill treatment
                                if (fieldName === 'diagnosis') {
                                    autoFillTreatmentForDiagnosis(item.english);
                                }
                            });
                        }
                        
                        suggestionsContainer.appendChild(suggestionItem);
                    });
                    suggestionsContainer.classList.add('active');
                } else {
                    suggestionsContainer.classList.remove('active');
                }
            });

            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!inputElement.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.classList.remove('active');
                }
            });

            // Keyboard navigation
            inputElement.addEventListener('keydown', function(e) {
                const suggestions = suggestionsContainer.querySelectorAll('.suggestion-item');
                let activeSuggestion = suggestionsContainer.querySelector('.suggestion-item.active');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!activeSuggestion) {
                        suggestions[0]?.classList.add('active');
                    } else {
                        activeSuggestion.classList.remove('active');
                        const next = activeSuggestion.nextElementSibling || suggestions[0];
                        next.classList.add('active');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!activeSuggestion) {
                        suggestions[suggestions.length - 1]?.classList.add('active');
                    } else {
                        activeSuggestion.classList.remove('active');
                        const prev = activeSuggestion.previousElementSibling || suggestions[suggestions.length - 1];
                        prev.classList.add('active');
                    }
                } else if (e.key === 'Enter' && activeSuggestion) {
                    e.preventDefault();
                    const detectedLanguage = detectLanguage(inputElement.value);
                    if (detectedLanguage === 'tagalog') {
                        inputElement.value = activeSuggestion.querySelector(`.${fieldName}-tagalog`).textContent;
                    } else {
                        inputElement.value = activeSuggestion.querySelector(`.${fieldName}-english`).textContent;
                    }
                    suggestionsContainer.classList.remove('active');
                    
                    // If this is diagnosis field, automatically fill treatment
                    if (fieldName === 'diagnosis') {
                        const diagnosisText = activeSuggestion.querySelector(`.${fieldName}-english`).textContent;
                        autoFillTreatmentForDiagnosis(diagnosisText);
                    }
                } else if (e.key === 'Escape') {
                    suggestionsContainer.classList.remove('active');
                }
            });
        }

        // FUNCTION TO AUTO-FILL TREATMENT BASED ON DIAGNOSIS
        function autoFillTreatmentForDiagnosis(diagnosis) {
            const treatmentInput = document.getElementById('treatment');
            const autoTreatmentNotice = document.getElementById('autoTreatmentNotice');
            
            if (!treatmentInput) return;
            
            const treatment = diagnosisTreatmentMap[diagnosis];
            
            if (treatment) {
                // Fill the treatment field
                treatmentInput.value = treatment;
                
                // Make the field editable and change background color
                treatmentInput.readOnly = false;
                treatmentInput.style.backgroundColor = '#ffffff';
                treatmentInput.placeholder = "You can modify the auto-filled treatment if needed";
                
                // Show the notice
                autoTreatmentNotice.style.display = 'block';
                
                // Scroll to treatment field for visibility
                treatmentInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                // If no treatment mapping found, enable the field for manual entry
                treatmentInput.value = "";
                treatmentInput.readOnly = false;
                treatmentInput.style.backgroundColor = '#ffffff';
                treatmentInput.placeholder = "Type treatment (English or Tagalog)";
                autoTreatmentNotice.style.display = 'none';
            }
        }

        // Initialize autocomplete for symptoms and diagnosis fields
        document.addEventListener('DOMContentLoaded', function() {
            const symptomsInput = document.getElementById('symptoms');
            const symptomsSuggestions = document.getElementById('symptomsSuggestions');
            const diagnosisInput = document.getElementById('diagnosis');
            const diagnosisSuggestions = document.getElementById('diagnosisSuggestions');

            if (symptomsInput && symptomsSuggestions) {
                setupAutocomplete(symptomsInput, symptomsSuggestions, symptomsDictionary, 'symptom');
            }
            if (diagnosisInput && diagnosisSuggestions) {
                setupAutocomplete(diagnosisInput, diagnosisSuggestions, diagnosisDictionary, 'diagnosis');
            }

            // Form Validation and Functionality
            const form = document.getElementById('consultationForm');
            const submitBtn = document.getElementById('submitBtn');
            const resetBtn = document.getElementById('resetBtn');
            const requiredFields = form.querySelectorAll('[required]');
            let formSubmitted = false;
            
            // Function to validate all required fields
            function validateAllFields() {
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                return isValid;
            }
            
            // Real-time validation only after first submit attempt
            requiredFields.forEach(field => {
                field.addEventListener('input', function() {
                    if (formSubmitted) {
                        validateField(this);
                    }
                });
                
                field.addEventListener('blur', function() {
                    if (formSubmitted) {
                        validateField(this);
                    }
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
                formSubmitted = true;
                
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
                    
                    // Show alert message
                    alert('Please fill in ALL fields before submitting the form. All fields are required.');
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
                
                return true;
            });
            
            // Reset form functionality
            resetBtn.addEventListener('click', function() {
                // Reset treatment field to initial state
                const treatmentInput = document.getElementById('treatment');
                const autoTreatmentNotice = document.getElementById('autoTreatmentNotice');
                
                treatmentInput.value = "";
                treatmentInput.readOnly = false;
                treatmentInput.style.backgroundColor = '#f8f9fa';
                treatmentInput.placeholder = "Treatment will be auto-filled when diagnosis is selected";
                autoTreatmentNotice.style.display = 'none';
                
                formSubmitted = false;
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

            // Update consultation time to current time when page loads
            function updateCurrentTime() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentTime = `${hours}:${minutes}`;
                
                const timeInput = document.getElementById('consultation_time');
                if (timeInput) {
                    timeInput.value = currentTime;
                }
            }

            // Update time immediately and then every minute
            updateCurrentTime();
            setInterval(updateCurrentTime, 60000);
        });
    </script>
</body>
</html>