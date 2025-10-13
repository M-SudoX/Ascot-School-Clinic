<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Login - ASCOT Online School Clinic</title>

  <!-- BOOTSTRAP & ICONS -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    /* PAGE BACKGROUND & LAYOUT */
    body {
      background: #f8f9fa;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    /* LOGIN CARD */
    .login-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      padding: 40px 35px;
      width: 100%;
      max-width: 400px;
    }

    .login-card .logo {
      display: block;
      margin: 0 auto 15px;
      width: 80px;
    }

    .login-card h5 {
      text-align: center;
      color: #333;
      font-weight: 600;
      margin-bottom: 20px;
      line-height: 1.3;
    }

    .alert {
      border: none;
      border-radius: 8px;
      font-weight: 500;
    }

    .alert-danger {
      background: #f8d7da;
      color: #721c24;
    }

    .form-control:focus {
      border-color: #ffc107;
      box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.25);
    }

    .signup-link, .forgot-link {
      text-align: center;
      margin-top: 15px;
      font-size: 0.9rem;
    }

    .signup-link a, .forgot-link a {
      color: #ffc107;
      font-weight: 600;
      text-decoration: none;
    }

    .signup-link a:hover, .forgot-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="login-card">
    <!-- SCHOOL LOGO -->
    <img src="img/logo.png" alt="ASCOT Logo" class="logo">

    <!-- SCHOOL NAME -->
    <h5>
      AURORA STATE COLLEGE OF TECHNOLOGY <br>
      ONLINE SCHOOL CLINIC
    </h5>

    <!-- ERROR MESSAGE -->
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= htmlspecialchars($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <form action="process_login.php" method="POST" class="mt-3">
      <!-- STUDENT NUMBER -->
      <div class="position-relative mb-3">
        <input type="text"
               name="student_number"
               class="form-control pe-5"
               placeholder="00-00-0000"
               value="<?= isset($_POST['student_number']) ? htmlspecialchars($_POST['student_number']) : '' ?>"
               required>
        <i class="bi bi-person position-absolute top-50 end-0 translate-middle-y me-3"></i>
      </div>

      <!-- PASSWORD -->
      <div class="position-relative mb-3">
        <input type="password"
               name="password"
               class="form-control pe-5"
               placeholder="********"
               required>
        <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3"
           style="cursor: pointer;"
           onclick="togglePassword()"></i>
      </div>

      <!-- ACTION BUTTONS -->
      <div class="d-flex justify-content-between align-items-center">
        <a href="index.php" class="btn btn-dark px-4">Back</a>
        <button type="submit" class="btn btn-warning text-dark fw-bold px-4">Log in</button>
      </div>

      <!-- LINKS -->
      <div class="signup-link">
        Don’t have an account? <a href="signup.php">Sign up</a>
      </div>

      <div class="forgot-link">
        Forgot your password? <a href="forgot_password.php">Click here</a>
      </div>


    </form>
  </div>

  <!-- JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>

  <script>
    // TOGGLE PASSWORD VISIBILITY
    function togglePassword() {
      const passwordInput = document.querySelector('input[name="password"]');
      const eyeIcon = document.querySelector('.bi-eye, .bi-eye-slash');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
      } else {
        passwordInput.type = 'password';
        eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
      }
    }

    // AUTO HIDE ALERTS
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => new bootstrap.Alert(alert).close());
    }, 5000);

    // VALIDATION
    document.querySelector('form').addEventListener('submit', function(e) {
      const studentNumber = document.querySelector('input[name="student_number"]').value.trim();
      const password = document.querySelector('input[name="password"]').value;

      if (!studentNumber || !password) {
        e.preventDefault();
        if (!document.querySelector('.alert-danger')) {
          const alertDiv = document.createElement('div');
          alertDiv.className = 'alert alert-danger mt-2';
          alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Please fill in all required fields.';
          document.querySelector('.login-card').prepend(alertDiv);
        }
      }
    });
  </script>
</body>
</html>
