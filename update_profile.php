<?php
// Simulan ang session para ma-store at ma-access ang user data across multiple pages
session_start();

// I-require ang database connection file na gumagamit ng PDO
require 'includes/db_connect.php';

// ✅ SECURITY CHECK: Siguraduhing naka-login ang user bago bigyan ng access
if (!isset($_SESSION['student_number'])) {
    // Kung hindi naka-login, i-redirect pabalik sa login page
    header("Location: student_login.php");
    exit(); // Itigil ang execution para maiwasan ang pag-load ng rest ng page
}

// Kunin ang student number mula sa session variable
$student_number = $_SESSION['student_number'];

// PDO PURPOSE 1: SECURE DATA RETRIEVAL - Kunin ang full name mula sa users table
// Gumamit ng prepared statement para maiwasan ang SQL injection attacks
try {
    // PDO PREPARED STATEMENT: Secure query para makuha ang user info
    // Ang :student_number ay named parameter placeholder
    $stmt_user = $pdo->prepare("SELECT fullname FROM users WHERE student_number = :student_number LIMIT 1");
    
    // PDO PARAMETER BINDING: Ligtas na paraan para ipasa ang parameter value
    // Ang execute() method ay automatic na nagbi-bind ng parameters
    $stmt_user->execute([':student_number' => $student_number]);
    
    // PDO FETCH: Kunin ang resulta bilang associative array
    // PDO::FETCH_ASSOC - ibabalik ang result bilang array na may column names as keys
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // PDO ERROR HANDLING: Maayos na pag-handle ng database errors
    $user_info = []; // I-set bilang empty array kung may error
    error_log("Note: full_name column may not exist in users table: " . $e->getMessage());
}

// PDO PURPOSE 2: MULTIPLE DATABASE QUERIES - Kumuha ng student information
// FIXED: Gamitin ang student_number column hindi id para consistency
try {
    // PDO PREPARED STATEMENT: Kumuha ng student information mula sa student_information table
    $stmt1 = $pdo->prepare("SELECT * FROM student_information WHERE student_number = :student_number LIMIT 1");
    
    // PDO EXECUTE WITH ARRAY: Mas concise na paraan ng parameter binding
    // Ang array ay naglalaman ng key-value pairs para sa named parameters
    $stmt1->execute([':student_number' => $student_number]);
    
    // PDO FETCH: Kunin ang isang row lang dahil naka-LIMIT 1
    $student_info = $stmt1->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ERROR HANDLING: Kung may problema sa query, i-log at i-set bilang empty
    $student_info = [];
    error_log("Error fetching student information: " . $e->getMessage());
}

// PDO PURPOSE 3: MEDICAL HISTORY RETRIEVAL - Kumuha ng medical records
try {
    // PDO PREPARED STATEMENT: Kumuha ng medical history mula sa medical_history table
    $stmt2 = $pdo->prepare("SELECT * FROM medical_history WHERE student_number = :student_number LIMIT 1");
    
    // PDO EXECUTE: I-execute ang query gamit ang student_number parameter
    $stmt2->execute([':student_number' => $student_number]);
    
    // PDO FETCH: Kunin ang medical information bilang associative array
    $medical_info = $stmt2->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ERROR HANDLING: Kung walang medical record o may error, i-set bilang empty
    $medical_info = [];
    error_log("Error fetching medical history: " . $e->getMessage());
}

// Check kung ang form ay naka-edit mode (binago mula sa URL parameter)
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

