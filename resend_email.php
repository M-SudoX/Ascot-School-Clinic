<?php
session_start();
require_once 'includes/db_connect.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT fullname, activation_code, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['is_verified'] == 0) {
        $fullname = $user['fullname'];
        $activation_code = $user['activation_code'];
        $activation_link = "http://192.168.8.38:8080/ascot-school-clinic/activate.php?code=$activation_code";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'cachemeifucan05@gmail.com';
            $mail->Password = 'zusittxqokhgzotm';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('cachemeifucan05@gmail.com', 'ASCOT Online Clinic');
            $mail->addAddress($email, $fullname);
            $mail->isHTML(true);
            $mail->Subject = 'Resend: Activate Your ASCOT Online Clinic Account';
            $mail->Body = "
                <h3>Hello again, $fullname!</h3>
                <p>Here is your activation link again:</p>
                <a href='$activation_link'
                style='background:#ffc107;padding:10px 20px;text-decoration:none;border-radius:6px;color:black;'>Activate Account</a>
            ";

            $mail->send();

            $_SESSION['success'] = "A new activation email has been sent!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to resend email. Try again later.";
        }
    } else {
        $_SESSION['error'] = "This account is already verified or does not exist.";
    }
}

header("Location: signup.php");
exit();
?>
