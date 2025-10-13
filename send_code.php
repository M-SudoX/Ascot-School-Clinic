<?php
session_start();
date_default_timezone_set('Asia/Manila');

require 'includes/db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$identifier = trim($_POST['identifier'] ?? '');

if (empty($identifier)) {
    $_SESSION['flash'] = 'Please enter your email or phone number.';
    header('Location: forgot_password.php');
    exit;
}

// Generic message for security (do not reveal whether the account exists)
$genericMsg = 'If that email or number exists, a code has been sent.';

try {
    // Find user by email OR contact_number
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? OR contact_number = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // DB error - show generic message but optionally log the error on server
    error_log("DB error in send_code.php: " . $e->getMessage());
    $_SESSION['flash'] = $genericMsg;
    header('Location: verify_code.php');
    exit;
}

if ($user) {
    // Generate 6-digit code as string (preserves leading zeros)
    $code = sprintf('%06d', random_int(0, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Save reset token & expiry
    $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $update->execute([$code, $expires, $user['id']]);

    // Save a pending marker in session (helps control flow)
    $_SESSION['pending_reset_user'] = $user['id'];

    // Send email with PHPMailer (silent fail if sending fails)
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cachemeifucan05@gmail.com'; // <- replace if needed
        $mail->Password = 'zusittxqokhgzotm';         // <- replace if needed (app password)
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('cachemeifucan05@gmail.com', 'ASCOT Online School Clinic');
        $mail->addAddress($user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body = "
            <p>Hello,</p>
            <p>Your password reset code is: <b>{$code}</b></p>
            <p>This code will expire in 10 minutes.</p>
        ";
        $mail->send();
    } catch (Exception $e) {
        // Log but don't reveal to user
        error_log("Mail error in send_code.php: " . $mail->ErrorInfo);
    }
}

// Always redirect to verification page with the generic message
$_SESSION['flash'] = $genericMsg;
header('Location: verify_code.php');
exit;
