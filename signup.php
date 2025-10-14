<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign Up - ASCOT Online School Clinic</title>

  <!-- Bootstrap 5 (offline) -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome (offline) -->
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    /* PAGE BACKGROUND & LAYOUT */
    body {
      background: #f8f9fa;
      display: flex;
      justify-content: center;
      align-items: flex-start; /* Changed from center to flex-start */
      min-height: 100vh;
      padding: 20px 20px 40px; /* Added bottom padding */
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      overflow-y: auto; /* Enable scrolling if needed */
    }

    /* SIGNUP CARD */
    .signup-card {
      background: #fff;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      padding: 30px 35px; /* Reduced padding */
      width: 100%;
      max-width: 500px;
      border: 1px solid #e9ecef;
      margin-top: 20px; /* Added top margin */
      margin-bottom: 20px; /* Added bottom margin */
    }

    .signup-card .logo {
      display: block;
      margin: 0 auto 15px; /* Reduced margin */
      width: 70px; /* Smaller logo */
      height: auto;
    }

    .signup-card h5 {
      text-align: center;
      color: #2c3e50;
      font-weight: 700;
      margin-bottom: 12px; /* Reduced margin */
      line-height: 1.3;
      font-size: 1rem; /* Smaller font */
    }

    .signup-card h6 {
      text-align: center;
      color: #e67e22;
      font-weight: 700;
      margin-bottom: 8px; /* Reduced margin */
      font-size: 1.2rem; /* Smaller font */
    }

    .signup-card > p {
      text-align: center;
      color: #7f8c8d;
      margin-bottom: 20px; /* Reduced margin */
      font-size: 0.9rem; /* Smaller font */
    }

    .alert {
      border: none;
      border-radius: 10px;
      font-weight: 500;
      font-size: 0.85rem; /* Smaller font */
      padding: 10px 12px; /* Reduced padding */
      margin-bottom: 15px;
    }

    .alert-danger {
      background: #ffeaea;
      color: #d63031;
      border-left: 4px solid #d63031;
    }

    .alert-success {
      background: #e8f8f5;
      color: #27ae60;
      border-left: 4px solid #27ae60;
    }

    .form-control {
      border-radius: 10px;
      padding: 12px 14px; /* Reduced padding */
      border: 2px solid #e9ecef;
      margin-bottom: 15px; /* Reduced margin */
      font-size: 0.9rem; /* Smaller font */
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: #ffc107;
      box-shadow: 0 0 0 0.3rem rgba(255,193,7,0.15);
      transform: translateY(-2px);
    }

    .btn-register {
      width: 100%;
      background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
      color: #000;
      font-weight: 700;
      padding: 12px; /* Reduced padding */
      border: none;
      border-radius: 10px;
      transition: all 0.3s ease;
      font-size: 0.95rem; /* Smaller font */
      margin-top: 8px; /* Reduced margin */
      box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
    }

    .btn-register:hover {
      background: linear-gradient(135deg, #ffb300 0%, #ffa000 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
    }

    .login-link {
      text-align: center;
      font-size: 0.85rem; /* Smaller font */
      margin-top: 20px; /* Reduced margin */
      color: #666;
    }

    .login-link a {
      color: #e67e22;
      font-weight: 600;
      text-decoration: none;
      transition: color 0.3s ease;
    }

    .login-link a:hover {
      color: #d35400;
      text-decoration: underline;
    }

    /* Password Requirements */
    .password-requirements {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 10px;
      margin: 8px 0 15px 0; /* Reduced margins */
      padding: 12px; /* Reduced padding */
      font-size: 0.8rem; /* Smaller font */
      transition: all 0.3s ease;
      display: none;
    }

    .password-requirements.show {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .password-requirements small {
      display: block;
      color: #e74c3c;
      margin-bottom: 5px; /* Reduced margin */
      transition: color 0.3s ease;
      font-weight: 500;
    }

    .password-requirements small.valid {
      color: #27ae60;
    }

    #password-match {
      font-size: 0.8rem; /* Smaller font */
      margin: -8px 0 12px 0; /* Reduced margins */
      font-weight: 500;
    }

    #form-valid {
      color: #27ae60;
      font-weight: 600;
      text-align: center;
      margin-top: 12px; /* Reduced margin */
      opacity: 1;
      transition: all 0.5s ease;
      font-size: 0.85rem; /* Smaller font */
    }

    #form-valid.hide {
      opacity: 0;
      visibility: hidden;
    }

    /* Compact Resend Section */
    .resend-section {
      background: #fff9e6;
      border: 1px solid #ffc107;
      border-radius: 8px;
      padding: 10px 12px; /* Reduced padding */
      margin-top: 15px; /* Reduced margin */
      font-size: 0.8rem; /* Smaller font */
    }

    .resend-header {
      display: flex;
      align-items: center;
      gap: 6px; /* Reduced gap */
      margin-bottom: 6px; /* Reduced margin */
    }

    .resend-header i {
      color: #e67e22;
      font-size: 0.85rem; /* Smaller icon */
    }

    .resend-header h6 {
      color: #e67e22;
      font-size: 0.85rem; /* Smaller font */
      font-weight: 600;
      margin: 0;
    }

    .resend-timer {
      background: #fff;
      padding: 5px 8px; /* Reduced padding */
      border-radius: 5px;
      border-left: 3px solid #e74c3c;
      margin: 6px 0; /* Reduced margin */
      font-size: 0.75rem; /* Smaller font */
    }

    .resend-timer p {
      margin: 0;
      display: flex;
      align-items: center;
      gap: 4px; /* Reduced gap */
      color: #e74c3c;
      font-weight: 500;
    }

    .resend-timer i {
      font-size: 0.75rem; /* Smaller icon */
    }

    #resend-btn {
      width: 100%;
      background: #ffc107;
      border: none;
      color: #000;
      font-weight: 600;
      padding: 6px 10px; /* Reduced padding */
      border-radius: 6px;
      font-size: 0.75rem; /* Smaller font */
      transition: all 0.3s ease;
    }

    #resend-btn:hover:not(:disabled) {
      background: #ffb300;
      transform: translateY(-1px);
    }

    #resend-btn:disabled {
      background: #e0e0e0;
      color: #9e9e9e;
      cursor: not-allowed;
      transform: none;
    }

    .resend-note {
      font-size: 0.7rem; /* Smaller font */
      color: #666;
      margin-top: 6px; /* Reduced margin */
      text-align: center;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      body {
        padding: 15px 15px 30px; /* Adjusted padding */
        align-items: flex-start;
      }

      .signup-card {
        padding: 25px 20px; /* Further reduced padding */
        max-width: 100%;
        border-radius: 12px;
        margin-top: 15px;
      }

      .signup-card .logo {
        width: 60px; /* Smaller logo */
        margin-bottom: 12px;
      }

      .signup-card h5 {
        font-size: 0.95rem;
      }

      .signup-card h6 {
        font-size: 1.1rem;
      }

      .form-control {
        padding: 11px 12px; /* Further reduced padding */
        font-size: 0.85rem;
      }

      .btn-register {
        padding: 11px; /* Further reduced padding */
      }

      .resend-section {
        padding: 8px 10px; /* Further reduced padding */
        margin-top: 12px;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 10px 10px 20px; /* Adjusted padding */
      }

      .signup-card {
        padding: 20px 15px; /* Further reduced padding */
        border-radius: 10px;
        margin-top: 10px;
      }

      .signup-card .logo {
        width: 55px; /* Smaller logo */
      }

      .signup-card h5 {
        font-size: 0.85rem;
        line-height: 1.3;
      }

      .signup-card h6 {
        font-size: 1rem;
      }

      .signup-card > p {
        font-size: 0.8rem;
      }

      .form-control {
        padding: 10px 12px;
        font-size: 0.85rem;
        margin-bottom: 12px;
      }

      .btn-register {
        padding: 10px;
        font-size: 0.9rem;
      }

      .resend-section {
        padding: 6px 8px;
        font-size: 0.75rem;
      }

      .resend-header {
        gap: 5px;
      }

      .resend-header h6 {
        font-size: 0.8rem;
      }

      .resend-timer {
        padding: 4px 6px;
        font-size: 0.7rem;
      }

      #resend-btn {
        padding: 5px 8px;
        font-size: 0.7rem;
      }

      .resend-note {
        font-size: 0.65rem;
      }
    }

    @media (max-width: 360px) {
      .signup-card {
        padding: 15px 12px;
      }

      .signup-card h5 {
        font-size: 0.8rem;
      }

      .signup-card h6 {
        font-size: 0.95rem;
      }

      .form-control {
        padding: 8px 10px;
      }
      
      .resend-section {
        padding: 5px 6px;
      }
    }

    /* Ensure content fits on screen */
    @media (max-height: 700px) {
      body {
        align-items: flex-start;
        padding-top: 10px;
      }
      
      .signup-card {
        margin-top: 10px;
      }
    }
  </style>
