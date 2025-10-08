<?php
// ==================== SESSION AT SECURITY ====================
session_start();  // SIMULIN ANG SESSION PARA MA-ACCESS ANG USER DATA
require 'includes/db_connect.php';  // IKONEK SA DATABASE GAMIT ANG PDO

// ✅ SECURITY CHECK: TINITIGNAN KUNG NAKA-LOGIN ANG USER
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");  // KUNG HINDI NAKA-LOGIN, BALIK SA LOGIN PAGE
    exit();  // ITIGIL ANG EXECUTION
}

$student_number = $_SESSION['student_number'] ?? ($_SESSION['student_id'] ?? 'N/A');

$stmt = $pdo->prepare("SELECT fullname, student_number, course_year, cellphone_number 
                       FROM student_information 
                       WHERE student_number = :student_number LIMIT 1");

$stmt->execute([':student_number' => $student_number]);

$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ ERROR HANDLING: BACKUP SYSTEM KUNG WALANG MAKUHA SA DATABASE
if (!$student_info) {
    // GUMAMIT NG SESSION DATA KUNG WALANG RECORD SA DATABASE
    $student_info = [
        'fullname' => $_SESSION['fullname'] ?? 'N/A',
        'student_number' => $student_number,
        'course_year' => 'Not set',
        'cellphone_number' => 'Not set'
    ];
} else {
    // ✅ UPDATE ANG SESSION DATA PARA CONSISTENT ANG INFORMATION
    $_SESSION['fullname'] = $student_info['fullname'];
    $_SESSION['student_number'] = $student_info['student_number']; // SIGURADUHING NA-SET
}

// Use PDO - Secure database access
// PDO was used to PROTECT the student information

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ASCOT Online School Clinic</title>
    <!-- CSS FILES FOR STYLING -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">  <!-- BOOTSTRAP FRAMEWORK -->
    <link href="assets/webfonts/all.min.css" rel="stylesheet">   <!-- FONT AWESOME ICONS -->
    <link href="assets/css/student_dashboard.css" rel="stylesheet"> <!-- CUSTOM STYLES -->
</head>
<body>
    <!-- HEADER SECTION - SCHOOL BRANDING -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img"> <!-- SCHOOL LOGO -->
                </div>
                <div class="col">
                    <div class="college-info">
                        <h5>Republic of the Philippines</h5>
                        <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
                        <p>ONLINE SCHOOL CLINIC</p>  <!-- SYSTEM TITLE -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS MESSAGE DISPLAY -->
    <?php if (isset($_SESSION['success'])): ?>
        <div id="successAlert" class="alert alert-soft-success fade show small" role="alert">
            ✅ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>  <!-- IPAKITA AT TANGGALIN ANG SUCCESS MESSAGE -->
        </div>
    <?php endif; ?>

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
                </nav>
                <!-- LOGOUT BUTTON -->
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- QUICK ACTION BUTTONS -->
                <div class="quick-actions">
                    <a href="update_profile.php" class="quick-action-btn">
                        <i class="fas fa-user"></i>
                        <span>Update Profile</span>  <!-- MABILIS NA ACCESS SA PROFILE UPDATE -->
                    </a>
                    <a href="schedule_consultation.php" class="quick-action-btn">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Schedule Consultation</span>  <!-- MABILIS NA ACCESS SA CONSULTATION -->
                    </a>
                </div>

                <!-- INFORMATION DISPLAY ROW -->
                <div class="row mt-4">
                    <!-- STUDENT INFORMATION CARD -->
                    <div class="col-lg-6">
                        <div class="info-card fade-in">
                            <h5>Student Information</h5>
                            
                            <!-- DISPLAY STUDENT FULL NAME -->
                            <div class="info-row">
                                <span class="info-label">Full Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($student_info['fullname']); ?></span>
                            </div>
                            
                            <!-- DISPLAY STUDENT ID NUMBER -->
                            <div class="info-row">
                                <span class="info-label">ID Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($student_info['student_number']); ?></span>
                            </div>
                            
                            <!-- DISPLAY COURSE AND YEAR -->
                            <div class="info-row">
                                <span class="info-label">Course/Year:</span>
                                <span class="info-value">
                                    <?php 
                                    $course_year = $student_info['course_year'] ?? 'Not set';
                                    // CHECK KUNG BLANK O "Not set" ANG COURSE/YEAR
                                    echo (empty($course_year) || $course_year === 'Not set') 
                                        ? '<span class="text-warning">Not set</span>'  // WARNING KUNG WALA
                                        : htmlspecialchars($course_year);  // IPAKITA KUNG MERON
                                    ?>
                                </span>
                            </div>
                            
                            <!-- DISPLAY CONTACT NUMBER -->
                            <div class="info-row">
                                <span class="info-label">Contact No:</span>
                                <span class="info-value">
                                    <?php 
                                    $contact = $student_info['cellphone_number'] ?? 'Not set';
                                    // CHECK KUNG BLANK O "Not set" ANG CONTACT NUMBER
                                    echo (empty($contact) || $contact === 'Not set') 
                                        ? '<span class="text-warning">Not set</span>'  // WARNING KUNG WALA
                                        : htmlspecialchars($contact);  // IPAKITA KUNG MERON
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- CALENDAR WIDGET -->
                    <div class="col-lg-6">
                        <div class="calendar-widget fade-in">
                            <h5>Consultation Schedule Overview</h5>
                            <div class="calendar-header">APPOINTMENT CALENDAR</div>
                            <div class="calendar-grid" id="calendar">
                                <!-- CALENDAR HEADER - DAYS OF WEEK -->
                                <div class="calendar-header-day">Sun</div>
                                <div class="calendar-header-day">Mon</div>
                                <div class="calendar-header-day">Tue</div>
                                <div class="calendar-header-day">Wed</div>
                                <div class="calendar-header-day">Thu</div>
                                <div class="calendar-header-day">Fri</div>
                                <div class="calendar-header-day">Sat</div>
                                
                                <!-- DYNAMIC CALENDAR DAYS GENERATION -->
                                <?php 
                                $days_in_month = date('t');  // KUHAIN ANG NUMBER OF DAYS SA CURRENT MONTH
                                for ($i = 1; $i <= $days_in_month; $i++): 
                                ?>
                                    <div class="calendar-day <?php echo $i == date('j') ? 'today' : ''; ?>">
                                        <?php echo $i; ?>  <!-- IPAKITA ANG DAY NUMBER -->
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- NEXT APPOINTMENT SECTION -->
                            <div class="next-appointment">
                                <h6>Next Upcoming Appointment</h6>
                                <div class="appointment-date">No upcoming appointments</div>  <!-- DEFAULT MESSAGE -->
                                <div class="appointment-time">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT FILES -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>  <!-- BOOTSTRAP JS COMPONENTS -->
    
    <script>
        // JAVASCRIPT FOR ENHANCED USER EXPERIENCE
        document.addEventListener('DOMContentLoaded', function() {
            // AUTO-HIDE SUCCESS ALERT AFTER 3 SECONDS
            const alertBox = document.getElementById("successAlert");
            if (alertBox) {
                setTimeout(() => {
                    alertBox.classList.remove("show");  // ALISIN ANG "SHOW" CLASS
                    setTimeout(() => alertBox.remove(), 300);  // TANGGALIN ANG ELEMENT AFTER ANIMATION
                }, 3000);  // 3 SECONDS DELAY
            }
        });
    </script>
</body>
</html>