// PROCESS FORM SUBMISSION: Kapag ang form ay na-submit via POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // PDO PURPOSE 4: FORM DATA PROCESSING - Kunin at i-sanitize ang form data
        
        // STUDENT INFORMATION FIELDS
        $fullname = $_POST['fullname'] ?? ''; // Null coalescing operator para maiwasan ang undefined index
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
        
        // PDO PURPOSE 5: UPSERT OPERATION (UPDATE OR INSERT) - Student Information
        // FIXED: Check kung existing na ang record gamit ang student_number
        $check_exists = $pdo->prepare("SELECT id FROM student_information WHERE student_number = ?");
        $check_exists->execute([$student_number]); // POSITIONAL PARAMETER: gamit ang ? placeholder
        $existing_record = $check_exists->fetch(); // Kunin ang resulta
        
        if ($existing_record) {
            // PDO UPDATE: Kung may existing record, i-update ito
            // POSITIONAL PARAMETERS: Gumamit ng ? placeholder para sa values
            $stmt = $pdo->prepare("
                UPDATE student_information SET 
                fullname = ?, address = ?, age = ?, sex = ?, civil_status = ?, 
                blood_type = ?, father_name = ?, course_year = ?, date = ?, 
                school_year = ?, cellphone_number = ?
                WHERE student_number = ?
            ");
            // PDO EXECUTE: I-execute ang query na may parameter array
            // IMPORTANTE: Dapat same order ng parameters sa query
            $stmt->execute([
                $fullname, $address, $age, $sex, $civil_status, $blood_type,
                $father_name, $course_year, $date, $school_year, $cellphone_number,
                $student_number  // Last parameter para sa WHERE clause
            ]);
        } else {
            // PDO INSERT: Kung walang existing record, gumawa ng bago
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
        }
        
        // PDO PURPOSE 6: MEDICAL HISTORY PROCESSING - Kunin ang medical data
        $medical_attention = $_POST['medical_attention'] ?? '';
        
        // PROCESS CHECKBOXES: I-convert ang array ng conditions sa comma-separated string
        $conditions = isset($_POST['conditions']) ? implode(',', $_POST['conditions']) : '';
        
        $other_conditions = $_POST['other_conditions'] ?? '';
        $previous_hospitalization = $_POST['previous_hospitalization'] ?? '';
        $hosp_year = $_POST['hosp_year'] ?? '';
        $surgery = $_POST['surgery'] ?? '';
        $surgery_details = $_POST['surgery_details'] ?? '';
        $food_allergies = $_POST['food_allergies'] ?? '';
        $medicine_allergies = $_POST['medicine_allergies'] ?? '';
        
        // PDO PURPOSE 7: MEDICAL HISTORY UPSERT - Parehong logic sa student information
        // Check kung may existing medical history record
        $check_medical = $pdo->prepare("SELECT id FROM medical_history WHERE student_number = ?");
        $check_medical->execute([$student_number]);
        $existing_medical = $check_medical->fetch();
        
        if ($existing_medical) {
            // PDO UPDATE MEDICAL: I-update ang existing medical history
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
        } else {
            // PDO INSERT MEDICAL: Gumawa ng bagong medical history record
            $stmt_medical = $pdo->prepare("
                INSERT INTO medical_history 
                (student_number, medical_attention, medical_conditions, other_conditions, previous_hospitalization, hosp_year, surgery, surgery_details, food_allergies, medicine_allergies) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_medical->execute([
                $student_number, $medical_attention, $conditions, $other_conditions, $previous_hospitalization, 
                $hosp_year, $surgery, $surgery_details, $food_allergies, $medicine_allergies
            ]);
        }
        
        // ✅ SESSION UPDATE: I-update ang session variables gamit ang bagong data
        // Ito ay para real-time na mag-reflect ang changes sa buong application
        $_SESSION['course_year'] = $course_year;
        $_SESSION['cellphone_number'] = $cellphone_number;
        $_SESSION['fullname'] = $fullname;

        // REDIRECT PARA IWASAN ANG FORM RESUBMISSION: 
        // Kapag nag-refresh ang user, hindi ma-resubmit ang form
        header("Location: update_profile.php?success=1");
        exit(); // Mahalaga ang exit() pagkatapos ng header redirect
        
    } catch (PDOException $e) {
        // PDO ERROR HANDLING: I-log ang technical error para sa developers
        error_log("Error updating profile: " . $e->getMessage());
        
        // USER-FRIENDLY ERROR MESSAGE: Ipakita ang generic message sa users
        $update_error = "There was an error updating your profile. Please try again.";
    }
}

// Check kung successful ang form submission (binago mula sa URL parameter)
$success = isset($_GET['success']) ? true : false;

