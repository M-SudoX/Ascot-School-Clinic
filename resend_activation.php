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
    
    try {
        // Kunin ang user data mula sa database
        $stmt = $pdo->prepare("SELECT id, fullname, activation_code, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Kung verified na ang account - clear sessions and redirect to login
            if ($user['is_verified'] == 1) {
                $_SESSION['success'] = "Your account is already activated. You can login now.";
                unset($_SESSION['resend_email']);
                unset($_SESSION['show_countdown']);
                unset($_SESSION['countdown_start']);
                header("Location: login.php");
                exit();
            }
            
            // Gumawa ng bagong activation code at expiration (60 SECONDS)
            $new_activation_code = bin2hex(random_bytes(16));
            $new_expiration = date('Y-m-d H:i:s', strtotime('+60 seconds'));
            
            // I-update ang activation code sa database
            $update_stmt = $pdo->prepare("UPDATE users SET activation_code = ?, code_expiration = ? WHERE email = ?");
            $update_stmt->execute([$new_activation_code, $new_expiration, $email]);
            
            // I-send ang bagong activation email
            $email_sent = sendActivationEmail($email, $user['fullname'], $new_activation_code);
            
            if ($email_sent) {
                $_SESSION['success'] = "New activation email has been sent to your email address! <strong>Activation code expires in 60 seconds.</strong>";
                $_SESSION['resend_email'] = $email;
                $_SESSION['show_countdown'] = true;
                $_SESSION['countdown_start'] = time();
            } else {
                $_SESSION['error'] = "Failed to send activation email. Please try again.";
                $_SESSION['resend_email'] = $email;
                $_SESSION['show_countdown'] = true;
                $_SESSION['countdown_start'] = time();
            }
            
        } else {
            $_SESSION['error'] = "Email not found in our system.";
            unset($_SESSION['resend_email']);
            unset($_SESSION['show_countdown']);
            unset($_SESSION['countdown_start']);
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        unset($_SESSION['resend_email']);
        unset($_SESSION['show_countdown']);
        unset($_SESSION['countdown_start']);
    }
    
    // I-redirect pabalik sa signup page
    header("Location: signup.php");
    exit();
} else {
    header("Location: signup.php");
    exit();
}

/**
 * Function to send activation email
 */
function sendActivationEmail($email, $fullname, $activation_code) {
    try {
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'zayantomara@gmail.com'; 
        $mail->Password = 'zjaoodlqbdtknyno'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('zayantomara@gmail.com', 'ASCOT Online Clinic');
        $mail->addAddress($email, $fullname);

        $mail->isHTML(true);
        $mail->Subject = 'Activate Your ASCOT Online Clinic Account - CODE EXPIRES IN 60 SECONDS';

        $activation_link = "http://192.168.1.77:8080/ascot-school-clinic/activate.php?code=$activation_code";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif;'>
                <h2>Hello, $fullname!</h2>
                <p>Thank you for registering for the <b>ASCOT Online School Clinic</b>.</p>
                <p>Please click the button below to activate your account:</p>
                <p style='text-align:center;'>
                    <a href='$activation_link' 
                       style='background:#ffc107;color:#000;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>
                       Activate My Account
                    </a>
                </p>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p><a href='$activation_link'>$activation_link</a></p>
                <p style='color: #dc3545; font-weight: bold;'>
                    ⚠️ IMPORTANT: This activation link will expire in 60 seconds!
                </p>
                <p>If your code expires, you can request a new one from the signup page.</p>
                <hr>
                <small>ASCOT Online School Clinic</small>
            </div>
        ";

        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
?>