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
  <title>Update Profile - ASCOT Clinic</title>
  
  <!-- Bootstrap -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  
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
        --accent: #ffda6a;
        --accent-light: #fff7da;
        --text-dark: #2c3e50;
        --text-light: #6c757d;
        --border-radius: 16px;
        --shadow: 0 8px 32px rgba(0,0,0,0.1);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
        padding-top: 80px;
        line-height: 1.6;
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Header Styles - ENHANCED */
    .top-header {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
        padding: 0.75rem 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        height: 80px;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255,255,255,0.2);
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
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        transition: var(--transition);
    }

    .logo-img:hover {
        transform: scale(1.05);
    }

    .school-info {
        flex: 1;
    }

    .republic {
        font-size: 0.7rem;
        opacity: 0.9;
        letter-spacing: 0.5px;
        color: var(--text-dark);
        font-weight: 600;
    }

    .school-name {
        font-size: 1.1rem;
        font-weight: 800;
        margin: 0.1rem 0;
        line-height: 1.2;
        color: var(--text-dark);
        background: linear-gradient(135deg, var(--text-dark), #495057);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .clinic-title {
        font-size: 0.8rem;
        opacity: 0.9;
        font-weight: 600;
        color: var(--text-dark);
        letter-spacing: 0.5px;
    }

    /* Mobile Menu Toggle - ENHANCED */
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
        box-shadow: var(--shadow);
        cursor: pointer;
        transition: var(--transition);
        backdrop-filter: blur(10px);
    }

    .mobile-menu-toggle:hover {
        transform: scale(1.05);
        background: var(--primary-dark);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
    }

    /* Dashboard Container - ENHANCED */
    .dashboard-container {
        display: flex;
        min-height: calc(100vh - 80px);
    }

    /* Sidebar Styles - ENHANCED */
    .sidebar {
        width: 280px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        box-shadow: 2px 0 20px rgba(0,0,0,0.08);
        padding: 2rem 0;
        transition: transform 0.3s ease;
        position: fixed;
        top: 80px;
        left: 0;
        bottom: 0;
        overflow-y: auto;
        z-index: 1020;
        border-right: 1px solid rgba(255,255,255,0.2);
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        height: 100%;
        gap: 0.5rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: var(--text-dark);
        text-decoration: none;
        transition: var(--transition);
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
        font-weight: 600;
        border-radius: 0 12px 12px 0;
        margin: 0.25rem 0;
        position: relative;
        overflow: hidden;
    }

    .nav-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 0;
        background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
        transition: var(--transition);
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.8);
        color: var(--primary);
        transform: translateX(5px);
    }

    .nav-item:hover::before {
        width: 100%;
    }

    .nav-item.active {
        background: linear-gradient(90deg, rgba(255,218,106,0.15) 0%, transparent 100%);
        color: var(--text-dark);
        border-left: 6px solid var(--accent);
    }

    .nav-item.active::before {
        width: 100%;
    }

    .nav-item i {
        width: 24px;
        margin-right: 1rem;
        font-size: 1.2rem;
        color: inherit;
        transition: var(--transition);
    }

    .nav-item span {
        flex: 1;
        color: inherit;
        font-size: 0.95rem;
    }

    .nav-item.logout {
        color: var(--danger);
        margin-top: auto;
        border-left: 6px solid transparent;
    }

    .nav-item.logout:hover {
        background: rgba(220, 53, 69, 0.1);
        color: var(--danger);
    }

    /* Main Content - ENHANCED */
    .main-content {
        flex: 1;
        padding: 2rem;
        overflow-x: hidden;
        margin-left: 280px;
        margin-top: 0;
    }

    /* Sidebar Overlay for Mobile - ENHANCED */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 80px;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        z-index: 1019;
    }

    .sidebar-overlay.active {
        display: block;
    }

    /* FORM HEADER WITH LOGO - ENHANCED */
    .form-header-with-logo {
        background: linear-gradient(135deg, rgba(255, 218, 106, 0.95) 0%, rgba(255, 247, 222, 0.98) 100%);
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255,255,255,0.3);
        color: var(--text-dark);
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
    }

    .form-header-with-logo::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.3;
    }

    .form-header-with-logo .logo-section {
        display: flex;
        align-items: center;
        gap: 2rem;
        position: relative;
        z-index: 2;
    }

    .form-header-with-logo .logo-img {
        height: 90px;
        width: auto;
        filter: drop-shadow(0 4px 12px rgba(0,0,0,0.15));
        transition: var(--transition);
    }

    .form-header-with-logo .logo-img:hover {
        transform: scale(1.05) rotate(5deg);
    }

    .form-header-with-logo .college-info h4 {
        margin: 0;
        color: var(--text-dark);
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.5px;
    }

    .form-header-with-logo .college-info h3 {
        margin: 0.5rem 0;
        color: var(--text-dark);
        font-weight: 800;
        font-size: 1.5rem;
        background: linear-gradient(135deg, var(--text-dark), #495057);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .form-header-with-logo .college-info p {
        margin: 0;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 0.95rem;
        opacity: 0.9;
    }

    /* Health Form Container - ENHANCED */
    .health-form-container {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(20px);
        border-radius: var(--border-radius);
        padding: 2.5rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255,255,255,0.3);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .health-form-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }

    .health-form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .health-form-container:hover::before {
        left: 100%;
    }

    .form-title {
        text-align: center;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 3px solid var(--accent-light);
    }

    .form-title h3 {
        color: var(--text-dark);
        font-weight: 800;
        font-size: 2rem;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, var(--text-dark), #495057);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .form-subtitle {
        color: var(--text-light);
        font-size: 1.1rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .form-section {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
        padding: 2.5rem;
        border-radius: var(--border-radius);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
        border: 1px solid rgba(255,255,255,0.2);
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .form-section:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.12);
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(135deg, var(--accent), #ffd24a);
        border-radius: 5px 5px 0 0;
    }

    .section-title {
        background: linear-gradient(135deg, var(--accent-light), #fff9e6);
        color: var(--text-dark);
        padding: 1.25rem 2rem;
        border-radius: 12px;
        font-weight: 800;
        margin-bottom: 2rem;
        font-size: 1.3rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(255,218,106,0.3);
        border-left: 6px solid var(--accent);
    }

    .form-label {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-label::before {
        content: '•';
        color: var(--primary);
        font-weight: bold;
        font-size: 1.2rem;
    }

    .form-control.underlined, .form-select.underlined {
        border: none;
        border-bottom: 2px solid #e9ecef;
        border-radius: 0;
        padding: 0.75rem 0;
        background: transparent;
        font-size: 1rem;
        transition: var(--transition);
        font-weight: 500;
    }

    .form-control.underlined:focus, .form-select.underlined:focus {
        box-shadow: none;
        border-bottom-color: var(--primary);
        background: transparent;
        transform: translateY(-2px);
    }

    .form-control:read-only, .form-select:disabled {
        background-color: rgba(248, 249, 250, 0.7);
        color: var(--text-light);
    }

    /* Medical Questions Enhancement - ENHANCED */
    .medical-question {
        background: linear-gradient(135deg, rgba(248, 249, 250, 0.9) 0%, rgba(233, 236, 239, 0.95) 100%);
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border-left: 6px solid var(--accent);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: var(--transition);
    }

    .medical-question:hover {
        transform: translateX(5px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }

    .question-text {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 1rem;
        font-size: 1.1rem;
        line-height: 1.5;
    }

    .instruction-text {
        font-style: italic;
        color: var(--text-light);
        margin-bottom: 1.5rem;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .radio-options {
        margin-bottom: 1.5rem;
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .form-check-inline {
        margin-right: 0;
    }

    .form-check-input {
        transform: scale(1.2);
        margin-right: 0.5rem;
        cursor: pointer;
    }

    .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    .form-check-label {
        font-weight: 600;
        color: var(--text-dark);
        cursor: pointer;
        transition: var(--transition);
    }

    .form-check-label:hover {
        color: var(--primary);
    }

    .conditions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-check-custom {
        padding: 1rem 1.25rem;
        background: rgba(255,255,255,0.8);
        border-radius: 10px;
        transition: var(--transition);
        border: 1px solid rgba(0,0,0,0.05);
        cursor: pointer;
    }

    .form-check-custom:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: translateX(8px);
        border-color: rgba(102, 126, 234, 0.2);
    }

    .form-check-custom .form-check-input {
        margin-right: 0.75rem;
    }

    /* Buttons Enhancement - ENHANCED */
    .action-buttons {
        padding: 2.5rem 0;
        border-top: 2px solid rgba(233, 236, 239, 0.8);
        text-align: center;
        background: linear-gradient(135deg, rgba(248, 249, 250, 0.9) 0%, rgba(233, 236, 239, 0.95) 100%);
        border-radius: 12px;
        margin-top: 2rem;
    }

    .btn-edit {
        background: linear-gradient(135deg, #27ae60, #219a52);
        color: white;
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 25px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .btn-edit::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .btn-edit:hover::before {
        left: 100%;
    }

    .btn-save {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 25px;
        font-weight: 700;
        margin-right: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .btn-save::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .btn-save:hover::before {
        left: 100%;
    }

    .btn-cancel {
        background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        color: white;
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 25px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .btn-cancel::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.6s ease;
    }

    .btn-cancel:hover::before {
        left: 100%;
    }

    .btn-edit:hover, .btn-save:hover, .btn-cancel:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        color: white;
        text-decoration: none;
    }

    /* Alerts Enhancement - ENHANCED */
    .alert {
        border-radius: 12px;
        border: none;
        margin: 1rem 0;
        box-shadow: var(--shadow);
        padding: 1.25rem 1.5rem;
        backdrop-filter: blur(10px);
        border-left: 6px solid;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(39, 174, 96, 0.95) 0%, rgba(33, 154, 82, 0.98) 100%);
        color: white;
        border-left-color: #27ae60;
    }

    .alert-danger {
        background: linear-gradient(135deg, rgba(231, 76, 60, 0.95) 0%, rgba(192, 57, 43, 0.98) 100%);
        color: white;
        border-left-color: #e74c3c;
    }

    .alert i {
        margin-right: 0.75rem;
        font-size: 1.2rem;
    }

    /* Responsive Design - ENHANCED */
    @media (max-width: 1200px) {
        .sidebar {
            width: 260px;
        }
        
        .main-content {
            margin-left: 260px;
        }
        
        .conditions-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .form-header-with-logo .logo-section {
            flex-direction: column;
            text-align: center;
            gap: 1.5rem;
        }

        .form-header-with-logo .logo-img {
            height: 70px;
        }

        .form-title h3 {
            font-size: 1.6rem;
        }

        .conditions-grid {
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
            display: flex;
            align-items: center;
            justify-content: center;
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
            width: 300px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(30px);
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
            padding: 1.5rem;
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

        .health-form-container {
            padding: 2rem;
        }

        .form-section {
            padding: 2rem;
        }

        .form-title h3 {
            font-size: 1.4rem;
        }

        .conditions-grid {
            grid-template-columns: 1fr;
        }

        .form-header-with-logo {
            padding: 1.5rem;
        }

        .radio-options {
            flex-direction: column;
            gap: 1rem;
        }

        .btn-edit, .btn-save, .btn-cancel {
            padding: 0.875rem 2rem;
            font-size: 0.95rem;
        }
    }

    @media (max-width: 576px) {
        .health-form-container {
            padding: 1.5rem;
        }

        .form-section {
            padding: 1.5rem;
        }

        .form-header-with-logo {
            padding: 1.25rem;
        }

        .form-header-with-logo .college-info h3 {
            font-size: 1.2rem;
        }

        .form-header-with-logo .college-info h4 {
            font-size: 0.9rem;
        }

        .section-title {
            font-size: 1.1rem;
            padding: 1rem 1.5rem;
        }

        .action-buttons {
            text-align: center;
        }

        .btn-save, .btn-cancel {
            display: block;
            width: 100%;
            margin: 0.75rem 0;
        }

        .main-content {
            padding: 1.25rem;
        }
        
        .mobile-menu-toggle {
            top: 80px;
            width: 45px;
            height: 45px;
        }

        .form-title h3 {
            font-size: 1.2rem;
        }

        .medical-question {
            padding: 1.5rem;
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
            padding: 1rem;
        }

        .health-form-container {
            padding: 1.25rem;
        }

        .form-section {
            padding: 1.25rem;
        }

        .form-header-with-logo .logo-img {
            height: 60px;
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
            padding: 0.75rem;
        }

        .health-form-container {
            padding: 1rem;
        }

        .form-section {
            padding: 1rem;
        }
    }

    /* Animations - ENHANCED */
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

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .fade-in {
        animation: fadeInUp 0.8s ease-out;
    }

    .slide-in-left {
        animation: slideInLeft 0.6s ease-out;
    }

    /* Loading States */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    /* Focus States for Accessibility */
    .focus-visible {
        outline: 3px solid var(--primary);
        outline-offset: 2px;
    }

    /* Scrollbar Styling */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--primary-dark), #6a4a9a);
    }

    /* Form Row Enhancements */
    .form-row-enhanced {
        margin-bottom: 1.5rem;
    }

    .form-row-enhanced .form-control.underlined:focus,
    .form-row-enhanced .form-select.underlined:focus {
        transform: translateY(-2px);
    }

    /* Touch Device Improvements */
    .touch-device .form-check-custom {
        padding: 1.25rem 1.5rem;
    }

    .touch-device .btn-edit,
    .touch-device .btn-save,
    .touch-device .btn-cancel {
        min-height: 54px;
    }
  </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - ENHANCED -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - ENHANCED -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - ENHANCED -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Sidebar - ENHANCED -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="student_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <a href="update_profile.php" class="nav-item active">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>

                <a href="schedule_consultation.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule Consultation</span>
                </a>

                <a href="student_report.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Report</span>
                </a>

                <a href="student_announcement.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcement</span>
                </a>

                <a href="activity_logs.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Activity Logs</span>
                </a>
                
                <a href="logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
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

            <!-- FORM HEADER WITH LOGO - ENHANCED -->
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

            <!-- HEALTH INFORMATION FORM - ENHANCED -->
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
                        <div class="row form-row-enhanced">
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Name:</label>
                                <input type="text" name="fullname" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($display_fullname); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Enter your full name" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Address:</label>
                                <input type="text" name="address" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['address'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Enter your complete address" required>
                            </div>
                        </div>

                        <!-- Personal Details Row -->
                        <div class="row form-row-enhanced">
                            <div class="col-md-6 col-lg-2 mb-4">
                                <label class="form-label">Age:</label>
                                <input type="number" name="age" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['age'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Age" min="1" max="100" required>
                            </div>
                            <div class="col-md-6 col-lg-2 mb-4">
                                <label class="form-label">Sex:</label>
                                <select name="sex" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo (($student_info['sex'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($student_info['sex'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <label class="form-label">Civil Status:</label>
                                <select name="civil_status" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                    <option value="">Select</option>
                                    <option value="Single" <?php echo (($student_info['civil_status'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (($student_info['civil_status'] ?? '') == 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Widowed" <?php echo (($student_info['civil_status'] ?? '') == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Separated" <?php echo (($student_info['civil_status'] ?? '') == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-2 mb-4">
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
                            <div class="col-md-6 col-lg-3 mb-4">
                                <label class="form-label">Student Number:</label>
                                <input type="text" class="form-control underlined" 
                                       value="<?php echo htmlspecialchars($student_number); ?>" readonly>
                            </div>
                        </div>

                        <!-- Family and School Details Row -->
                        <div class="row form-row-enhanced">
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Parent's Name/Guardian:</label>
                                <input type="text" name="father_name" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['father_name'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Parent/Guardian name" required>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <label class="form-label">Date:</label>
                                <input type="date" name="date" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['date'] ?? date('Y-m-d')); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?> required>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <label class="form-label">School Year:</label>
                                <input type="text" name="school_year" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['school_year'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="e.g. 2023-2024" required>
                            </div>
                        </div>

                        <!-- Course and Contact Details Row -->
                        <div class="row form-row-enhanced">
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Course/Year:</label>
                                <input type="text" name="course_year" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['course_year'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="e.g. BSIT-3" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Cellphone Number:</label>
                                <input type="tel" name="cellphone_number" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['cellphone_number'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="+69" 
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
                            <div class="row mt-4">
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
                        <div class="row form-row-enhanced">
                            <div class="col-md-6 mb-4">
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
                            <div class="col-md-6 mb-4">
                                <label class="form-label">If yes, Year:</label>
                                <input type="text" name="hosp_year" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($medical_info['hosp_year'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Year of hospitalization">
                            </div>
                        </div>

                        <!-- Operation/Surgery Section -->
                        <div class="row form-row-enhanced">
                            <div class="col-md-6 mb-4">
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
                            <div class="col-md-6 mb-4">
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
                            <div class="row form-row-enhanced">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Food:</label>
                                    <input type="text" name="food_allergies" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($medical_info['food_allergies'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Food allergies">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Medicine:</label>
                                    <input type="text" name="medicine_allergies" class="form-control underlined"
                                           value="<?php echo htmlspecialchars($medical_info['medicine_allergies'] ?? ''); ?>"
                                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                           placeholder="Medicine allergies">
                                </div>
                            </div>

                            <!-- EDIT AND SAVE BUTTONS SECTION -->
                            <div class="action-buttons">
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
        </main>
    </div>

    <!-- JS -->
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

            // MOBILE MENU FUNCTIONALITY - ENHANCED
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleMobileMenu() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
                
                // Add animation class
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.add('slide-in-left');
                } else {
                    sidebar.classList.remove('slide-in-left');
                }
            }

            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            sidebarOverlay.addEventListener('click', toggleMobileMenu);

            // Close sidebar when clicking nav items on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                    });
                });
            }

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

            // ENHANCED INTERACTIONS
            const formSections = document.querySelectorAll('.form-section');
            formSections.forEach(section => {
                section.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                section.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                });
            });

            // FOCUS MANAGEMENT FOR ACCESSIBILITY
            const focusableElements = document.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            focusableElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.classList.add('focus-visible');
                });
                
                element.addEventListener('blur', function() {
                    this.classList.remove('focus-visible');
                });
            });

            // TOUCH DEVICE ENHANCEMENTS
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
                
                // Increase tap targets
                const tapTargets = document.querySelectorAll('.form-check-custom, .btn-edit, .btn-save, .btn-cancel, .nav-item');
                tapTargets.forEach(target => {
                    target.style.minHeight = '44px';
                });
            }

            // RESIZE HANDLER
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });

            // FORM VALIDATION ENHANCEMENT
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                field.addEventListener('invalid', function() {
                    this.style.borderBottomColor = 'var(--danger)';
                });
                
                field.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.style.borderBottomColor = 'var(--success)';
                    }
                });
            });
        });
    </script>
</body>
</html>