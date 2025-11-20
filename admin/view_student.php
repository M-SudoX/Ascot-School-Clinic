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

// Define edit mode
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

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

// Handle form submission for admin editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $edit_mode) {
    try {
        // Get form data for student information
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
        
        // Update student information
        $update_stmt = $pdo->prepare("
            UPDATE student_information SET 
            fullname = ?, address = ?, age = ?, sex = ?, civil_status = ?, 
            blood_type = ?, father_name = ?, course_year = ?, date = ?, 
            school_year = ?, cellphone_number = ?
            WHERE id = ?
        ");
        $update_stmt->execute([
            $fullname, $address, $age, $sex, $civil_status, $blood_type,
            $father_name, $course_year, $date, $school_year, $cellphone_number,
            $student_id
        ]);
        
        // Process medical history
        $medical_attention = $_POST['medical_attention'] ?? '';
        $conditions = isset($_POST['conditions']) ? implode(',', $_POST['conditions']) : '';
        $other_conditions = $_POST['other_conditions'] ?? '';
        $previous_hospitalization = $_POST['previous_hospitalization'] ?? '';
        $hosp_year = $_POST['hosp_year'] ?? '';
        $surgery = $_POST['surgery'] ?? '';
        $surgery_details = $_POST['surgery_details'] ?? '';
        $food_allergies = $_POST['food_allergies'] ?? '';
        $medicine_allergies = $_POST['medicine_allergies'] ?? '';
        
        // Check if medical history exists
        $check_medical = $pdo->prepare("SELECT id FROM medical_history WHERE student_number = ?");
        $check_medical->execute([$student_info['student_number']]);
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
                $student_info['student_number']
            ]);
        } else {
            // INSERT medical history
            $stmt_medical = $pdo->prepare("
                INSERT INTO medical_history 
                (student_number, medical_attention, medical_conditions, other_conditions, previous_hospitalization, hosp_year, surgery, surgery_details, food_allergies, medicine_allergies) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_medical->execute([
                $student_info['student_number'], $medical_attention, $conditions, $other_conditions, $previous_hospitalization, 
                $hosp_year, $surgery, $surgery_details, $food_allergies, $medicine_allergies
            ]);
        }
        
        // Redirect to view mode with success message
        header("Location: view_student.php?id=" . $student_id . "&success=1");
        exit();
        
    } catch (PDOException $e) {
        $update_error = "There was an error updating the student profile. Please try again.";
    }
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

