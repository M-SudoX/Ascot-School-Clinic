<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login - ASCOT Online School Clinic</title>

  <!-- Bootstrap CSS (local copy) -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons (local copy) -->
  <link href="../assets/css/ bootstrap-icons.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="../assets/css/style.css" rel="stylesheet" />
</head>
<body>

  <!-- Left Section -->
  <div class="split left">
    <!-- Logo -->
    <img src="../img/logo.png" alt="ASCOT Logo" class="logo-left" />

    <!-- Header -->
    <h5>
      AURORA STATE COLLEGE OF TECHNOLOGY<br />
      ADMIN LOGIN
    </h5>

    <!-- Login Form -->
    <form action="process_admin_login.php" method="POST" class="mt-3 px-">
      <div class="container">
        <div class="mx-auto" style="max-width: 360px;"> 

          <!-- Input: Username -->
          <div class="position-relative mb-3">
            <input type="text" name="username" class="form-control pe-5" placeholder="Username" required>
            <i class="bi bi-person position-absolute top-50 end-0 translate-middle-y me-3"></i>
          </div>

          <!-- Input: Password -->
          <div class="position-relative mb-3">
            <input type="password" name="password" class="form-control pe-5" placeholder="********" required>
            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3"></i>
          </div>

          <!-- Buttons -->
          <div class="d-flex justify-content-between">
            <a href="../index.php" class="btn btn-dark px-4">Back</a>
            <button type="submit" class="btn btn-warning text-dark fw-bold px-4">Log in</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Forgot Password -->
    <div class="text-center mt-3">
      <a href="#" class="small text-decoration-none">Forgot Password? Contact IT admin.</a>
    </div>
  </div>

  <!-- Right Section -->
  <div class="split right">
    <img src="../img/logo.png" alt="ASCOT Logo" class="logo-right" />
  </div>

  <!-- Bootstrap Bundle JS -->
  <script src="../assets/css/bootstrap.bundle.min.js"></script>
</body>
</html>
