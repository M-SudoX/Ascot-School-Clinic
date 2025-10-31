<?php 
session_start();
require_once 'includes/db_connect.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ✅ Basic Validation
    if (empty($fullname) || empty($student_number) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'All fields are required.';
        header("Location: signup.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match.';
        header("Location: signup.php");
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long.';
        header("Location: signup.php");
        exit();
    }

    if (!preg_match('/[!@#$%^&*]/', $password)) {
        $_SESSION['error'] = 'Password must contain at least one special character (!@#$%^&*).';
        header("Location: signup.php");
        exit();
    }

    try {
        // ✅ Check if account exists
        $check_stmt = $pdo->prepare("SELECT id, email, is_verified FROM users WHERE student_number = ? OR email = ?");
        $check_stmt->execute([$student_number, $email]);
        $existing_user = $check_stmt->fetch(PDO::FETCH_ASSOC);

        // 🔹 If user exists but not verified — show resend option
        if ($existing_user && $existing_user['is_verified'] == 0) {
            $_SESSION['resend_email'] = $email;
            $_SESSION['show_countdown'] = true;
            $_SESSION['countdown_start'] = time();
            $_SESSION['error'] = 'Your account already exists but is not yet activated. You can resend the activation email below.';
            header("Location: signup.php");
            exit();
        }

        // 🔹 If user exists and is verified — stop registration
        if ($existing_user && $existing_user['is_verified'] == 1) {
            $_SESSION['error'] = 'Student number or email already exists.';
            header("Location: signup.php");
            exit();
        }

        // ✅ Hash password and generate activation code
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $activation_code = bin2hex(random_bytes(16));
        $is_verified = 0;

        // ✅ Ensure required columns exist
        $pdo->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS activation_code VARCHAR(64) NULL");
        $pdo->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");
        $pdo->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS code_expiration DATETIME NULL");

        // ✅ Check for role column
        $check_role = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $role_exists = $check_role->rowCount() > 0;

        // ✅ Set expiration time (60 SECONDS from now)
        $code_expiration = date('Y-m-d H:i:s', strtotime('+60 seconds'));

        // ✅ Insert user record
        if ($role_exists) {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, student_number, email, password, role, activation_code, is_verified, code_expiration) 
                                   VALUES (?, ?, ?, ?, 'student', ?, ?, ?)");
            $stmt->execute([$fullname, $student_number, $email, $hashed_password, $activation_code, $is_verified, $code_expiration]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, student_number, email, password, activation_code, is_verified, code_expiration) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullname, $student_number, $email, $hashed_password, $activation_code, $is_verified, $code_expiration]);
        }

        $user_id = $pdo->lastInsertId();

        // ✅ Insert student information
        try {
            $current_school_year = date('Y') . '-' . (date('Y') + 1);
            $check_user_id_col = $pdo->query("SHOW COLUMNS FROM student_information LIKE 'user_id'");
            $has_user_id = $check_user_id_col->rowCount() > 0;

            if ($has_user_id) {
                $stmt2 = $pdo->prepare("INSERT INTO student_information 
                    (user_id, fullname, student_number, address, age, sex, civil_status, blood_type, father_name, course_year, cellphone_number, date, school_year, created_at, updated_at) 
                    VALUES (?, ?, ?, '', 0, '', '', '', '', '', '', CURDATE(), ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt2->execute([$user_id, $fullname, $student_number, $current_school_year]);
            } else {
                $stmt2 = $pdo->prepare("INSERT INTO student_information 
                    (fullname, student_number, address, age, sex, civil_status, blood_type, father_name, course_year, cellphone_number, date, school_year, created_at, updated_at) 
                    VALUES (?, ?, '', 0, '', '', '', '', '', '', CURDATE(), ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt2->execute([$fullname, $student_number, $current_school_year]);
            }
        } catch (PDOException $e) {
            error_log("Student info insert failed: " . $e->getMessage());
        }

        // ✅ Send Activation Email
        $email_sent = sendActivationEmail($email, $fullname, $activation_code);

        if ($email_sent) {
            $_SESSION['success'] = "Registration successful! Please check your email to activate your account. <strong>Activation code expires in 60 seconds.</strong>";
            $_SESSION['resend_email'] = $email;
            $_SESSION['show_countdown'] = true;
            $_SESSION['countdown_start'] = time();
        } else {
            $_SESSION['error'] = "Account created, but email sending failed. You can resend the activation email below.";
            $_SESSION['resend_email'] = $email;
            $_SESSION['show_countdown'] = true;
            $_SESSION['countdown_start'] = time();
        }

        header("Location: signup.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        header("Location: signup.php");
        exit();
    }

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
        $mail->Username = 'ascotclinic79@gmail.com'; 
        $mail->Password = 'ysgutmnwuesppaus'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('ascotclinic79@gmail.com', 'ASCOT Online Clinic');
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