</head>

<body>
  <div class="signup-card">
    <!-- SCHOOL LOGO -->
    <img src="img/logo.png" alt="ASCOT Logo" class="logo">

    <!-- SCHOOL NAME -->
    <h5>
      AURORA STATE COLLEGE OF TECHNOLOGY<br>
      ONLINE SCHOOL CLINIC
    </h5>

    <h6 class="fw-bold">SIGN UP</h6>
    <p>Create your account to get started</p>

    <!-- Error/Success Messages -->
    <?php
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }

    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success text-center">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']);
    }
    ?>

    <form method="POST" action="process_signup.php" onsubmit="return validateForm()">
      <input type="text" name="fullname" id="fullname" class="form-control" placeholder="Full Name:" required onkeyup="checkFormComplete()" />
      <input type="text" name="student_number" id="student_number" class="form-control" placeholder="Student Number:" required onkeyup="checkFormComplete()" />
      <input type="email" name="email" id="email" class="form-control" placeholder="Email Address:" required onkeyup="checkFormComplete()" />

      <!-- Password Field -->
      <input type="password" name="password" id="password" class="form-control" placeholder="Password:" required
             onfocus="showPasswordRequirements()" 
             onkeyup="checkPasswordStrength(); checkPasswordMatch(); checkFormComplete();" />

      <!-- Password Requirements Box -->
      <div id="password-requirements" class="password-requirements">
        <small id="req-length">✗ At least 6 characters</small>
        <small id="req-number">✗ At least 1 number</small>
        <small id="req-symbol">✗ At least 1 special character (!, @, #, $, %, ^, &, *)</small>
        <small id="req-letter">✗ At least 1 letter</small>
      </div>

      <!-- Confirm Password -->
      <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password:" required 
             onkeyup="checkPasswordMatch(); checkFormComplete();" />
      <div id="password-match" class="small mt-1"></div>

      <button type="submit" class="btn btn-register">CREATE ACCOUNT</button>

      <!-- ✅ Validity Message -->
      <div id="form-valid"></div>
    </form>

    <!-- Compact Resend Activation Code Section -->
    <?php 
    if (isset($_SESSION['resend_email']) && isset($_SESSION['show_countdown'])):
        require_once 'includes/db_connect.php';
        $email = $_SESSION['resend_email'];
        $check_verified = $pdo->prepare("SELECT is_verified FROM users WHERE email = ?");
        $check_verified->execute([$email]);
        $user_status = $check_verified->fetch(PDO::FETCH_ASSOC);
        
        if ($user_status && $user_status['is_verified'] == 0):
    ?>
    <div class="resend-section">
      <div class="resend-header">
        <i class="fas fa-envelope"></i>
        <h6>No activation email?</h6>
      </div>
      
      <!-- Countdown Timer -->
      <div id="countdown-timer" class="resend-timer">
        <p>
          <i class="fas fa-clock"></i>
          <span>Resend available in: <strong id="countdown">60</strong>s</span>
        </p>
      </div>
      
      <form method="POST" action="resend_activation.php" id="resend-form">
        <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['resend_email']); ?>">
        <button type="submit" class="btn" id="resend-btn">
          <i class="fas fa-redo-alt"></i> Resend Code
        </button>
      </form>
      
      <div class="resend-note">
        <small>Check spam folder. Code expires in 60s.</small>
      </div>
    </div>
    <?php 
        else:
            unset($_SESSION['resend_email']);
            unset($_SESSION['show_countdown']);
        endif;
    endif; 
    ?>

    <div class="login-link">
      Already have an account? <a href="student_login.php">Log in here</a>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
    function showPasswordRequirements() {
      const box = document.getElementById('password-requirements');
      box.classList.add('show');
    }

    function checkPasswordStrength() {
      const password = document.getElementById('password').value;
      const box = document.getElementById('password-requirements');

      if (password.length > 0) {
        box.classList.add('show');
      } else {
        box.classList.remove('show');
      }

      const hasLength = password.length >= 6;
      const hasNumber = /\d/.test(password);
      const hasSymbol = /[!@#$%^&*]/.test(password);
      const hasLetter = /[A-Za-z]/.test(password);

      document.getElementById('req-length').innerHTML = (hasLength ? "✓" : "✗") + " At least 6 characters";
      document.getElementById('req-number').innerHTML = (hasNumber ? "✓" : "✗") + " At least 1 number";
      document.getElementById('req-symbol').innerHTML = (hasSymbol ? "✓" : "✗") + " At least 1 special character (!@#$%^&*)";
      document.getElementById('req-letter').innerHTML = (hasLetter ? "✓" : "✗") + " At least 1 letter";

      document.getElementById('req-length').classList.toggle('valid', hasLength);
      document.getElementById('req-number').classList.toggle('valid', hasNumber);
      document.getElementById('req-symbol').classList.toggle('valid', hasSymbol);
      document.getElementById('req-letter').classList.toggle('valid', hasLetter);

      if (hasLength && hasNumber && hasSymbol && hasLetter) {
        setTimeout(() => {
          box.classList.remove('show');
        }, 1000);
      }
    }

    function checkPasswordMatch() {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const matchElement = document.getElementById('password-match');

      if (confirmPassword === '') {
        matchElement.innerHTML = '';
      } else if (password === confirmPassword) {
        matchElement.innerHTML = '✓ Passwords match';
        matchElement.style.color = '#27ae60';
      } else {
        matchElement.innerHTML = '✗ Passwords do not match';
        matchElement.style.color = '#e74c3c';
      }
    }

    function checkFormComplete() {
      const fullname = document.getElementById('fullname').value.trim();
      const student_number = document.getElementById('student_number').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      const hasLength = password.length >= 6;
      const hasNumber = /\d/.test(password);
      const hasSymbol = /[!@#$%^&*]/.test(password);
      const hasLetter = /[A-Za-z]/.test(password);

      const allValid = fullname && student_number && email &&
                      hasLength && hasNumber && hasSymbol && hasLetter &&
                      password === confirmPassword;

      const validMsg = document.getElementById('form-valid');

      if (allValid) {
        validMsg.innerHTML = "✅ All fields are valid!";
        validMsg.classList.remove('hide');
        clearTimeout(validMsg.timeout);
        validMsg.timeout = setTimeout(() => {
          validMsg.classList.add('hide');
        }, 5000);
      } else {
        validMsg.innerHTML = "";
        validMsg.classList.add('hide');
      }
    }

    function validateForm() {
      const fullname = document.getElementById('fullname').value.trim();
      const student_number = document.getElementById('student_number').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      const hasLength = password.length >= 6;
      const hasNumber = /\d/.test(password);
      const hasSymbol = /[!@#$%^&*]/.test(password);
      const hasLetter = /[A-Za-z]/.test(password);

      if (!fullname || !student_number || !email || !password || !confirmPassword) {
        alert('Please fill out all fields.');
        return false;
      }

      if (!hasLength || !hasNumber || !hasSymbol || !hasLetter) {
        alert('Password must include at least 6 characters, 1 letter, 1 number, and 1 special character (!@#$%^&*).');
        return false;
      }

      if (password !== confirmPassword) {
        alert('Passwords do not match.');
        return false;
      }

      return true;
    }

    // Simple Countdown Timer
    document.addEventListener('DOMContentLoaded', function() {
      const countdownTimer = document.getElementById('countdown-timer');
      const countdownDisplay = document.getElementById('countdown');
      const resendBtn = document.getElementById('resend-btn');
      const resendForm = document.getElementById('resend-form');
      
      if (countdownTimer && resendBtn) {
        let countdown = localStorage.getItem('countdown_remaining') || 60;
        countdown = parseInt(countdown);
        
        let timerInterval;
        
        function startCountdown() {
          if (countdown <= 0) {
            finishCountdown();
            return;
          }
          
          resendBtn.disabled = true;
          updateDisplay();
          
          timerInterval = setInterval(function() {
            countdown--;
            localStorage.setItem('countdown_remaining', countdown);
            updateDisplay();
            
            if (countdown <= 0) {
              clearInterval(timerInterval);
              finishCountdown();
              localStorage.removeItem('countdown_remaining');
            }
          }, 1000);
        }
        
        function updateDisplay() {
          if (countdownDisplay) countdownDisplay.textContent = countdown;
        }
        
        function finishCountdown() {
          resendBtn.disabled = false;
        }
        
        // Handle form submission
        if (resendForm) {
          resendForm.addEventListener('submit', function(e) {
            if (!resendBtn.disabled) {
              localStorage.setItem('countdown_remaining', 60);
              countdown = 60;
              resendBtn.disabled = true;
              resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
              setTimeout(startCountdown, 2000);
            }
          });
        }
        
        startCountdown();
      }
    });

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.opacity = '0';
          setTimeout(() => alert.remove(), 500);
        }, 5000);
      });
    });
  </script>

  <!-- Bootstrap JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>