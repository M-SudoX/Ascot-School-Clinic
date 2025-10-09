<?php

// FRONTEND: HTML + CSS + JavaScript + Bootstrap

// BACKEND: PHP + PDO + MySQL + Sessions

// SECURITY: Parameter Binding + Input Validation + Session Protection

// DESIGN: Responsive Layout + Professional Styling + Intuitive UI


//Gumamit ng COMBINATION ng modern web technologies 

// para gumawa ng SECURE, USER-FRIENDLY, at PROFESSIONAL

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration for 24-hour timeout
ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_set_cookie_params(86400);
session_start();

// Include database connection
require_once 'includes/db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aurora State College of Technology - Online School Clinic</title>
  
  <!-- Bootstrap CSS (local file mula sa assets/css folder) -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Custom CSS mo (sariling style para sa design ng system) -->
  <link href="assets/css/style.css" rel="stylesheet" />
</head>
<body>
  <!-- Kaliwang bahagi ng screen -->
  <div class="split left">
    <!-- Logo ng ASCOT sa kaliwa -->
    <img src="img/logo.png" alt="ASCOT Logo" class="logo-left" />

    <div>
      <!-- Title ng system -->
      <h5>
        AURORA STATE COLLEGE OF TECHNOLOGY<br>
        ONLINE SCHOOL CLINIC
      </h5>

     <!-- Form where the user will choose the type (Student or Admin) -->

     <!-- process_user_type.php = file that will handle the selection of user type -->
      
      <form method="POST" action="process_user_type.php" class="mt-4">

        <!-- Label at Dropdown para pumili ng type of user -->
        <label for="userType" class="form-label">-Select type of user to log-in-</label>
        <select class="form-select" id="userType" name="userType" required>
          <option value="" disabled selected>Select user type</option>
          <option value="student">STUDENT</option>
          <option value="admin">ADMIN</option>
        </select>

        <!-- Sign-up link (para sa mga bagong user) at Next button -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <!-- Link papuntang Sign-Up page -->
          <!-- Button para magsumite ng form (dadalhin sa process_user_type.php) -->
          <button type="submit" class="btn btn-next">Next</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Kanang bahagi ng split screen -->
  <div class="split right">
    <!-- Logo ulit ng ASCOT pero nasa kanan -->
    <img src="img/logo.png" alt="ASCOT Logo" class="logo-right" />
  </div>

  <!-- Bootstrap JS Bundle (local file mula sa assets/css folder) -->
  <!-- Naglalaman ng JS code ng Bootstrap at Popper.js para gumana ang mga interactive components -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>