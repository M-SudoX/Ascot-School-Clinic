<?php
session_start();
date_default_timezone_set('Asia/Manila');
require 'includes/db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Handle RESEND code
if (isset($_POST['resend']) && isset($_SESSION['pending_reset_user'])) {
    $user_id = $_SESSION['pending_reset_user'];
    $new_code = sprintf("%06d", rand(0, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $checkStmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $checkStmt->execute([$user_id]);
    $user = $checkStmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmt->execute([$new_code, $expires, $user_id]);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'cachemeifucan05@gmail.com';
            $mail->Password = 'zusittxqokhgzotm';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->setFrom('cachemeifucan05@gmail.com', 'ASCOT Online School Clinic');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Your New Password Reset Code';
            $mail->Body = "<p>Your new password reset code is: <b>{$new_code}</b></p><p>This code will expire in 10 minutes.</p>";
            $mail->send();
        } catch (Exception $e) {}

        $_SESSION['flash'] = "✅ A new verification code has been sent to your email!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash'] = "❌ User not found.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: verify_code.php");
    exit;
}

// ✅ Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
    $code = trim($_POST['code'] ?? '');

    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        $_SESSION['flash'] = "Please enter a valid 6-digit code.";
        $_SESSION['flash_type'] = "danger";
        header('Location: verify_code.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['verified_email'] = $user['email'];
        unset($_SESSION['pending_reset_user']);
        header("Location: reset_password.php");
        exit;
    } else {
        $_SESSION['flash'] = "Invalid or expired code. Please try again.";
        $_SESSION['flash_type'] = "danger";
        header('Location: verify_code.php');
        exit;
    }
}

if (!isset($_SESSION['pending_reset_user'])) {
    header("Location: forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Code - ASCOT Clinic</title>
<link href="assets/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/webfonts/all.min.css" rel="stylesheet">
<style>
body {
  background-color: #f8f9fa;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  padding: 15px;
}
.card {
  width: 100%;
  max-width: 400px;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.1);
  background: #fff;
  padding: 35px 25px;
}
.card .logo {
  display: block;
  margin: 0 auto 10px;
  width: 80px;
}
h5 {
  text-align: center;
  color: #333;
  font-weight: 600;
  margin-bottom: 8px;
}
p {
  text-align: center;
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 15px;
}
.alert {
  border: none;
  border-radius: 8px;
  text-align: center;
  font-weight: 500;
}
.alert-success {
  background: #d1e7dd;
  color: #0f5132;
}
.alert-danger {
  background: #f8d7da;
  color: #842029;
}
.alert-info {
  background: #cff4fc;
  color: #055160;
}
.form-control {
  text-align: center;
  letter-spacing: 6px;
  font-weight: 600;
  font-size: 20px;
  padding: 10px 0;
  height: 50px;
}
.form-control:focus {
  border-color: #ffc107;
  box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.25);
}
.btn-warning {
  background-color: #ffc107;
  border: none;
  font-weight: 600;
}
.btn-warning:hover {
  background-color: #e0ac07;
}
.btn-outline-secondary {
  font-weight: 500;
}
.helper-text {
  text-align: center;
  color: #6c757d;
  font-size: 0.85rem;
  margin-top: 5px;
}
.resend-btn {
  background: none;
  border: none;
  color: #ffc107;
  font-weight: 600;
  font-size: 0.9rem;
  margin-top: 10px;
}
.resend-btn:hover {
  text-decoration: underline;
  color: #e0ac07;
}
.countdown {
  text-align: center;
  color: #6c757d;
  font-size: 0.85rem;
  margin-top: 5px;
}
@media (max-width: 576px) {
  .card { padding: 30px 20px; }
  .form-control { font-size: 18px; letter-spacing: 4px; }
}
</style>
</head>
<body>
  <div class="card">
    <img src="img/logo.png" alt="ASCOT Logo" class="logo">
    <h5>Verification Code</h5>
    <p>Enter the 6-digit code sent to your email.</p>

    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?>">
        <?= htmlentities($_SESSION['flash']); ?>
      </div>
      <?php unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <form method="POST" id="verifyForm">
      <div class="mb-3">
        <input type="text" name="code" class="form-control" maxlength="6" required pattern="\d{6}" placeholder="000000" inputmode="numeric">
      </div>
      <div class="helper-text">Code expires in 10 minutes</div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <a href="forgot_password.php" class="btn btn-outline-secondary">Back</a>
        <button type="submit" class="btn btn-warning">Verify Code</button>
      </div>
    </form>

    <form method="POST" class="text-center mt-3">
      <input type="hidden" name="resend" value="1">
      <button type="submit" class="resend-btn" id="resendBtn">Resend Code</button>
      <div class="countdown" id="countdownTimer" style="display:none;">
        Resend available in <span id="countdown">60</span>s
      </div>
    </form>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Countdown for resend button
    const resendBtn = document.getElementById('resendBtn');
    const countdownTimer = document.getElementById('countdownTimer');
    const countdownDisplay = document.getElementById('countdown');
    let countdown = 0;
    function startCountdown() {
      countdown = 60;
      resendBtn.disabled = true;
      countdownTimer.style.display = 'block';
      const interval = setInterval(() => {
        countdown--;
        countdownDisplay.textContent = countdown;
        if (countdown <= 0) {
          clearInterval(interval);
          resendBtn.disabled = false;
          countdownTimer.style.display = 'none';
        }
      }, 1000);
    }
    resendBtn.addEventListener('click', () => startCountdown());
  </script>
</body>
</html>
