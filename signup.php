<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign Up - ASCOT Online School Clinic</title>

  <!-- Bootstrap 5 (offline) -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (offline) -->
  <link href="assets/css/all.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

  <div class="main-container">
    <!-- Left side (yellow background with large logo) -->
    <div class="left-side">
      <img src="img/logo.png" alt="ASCOT Logo">
    </div>

    <!-- Right side (form section) -->
    <div class="right-side">
      <img src="img/logo.png" alt="ASCOT Logo" class="top-logo" />
      <h5>
        AURORA STATE COLLEGE OF TECHNOLOGY<br>
        ONLINE SCHOOL CLINIC
      </h5>

      <div class="form-container">
        <h6 class="fw-bold mb-3">SIGN UP</h6>
        <p class="mb-4">Create Account</p>

        <!-- Error/Success Messages -->
        <?php
        session_start();
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <form method="POST" action="process_signup.php" onsubmit="return validatePassword()">
          <input type="text" name="fullname" placeholder="Full Name:" required />
          <input type="text" name="student_number" placeholder="Student Number:" required />
          <input type="email" name="email" placeholder="Email:" required />
          
         <!-- Password with requirements -->
<input type="password" name="password" id="password" placeholder="Password:" required 
       onfocus="showPasswordRequirements()" onkeyup="checkPasswordStrength()" />

<!-- Initially hidden -->
<div id="password-requirements" class="password-requirements" style="display: none;">
    <small>Must contain special character: !@#$%^&*</small>
</div>

<input type="password" name="confirm_password" id="confirm_password" placeholder="Re-Type Password:" required 
       onkeyup="checkPasswordMatch()" />
<div id="password-match" class="small mt-1"></div>
          <button type="submit" class="btn btn-register mt-2">REGISTER</button>
        </form>

        <div class="login-link">
          Already have an account? <a href="index.php">LOG IN</a>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript for Password Validation -->
  <script>
    function checkPasswordStrength() {
      const password = document.getElementById('password').value;
      
      // Check requirements - SPECIAL CHARACTERS LANG
      const hasLength = password.length >= 6;
      const hasSpecial = /[!@#$%^&*]/.test(password);
      
      // Update requirement indicators
      document.getElementById('req-length').style.color = hasLength ? 'green' : 'red';
      document.getElementById('req-special').style.color = hasSpecial ? 'green' : 'red';
    }
    
    function checkPasswordMatch() {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const matchElement = document.getElementById('password-match');
      
      if (confirmPassword === '') {
        matchElement.innerHTML = '';
        matchElement.style.color = '';
      } else if (password === confirmPassword) {
        matchElement.innerHTML = '✓ Passwords match';
        matchElement.style.color = 'green';
      } else {
        matchElement.innerHTML = '✗ Passwords do not match';
        matchElement.style.color = 'red';
      }
    }
    
    function validatePassword() {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      
      // Check if passwords match
      if (password !== confirmPassword) {
        alert('Passwords do not match!');
        return false;
      }
      
      // Check password strength - SPECIAL CHARACTERS LANG
      const hasLength = password.length >= 6;
      const hasSpecial = /[!@#$%^&*]/.test(password);
      
      if (!hasLength || !hasSpecial) {
        alert('Password must be at least 6 characters long and contain at least one special character (!@#$%^&*)');
        return false;
      }
      
      return true;
    }
  </script>

</body>
</html>