<?php
session_start();
require '../includes/db_connect.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../phpmailer/src/Exception.php';
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if admin email exists
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        $reset_code = rand(100000, 999999);
        $_SESSION['reset_code'] = $reset_code;
        $_SESSION['reset_email'] = $email;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'bihasamaynard070@gmail.com';   
            $mail->Password = 'zjopucbvhzfcuosv';     
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('bihasamaynard070@gmail.com', 'ASCOT Clinic');
            $mail->addAddress($email, $admin['username']);

            $mail->isHTML(true);
            $mail->Subject = 'ASCOT Admin Password Reset Code';
            $mail->Body = "
                <p>Hello <strong>{$admin['username']}</strong>,</p>
                <p>Your ASCOT Admin password reset code is:</p>
                <h2 style='color:#ffc107;'>$reset_code</h2>
                <p>If you did not request this, please ignore this email.</p>
                <br><small>- ASCOT Online School Clinic</small>
            ";

            $mail->send();
            header("Location: admin_verify_code.php");
            exit();
        } catch (Exception $e) {
            $message = "Mailer Error: " . $mail->ErrorInfo;
        }
    } else {
        $message = "Email not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password - Admin | ASCOT Online School Clinic</title>

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

    .forgot-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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

  <div class="forgot-card">
    <!-- LOGO -->
    <img src="../img/logo.png" alt="ASCOT Logo" class="logo">

    <!-- TITLE -->
    <h5>
      AURORA STATE COLLEGE OF TECHNOLOGY <br>
      <span class="text-warning">ADMIN PASSWORD RESET</span>
    </h5>

    <!-- MESSAGE -->
    <?php if ($message): ?>
      <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- FORM -->
    <form method="POST" class="mt-3">
      <div class="position-relative mb-3">
        <input type="email" 
               name="email" 
               class="form-control pe-5" 
               placeholder="Enter your admin email" 
               required>
        <i class="bi bi-envelope position-absolute top-50 end-0 translate-middle-y me-3"></i>
      </div>

      <button type="submit" class="btn btn-warning w-100 fw-bold text-dark">
        Send Reset Code
      </button>

      <div class="back-link">
        <a href="admin_login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
      </div>
    </form>
  </div>

  <!-- JS -->
  <script src="../assets/js/bootstrap.bundle.min.js"></script>

  <script>
    // AUTO HIDE ALERTS
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => new bootstrap.Alert(alert).close());
    }, 5000);
  </script>

</body>
</html>
