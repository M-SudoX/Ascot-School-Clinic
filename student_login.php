

<!--HTML - Form and structure -->

<!--CSS/Bootstrap - Design and layout-->

<!--JavaScript - Interactive features-->

<!--PHP - Error handling-->



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Login - ASCOT Online School Clinic</title>

  <!-- CSS FILES FOR STYLING -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet" />
 <!-- Use a combination of HTML, CSS, PHP, JavaScript, and Bootstrap to create a secure and user-friendly login system for students. -->
  <style>
    /* CUSTOM STYLES FOR LOGIN PAGE */
    .alert {
        border: none;
        border-radius: 8px;
        font-weight: 500;
    }
    .alert-danger {
        background: #f8d7da;  /* LIGHT RED BACKGROUND */
        color: #721c24;       /* DARK RED TEXT */
    }
    .form-control:focus {
        border-color: #ffc107; /* YELLOW BORDER WHEN CLICKED */
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
    }
  </style>
</head>
<body>

  <!-- LEFT SIDE - LOGIN FORM -->
  <div class="split left">
    <!-- SCHOOL LOGO -->
    <img src="img/logo.png" alt="ASCOT Logo" class="logo-left" />

    <!-- SCHOOL NAME HEADER -->
    <h5>&nbsp;&nbsp;&nbsp;&nbsp;AURORA STATE COLLEGE OF TECHNOLOGY<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ONLINE SCHOOL CLINIC
    </h5>

    <!-- ERROR MESSAGE DISPLAY - DITO LUMALABAS ANG MGA ERROR -->
    <?php if (isset($_SESSION['error'])): ?>
    <div class="container mt-3">
      <div class="mx-auto" style="max-width: 360px;">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <?= htmlspecialchars($_SESSION['error']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      </div>
    </div>
    <?php unset($_SESSION['error']); ?>  <!-- TANGGALIN ANG ERROR AFTER IPAKITA -->
    <?php endif; ?>

    <!-- LOGIN FORM - DITO NAGSESEND NG DATA SA PROCESS_LOGIN.PHP -->
    <form action="process_login.php" method="POST" class="mt-3 px-">
      <div class="container">
        <div class="mx-auto" style="max-width: 360px;"> 

          <!-- STUDENT NUMBER INPUT -->
          <div class="position-relative mb-3">
            <input type="text" 
                   name="student_number" 
                   class="form-control pe-5" 
                   placeholder="00-00-0000" 
                   value="<?= isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : '' ?>" 
                   required>
            <!-- PERSON ICON SA LOOB NG INPUT -->
            <i class="bi bi-person position-absolute top-50 end-0 translate-middle-y me-3"></i>
          </div>
      
          <!-- PASSWORD INPUT -->
          <div class="position-relative mb-3">
            <input type="password" 
                   name="password" 
                   class="form-control pe-5" 
                   placeholder="********" 
                   required>
            <!-- EYE ICON PARA MA-SHOW/HIDE ANG PASSWORD -->
            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3" 
               style="cursor: pointer;" 
               onclick="togglePassword()"></i>
          </div>

          <!-- ACTION BUTTONS -->
          <div class="d-flex justify-content-between">
            <a href="index.php" class="btn btn-dark px-4">Back</a>  <!-- BUMALIK SA HOME -->
            <button type="submit" class="btn btn-warning text-dark fw-bold px-4">Log in</button>
          </div>
        </div>
      </div>
    </form>

    <!-- FORGOT PASSWORD LINK -->
     <br>
            <div class="signup-link">
          Do not have account? <a href="signup.php">SIGN UP</a>
           </div>
    <div class="text-center mt-1">
      Forgot your password? <a href="#">Click here</a>
    </div>
  </div>

  <!-- RIGHT SIDE - DESIGN ONLY -->
  <div class="split right">
    <img src="img/logo.png" alt="ASCOT Logo" class="logo-right" />
  </div>

  <!-- JAVASCRIPT FILES -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>

  <script>
    // FUNCTION PARA I-SHOW/HIDE ANG PASSWORD
    function togglePassword() {
      const passwordInput = document.querySelector('input[name="password"]');
      const eyeIcon = document.querySelector('.bi-eye');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';  // IPAKITA ANG PASSWORD
        eyeIcon.classList.remove('bi-eye');
        eyeIcon.classList.add('bi-eye-slash');  // PALITAN NG SLASHED EYE ICON
      } else {
        passwordInput.type = 'password';  // ITAGO ANG PASSWORD
        eyeIcon.classList.remove('bi-eye-slash');
        eyeIcon.classList.add('bi-eye');  // BALIK SA NORMAL EYE ICON
      }
    }

    // AUTO-HIDE ALERTS AFTER 5 SECONDS
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();  // SARADO AUTOMATICALLY ANG ERROR MESSAGES
      });
    }, 5000);
    
    // FORM VALIDATION - CHECK KUNG BLANK ANG FIELDS
    document.querySelector('form').addEventListener('submit', function(e) {
      const studentNumber = document.querySelector('input[name="student_number"]').value.trim();
      const password = document.querySelector('input[name="password"]').value;
      
      if (!studentNumber || !password) {
        e.preventDefault();  // HINDI PAPAYAGAN MAG-SUBMIT
        if (!document.querySelector('.alert-danger')) {
          // GUMAWA NG ERROR MESSAGE KUNG WALA PA
          const alertDiv = document.createElement('div');
          alertDiv.className = 'alert alert-danger';
          alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Please fill in all required fields.';
          document.querySelector('.container').prepend(alertDiv);
        }
      }
    });
  </script>
</body>
</html>