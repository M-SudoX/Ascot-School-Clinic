<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password - ASCOT Online School Clinic</title>

  <!-- BOOTSTRAP & ICONS -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    /* PAGE BACKGROUND & CENTER LAYOUT */
    body {
      background: #f8f9fa;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 15px;
    }

    /* FORGOT PASSWORD CARD */
    .forgot-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      padding: 40px 35px;
      width: 100%;
      max-width: 400px;
    }

    .forgot-card .logo {
      display: block;
      margin: 0 auto 15px;
      width: 80px;
    }

    .forgot-card h5 {
      text-align: center;
      color: #333;
      font-weight: 600;
      margin-bottom: 10px;
      line-height: 1.3;
    }

    .forgot-card p {
      text-align: center;
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 20px;
    }

    .alert {
      border: none;
      border-radius: 8px;
      font-weight: 500;
      text-align: center;
    }

    .alert-info {
      background: #e9f5ff;
      color: #055160;
    }

    .alert-danger {
      background: #f8d7da;
      color: #721c24;
    }

    .form-control:focus {
      border-color: #ffc107;
      box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
    }

    /* BUTTONS */
    .btn-warning {
      background-color: #ffc107;
      color: #000;
      font-weight: 600;
    }

    .btn-warning:hover {
      background-color: #e0ac07;
    }

    .btn-outline-secondary {
      font-weight: 500;
    }

    /* LINK STYLES */
    .back-link {
      text-align: center;
      margin-top: 15px;
      font-size: 0.9rem;
    }

    .back-link a {
      color: #ffc107;
      font-weight: 600;
      text-decoration: none;
    }

    .back-link a:hover {
      text-decoration: underline;
    }

    /* RESPONSIVE */
    @media (max-width: 576px) {
      .forgot-card {
        padding: 30px 25px;
      }
      .forgot-card .logo {
        width: 70px;
      }
      .forgot-card h5 {
        font-size: 1.1rem;
      }
    }
  </style>
</head>
<body>

  <div class="forgot-card">
    <!-- SCHOOL LOGO -->
    <img src="img/logo.png" alt="ASCOT Logo" class="logo">

    <!-- HEADER -->
    <h5>AURORA STATE COLLEGE OF TECHNOLOGY</h5>
    <p>Forgot your password? Enter your email or phone number to receive a reset code.</p>

    <!-- FLASH MESSAGE -->
    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-info alert-dismissible fade show mt-2" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <?= htmlentities($_SESSION['flash']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- FORM -->
    <form action="send_code.php" method="POST" class="mt-3">
      <div class="position-relative mb-3">
        <input type="text"
               name="identifier"
               class="form-control pe-5"
               placeholder="Email or Phone Number"
               required>
        <i class="bi bi-person-circle position-absolute top-50 end-0 translate-middle-y me-3"></i>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="student_login.php" class="btn btn-outline-secondary px-4">Cancel</a>
        <button type="submit" class="btn btn-warning text-dark px-4">Send Code</button>
      </div>
    </form>

    <!-- BACK LINK -->
    <div class="back-link">
      <a href="student_login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
  </div>

  <!-- JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // AUTO HIDE ALERTS
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => new bootstrap.Alert(alert).close());
    }, 5000);
  </script>

</body>
</html>
