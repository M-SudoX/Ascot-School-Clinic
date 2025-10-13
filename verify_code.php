<?php
session_start();
date_default_timezone_set('Asia/Manila');
require 'includes/db_connect.php';

// Optional: if user tries to open verify directly without flow, we allow it but show instructions
if (!isset($_SESSION['flash']) && !isset($_SESSION['pending_reset_user'])) {
    // show a gentle message (not mandatory)
    $_SESSION['flash'] = 'Enter the 6-digit code sent to your email or phone.';
}

// Handle POST verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $_SESSION['flash'] = 'Please enter the verification code.';
        header('Location: verify_code.php');
        exit;
    }

    // Make sure code is 6 digits numeric string
    if (!preg_match('/^\d{6}$/', $code)) {
        $_SESSION['flash'] = 'Invalid code format.';
        header('Location: verify_code.php');
        exit;
    }

    // Query DB for matching token that hasn't expired
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Verified: allow reset
        $_SESSION['reset_user_id'] = $user['id'];
        // Clear the pending marker if any
        unset($_SESSION['pending_reset_user']);
        // Optionally clear flash to avoid confusion
        unset($_SESSION['flash']);
        header("Location: reset_password.php");
        exit;
    } else {
        $_SESSION['flash'] = 'Invalid or expired code.';
        header('Location: verify_code.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Enter Verification Code</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="card p-4 shadow" style="max-width:400px;width:100%;">
    <h4 class="text-center mb-3">Enter Verification Code</h4>

    <?php if (isset($_SESSION['flash'])): ?>
      <?php $cls = ($_SESSION['flash'] === 'Invalid or expired code.' || $_SESSION['flash'] === 'Invalid code format.') ? 'alert-danger' : 'alert-info'; ?>
      <div class="alert <?= $cls; ?>"><?= htmlentities($_SESSION['flash']); ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="code" class="form-control mb-3" placeholder="6-digit code" required maxlength="6" pattern="\d{6}" inputmode="numeric">
      <div class="d-flex justify-content-between">
        <a href="forgot_password.php" class="btn btn-dark px-4">Cancel</a>
        <button type="submit" class="btn btn-warning text-dark fw-bold px-4">Verify</button>
      </div>
    </form>
  </div>
</body>
</html>
