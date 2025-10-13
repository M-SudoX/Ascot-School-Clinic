<?php
session_start();
require_once 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($student_number) || empty($password)) {
        $_SESSION['error'] = 'Please enter both ID Number and Password.';
        header("Location: student_login.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id, student_number, fullname, password, is_verified FROM users WHERE student_number = :student_number LIMIT 1");
        $stmt->bindParam(':student_number', $student_number, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("LOGIN FAILED: User not found - " . $student_number);
            $_SESSION['error'] = 'Invalid ID Number or Password.';
            header("Location: student_login.php");
            exit();
        }

        // ✅ Check if account is activated
        if ($user['is_verified'] == 0) {
            $_SESSION['error'] = 'Your account is not activated. Please check your email.';
            header("Location: student_login.php");
            exit();
        }

        if (password_verify($password, $user['password'])) {
            $_SESSION['student_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['student_number'] = $user['student_number'];
            $_SESSION['last_login'] = time();

            error_log("LOGIN SUCCESS: " . $student_number);
            header("Location: student_dashboard.php");
            exit();
        } else {
            error_log("LOGIN FAILED: Wrong password for - " . $student_number);
            $_SESSION['error'] = 'Invalid ID Number or Password.';
            header("Location: student_login.php");
            exit();
        }

    } catch (PDOException $e) {
        error_log("Login system error: " . $e->getMessage());
        $_SESSION['error'] = 'System error. Please try again later.';
        header("Location: student_login.php");
        exit();
    }
} else {
    header("Location: student_login.php");
    exit();
}
?>
