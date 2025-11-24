<?php
// handle_bell_click.php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

// Mark that the bell has been clicked
$_SESSION['bell_clicked'] = true;

// Redirect back to dashboard
header("Location: student_dashboard.php");
exit();
?>