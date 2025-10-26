<?php
session_start();
require '../phpmailer/src/Exception.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect if no reset email
if (!isset($_SESSION['reset_email'])) {
    header("Location: admin_forget_password.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ VERIFY CODE BUTTON
    if (isset($_POST['verify'])) {
        $input_code = trim($_POST['code']);

        if ($input_code == $_SESSION['reset_code']) {
            $_SESSION['verified'] = true;
            header("Location: admin_reset_password.php");
            exit();
        } else {
            $message = "Invalid code. Please try again.";
        }
    }

    // 🔁 RESEND CODE BUTTON
    if (isset($_POST['resend'])) {
        $email = $_SESSION['reset_email'];
        $reset_code = rand(100000, 999999);
        $_SESSION['reset_code'] = $reset_code;

        // Send email again using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'zayantomara@gmail.com';
            $mail->Password = 'zjaoodlqbdtknyno';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('yourgmail@gmail.com', 'ASCOT Clinic');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'ASCOT Admin Password Reset Code (Resent)';
            $mail->Body = "
                <p>Hello,</p>
                <p>Your new ASCOT Admin password reset code is:</p>
                <h2 style='color:#ffc107;'>$reset_code</h2>
                <p>If you did not request this, please ignore this email.</p>
                <br><small>- ASCOT Online School Clinic</small>
            ";

            $mail->send();
            $message = "A new code has been sent to your email.";
        } catch (Exception $e) {
            $message = "Error sending email: " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Code - Admin | ASCOT Online School Clinic</title>

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

.verify-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.1);
  padding: 40px 35px;
  width: 100%;
  max-width: 400px;
}

.verify-card .logo {
  display: block;
  margin: 0 auto 15px;
  width: 80px;
}

.verify-card h5 {
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

.alert-info {
  background: #e1f5fe;
  color: #0277bd;
}

.form-control:focus {
  border-color: #ffc107;
  box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.25);
}

.btn-warning {
  background-color: #ffc107;
  border: none;
}

.btn-warning:hover {
  background-color: #e0a800;
}

.btn-outline-dark {
  border-radius: 8px;
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

<div class="verify-card">
  <!-- LOGO -->
  <img src="../img/logo.png" alt="ASCOT Logo" class="logo">

  <!-- TITLE -->
  <h5>
    AURORA STATE COLLEGE OF TECHNOLOGY <br>
    <span class="text-warning">VERIFY RESET CODE</span>
  </h5>

  <!-- MESSAGE -->
  <?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show text-center mt-2" role="alert">
      <i class="bi bi-info-circle-fill me-2"></i>
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- FORM -->
  <form method="POST" class="mt-3 text-center" id="verifyForm">
    <div class="position-relative mb-3">
      <input type="text" 
             name="code" 
             class="form-control text-center pe-5" 
             placeholder="Enter 6-digit code" 
             maxlength="6" 
             required>
      <i class="bi bi-shield-lock position-absolute top-50 end-0 translate-middle-y me-3"></i>
    </div>

    <div class="d-grid gap-2">
      <button type="submit" name="verify" class="btn btn-warning fw-bold text-dark">Verify Code</button>
      <button type="submit" name="resend" class="btn btn-outline-dark fw-bold" formnovalidate>Resend Code</button>
    </div>

    <div class="back-link">
      <a href="admin_forget_password.php"><i class="bi bi-arrow-left"></i> Back to Forgot Password</a>
    </div>
  </form>
</div>

<!-- JS -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function validateCode() {
  const code = document.querySelector('input[name="code"]').value.trim();
  if (code === '') {
    alert('Please enter the 6-digit code to verify.');
    return false;
  }
  return true;
}

// Auto-hide alerts
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => new bootstrap.Alert(alert).close());
}, 5000);
</script>

</body>
</html>
