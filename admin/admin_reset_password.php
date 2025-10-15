<?php
session_start();
require '../includes/db_connect.php';

if (!isset($_SESSION['verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: admin_forget_password.php");
    exit();
}

$message = '';

// ✅ Password Strength Checker (PHP side)
function isStrongPassword($password) {
    return preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[!@#$%^&*]).{6,}$/', $password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (!isStrongPassword($password)) {
        $message = "Password must have at least 6 characters, 1 letter, 1 number, and 1 special character (!@#$%^&*).";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE email = ?");
        $stmt->execute([$hashed, $_SESSION['reset_email']]);

        unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['verified']);
        $_SESSION['success'] = "Password reset successful! Please log in.";
        header("Location: admin_login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password - Admin | ASCOT Online School Clinic</title>

<!-- BOOTSTRAP & ICONS -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/webfonts/all.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">

<style>
body {
  background: #f8f9fa;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
}

.reset-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.1);
  padding: 40px 35px;
  width: 100%;
  max-width: 400px;
}

.reset-card .logo {
  display: block;
  margin: 0 auto 15px;
  width: 80px;
}

.reset-card h5 {
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

#passwordRules {
  display: none;
  font-size: 0.9rem;
  margin-top: 5px;
  background: #fffbea;
  border-radius: 6px;
  padding: 8px 10px;
  box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
  transition: opacity 0.4s ease, max-height 0.4s ease;
  opacity: 1;
  max-height: 200px;
}
#passwordRules.hide {
  opacity: 0;
  max-height: 0;
  overflow: hidden;
}
#passwordRules span {
  display: block;
}
.valid {
  color: green;
}
.invalid {
  color: red;
}

#matchMessage {
  font-size: 0.9rem;
  margin-top: 5px;
  display: none;
}
#matchMessage.valid {
  color: green;
}
#matchMessage.invalid {
  color: red;
}

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
</style>
</head>
<body>

<div class="reset-card">
  <!-- LOGO -->
  <img src="../img/logo.png" alt="ASCOT Logo" class="logo">

  <!-- TITLE -->
  <h5>
    AURORA STATE COLLEGE OF TECHNOLOGY <br>
    <span class="text-warning">RESET ADMIN PASSWORD</span>
  </h5>

  <!-- MESSAGE -->
  <?php if ($message): ?>
    <div class="alert alert-danger alert-dismissible fade show text-center mt-2" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- FORM -->
  <form method="POST" id="resetForm" class="mt-3">
    <div class="position-relative mb-3">
      <input type="password" 
             name="password" 
             id="password" 
             class="form-control pe-5" 
             placeholder="New password" 
             required>
      <i class="bi bi-lock position-absolute top-50 end-0 translate-middle-y me-3"></i>

      <div id="passwordRules">
        <span id="length" class="invalid">• At least 6 characters</span>
        <span id="letter" class="invalid">• At least 1 letter</span>
        <span id="number" class="invalid">• At least 1 number</span>
        <span id="special" class="invalid">• At least 1 special character (!@#$%^&*)</span>
      </div>
    </div>

    <div class="position-relative mb-1">
      <input type="password" 
             name="confirm" 
             id="confirm" 
             class="form-control pe-5" 
             placeholder="Confirm password" 
             required>
      <i class="bi bi-shield-lock position-absolute top-50 end-0 translate-middle-y me-3"></i>
    </div>
    <div id="matchMessage"></div>

    <button type="submit" class="btn btn-warning w-100 fw-bold text-dark mt-3">Reset Password</button>

    <div class="back-link">
      <a href="admin_login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
  </form>
</div>

<!-- JS -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm');
const matchMessage = document.getElementById('matchMessage');

const rules = document.getElementById('passwordRules');
const lengthRule = document.getElementById('length');
const letterRule = document.getElementById('letter');
const numberRule = document.getElementById('number');
const specialRule = document.getElementById('special');

// 🔹 Show password rules dynamically and hide once complete
passwordInput.addEventListener('input', () => {
  const value = passwordInput.value;

  // Show rules when typing
  if (value.length > 0) {
    rules.style.display = 'block';
    rules.classList.remove('hide');
  } else {
    rules.classList.add('hide');
    return;
  }

  // Validate
  const hasLength = value.length >= 6;
  const hasLetter = /[A-Za-z]/.test(value);
  const hasNumber = /\d/.test(value);
  const hasSpecial = /[!@#$%^&*]/.test(value);

  hasLength
    ? lengthRule.classList.replace('invalid', 'valid')
    : lengthRule.classList.replace('valid', 'invalid');
  hasLetter
    ? letterRule.classList.replace('invalid', 'valid')
    : letterRule.classList.replace('valid', 'invalid');
  hasNumber
    ? numberRule.classList.replace('invalid', 'valid')
    : numberRule.classList.replace('valid', 'invalid');
  hasSpecial
    ? specialRule.classList.replace('invalid', 'valid')
    : specialRule.classList.replace('valid', 'invalid');

  // 🔸 Hide smoothly when all are satisfied
  if (hasLength && hasLetter && hasNumber && hasSpecial) {
    rules.classList.add('hide');
  } else {
    rules.classList.remove('hide');
  }

  // Trigger match check in real time
  checkPasswordMatch();
});

// 🔹 Check password match dynamically
confirmInput.addEventListener('input', checkPasswordMatch);

function checkPasswordMatch() {
  const pass = passwordInput.value;
  const confirm = confirmInput.value;

  if (confirm.length === 0) {
    matchMessage.style.display = 'none';
    return;
  }

  matchMessage.style.display = 'block';

  if (pass === confirm) {
    matchMessage.textContent = "✅ Passwords match";
    matchMessage.classList.add('valid');
    matchMessage.classList.remove('invalid');
  } else {
    matchMessage.textContent = "❌ Passwords do not match";
    matchMessage.classList.add('invalid');
    matchMessage.classList.remove('valid');
  }
}

// Auto-hide alerts
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => new bootstrap.Alert(alert).close());
}, 5000);
</script>

</body>
</html>
