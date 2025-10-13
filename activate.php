<?php
session_start();
require_once 'includes/db_connect.php';

if (isset($_GET['code'])) {
    $code = trim($_GET['code']);

    try {
        // Check if activation code exists
        $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE activation_code = ?");
        $stmt->execute([$code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['is_verified'] == 1) {
                $_SESSION['success'] = "Your account is already activated. You can log in now.";
                header("Location: student_login.php");
                exit();
            } else {
                // Activate account
                $update = $pdo->prepare("UPDATE users SET is_verified = 1, activation_code = NULL WHERE id = ?");
                $update->execute([$user['id']]);

                $_SESSION['success'] = "Your account has been successfully activated! You can now log in.";
                header("Location: student_login.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid activation link.";
            header("Location: student_login.php");
            exit();
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Activation failed: " . $e->getMessage();
        header("Location: student_login.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid activation request.";
    header("Location: student_login.php");
    exit();
}
?>
