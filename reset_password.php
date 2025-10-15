<?php
session_start();
date_default_timezone_set('Asia/Manila');
require 'includes/db_connect.php'; // must provide $pdo

$error = null;

// If no verified reset user, redirect back to forgot flow
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hasLetter = preg_match('/[a-zA-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecialChar = preg_match('/[!@#$%^&*]/', $password);

        if (!$hasLetter) {
            $error = "Password must contain at least 1 letter.";
        } elseif (!$hasNumber) {
            $error = "Password must contain at least 1 number.";
        } elseif (!$hasSpecialChar) {
            $error = "Password must contain at least 1 special character (!@#$%^&*).";
        } else {
            // Save hashed password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->execute([$hash, $_SESSION['reset_user_id']]);

            // Clear session reset markers
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['pending_reset_user']);

            // Flash and redirect
            $_SESSION['flash'] = 'Password updated successfully! You can now log in.';
            header("Location: student_login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reset Password - ASCOT Online School Clinic</title>

  <!-- Bootstrap 5 -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome (optional) -->
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <!-- Optional global CSS -->
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    /* ---------- Page layout (centered) ---------- */
    body {
      background: #f8f9fa;
      display: flex;
      justify-content: center;
      align-items: center; /* centered vertically */
      min-height: 100vh;
      padding: 12px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* ---------- Card ---------- */
    .signup-card {
      background: #fff;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      padding: 30px 35px;
      width: 100%;
      max-width: 520px;
      border: 1px solid #e9ecef;
      margin: 20px;
      box-sizing: border-box;
    }

    .signup-card .logo {
      display: block;
      margin: 0 auto 14px;
      width: 72px;
      height: auto;
    }

    .signup-card h5 {
      text-align: center;
      color: #2c3e50;
      font-weight: 700;
      margin-bottom: 10px;
      line-height: 1.2;
      font-size: 1rem;
    }

    .signup-card h6 {
      text-align: center;
      color: #e67e22;
      font-weight: 700;
      margin-bottom: 8px;
      font-size: 1.15rem;
    }

    .signup-card p.lead {
      text-align: center;
      color: #7f8c8d;
      margin-bottom: 18px;
      font-size: 0.95rem;
    }

    .alert {
      border: none;
      border-radius: 10px;
      font-weight: 500;
      font-size: 0.9rem;
      padding: 10px 12px;
      margin-bottom: 15px;
    }
    .alert-danger { background: #ffeaea; color: #d63031; border-left: 4px solid #d63031; }
    .alert-success { background: #e8f8f5; color: #27ae60; border-left: 4px solid #27ae60; }

    /* ---------- Inputs & Button ---------- */
    .form-control {
      border-radius: 10px;
      padding: 12px 14px;
      border: 2px solid #e9ecef;
      margin-bottom: 14px;
      font-size: 0.95rem;
      transition: all 0.25s ease;
    }
    .form-control:focus {
      border-color: #ffc107;
      box-shadow: 0 0 0 0.25rem rgba(255,193,7,0.12);
      transform: translateY(-2px);
    }

    .btn-update {
      width: 100%;
      background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
      color: #000;
      font-weight: 700;
      padding: 12px;
      border: none;
      border-radius: 10px;
      transition: all 0.25s ease;
      font-size: 0.98rem;
      margin-top: 6px;
      box-shadow: 0 4px 15px rgba(255, 193, 7, 0.25);
    }
    .btn-update:active { transform: translateY(-1px) scale(0.998); }
    .btn-update:hover {
      background: linear-gradient(135deg, #ffb300 0%, #ffa000 100%);
      transform: translateY(-3px);
      box-shadow: 0 6px 22px rgba(255, 170, 0, 0.28);
    }

    /* ---------- Password requirements box ---------- */
    .password-requirements {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 10px;
      margin: 6px 0 14px 0;
      padding: 12px;
      font-size: 0.9rem;
      color: #e74c3c;
      display: none;
      transform-origin: top center;
      opacity: 0;
      will-change: opacity, transform;
    }

    /* When shown: slide down + fade in */
    .password-requirements.show {
      display: block;
      animation: slideFadeIn 320ms ease forwards;
    }

    @keyframes slideFadeIn {
      from { opacity: 0; transform: translateY(-8px) scaleY(0.98); }
      to   { opacity: 1; transform: translateY(0) scaleY(1); }
    }

    /* When hiding with fade */
    .password-requirements.hide-anim {
      animation: slideFadeOut 320ms ease forwards;
    }
    @keyframes slideFadeOut {
      from { opacity: 1; transform: translateY(0); }
      to   { opacity: 0; transform: translateY(-6px); }
    }

    .password-requirements small {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #e74c3c;
    }
    .password-requirements small.valid {
      color: #27ae60;
    }

    #password-match {
      font-size: 0.88rem;
      margin: -6px 0 10px 0;
      font-weight: 600;
    }

    #form-valid {
      color: #27ae60;
      font-weight: 600;
      text-align: center;
      margin-top: 10px;
      opacity: 1;
      transition: all 0.4s ease;
      font-size: 0.92rem;
    }
    #form-valid.hide { opacity: 0; visibility: hidden; }

    .login-link { text-align:center; margin-top:12px; color:#666; }
    .login-link a { color:#e67e22; font-weight:600; text-decoration:none; }
    .login-link a:hover { color:#d35400; text-decoration:underline; }

    /* ---------- Responsiveness ---------- */
    @media (max-width: 520px) {
      .signup-card { padding: 22px 18px; margin: 12px; }
      .signup-card .logo { width: 60px; margin-bottom: 12px; }
      .form-control { font-size: 0.92rem; padding: 11px 12px; }
      .btn-update { padding: 11px; font-size: 0.95rem; }
    }

    /* Extra small screens: ensure spacing and avoid horizontal scroll */
    @media (max-width: 380px) {
      body { padding: 8px; }
      .signup-card { padding: 18px 14px; border-radius: 12px; margin: 10px; }
      .signup-card h6 { font-size: 1.05rem; }
      .signup-card p.lead { font-size: 0.88rem; margin-bottom: 14px; }
      .signup-card .logo { width: 52px; }
      .form-control { padding: 10px 12px; font-size: 0.9rem; }
      .password-requirements { font-size: 0.85rem; padding: 10px; margin: 6px 0 12px 0; }
      .password-requirements small { margin-bottom: 5px; font-weight: 600; }
      .btn-update { padding: 10px; font-size: 0.92rem; }
      #form-valid { font-size: 0.9rem; }
    }

    /* Very tall small devices: reduce top offset */
    @media (max-height: 600px) and (max-width: 520px) {
      .signup-card { margin: 8px; }
    }

  </style>
</head>
<body>
  <div class="signup-card" role="main" aria-labelledby="page-title">
    <img src="img/logo.png" alt="ASCOT Logo" class="logo">

    <h5 id="page-title">
      AURORA STATE COLLEGE OF TECHNOLOGY<br>
      ONLINE SCHOOL CLINIC
    </h5>

    <h6 class="fw-bold">RESET PASSWORD</h6>
    <p class="lead">Set a new secure password for your account</p>

    <!-- Server-side messages -->
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlentities($error); ?></div>
    <?php endif; ?>

    <?php
      if (!empty($_SESSION['flash'])) {
        echo '<div class="alert alert-success text-center">' . htmlentities($_SESSION['flash']) . '</div>';
        unset($_SESSION['flash']);
      }
    ?>

    <form method="POST" onsubmit="return clientValidate();">
      <!-- New password -->
      <input
        type="password"
        name="password"
        id="password"
        class="form-control"
        placeholder="New password"
        required
        minlength="8"
        aria-describedby="password-requirements"
        oninput="handlePasswordInput();"
      >

      <!-- Password requirements (hidden by default) -->
      <div id="password-requirements" class="password-requirements" aria-live="polite">
        <small id="req-length">✗ At least 8 characters</small>
        <small id="req-number">✗ At least 1 number</small>
        <small id="req-symbol">✗ At least 1 special character (!@#$%^&*)</small>
        <small id="req-letter">✗ At least 1 letter</small>
      </div>

      <!-- Confirm password -->
      <input
        type="password"
        name="confirm"
        id="confirm"
        class="form-control"
        placeholder="Confirm password"
        required
        minlength="8"
        oninput="checkPasswordMatch(); checkFormComplete();"
      >

      <div id="password-match" aria-live="polite"></div>

      <button type="submit" class="btn-update">UPDATE PASSWORD</button>

      <div id="form-valid" class="hide" aria-live="polite"></div>
    </form>

    <div class="login-link">
      <a href="student_login.php">Back to Login</a>
    </div>
  </div>

  <!-- Scripts -->
  <script>
    // Elements
    const passwordInput = document.getElementById('password');
    const confirmInput  = document.getElementById('confirm');
    const reqBox = document.getElementById('password-requirements');
    const reqLength = document.getElementById('req-length');
    const reqNumber = document.getElementById('req-number');
    const reqSymbol = document.getElementById('req-symbol');
    const reqLetter = document.getElementById('req-letter');
    const matchEl = document.getElementById('password-match');
    const formValid = document.getElementById('form-valid');

    // Helper: show requirements (adds class .show)
    function showReqBox() {
      reqBox.classList.remove('hide-anim');
      reqBox.classList.add('show');
    }

    // Helper: hide with animation
    function hideReqBoxAnimated() {
      reqBox.classList.add('hide-anim');
      setTimeout(() => {
        reqBox.classList.remove('show');
        reqBox.classList.remove('hide-anim');
        reqBox.style.display = 'none';
      }, 340);
    }

    // When typing or deleting in password field
    function handlePasswordInput() {
      const pwd = passwordInput.value;
      const trimmed = pwd;

      // If user starts typing (has at least 1 char), show box. If empty, hide immediately.
      if (trimmed.length > 0) {
        // ensure visible (display block) before adding .show to trigger animation
        reqBox.style.display = 'block';
        requestAnimationFrame(() => { showReqBox(); });
      } else {
        // hide immediately if blank
        reqBox.classList.remove('show');
        reqBox.style.display = 'none';
      }

      updateRequirements(trimmed);
      checkPasswordMatch();
      checkFormComplete();
    }

    function updateRequirements(password) {
      const hasLength = password.length >= 8;
      const hasNumber = /\d/.test(password);
      const hasSymbol = /[!@#$%^&*]/.test(password);
      const hasLetter = /[A-Za-z]/.test(password);

      // Update text + classes
      reqLength.innerHTML = (hasLength ? "✓" : "✗") + " At least 8 characters";
      reqNumber.innerHTML = (hasNumber ? "✓" : "✗") + " At least 1 number";
      reqSymbol.innerHTML = (hasSymbol ? "✓" : "✗") + " At least 1 special character (!@#$%^&*)";
      reqLetter.innerHTML = (hasLetter ? "✓" : "✗") + " At least 1 letter";

      reqLength.classList.toggle('valid', hasLength);
      reqNumber.classList.toggle('valid', hasNumber);
      reqSymbol.classList.toggle('valid', hasSymbol);
      reqLetter.classList.toggle('valid', hasLetter);

      // If all valid -> auto-hide after a short delay
      if (hasLength && hasNumber && hasSymbol && hasLetter) {
        setTimeout(() => {
          hideReqBoxAnimated();
        }, 850);
      }
    }

    function checkPasswordMatch() {
      const pwd = passwordInput.value;
      const conf = confirmInput.value;

      if (conf === '') {
        matchEl.innerHTML = '';
      } else if (pwd === conf) {
        matchEl.innerHTML = '✓ Passwords match';
        matchEl.style.color = '#27ae60';
      } else {
        matchEl.innerHTML = '✗ Passwords do not match';
        matchEl.style.color = '#e74c3c';
      }
    }

    function checkFormComplete() {
      const pwd = passwordInput.value;
      const conf = confirmInput.value;

      const hasLength = pwd.length >= 8;
      const hasNumber = /\d/.test(pwd);
      const hasSymbol = /[!@#$%^&*]/.test(pwd);
      const hasLetter = /[A-Za-z]/.test(pwd);

      const allValid = hasLength && hasNumber && hasSymbol && hasLetter && (pwd === conf);
      if (allValid) {
        formValid.innerHTML = "✅ Password looks good!";
        formValid.classList.remove('hide');
        clearTimeout(formValid._timeout);
        formValid._timeout = setTimeout(() => {
          formValid.classList.add('hide');
        }, 3500);
      } else {
        formValid.innerHTML = "";
        formValid.classList.add('hide');
      }
    }

    function clientValidate() {
      const pwd = passwordInput.value;
      const conf = confirmInput.value;

      const hasLength = pwd.length >= 8;
      const hasNumber = /\d/.test(pwd);
      const hasSymbol = /[!@#$%^&*]/.test(pwd);
      const hasLetter = /[A-Za-z]/.test(pwd);

      if (!hasLength || !hasNumber || !hasSymbol || !hasLetter) {
        alert('Password must include at least 8 characters, 1 letter, 1 number, and 1 special character (!@#$%^&*).');
        return false;
      }
      if (pwd !== conf) {
        alert('Passwords do not match.');
        return false;
      }
      return true;
    }

    // Accessibility: hide reqBox at load and auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
      reqBox.style.display = 'none';

      // auto-hide any server alerts after 5s
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.transition = 'opacity 300ms ease';
          alert.style.opacity = '0';
          setTimeout(() => { if (alert.parentNode) alert.parentNode.removeChild(alert); }, 350);
        }, 5000);
      });
    });
  </script>

  <!-- Bootstrap JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
