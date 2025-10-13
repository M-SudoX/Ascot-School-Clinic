<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
  <div class="card p-4 shadow" style="max-width:400px;width:100%;">
    <h4 class="text-center mb-3">Forgot Password</h4>

    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-info"><?= htmlentities($_SESSION['flash']); ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <form action="send_code.php" method="POST">
      <div class="mb-3">
        <input type="text" name="identifier" class="form-control" placeholder="Enter your email or phone number" required>
      </div>

      <div class="d-flex justify-content-between">
        <a href="student_login.php" class="btn btn-dark px-4">Cancel</a>
        <button type="submit" class="btn btn-warning text-dark fw-bold px-4">Send Code</button>
      </div>
    </form>
  </div>
</body>
</html>
