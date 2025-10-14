<?php
session_start();
date_default_timezone_set('Asia/Manila');
require 'includes/db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Debug: Check what's in session
error_log("Session data: " . print_r($_SESSION, true));

// ✅ Handle RESEND first (independent from verification)
if (isset($_POST['resend']) && isset($_SESSION['pending_reset_user'])) {
    $user_id = $_SESSION['pending_reset_user'];  // This is the user ID, not email!
    
    error_log("Resend requested for user ID: " . $user_id);

    // Generate a new 6-digit token
    $new_code = sprintf("%06d", rand(0, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Update it in DB using user ID
    try {
        // First, let's check if the user exists and get their email
        $checkStmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
        $checkStmt->execute([$user_id]);
        $user = $checkStmt->fetch();
        
        if (!$user) {
            error_log("RESEND FAILED: User not found - ID: $user_id");
            $_SESSION['flash'] = "❌ User not found. Please request a new password reset.";
            $_SESSION['flash_type'] = 'danger';
            header("Location: verify_code.php");
            exit;
        }
        
        error_log("User found: " . $user['email'] . ", proceeding with update...");
        
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $result = $stmt->execute([$new_code, $expires, $user_id]);
        
        if ($result) {
            error_log("RESEND SUCCESS: New code $new_code for user ID: $user_id");
            
            // Send email again with PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'cachemeifucan05@gmail.com';
                $mail->Password = 'zusittxqokhgzotm';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('cachemeifucan05@gmail.com', 'ASCOT Online School Clinic');
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Your New Password Reset Code';
                $mail->Body = "
                    <p>Hello,</p>
                    <p>Your new password reset code is: <b>{$new_code}</b></p>
                    <p>This code will expire in 10 minutes.</p>
                ";
                $mail->send();
                error_log("Resend email sent successfully to: " . $user['email']);
            } catch (Exception $e) {
                error_log("Resend Mail error: " . $mail->ErrorInfo);
                // Still show success message even if email fails
            }
            
            $_SESSION['flash'] = "✅ A new verification code has been sent to your email!";
            $_SESSION['flash_type'] = 'success';
        } else {
            error_log("RESEND FAILED: Execute returned false for user ID: $user_id");
            $_SESSION['flash'] = "❌ Failed to generate new code. Please try again.";
            $_SESSION['flash_type'] = 'danger';
        }
    } catch (PDOException $e) {
        error_log("RESEND DB ERROR: " . $e->getMessage());
        $_SESSION['flash'] = "❌ Database error. Please try again.";
        $_SESSION['flash_type'] = 'danger';
    }
    
    header("Location: verify_code.php");
    exit;
}

// ✅ Show friendly message only if coming fresh
if (!isset($_SESSION['flash']) && !isset($_SESSION['pending_reset_user'])) {
    $_SESSION['flash'] = 'Enter the 6-digit code sent to your email.';
    $_SESSION['flash_type'] = 'info';
}

// ✅ Handle VERIFICATION submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $_SESSION['flash'] = 'Please enter the verification code.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: verify_code.php');
        exit;
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        $_SESSION['flash'] = 'Invalid code format. Must be 6 digits.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: verify_code.php');
        exit;
    }

    // ✅ Check token correctness and expiration
    try {
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['verified_email'] = $user['email'];
            unset($_SESSION['pending_reset_user']);
            unset($_SESSION['flash']);
            unset($_SESSION['flash_type']);
            
            error_log("VERIFICATION SUCCESS: User " . $user['id'] . " verified");
            header("Location: reset_password.php");
            exit;
        } else {
            $_SESSION['flash'] = 'Invalid or expired code. Please try again.';
            $_SESSION['flash_type'] = 'danger';
            error_log("VERIFICATION FAILED: Invalid code $code");
        }
    } catch (PDOException $e) {
        error_log("VERIFICATION DB ERROR: " . $e->getMessage());
        $_SESSION['flash'] = 'Database error. Please try again.';
        $_SESSION['flash_type'] = 'danger';
    }
    
    header('Location: verify_code.php');
    exit;
}

// Check if user should be on this page
if (!isset($_SESSION['pending_reset_user'])) {
    error_log("REDIRECT: No pending_reset_user in session");
    header("Location: forgot_password.php");
    exit;
}

