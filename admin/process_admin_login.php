<?php
session_start();
require_once '../includes/db_connect.php'; // adjust path kung nasa admin folder



// WHY: Check if form was submitted via POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';


     // WHY: Basic validation to prevent empty submissions
    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // ✅ Success login
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['fullname']       = $admin['fullname'] ?? $admin['username'];
            $_SESSION['role']           = 'admin';

            // Siguraduhin tama filename dito
            header("Location: admin_dashboard.php");
            exit();
        } else {
            echo "<script>alert('Invalid Username or Password'); window.location.href='admin_login.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Please enter both Username and Password'); window.location.href='admin_login.php';</script>";
        exit();
    }
}