// Check for success message
$success = isset($_GET['success']) ? true : false;
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 80px;
            line-height: 1.6;
        }

        /* Header Styles - SAME AS STUDENT */
        .top-header {
            background: 
                linear-gradient(90deg, 
                    #ffda6a 0%, 
                    #ffda6a 30%, 
                    #FFF5CC 70%, 
                    #ffffff 100%);
            color: white;
            padding: 0.75rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 80px;
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
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            color: #555;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
            color: #555;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
            color: #555;
        }

        /* Mobile Menu Toggle - SAME AS STUDENT */
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        /* Dashboard Container - SAME AS STUDENT */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS STUDENT */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1020;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.25rem;
            color: #555;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #555;
            border-left: 8px solid #ffda6a;
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
            color: #555;
        }

        .nav-item span {
            flex: 1;
            color: #555;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
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
            padding: 0.7rem 1.25rem 0.7rem 3.25rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: var(--primary);
        }

        .submenu-item i {
            width: 18px;
            margin-right: 0.7rem;
            font-size: 0.9rem;
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - SAME AS STUDENT */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS STUDENT */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1019;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Welcome Section - SAME AS STUDENT */
        .welcome-section {
            background: linear-gradient(110deg, #fff7da 50%, #fff7da 50%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(206, 224, 144, 0.2);
            border-left: 10px solid #ffda6a;
        }

        .welcome-content h1 {
            color: #555;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Dashboard Card - SAME AS STUDENT */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-title {
            color: #555;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #555;
            background: #fff7da;
            transition: all 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.1);
        }

        /* Form Sections */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }

        .section-title {
            color: #555;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #ffda6a;
        }

        /* Info Groups */
        .info-group {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #ffda6a;
        }

        .info-group.missing-field {
            border-left-color: var(--danger);
            background: #fff5f5;
        }

        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .info-value {
            color: #333;
            font-size: 1rem;
        }

        .form-control.underlined, .form-select.underlined {
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
            padding: 0.75rem 0;
            background: transparent;
            font-size: 1rem;
            transition: all 0.3s ease;
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
            color: var(--gray);
        }

        .conditions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .condition-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background: white;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }

        .form-check-custom {
            padding: 1rem 1.25rem;
            background: rgba(255,255,255,0.8);
            border-radius: 10px;
            transition: all 0.3s ease;
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

        /* Status Badges */
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
        }

        .part-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .part-complete {
            background: var(--success);
        }

        .part-incomplete {
            background: var(--warning);
        }

        .completion-details {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255,255,255,0.7);
            border-radius: 8px;
        }

        .missing-fields-list {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: #fff3cd;
            border-radius: 5px;
            border-left: 4px solid var(--warning);
        }

        /* Action Buttons */
                /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .action-buttons .text-center {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            width: 100%;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }
            
            .action-buttons .text-center {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }
            
            .btn-edit, .btn-save, .btn-cancel, .back-btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }

        .btn-edit {
            background: #ffda6a;
            color: #555;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #ffd24c;
            color: #555;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .btn-save {
            background: #28a745;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-save:hover {
            background: #218838;
            color: white;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            margin: 1rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
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

        /* Medical Questions */
        .medical-question {
            background: linear-gradient(135deg, rgba(248, 249, 250, 0.9) 0%, rgba(233, 236, 239, 0.95) 100%);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 6px solid #ffda6a;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .question-text {
            font-weight: 700;
            color: #555;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .instruction-text {
            font-style: italic;
            color: var(--gray);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
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
            color: #555;
            cursor: pointer;
        }

        /* Responsive Design - SAME AS STUDENT */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
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
                display: block;
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
                width: 280px;
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
                padding: 2rem 1.25rem 1.25rem;
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

            .dashboard-card, .form-section {
                padding: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .conditions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .dashboard-card, .form-section {
                padding: 1.25rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-content h1 {
                font-size: 1.5rem;
            }

            .main-content {
                padding: 1.75rem 1rem 1rem;
            }
            
            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }

            .conditions-grid {
                grid-template-columns: 1fr;
            }

            .radio-options {
                flex-direction: column;
                gap: 1rem;
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
                padding: 1.5rem 1rem 1rem;
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
                padding: 1.25rem 0.75rem 0.75rem;
            }
        }

        /* ANIMATIONS - SAME AS STUDENT */
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
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button - SAME AS STUDENT -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS STUDENT -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS STUDENT -->
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

    <div class="dashboard-container">
        <!-- Sidebar - ADMIN MENU ITEMS WITH STUDENT STYLING -->
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
                        <a href="users_logs.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            Users Logs
                        </a>
                        <a href="backup_restore.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Back up & Restore
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

        <!-- Main Content - FOLLOWING STUDENT DASHBOARD STRUCTURE -->
        <main class="main-content">
            <!-- WELCOME SECTION -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Student Profile Details 👨‍🎓</h1>
                    <p>View and manage student information and medical history</p>
                </div>
            </div>

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
                    <i class="fas fa-check-circle me-2"></i> Student profile updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- STUDENT STATUS CARD -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3 class="card-title">Student Status</h3>
                    <div class="card-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                
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
                </div>
            </div>

            <!-- MAIN FORM -->
            <form id="studentForm" action="view_student.php?id=<?php echo $student_id; ?>&edit=true" method="POST">
                <!-- PART I: STUDENT INFORMATION -->
                <div class="form-section fade-in">
                    <div class="section-title">PART I: STUDENT INFORMATION</div>
                    
                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="fullname" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['fullname'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Enter full name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['address'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Enter complete address" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group mb-4">
                                <label class="form-label">Age</label>
                                <input type="number" name="age" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['age'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Age" min="1" max="100" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-4">
                                <label class="form-label">Sex</label>
                                <select name="sex" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo (($student_info['sex'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($student_info['sex'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-4">
                                <label class="form-label">Civil Status</label>
                                <select name="civil_status" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?> required>
                                    <option value="">Select</option>
                                    <option value="Single" <?php echo (($student_info['civil_status'] ?? '') == 'Single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo (($student_info['civil_status'] ?? '') == 'Married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="Widowed" <?php echo (($student_info['civil_status'] ?? '') == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Separated" <?php echo (($student_info['civil_status'] ?? '') == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-4">
                                <label class="form-label">Blood Type</label>
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
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Parent/Guardian Name</label>
                                <input type="text" name="father_name" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['father_name'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="Parent/Guardian name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-4">
                                <label class="form-label">Date Registered</label>
                                <input type="date" name="date" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['date'] ?? date('Y-m-d')); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?> required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-4">
                                <label class="form-label">School Year</label>
                                <input type="text" name="school_year" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['school_year'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="e.g. 2023-2024" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Course/Year</label>
                                <input type="text" name="course_year" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['course_year'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="e.g. BSIT-3" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Cellphone Number</label>
                                <input type="tel" name="cellphone_number" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['cellphone_number'] ?? ''); ?>"
                                       <?php echo !$edit_mode ? 'readonly' : ''; ?>
                                       placeholder="+63" 
                                       pattern="[0-9]{4} [0-9]{3} [0-9]{4}" 
                                       title="Please enter phone number in 4-3-4 format (e.g., 0917 123 4567)" 
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['email'] ?? 'Not set'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="form-label">Account Created</label>
                                <input type="text" class="form-control underlined"
                                       value="<?php echo htmlspecialchars($student_info['user_created'] ?? 'Not set'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PART II: MEDICAL HISTORY -->
                <div class="form-section fade-in">
                    <div class="section-title">PART II: MEDICAL HISTORY</div>
                    
                    <!-- Medical Attention -->
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
                    <div class="row">
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
                    <div class="row">
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
                        <div class="row">
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
                    </div>
                </div>

                            <!-- Action Buttons -->
            <div class="action-buttons">
                <div class="text-center w-100">
                    <?php if (!$edit_mode): ?>
                        <!-- EDIT BUTTON -->
                        <a href="view_student.php?id=<?php echo $student_id; ?>&edit=true" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit Student Profile
                        </a>
                    <?php else: ?>
                        <!-- SAVE BUTTON -->
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <!-- CANCEL BUTTON -->
                        <a href="view_student.php?id=<?php echo $student_id; ?>" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                    <a href="students.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Students List
                    </a>
                </div>
            </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DROPDOWN TOGGLE FUNCTIONALITY FOR SIDEBAR MENUS
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

            // MOBILE MENU FUNCTIONALITY - SAME AS STUDENT
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

            // LOADING ANIMATIONS
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.2}s`;
            });

            // Enable disabled elements when in edit mode during form submission
            const form = document.getElementById('studentForm');
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

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>