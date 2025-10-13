<?php
session_start();
date_default_timezone_set('Asia/Manila');
require 'includes/db_connect.php';

// If no verified reset user, redirect back to forgot flow
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="card p-4 shadow" style="max-width:400px;width:100%;">
    <h4 class="text-center mb-3">Set New Password</h4>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlentities($error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <input type="password" name="password" class="form-control" placeholder="New password" required minlength="8">
      </div>
      <div class="mb-3">
        <input type="password" name="confirm" class="form-control" placeholder="Confirm password" required minlength="8">
      </div>
      <button type="submit" class="btn btn-warning w-100 fw-bold text-dark">Update Password</button>
    </form>
  </div>
</body>
</html>