// DISPLAY LOGIC: Tukuyin kung ano ang ipapakitang fullname
// FIXED: Gumamit ng tamang priority para sa fullname display
// Priority: 
// 1. student_information fullname (pinakabago), 
// 2. users table fullname (original), 
// 3. Student number (fallback kung wala)
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
    .form-header-with-logo {
    background: linear-gradient(135deg, #ffda6a, #fff7de);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
    .form-header-with-logo .logo-section {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .form-header-with-logo .logo-img {
        height: 80px;
        width: auto;
    }
    .form-header-with-logo .college-info h4 {
        margin: 0;
        color: #333;
        font-weight: bold;
        font-size: 1.20rem;
    }
    .form-header-with-logo .college-info p {
        margin: 5px 0 0 0;
        color: #333;
        font-weight: normal;
        font-size: 16px;
        margin-top: -10px; 
    }
    @media (max-width: 768px) {
        .form-header-with-logo .logo-img {
            height: 60px;
        }
        .form-header-with-logo .college-info h4 {
            font-size: 0.9em;
        }
    }
  </style>
</head>
<body>
  <!-- HEADER SECTION -->
  <div class="header">
      <div class="container-fluid">
          <div class="row align-items-center">
              <div class="col-auto">
                  <div class="logo">
                      <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                  </div>
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
            <!-- SIDEBAR NAVIGATION -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column">
                    <!-- NAVIGATION LINKS -->
                    <a class="nav-link active" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>

                    <!-- ✅ ADDED REPORT LINK -->
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                <a class="nav-link" href="student_announcement.php">
        <i class="fas fa-bullhorn"></i> Announcement
    </a>
</nav>


                <!-- LOGOUT BUTTON -->
                 <br>
                 <br>
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

      <!-- MAIN CONTENT AREA -->
      <div class="col-md-9 col-lg-10 main-content">
        <!-- ERROR MESSAGE DISPLAY -->
        <?php if (isset($update_error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $update_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        
        <!-- SUCCESS MESSAGE DISPLAY -->
        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> Profile updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        <br>
        <br>
        <br>
        <br>
        <br>
  
        <!-- FORM HEADER WITH LOGO -->
        <div class="form-header-with-logo">
          <div class="container">
            <div class="row align-items-center">
              <div class="col-auto">
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
        <div class="health-form-container">
          <div class="form-title">
            <h3>HEALTH INFORMATION FORM</h3>
            <p class="form-subtitle">Do not leave any item unanswered</p>
          </div>

          <!-- MAIN FORM -->
          <form id="healthForm" action="update_profile.php" method="POST">
            <!-- HIDDEN FIELD: Student number para sa form processing -->
            <input type="hidden" name="student_number" value="<?php echo htmlspecialchars($student_number); ?>">

            <!-- PART I: STUDENT INFORMATION SECTION -->
            <div class="form-section">
              <div class="section-title">PART I: STUDENT INFORMATION</div>
              
              <!-- Name and Address Row -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Name:</label>
                  <input type="text" name="fullname" class="form-control underlined"
                         value="<?php echo htmlspecialchars($display_fullname); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Enter your full name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Address:</label>
                  <input type="text" name="address" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['address'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Enter your complete address">
                </div>
              </div>

              <!-- Personal Details Row -->
              <div class="row mb-3">
                <div class="col-md-2">
                  <label class="form-label">Age:</label>
                  <input type="number" name="age" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['age'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Age">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Sex:</label>
                  <select name="sex" class="form-select underlined" <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                    <option value="">Select</option>
                    <option value="Male" <?php if(($student_info['sex'] ?? '')=='Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if(($student_info['sex'] ?? '')=='Female') echo 'selected'; ?>>Female</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Civil Status:</label>
                  <input type="text" name="civil_status" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['civil_status'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Civil Status">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Blood Type:</label>
                  <input type="text" name="blood_type" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['blood_type'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Blood Type">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Student Number:</label>
                  <input type="text" class="form-control underlined" 
                         value="<?php echo htmlspecialchars($student_number); ?>" readonly>
                </div>
              </div>

              <!-- Family and School Details Row -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Parent's Name/Guardian:</label>
                  <input type="text" name="father_name" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['father_name'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Parent/Guardian name">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date:</label>
                  <input type="date" name="date" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['date'] ?? date('Y-m-d')); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-3">
                  <label class="form-label">School Year:</label>
                  <input type="text" name="school_year" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['school_year'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="e.g. 2023-2024">
                </div>
              </div>

              <!-- Course and Contact Details Row -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Course/Year:</label>
                  <input type="text" name="course_year" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['course_year'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="e.g ">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Cellphone Number:</label>
                  <input type="tel" name="cellphone_number" class="form-control underlined"
                         value="<?php echo htmlspecialchars($student_info['cellphone_number'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="09XXXXXXXXX">
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
                           <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="medical_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="medical_attention" id="medical_yes" value="Yes"
                           <?php echo (($medical_info['medical_attention'] ?? '') == 'Yes') ? 'checked' : ''; ?>
                           <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="medical_yes">Yes</label>
                  </div>
                </div>
                
                <p class="instruction-text">Please check the following that apply and give more information as needed</p>
                
                <!-- Medical Conditions Checkboxes -->
                <div class="conditions-grid">
                  <?php
                  // Kunin ang saved conditions at i-convert sa array
                  $conditions_saved = explode(',', $medical_info['medical_conditions'] ?? '');
                  
                  // Listahan ng available medical conditions
                  $options = [
                    "Asthma", "Fainting", "Diabetes", "Heart Condition", 
                    "Seizure Disorder", "Hyperventilation", "Vision Problem", 
                    "Kidney Disease", "Migraine"
                  ];
                  
                  // Generate checkboxes para sa bawat condition
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
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Previous Hospitalization</label>
                  <div class="radio-options">
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="previous_hospitalization" id="hosp_no" value="No"
                             <?php echo (($medical_info['previous_hospitalization'] ?? '') == 'No') ? 'checked' : ''; ?>
                             <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                      <label class="form-check-label" for="hosp_no">No</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="previous_hospitalization" id="hosp_yes" value="Yes"
                             <?php echo (($medical_info['previous_hospitalization'] ?? '') == 'Yes') ? 'checked' : ''; ?>
                             <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                      <label class="form-check-label" for="hosp_yes">Yes</label>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">If yes, Year:</label>
                  <input type="text" name="hosp_year" class="form-control underlined"
                         value="<?php echo htmlspecialchars($medical_info['hosp_year'] ?? ''); ?>"
                         <?php echo !$edit_mode ? 'readonly' : ''; ?>
                         placeholder="Year of hospitalization">
                </div>
              </div>

              <!-- Operation/Surgery Section -->
              <div class="row mb-4">
                <div class="col-md-6">
                  <label class="form-label">Operation/Surgery</label>
                  <div class="radio-options">
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="surgery" id="surgery_no" value="No"
                             <?php echo (($medical_info['surgery'] ?? '') == 'No') ? 'checked' : ''; ?>
                             <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                      <label class="form-check-label" for="surgery_no">No</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="surgery" id="surgery_yes" value="Yes"
                             <?php echo (($medical_info['surgery'] ?? '') == 'Yes') ? 'checked' : ''; ?>
                             <?php echo !$edit_mode ? 'disabled' : ''; ?>>
                      <label class="form-check-label" for="surgery_yes">Yes</label>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
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
                  <div class="col-md-6">
                    <label class="form-label">Food:</label>
                    <input type="text" name="food_allergies" class="form-control underlined"
                           value="<?php echo htmlspecialchars($medical_info['food_allergies'] ?? ''); ?>"
                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                           placeholder="Food allergies">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Medicine:</label>
                    <input type="text" name="medicine_allergies" class="form-control underlined"
                           value="<?php echo htmlspecialchars($medical_info['medicine_allergies'] ?? ''); ?>"
                           <?php echo !$edit_mode ? 'readonly' : ''; ?>
                           placeholder="Medicine allergies">
                  </div>
                </div>

                <!-- EDIT AND SAVE BUTTONS SECTION -->
                <div class="action-buttons mt-5 text-center">
                  <?php if (!$edit_mode): ?>
                    <!-- EDIT BUTTON: Papalitan ang URL para mag-switch sa edit mode -->
                    <a href="update_profile.php?edit=true" class="btn btn-edit">
                      <i class="fas fa-edit"></i> Edit
                    </a>
                  <?php else: ?>
                    <!-- SAVE BUTTON: I-submit ang form para i-save ang changes -->
                    <button type="submit" class="btn btn-save">
                      <i class="fas fa-save"></i> Save
                    </button>
                    <!-- CANCEL BUTTON: Babalik sa view mode nang walang save -->
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
    // Auto-hide success message after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const alert = document.querySelector('.alert');
      if (alert) {
        setTimeout(() => {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }, 5000);
      }

      // Enable disabled select elements when in edit mode during form submission
      const form = document.getElementById('healthForm');
      if (form) {
        form.addEventListener('submit', function() {
          const disabledElements = form.querySelectorAll('select:disabled, input:disabled');
          disabledElements.forEach(element => {
            element.disabled = false; // I-enable ang disabled elements bago i-submit
          });
        });
      }
    });
  </script>
</body>
</html>