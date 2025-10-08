<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userType = $_POST["userType"];

    if ($userType === "student") {
        header("Location: student_login.php");
        exit();
    } elseif ($userType === "admin") {
        header("Location: admin/admin_login.php");
        exit();
    } else {
        header("Location: index.php?error=invalid_user_type");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