// Debug current user
if (isset($_SESSION['pending_reset_user'])) {
    // Get user email for logging
    $debugStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $debugStmt->execute([$_SESSION['pending_reset_user']]);
    $userEmail = $debugStmt->fetchColumn();
    error_log("Current pending user ID: " . $_SESSION['pending_reset_user'] . ", Email: " . $userEmail);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Verification Code</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            position: relative;
            overflow: hidden;
        }

        /* Animated circles */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 15s infinite ease-in-out;
        }

        body::before {
            width: 300px;
            height: 300px;
            top: -150px;
            left: -150px;
        }

        body::after {
            width: 250px;
            height: 250px;
            bottom: -125px;
            right: -125px;
            animation-delay: -7s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .verify-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 35px 30px;
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .verify-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon-container {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .icon-container i {
            color: #fff;
            font-size: 28px;
        }

        .verify-header h4 {
            color: #1a202c;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .verify-header p {
            color: #718096;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .code-input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 1;
        }

        .input-icon i {
            font-size: 18px;
        }

        .form-control {
            height: 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px 12px 45px;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: #f7fafc;
            color: #2d3748;
            width: 100%;
            text-align: center;
            letter-spacing: 8px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: #fff;
            outline: none;
            transform: translateY(-1px);
        }

        .form-control::placeholder {
            color: #a0aec0;
            font-weight: 400;
            letter-spacing: 2px;
            font-size: 14px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-custom {
            flex: 1;
            height: 50px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-cancel {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-cancel:hover {
            background: #edf2f7;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 25px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            animation: slideIn 0.4s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.25);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
            color: #fff;
            box-shadow: 0 6px 16px rgba(245, 101, 101, 0.25);
        }

        .alert-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: #fff;
            box-shadow: 0 6px 16px rgba(72, 187, 120, 0.25);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-15px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .helper-text {
            text-align: center;
            color: #718096;
            font-size: 12px;
            margin-top: 15px;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #667eea;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            padding: 10px 16px;
            border-radius: 8px;
            margin: 0 auto;
        }

        .resend-btn:hover {
            color: #764ba2;
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .resend-btn:disabled {
            color: #a0aec0;
            cursor: not-allowed;
            transform: none;
            background: none;
        }

        .resend-btn i {
            font-size: 14px;
        }

        /* Countdown Timer */
        .countdown-timer {
            text-align: center;
            color: #718096;
            font-size: 12px;
            margin-top: 8px;
            font-weight: 500;
        }

        /* Responsive styles */
        @media (max-width: 480px) {
            .verify-container {
                padding: 25px 20px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .resend-btn {
                font-size: 13px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <div class="icon-container">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h4>Verification Code</h4>
            <p>Enter the 6-digit code sent to your email</p>
        </div>

        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?>">
                <?= htmlentities($_SESSION['flash']); ?>
            </div>
            <?php unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <form method="POST" id="verifyForm">
            <div class="form-group">
                <div class="code-input-wrapper">
                    <div class="input-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <input type="text" name="code" class="form-control" placeholder="000000" required maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="off" autofocus>
                </div>
            </div>

            <div class="helper-text">
                Code expires in 10 minutes
            </div>
            
            <div class="btn-group">
                <a href="forgot_password.php" class="btn-custom btn-cancel">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
                <button type="submit" class="btn-custom btn-submit">
                    <i class="fas fa-check me-2"></i>Verify Code
                </button>
            </div>
        </form>

        <div class="back-link">
            <form method="POST" id="resendForm">
                <input type="hidden" name="resend" value="1">
                <button type="submit" class="resend-btn" id="resendBtn">
                    <i class="fas fa-redo"></i>
                    <span id="resendText">Resend Code</span>
                </button>
            </form>
            <div class="countdown-timer" id="countdownTimer" style="display: none;">
                Resend available in: <span id="countdown">60</span>s
            </div>
        </div>
    </div>

    <script>
        // Countdown timer for resend button
        document.addEventListener('DOMContentLoaded', function() {
            const resendBtn = document.getElementById('resendBtn');
            const resendText = document.getElementById('resendText');
            const countdownTimer = document.getElementById('countdownTimer');
            const countdownDisplay = document.getElementById('countdown');
            const resendForm = document.getElementById('resendForm');
            
            let countdown = localStorage.getItem('resend_countdown') ? parseInt(localStorage.getItem('resend_countdown')) : 0;
            
            let timerInterval;
            
            function startCountdown() {
                countdown = 60; // Reset to 60 seconds
                
                resendBtn.disabled = true;
                countdownTimer.style.display = 'block';
                updateDisplay();
                
                timerInterval = setInterval(function() {
                    countdown--;
                    localStorage.setItem('resend_countdown', countdown);
                    updateDisplay();
                    
                    if (countdown <= 0) {
                        clearInterval(timerInterval);
                        finishCountdown();
                        localStorage.removeItem('resend_countdown');
                    }
                }, 1000);
            }
            
            function updateDisplay() {
                if (countdownDisplay) countdownDisplay.textContent = countdown;
            }
            
            function finishCountdown() {
                countdownTimer.style.display = 'none';
                resendBtn.disabled = false;
                resendText.textContent = 'Resend Code';
                resendBtn.innerHTML = '<i class="fas fa-redo"></i><span id="resendText">Resend Code</span>';
            }
            
            // Handle form submission
            if (resendForm) {
                resendForm.addEventListener('submit', function(e) {
                    if (!resendBtn.disabled) {
                        // Show loading state
                        resendBtn.disabled = true;
                        resendText.textContent = 'Sending...';
                        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span id="resendText">Sending...</span>';
                        
                        // Start countdown after a short delay to show loading state
                        setTimeout(() => {
                            startCountdown();
                        }, 1000);
                    }
                });
            }
            
            // Check if countdown is already running
            if (countdown > 0) {
                resendBtn.disabled = true;
                countdownTimer.style.display = 'block';
                updateDisplay();
                
                timerInterval = setInterval(function() {
                    countdown--;
                    localStorage.setItem('resend_countdown', countdown);
                    updateDisplay();
                    
                    if (countdown <= 0) {
                        clearInterval(timerInterval);
                        finishCountdown();
                        localStorage.removeItem('resend_countdown');
                    }
                }, 1000);
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }, 5000);
            });
        });

        // Auto-advance input fields (for better UX)
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.querySelector('input[name="code"]');
            if (codeInput) {
                codeInput.focus();
                
                codeInput.addEventListener('input', function(e) {
                    if (this.value.length === 6) {
                        document.getElementById('verifyForm').submit();
                    }
                });
            }
        });
    </script>
</body>
</html>