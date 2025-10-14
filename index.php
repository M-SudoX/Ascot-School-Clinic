<?php
// FRONTEND: HTML + CSS + JavaScript + Bootstrap
// BACKEND: PHP + PDO + MySQL + Sessions
// SECURITY: Parameter Binding + Input Validation + Session Protection
// DESIGN: Responsive Layout + Professional Styling + Intuitive UI

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration for 24-hour timeout
ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_set_cookie_params(86400);
session_start();

// Include database connection
require_once 'includes/db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aurora State College of Technology - Online School Clinic</title>

  <!-- Bootstrap CSS -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Custom CSS -->
  <style>
    /* Global page styling */
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      margin: 0;
      background-color: #f8f9fa;
      font-family: "Poppins", sans-serif;
    }

    .split {
      display: flex;
      flex: 1;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }

    /* Layout for large screens */
    @media (min-width: 768px) {
      body {
        flex-direction: row;
      }
      .split {
        width: 50%;
        height: 100vh;
      }
    }

    .left {
      background: linear-gradient(135deg, #ffda6a, #fff7da);
      color: #fff;
      text-align: center;
    }

    .right {
      background: url('img/clinic-bg.jpg') center/cover no-repeat;
      position: relative;
    }

    .right::before {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.4);
    }

    .logo-left, .logo-right {
      width: 120px;
      height: 120px;
      margin-bottom: 15px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }

    h5 {
      font-size: 1.1rem;
      font-weight: 600;
      line-height: 1.5;
      letter-spacing: 0.5px;
      margin-bottom: 25px;
      color: #333;
    }

    label {
      font-weight: 500;
      color: #333;
    }

    select {
      border-radius: 8px;
      padding: 10px;
    }

    .btn-next {
      background-color: #0d6efd;
      color: white;
      font-weight: 500;
      padding: 10px 25px;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .btn-next:hover {
      background-color: #0b5ed7;
      transform: scale(1.05);
    }

    /* Responsive: center form nicely on mobile */
    form {
      max-width: 320px;
      width: 100%;
    }
  </style>
</head>

<body>
  <!-- LEFT SIDE -->
  <div class="split left">
    <img src="img/logo.png" alt="ASCOT Logo" class="logo-left" />
    <h5>
      AURORA STATE COLLEGE OF TECHNOLOGY<br>
      ONLINE SCHOOL CLINIC
    </h5>

    <form method="POST" action="process_user_type.php" class="shadow-lg p-4 rounded bg-light bg-opacity-25">
      <label for="userType" class="form-label">Select user type to log in</label>
      <select class="form-select mb-3" id="userType" name="userType" required>
        <option value="" disabled selected>Select user type</option>
        <option value="student">STUDENT</option>
        <option value="admin">ADMIN</option>
      </select>
      <div class="d-grid">
        <button type="submit" class="btn btn-next">Next</button>
      </div>
    </form>
  </div>

  <!-- RIGHT SIDE -->
  <div class="split right">
    <img src="img/logo.png" alt="ASCOT Logo" class="logo-right position-absolute top-50 start-50 translate-middle" />
  </div>

  <!-- Bootstrap JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
