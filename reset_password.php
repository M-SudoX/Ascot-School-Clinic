<?php
session_start();
date_default_timezone_set('Asia/Manila');
require 'includes/db_connect.php';

// ✅ FIX: Initialize error to avoid "Undefined variable" notice
$error = null;

// If no verified reset user, redirect back to forgot flow
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        // ✅ NEW: Password complexity validation
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
            // All good: hash and update
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->execute([$hash, $_SESSION['reset_user_id']]);

            // Clear session reset id
            unset($_SESSION['reset_user_id']);
            // Optionally clear pending marker
            unset($_SESSION['pending_reset_user']);

            // Redirect to login with success msg
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
  <meta charset="utf-8">
  <title>Reset Password</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 15px;
    }

    .card {
      border-radius: 18px;
      padding: 30px 25px !important;
      animation: fadeIn 0.4s ease-in-out;
    }

    .form-control {
      height: 48px;
      border-radius: 10px;
      border: 2px solid #e2e8f0;
      background: #f7fafc;
      font-size: 15px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: #667eea;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }

    .btn-warning {
      border-radius: 10px;
      transition: all 0.3s ease;
      height: 48px;
      font-size: 15px;
    }

    .btn-warning:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
    }

    h4 {
      font-weight: 700;
      color: #333;
    }

    .alert {
      border-radius: 10px;
      font-size: 13px;
      animation: slideIn 0.3s ease-out;
    }

    .password-requirements {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      border-left: 4px solid #667eea;
      font-size: 13px;
      max-height: 500px;
      overflow: hidden;
      transition: all 0.4s ease-in-out;
    }

    .password-requirements.collapsed {
      max-height: 45px;
      cursor: pointer;
      background: #e9ecef;
    }

    .password-requirements.collapsed:hover {
      background: #dee2e6;
    }

    .requirements-header {
      display: flex;
      justify-content: between;
      align-items: center;
      margin-bottom: 10px;
    }

    .requirements-header .title {
      font-weight: 600;
      color: #495057;
      flex: 1;
    }

    .requirements-header .toggle-icon {
      font-size: 12px;
      color: #6c757d;
      transition: transform 0.3s ease;
    }

    .password-requirements.collapsed .toggle-icon {
      transform: rotate(-90deg);
    }

    .password-requirements.collapsed .requirements-list {
      opacity: 0;
      visibility: hidden;
      height: 0;
      margin: 0;
    }

    .requirements-list {
      opacity: 1;
      visibility: visible;
      height: auto;
      transition: all 0.3s ease;
    }

    .requirement {
      margin-bottom: 5px;
      display: flex;
      align-items: center;
      transition: color 0.3s ease;
    }

    .requirement i {
      margin-right: 8px;
      font-size: 14px;
      width: 16px;
      text-align: center;
    }

    .requirement.valid {
      color: #28a745;
      font-weight: 500;
    }

    .requirement.invalid {
      color: #6c757d;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.98); }
      to { opacity: 1; transform: scale(1); }
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-5px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Mobile Optimization */
    @media (max-width: 450px) {
      .card {
        padding: 25px 20px !important;
      }

      h4 {
        font-size: 20px;
      }

      .form-control, .btn-warning {
        font-size: 14px;
        height: 45px;
      }

      .password-requirements {
        font-size: 12px;
        padding: 12px;
      }

      .password-requirements.collapsed {
        max-height: 40px;
      }
    }
  </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
  <div class="card shadow" style="max-width:400px;width:100%;">
    <h4 class="text-center mb-3">Set New Password</h4>

    <?php if (!empty($error)): ?> <!-- ✅ Safe check -->
      <div class="alert alert-danger"><?= htmlentities($error); ?></div>
    <?php endif; ?>

    <div class="password-requirements collapsed" id="passwordRequirements">
      <div class="requirements-header">
        <span class="title">Password Requirements</span>
        <span class="toggle-icon">▼</span>
      </div>
      <div class="requirements-list">
        <div class="requirement invalid" id="req-length">
          <i>•</i> At least 8 characters
        </div>
        <div class="requirement invalid" id="req-letter">
          <i>•</i> At least 1 letter
        </div>
        <div class="requirement invalid" id="req-number">
          <i>•</i> At least 1 number
        </div>
        <div class="requirement invalid" id="req-special">
          <i>•</i> At least 1 special character (!@#$%^&*)
        </div>
      </div>
    </div>

    <form method="POST" id="passwordForm">
      <div class="mb-3">
        <input type="password" name="password" class="form-control" placeholder="New password" required minlength="8" id="password">
      </div>
      <div class="mb-3">
        <input type="password" name="confirm" class="form-control" placeholder="Confirm password" required minlength="8" id="confirmPassword">
      </div>

      <div class="d-flex gap-2">
        <a href="student_login.php" class="btn btn-secondary w-100 fw-bold">Cancel</a>
        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark">Update Password</button>
      </div>
    </form>
  </div>

  <script>
    // Auto-hide/show password requirements
    const passwordRequirements = document.getElementById('passwordRequirements');
    const passwordInput = document.getElementById('password');
    
    // Toggle requirements visibility
    passwordRequirements.addEventListener('click', function() {
      this.classList.toggle('collapsed');
    });

    // Auto-expand when password field is focused
    passwordInput.addEventListener('focus', function() {
      passwordRequirements.classList.remove('collapsed');
    });

    // Auto-collapse when all requirements are met
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      
      // Check requirements
      const hasLength = password.length >= 8;
      const hasLetter = /[a-zA-Z]/.test(password);
      const hasNumber = /[0-9]/.test(password);
      const hasSpecial = /[!@#$%^&*]/.test(password);
      
      // Update requirement indicators
      updateRequirement('req-length', hasLength);
      updateRequirement('req-letter', hasLetter);
      updateRequirement('req-number', hasNumber);
      updateRequirement('req-special', hasSpecial);
      
      // Auto-hide if all requirements are met
      const allMet = hasLength && hasLetter && hasNumber && hasSpecial;
      if (allMet && !passwordRequirements.classList.contains('collapsed')) {
        setTimeout(() => {
          passwordRequirements.classList.add('collapsed');
        }, 1000); // Hide after 1 second delay
      }
      
      // Auto-show if user starts typing and requirements are collapsed
      if (password.length > 0 && passwordRequirements.classList.contains('collapsed') && !allMet) {
        passwordRequirements.classList.remove('collapsed');
      }
    });

    // Auto-collapse when clicking outside
    document.addEventListener('click', function(event) {
      if (!passwordRequirements.contains(event.target) && 
          event.target !== passwordInput && 
          !passwordInput.contains(event.target)) {
        const password = passwordInput.value;
        const hasLength = password.length >= 8;
        const hasLetter = /[a-zA-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*]/.test(password);
        const allMet = hasLength && hasLetter && hasNumber && hasSpecial;
        
        if (!allMet) {
          passwordRequirements.classList.add('collapsed');
        }
      }
    });

    function updateRequirement(elementId, isValid) {
      const element = document.getElementById(elementId);
      if (isValid) {
        element.classList.remove('invalid');
        element.classList.add('valid');
        element.innerHTML = '<i>✓</i> ' + element.textContent.substring(2);
      } else {
        element.classList.remove('valid');
        element.classList.add('invalid');
        element.innerHTML = '<i>•</i> ' + element.textContent.substring(2);
      }
    }

    // Real-time password match checking
    document.getElementById('confirmPassword').addEventListener('input', function() {
      const password = document.getElementById('password').value;
      const confirm = this.value;
      
      if (confirm && password !== confirm) {
        this.style.borderColor = '#dc3545';
        this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
      } else if (confirm && password === confirm) {
        this.style.borderColor = '#28a745';
        this.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
      } else {
        this.style.borderColor = '#e2e8f0';
        this.style.boxShadow = 'none';
      }
    });
  </script>
</body>
</html>