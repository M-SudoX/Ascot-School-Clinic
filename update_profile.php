<?php
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

if (!isset($_SESSION['student_number'])) {
    header("Location: student_login.php");
    exit();
}

$student_number = $_SESSION['student_number'];
$student_id = $_SESSION['student_id'] ?? $student_number;

// ✅ Define edit_mode variable
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

// ✅ Fetch student information from database
try {
    // Fetch student information
    $stmt = $pdo->prepare("SELECT * FROM student_information WHERE student_number = ?");
    $stmt->execute([$student_number]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch medical history
    $stmt_medical = $pdo->prepare("SELECT * FROM medical_history WHERE student_number = ?");
    $stmt_medical->execute([$student_number]);
    $medical_info = $stmt_medical->fetch(PDO::FETCH_ASSOC);

    // Fetch user info from users table if needed
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE student_number = ?");
    $stmt_user->execute([$student_number]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle database error
    $student_info = [];
    $medical_info = [];
    $user_info = [];
}

// PROCESS FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Kunin ang form data
        $fullname = $_POST['fullname'] ?? '';
        $address = $_POST['address'] ?? '';
        $age = $_POST['age'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $civil_status = $_POST['civil_status'] ?? '';
        $blood_type = $_POST['blood_type'] ?? '';
        $father_name = $_POST['father_name'] ?? '';
        $course_year = $_POST['course_year'] ?? '';
        $date = $_POST['date'] ?? '';
        $school_year = $_POST['school_year'] ?? '';
        $cellphone_number = $_POST['cellphone_number'] ?? '';
        
        // Check kung existing na ang record
        $check_exists = $pdo->prepare("SELECT id FROM student_information WHERE student_number = ?");
        $check_exists->execute([$student_number]);
        $existing_record = $check_exists->fetch();
        
        if ($existing_record) {
            // UPDATE existing record
            $stmt = $pdo->prepare("
                UPDATE student_information SET 
                fullname = ?, address = ?, age = ?, sex = ?, civil_status = ?, 
                blood_type = ?, father_name = ?, course_year = ?, date = ?, 
                school_year = ?, cellphone_number = ?
                WHERE student_number = ?
            ");
            $stmt->execute([
                $fullname, $address, $age, $sex, $civil_status, $blood_type,
                $father_name, $course_year, $date, $school_year, $cellphone_number,
                $student_number
            ]);
            
            // ✅ SPECIFIC ACTION: Profile Update
            logActivity($pdo, $student_id, "Updated profile information");
        } else {
            // INSERT new record
            $stmt = $pdo->prepare("
                INSERT INTO student_information 
                (fullname, address, age, sex, civil_status, blood_type, father_name, course_year, date, school_year, cellphone_number, student_number) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fullname, $address, $age, $sex, $civil_status, $blood_type,
                $father_name, $course_year, $date, $school_year, $cellphone_number,
                $student_number
            ]);
            
            // ✅ SPECIFIC ACTION: Profile Creation
            logActivity($pdo, $student_id, "Created profile information");
        }
        
        // PROCESS MEDICAL HISTORY
        $medical_attention = $_POST['medical_attention'] ?? '';
        $conditions = isset($_POST['conditions']) ? implode(',', $_POST['conditions']) : '';
        $other_conditions = $_POST['other_conditions'] ?? '';
        $previous_hospitalization = $_POST['previous_hospitalization'] ?? '';
        $hosp_year = $_POST['hosp_year'] ?? '';
        $surgery = $_POST['surgery'] ?? '';
        $surgery_details = $_POST['surgery_details'] ?? '';
        $food_allergies = $_POST['food_allergies'] ?? '';
        $medicine_allergies = $_POST['medicine_allergies'] ?? '';
        
        // Check medical history record
        $check_medical = $pdo->prepare("SELECT id FROM medical_history WHERE student_number = ?");
        $check_medical->execute([$student_number]);
        $existing_medical = $check_medical->fetch();
        
        if ($existing_medical) {
            // UPDATE medical history
            $stmt_medical = $pdo->prepare("
                UPDATE medical_history SET 
                medical_attention = ?, medical_conditions = ?, other_conditions = ?,
                previous_hospitalization = ?, hosp_year = ?, surgery = ?, surgery_details = ?, 
                food_allergies = ?, medicine_allergies = ?
                WHERE student_number = ?
            ");
            $stmt_medical->execute([
                $medical_attention, $conditions, $other_conditions, $previous_hospitalization, 
                $hosp_year, $surgery, $surgery_details, $food_allergies, $medicine_allergies,
                $student_number
            ]);
            
            // ✅ SPECIFIC ACTION: Medical History Update
            logActivity($pdo, $student_id, "Updated medical history");
        } else {
            // INSERT medical history
            $stmt_medical = $pdo->prepare("
                INSERT INTO medical_history 
                (student_number, medical_attention, medical_conditions, other_conditions, previous_hospitalization, hosp_year, surgery, surgery_details, food_allergies, medicine_allergies) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_medical->execute([
                $student_number, $medical_attention, $conditions, $other_conditions, $previous_hospitalization, 
                $hosp_year, $surgery, $surgery_details, $food_allergies, $medicine_allergies
            ]);
            
            // ✅ SPECIFIC ACTION: Medical History Creation
            logActivity($pdo, $student_id, "Created medical history");
        }
        
        // Update session variables
        $_SESSION['course_year'] = $course_year;
        $_SESSION['cellphone_number'] = $cellphone_number;
        $_SESSION['fullname'] = $fullname;

        // Redirect
        header("Location: update_profile.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        $update_error = "There was an error updating your profile. Please try again.";
    }
}

// Check kung successful ang update
$success = isset($_GET['success']) ? true : false;

// Display logic para sa fullname
$display_fullname = $student_info['fullname'] ?? ($user_info['fullname'] ?? $student_number);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Profile - ASCOT Online School Clinic</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/update_profile.css" rel="stylesheet">
  <style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --accent-color: #e74c3c;
        --success-color: #27ae60;
        --warning-color: #f39c12;
        --sidebar-width: 280px;
        --header-height: 80px;
    }

    * {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* ========== ENHANCED HEADER DESIGN ========== */
    .header {
        background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255,255,255,0.2);
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        padding: 15px 0;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        height: var(--header-height);
    }

    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        opacity: 0.05;
        z-index: -1;
    }

    .header .logo-img {
        height:80px;
        width: 80px;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
        transition: transform 0.3s ease;
        margin-top: -16px;
    }

    .header .logo-img:hover {
        transform: scale(1.05);
    }

    .header .college-info {
        text-align: center;
    }

    .header .college-info h4 {
        font-size: 1rem;
        margin-bottom: 0.2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #2c3e50, #3498db);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .header .college-info p {
        font-size: 0.85rem;
        margin-bottom: 0;
        color: #7f8c8d;
        font-weight: 600;
        letter-spacing: 1px;
    }

    /* ========== ENHANCED SIDEBAR DESIGN ========== */
    .sidebar {
        background: linear-gradient(135deg, rgba(44, 62, 80, 0.95) 0%, rgba(52, 73, 94, 0.98) 100%);
        backdrop-filter: blur(20px);
        border-right: 1px solid rgba(255,255,255,0.1);
        box-shadow: 8px 0 32px rgba(0,0,0,0.2);
        min-height: calc(100vh - var(--header-height));
        padding: 30px 0;
        position: fixed;
        top: var(--header-height);
        left: 0;
        width: var(--sidebar-width);
        z-index: 999;
        overflow-y: auto;
    }

    .sidebar .nav {
        padding: 0 20px;
    }

    .sidebar .nav-link {
        color: #ecf0f1 !important;
        padding: 15px 20px;
        margin: 8px 0;
        border-radius: 12px;
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s ease;
    }

    .sidebar .nav-link:hover::before {
        left: 100%;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.2) 0%, rgba(41, 128, 185, 0.2) 100%);
        border-left: 4px solid #3498db;
        transform: translateX(8px);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }

    .sidebar .nav-link i {
        width: 25px;
        text-align: center;
        margin-right: 15px;
        font-size: 1.1rem;
        transition: transform 0.3s ease;
    }

    .sidebar .nav-link:hover i {
        transform: scale(1.2);
    }

    .sidebar .nav-link.active i {
        color: #3498db;
    }

    .logout-btn .nav-link {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.2) 0%, rgba(192, 57, 43, 0.2) 100%);
        border: 1px solid rgba(231, 76, 60, 0.3);
        margin-top: 20px;
    }

    .logout-btn .nav-link:hover {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.3) 0%, rgba(192, 57, 43, 0.3) 100%);
        border-left: 4px solid #e74c3c;
        transform: translateX(8px);
    }

    /* ========== MOBILE SIDEBAR ENHANCEMENTS ========== */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1100;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 1.3rem;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        transition: all 0.3s ease;
    }

    .mobile-menu-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 998;
        backdrop-filter: blur(5px);
    }

    /* ========== MAIN CONTENT ENHANCEMENTS ========== */
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 30px;
        background: rgba(248, 249, 250, 0.95);
        backdrop-filter: blur(10px);
        min-height: calc(100vh - var(--header-height));
        margin-top: var(--header-height);
    }

    /* ========== FORM HEADER ENHANCEMENT ========== */
    .form-header-with-logo {
        background: linear-gradient(135deg, rgba(255, 218, 106, 0.9) 0%, rgba(255, 247, 222, 0.95) 100%);
        backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .form-header-with-logo .logo-section {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .form-header-with-logo .logo-img {
        height: 80px;
        width: auto;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
    }

    .form-header-with-logo .college-info h4 {
        margin: 0;
        color: #2c3e50;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .form-header-with-logo .college-info h3 {
        margin: 5px 0;
        color: #2c3e50;
        font-weight: 800;
        font-size: 1.4rem;
    }

    .form-header-with-logo .college-info p {
        margin: 0;
        color: #7f8c8d;
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* ========== FORM SECTIONS ENHANCEMENT ========== */
    .health-form-container {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .form-title {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #3498db;
    }

    .form-title h3 {
        color: #2c3e50;
        font-weight: 800;
        font-size: 2rem;
        margin-bottom: 10px;
        background: linear-gradient(135deg, #2c3e50, #3498db);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .form-subtitle {
        color: #7f8c8d;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .form-section {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
        backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.2);
        position: relative;
        overflow: hidden;
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .section-title {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        font-weight: 700;
        margin-bottom: 25px;
        font-size: 1.2rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }

    .form-label {
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 10px;
        font-size: 0.95rem;
    }

    .form-control.underlined, .form-select.underlined {
        border: none;
        border-bottom: 2px solid #e9ecef;
        border-radius: 0;
        padding: 10px 0;
        background: transparent;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control.underlined:focus, .form-select.underlined:focus {
        box-shadow: none;
        border-bottom-color: #3498db;
        background: transparent;
        transform: translateY(-2px);
    }

    .form-control:read-only, .form-select:disabled {
        background-color: rgba(248, 249, 250, 0.7);
        color: #6c757d;
    }

    /* ========== MEDICAL QUESTIONS ENHANCEMENT ========== */
    .medical-question {
        background: linear-gradient(135deg, rgba(248, 249, 250, 0.8) 0%, rgba(233, 236, 239, 0.9) 100%);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        border-left: 4px solid #3498db;
    }

    .question-text {
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .instruction-text {
        font-style: italic;
        color: #7f8c8d;
        margin-bottom: 20px;
        font-size: 0.95rem;
    }

    .radio-options {
        margin-bottom: 20px;
    }

    .form-check-inline {
        margin-right: 20px;
    }

    .form-check-input {
        transform: scale(1.2);
        margin-right: 8px;
    }

    .form-check-label {
        font-weight: 600;
        color: #2c3e50;
    }

    .conditions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .form-check-custom {
        padding: 8px 0;
        background: rgba(255,255,255,0.7);
        padding: 10px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .form-check-custom:hover {
        background: rgba(52, 152, 219, 0.1);
        transform: translateX(5px);
    }

    /* ========== BUTTONS ENHANCEMENT ========== */
    .action-buttons {
        padding: 30px 0;
        border-top: 2px solid rgba(233, 236, 239, 0.8);
        text-align: center;
    }

    .btn-edit {
        background: linear-gradient(135deg, #27ae60, #219a52);
        color: white;
        padding: 12px 35px;
        border: none;
        border-radius: 25px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        transition: all 0.3s ease;
    }

    .btn-save {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 12px 35px;
        border: none;
        border-radius: 25px;
        font-weight: 700;
        margin-right: 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        transition: all 0.3s ease;
    }

    .btn-cancel {
        background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        color: white;
        padding: 12px 35px;
        border: none;
        border-radius: 25px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        transition: all 0.3s ease;
    }

    .btn-edit:hover, .btn-save:hover, .btn-cancel:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        color: white;
    }

    /* ========== ALERTS ENHANCEMENT ========== */
    .alert {
        border-radius: 12px;
        border: none;
        margin: 15px 0;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
        padding: 15px 20px;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.9) 0%, rgba(33, 154, 82, 0.95) 100%);
        color: white;
    }

    .alert-danger {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.9) 0%, rgba(192, 57, 43, 0.95) 100%);
        color: white;
    }

    /* ========== RESPONSIVE BREAKPOINTS ========== */
    @media (max-width: 1199.98px) {
        .form-header-with-logo .college-info h3 {
            font-size: 1.2rem;
        }
        
        .form-header-with-logo .college-info h4 {
            font-size: 1rem;
        }
    }

    @media (max-width: 991.98px) {
        .sidebar {
            left: -100%;
            width: 300px;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar.active {
            left: 0;
        }

        .mobile-menu-btn {
            display: block;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .main-content {
            margin-left: 0;
            padding: 20px;
        }

        .form-header-with-logo .logo-section {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }

        .form-header-with-logo .logo-img {
            height: 60px;
        }
    }

    @media (max-width: 767.98px) {
        :root {
            --header-height: 70px;
        }

        .header {
            padding: 10px 0;
        }

        .header .logo-img {
            height: 40px;
        }

        .main-content {
            padding: 15px;
            margin-top: 70px;
        }

        .health-form-container {
            padding: 20px;
        }

        .form-section {
            padding: 20px;
        }

        .form-title h3 {
            font-size: 1.6rem;
        }

        .conditions-grid {
            grid-template-columns: 1fr;
        }

        .btn-edit, .btn-save, .btn-cancel {
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .mobile-menu-btn {
            top: 15px;
            left: 15px;
            padding: 10px 14px;
            font-size: 1.2rem;
        }
    }

    @media (max-width: 575.98px) {
        .form-header-with-logo {
            padding: 15px;
        }

        .form-header-with-logo .college-info h3 {
            font-size: 1.1rem;
        }

        .form-header-with-logo .college-info h4 {
            font-size: 0.9rem;
        }

        .health-form-container {
            padding: 15px;
        }

        .form-section {
            padding: 15px;
        }

        .section-title {
            font-size: 1rem;
            padding: 12px 20px;
        }

        .action-buttons {
            text-align: center;
        }

        .btn-save, .btn-cancel {
            display: block;
            width: 100%;
            margin: 10px 0;
        }
    }

    /* ========== ANIMATIONS ========== */
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

    /* ========== CUSTOM SCROLLBAR ========== */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.1);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #3498db, #2980b9);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #2980b9, #21618c);
    }
  </style>
</head>
<body>
    <!-- MOBILE MENU BUTTON -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ENHANCED HEADER SECTION -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                </div>
                <div class="col">
                    <div class="college-info">
                        <h4>Republic of the Philippines</h4>
                        <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
                        <p>ONLINE SCHOOL CLINIC</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN LAYOUT CONTAINER -->
    <div class="container-fluid">
        <div class="row">
            <!-- ENHANCED SIDEBAR NAVIGATION -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link active" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                    <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                    <a class="nav-link" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </nav>
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- ERROR MESSAGE DISPLAY -->
                <?php if (isset($update_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $update_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- SUCCESS MESSAGE DISPLAY -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> Profile updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- FORM HEADER WITH LOGO -->
                <div class="form-header-with-logo fade-in">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-12">
                                <div class="logo-section">
                                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                                    <div class="college-info">
                                        <h4>Republic of the Philippines</h4>
                                        <h3>AURORA STATE COLLEGE OF TECHNOLOGY</h3>
                                        <p class="mb-0">Zabali, Baler, Aurora - Philippines</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HEALTH INFORMATION FORM -->
                <div class="health-form-container fade-in">
                    <div class="form-title">
                        <h3>HEALTH INFORMATION FORM</h3>
                        <p class="form-subtitle">Do not leave any item unanswered</p>
                    </div>

                    <!-- MAIN FORM -->
                    <form id="healthForm" action="update_profile.php" method="POST">
                        <input type="hidden" name="student_number" value="<?php echo htmlspecialchars($student_number); ?>">

                        <!-- PART I: STUDENT INFORMATION SECTION -->
                        <div class="form-section">
                            <div class="section-title">PART I: STUDENT INFORMATION</div>
                            
                            <!-- Name and Address Row -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Name:</label>
                                    <input type="text" name="fullname" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($display_fullname); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Enter your full name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Address:</label>
                                    <input type="text" name="address" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['address'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Enter your complete address" required>
                                </div>
                            </div>

                            <!-- Personal Details Row -->
                            <div class="row mb-4">
                                <div class="col-md-6 col-lg-2 mb-3">
                                    <label class="form-label">Age:</label>
                                    <input type="number" name="age" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['age'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Age" min="1" max="100" required>
                                </div>
                                <div class="col-md-6 col-lg-2 mb-3">
                                    <label class="form-label">Sex:</label>
                                    <select name="sex" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                        <option value="">Select</option>
                                        <option value="Male" <?php echo (($student_info['sex'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (($student_info['sex'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <label class="form-label">Civil Status:</label>
                                    <select name="civil_status" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                        <option value="">Select</option>
                                        <option value="Single" <?php echo (($student_info['civil_status'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo (($student_info['civil_status'] ?? '') == 'Married') ? 'selected' : ''; ?>>Married</option>
                                        <option value="Widowed" <?php echo (($student_info['civil_status'] ?? '') == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        <option value="Separated" <?php echo (($student_info['civil_status'] ?? '') == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-6 col-lg-2 mb-3">
                                    <label class="form-label">Blood Type:</label>
                                    <select name="blood_type" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                                        <option value="">Select</option>
                                        <option value="A+" <?php echo (($student_info['blood_type'] ?? '') == 'A+') ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo (($student_info['blood_type'] ?? '') == 'A-') ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo (($student_info['blood_type'] ?? '') == 'B+') ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo (($student_info['blood_type'] ?? '') == 'B-') ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo (($student_info['blood_type'] ?? '') == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo (($student_info['blood_type'] ?? '') == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo (($student_info['blood_type'] ?? '') == 'O+') ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo (($student_info['blood_type'] ?? '') == 'O-') ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <label class="form-label">Student Number:</label>
                                    <input type="text" class="form-control underlined" 
                                           value="<?php echo htmlspecialchars($student_number); ?>" readonly>
                                </div>
                            </div>

                            <!-- Family and School Details Row -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Parent's Name/Guardian:</label>
                                    <input type="text" name="father_name" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['father_name'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Parent/Guardian name" required>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <label class="form-label">Date:</label>
                                    <input type="date" name="date" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['date'] ?? date('Y-m-d')); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?> required>
                                </div>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <label class="form-label">School Year:</label>
                                    <input type="text" name="school_year" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['school_year'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="e.g. 2023-2024" required>
                                </div>
                            </div>

                            <!-- Course and Contact Details Row -->
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Course/Year:</label>
                                    <input type="text" name="course_year" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['course_year'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="e.g. BSIT-3" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Cellphone Number:</label>
                                    <input type="tel" name="cellphone_number" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($student_info['cellphone_number'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="0917 123 4567" 
                                           pattern="[0-9]{4} [0-9]{3} [0-9]{4}" 
                                           title="Please enter phone number in 4-3-4 format (e.g., 0917 123 4567)" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <!-- PART II: MEDICAL HISTORY SECTION -->
                        <div class="form-section">
                            <div class="section-title">PART II: MEDICAL HISTORY</div>
                            
                            <!-- Question 1: Medical Attention -->
                            <div class="medical-question mb-4">
                                <p class="question-text">1. Do you need medical attention or has known medical illness?</p>
                                <div class="radio-options">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="medical_attention" id="medical_no" value="No" 
                                               <?php echo (($medical_info['medical_attention'] ?? '') == 'No') ? 'checked' : ''; ?>
                                               <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                        <label class="form-check-label" for="medical_no">No</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="medical_attention" id="medical_yes" value="Yes"
                                               <?php echo (($medical_info['medical_attention'] ?? '') == 'Yes') ? 'checked' : ''; ?>
                                               <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                        <label class="form-check-label" for="medical_yes">Yes</label>
                                    </div>
                                </div>
                                
                                <p class="instruction-text">Please check the following that apply and give more information as needed</p>
                                
                                <!-- Medical Conditions Checkboxes -->
                                <div class="conditions-grid">
                                    <?php
                                    $conditions_saved = explode(',', $medical_info['medical_conditions'] ?? '');
                                    $options = [
                                        "Asthma", "Fainting", "Diabetes", "Heart Condition", 
                                        "Seizure Disorder", "Hyperventilation", "Vision Problem", 
                                        "Kidney Disease", "Migraine"
                                    ];
                                    
                                    foreach ($options as $opt) {
                                        $checked = in_array($opt, $conditions_saved) ? 'checked' : '';
                                        $disabled = !$edit_mode ? 'disabled' : '';
                                        echo "<div class='form-check-custom'>
                                                <input class='form-check-input' type='checkbox' name='conditions[]' value='$opt' id='condition_$opt' $checked $disabled>
                                                <label class='form-check-label' for='condition_$opt'>$opt</label>
                                            </div>";
                                    }
                                    ?>
                                </div>

                                <!-- Other Conditions Field -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Others:</label>
                                        <input type="text" name="other_conditions" class="form-control underlined"
                                               value="<?php echo htmlspecialchars($medical_info['other_conditions'] ?? ''); ?>"
                                               <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                               placeholder="Other medical conditions">
                                    </div>
                                </div>
                            </div>

                            <!-- Previous Hospitalization Section -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Previous Hospitalization</label>
                                    <div class="radio-options">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="previous_hospitalization" id="hosp_no" value="No"
                                                   <?php echo (($medical_info['previous_hospitalization'] ?? '') == 'No') ? 'checked' : ''; ?>
                                                   <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                            <label class="form-check-label" for="hosp_no">No</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="previous_hospitalization" id="hosp_yes" value="Yes"
                                                   <?php echo (($medical_info['previous_hospitalization'] ?? '') == 'Yes') ? 'checked' : ''; ?>
                                                   <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                            <label class="form-check-label" for="hosp_yes">Yes</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">If yes, Year:</label>
                                    <input type="text" name="hosp_year" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($medical_info['hosp_year'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Year of hospitalization">
                                </div>
                            </div>

                            <!-- Operation/Surgery Section -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Operation/Surgery</label>
                                    <div class="radio-options">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="surgery" id="surgery_no" value="No"
                                                   <?php echo (($medical_info['surgery'] ?? '') == 'No') ? 'checked' : ''; ?>
                                                   <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                            <label class="form-check-label" for="surgery_no">No</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="surgery" id="surgery_yes" value="Yes"
                                                   <?php echo (($medical_info['surgery'] ?? '') == 'Yes') ? 'checked' : ''; ?>
                                                   <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                            <label class="form-check-label" for="surgery_yes">Yes</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">If yes, details:</label>
                                    <input type="text" name="surgery_details" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($medical_info['surgery_details'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Surgery details">
                                </div>
                            </div>

                            <!-- Question 2: Allergies Information -->
                            <div class="medical-question">
                                <p class="question-text">2. Additional Information for Student with medical information</p>
                                <p class="instruction-text">The history of allergies to the following:</p>
                                
                                <!-- Allergies Information -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Food:</label>
                                        <input type="text" name="food_allergies" class="form-control underlined"
                                               value="<?php echo htmlspecialchars($medical_info['food_allergies'] ?? ''); ?>"
                                               <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                               placeholder="Food allergies">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Medicine:</label>
                                        <input type="text" name="medicine_allergies" class="form-control underlined"
                                               value="<?php echo htmlspecialchars($medical_info['medicine_allergies'] ?? ''); ?>"
                                               <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                               placeholder="Medicine allergies">
                                    </div>
                                </div>

                                <!-- EDIT AND SAVE BUTTONS SECTION -->
                                <div class="action-buttons mt-5">
                                    <?php if (!$edit_mode): ?>
                                        <!-- EDIT BUTTON -->
                                        <a href="update_profile.php?edit=true" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> Edit Profile
                                        </a>
                                    <?php else: ?>
                                        <!-- SAVE BUTTON -->
                                        <button type="submit" class="btn btn-save">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <!-- CANCEL BUTTON -->
                                        <a href="update_profile.php" class="btn btn-cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT FILES -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // AUTO-HIDE ALERT
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }

            // MOBILE SIDEBAR FUNCTIONALITY
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                
                // UPDATE MENU ICON
                const icon = mobileMenuBtn.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                } else {
                    icon.className = 'fas fa-bars';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
                }
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
            }
            
            if (mobileMenuBtn && sidebar && sidebarOverlay) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991.98) {
                            closeSidebar();
                        }
                    });
                });
                
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                });
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });

            // Enable disabled elements when in edit mode during form submission
            const form = document.getElementById('healthForm');
            if (form) {
                form.addEventListener('submit', function() {
                    const disabledElements = form.querySelectorAll('select:disabled, input:disabled');
                    disabledElements.forEach(element => {
                        element.disabled = false;
                    });
                });
            }

            // Auto-format phone number to 4-3-4 format
            const phoneInput = document.querySelector('input[name="cellphone_number"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    
                    if (value.length > 0) {
                        if (value.length <= 4) {
                            value = value;
                        } else if (value.length <= 7) {
                            value = value.replace(/(\d{4})(\d{0,3})/, '$1 $2');
                        } else {
                            value = value.replace(/(\d{4})(\d{3})(\d{0,4})/, '$1 $2 $3');
                        }
                    }
                    e.target.value = value;
                });

                phoneInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                    const cleaned = pastedText.replace(/\D/g, '').substring(0, 11);
                    
                    if (cleaned.length > 0) {
                        if (cleaned.length <= 4) {
                            this.value = cleaned;
                        } else if (cleaned.length <= 7) {
                            this.value = cleaned.replace(/(\d{4})(\d{0,3})/, '$1 $2');
                        } else {
                            this.value = cleaned.replace(/(\d{4})(\d{3})(\d{0,4})/, '$1 $2 $3');
                        }
                    }
                });
            }

            // Add loading animations
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>