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

  <style>
    .password-requirements {
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 6px;
      margin-top: 8px;
      padding: 10px 15px;
      font-size: 0.9rem;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      transition: opacity 0.4s ease;
      display: none;
    }

    .password-requirements small {
      display: block;
      color: red;
      margin-bottom: 4px;
      transition: color 0.3s ease;
    }

    .password-requirements small.valid {
      color: green;
    }

    #password-match, #form-valid {
      font-size: 0.85rem;
    }

    #form-valid {
      color: green;
      font-weight: bold;
      text-align: center;
      margin-top: 12px;
      opacity: 1;
      transition: opacity 1.5s ease-out;
    }

    #form-valid.hide {
      opacity: 0;
      visibility: hidden;
    }
  </style>
</head>

<body>
  <div class="main-container">
    <!-- Left side -->
    <div class="left-side">
      <img src="img/logo.png" alt="ASCOT Logo">
    </div>

    <!-- Right side -->
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

        <form method="POST" action="process_signup.php" onsubmit="return validateForm()">
          <input type="text" name="fullname" id="fullname" placeholder="Full Name:" required onkeyup="checkFormComplete()" />
          <input type="text" name="student_number" id="student_number" placeholder="Student Number:" required onkeyup="checkFormComplete()" />
          <input type="email" name="email" id="email" placeholder="Email:" required onkeyup="checkFormComplete()" />

          <!-- Password Field -->
          <input type="password" name="password" id="password" placeholder="Password:" required
                 onkeyup="checkPasswordStrength(); checkPasswordMatch(); checkFormComplete();" />

          <!-- Password Requirements Box -->
          <div id="password-requirements" class="password-requirements">
            <small id="req-length">✗ At least 6 characters</small>
            <small id="req-number">✗ At least 1 number</small>
            <small id="req-symbol">✗ At least 1 special character (!, @, #, $, %, ^, &, *)</small>
            <small id="req-letter">✗ At least 1 letter</small>
          </div>

          <!-- Confirm Password -->
          <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-Type Password:" required 
                 onkeyup="checkPasswordMatch(); checkFormComplete();" />
          <div id="password-match" class="small mt-1"></div>

          <button type="submit" class="btn btn-register mt-2">REGISTER</button>

          <!-- ✅ Validity Message -->
          <div id="form-valid"></div>
        </form>

        <div class="login-link mt-3">
          Already have an account? <a href="index.php">LOG IN</a>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript for Validation -->
  <script>
    function checkPasswordStrength() {
      const password = document.getElementById('password').value;
      const box = document.getElementById('password-requirements');

      if (password.length > 0) {
        box.style.display = "block";
        box.style.opacity = "1";
      } else {
        box.style.opacity = "0";
        setTimeout(() => box.style.display = "none", 400);
      }

      // ✅ Separate checks for number and special character
      const hasLength = password.length >= 6;
      const hasNumber = /\d/.test(password);
      const hasSymbol = /[!@#$%^&*]/.test(password);
      const hasLetter = /[A-Za-z]/.test(password);

      const reqLength = document.getElementById('req-length');
      const reqNumber = document.getElementById('req-number');
      const reqSymbol = document.getElementById('req-symbol');
      const reqLetter = document.getElementById('req-letter');

      reqLength.innerHTML = (hasLength ? "✓" : "✗") + " At least 6 characters";
      reqNumber.innerHTML = (hasNumber ? "✓" : "✗") + " At least 1 number";
      reqSymbol.innerHTML = (hasSymbol ? "✓" : "✗") + " At least 1 special character !@#$%^&*";
      reqLetter.innerHTML = (hasLetter ? "✓" : "✗") + " At least 1 letter";

      reqLength.classList.toggle('valid', hasLength);
      reqNumber.classList.toggle('valid', hasNumber);
      reqSymbol.classList.toggle('valid', hasSymbol);
      reqLetter.classList.toggle('valid', hasLetter);

      if (hasLength && hasNumber && hasSymbol && hasLetter) {
        box.style.opacity = "0";
        setTimeout(() => box.style.display = "none", 400);
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
        matchElement.style.color = 'green';
      } else {
        matchElement.innerHTML = '✗ Passwords do not match';
        matchElement.style.color = 'red';
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

        // stay visible for 5 seconds then fade smoothly
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
        alert('Password must include at least 6 characters, 1 letter, 1 number, and 1 special character.!@#$%^&*');
        return false;
      }

      if (password !== confirmPassword) {
        alert('Passwords do not match.');
        return false;
      }

      return true;
    }
  </script>
</body>
</html>
