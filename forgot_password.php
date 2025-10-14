<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
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

        .forgot-container {
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

        .forgot-header {
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

        .icon-container svg {
            width: 32px;
            height: 32px;
            color: #fff;
        }

        .forgot-header h4 {
            color: #1a202c;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .forgot-header p {
            color: #718096;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        .form-group {
            margin-bottom: 25px;
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

        .input-icon svg {
            width: 20px;
            height: 20px;
        }

        .form-control {
            height: 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px 12px 45px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #f7fafc;
            color: #2d3748;
            width: 100%;
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

        .btn-submit:active, .btn-cancel:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 25px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            animation: slideIn 0.4s ease-out;
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.25);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert::before {
            content: 'ℹ';
            font-size: 18px;
            font-weight: bold;
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

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link a:hover {
            color: #764ba2;
            transform: translateX(-2px);
        }

        .back-link a svg {
            width: 14px;
            height: 14px;
        }

        /* Mobile Small (< 400px) */
        @media (max-width: 399px) {
            .forgot-container {
                padding: 30px 25px;
                border-radius: 18px;
                max-width: 100%;
            }

            .forgot-header h4 {
                font-size: 22px;
            }

            .forgot-header p {
                font-size: 12px;
            }

            .icon-container {
                width: 60px;
                height: 60px;
            }

            .icon-container svg {
                width: 28px;
                height: 28px;
            }

            .form-control {
                height: 48px;
                font-size: 13px;
                padding: 12px 14px 12px 42px;
            }

            .btn-custom {
                height: 48px;
                font-size: 14px;
            }

            .btn-group {
                gap: 10px;
            }

            .alert {
                font-size: 12px;
                padding: 12px 16px;
            }

            .back-link a {
                font-size: 12px;
            }
        }

        /* Mobile (400px - 576px) */
        @media (min-width: 400px) and (max-width: 576px) {
            .forgot-container {
                padding: 32px 28px;
                max-width: 380px;
            }

            .forgot-header h4 {
                font-size: 23px;
            }

            .icon-container {
                width: 62px;
                height: 62px;
            }

            .icon-container svg {
                width: 30px;
                height: 30px;
            }

            .form-control {
                height: 49px;
            }

            .btn-custom {
                height: 49px;
            }
        }

        /* Tablet Portrait (577px - 768px) */
        @media (min-width: 577px) and (max-width: 768px) {
            .forgot-container {
                max-width: 420px;
                padding: 38px 32px;
            }

            .forgot-header h4 {
                font-size: 25px;
            }

            .icon-container {
                width: 68px;
                height: 68px;
            }

            .icon-container svg {
                width: 33px;
                height: 33px;
            }
        }

        /* Tablet Landscape & Desktop (769px - 1199px) */
        @media (min-width: 769px) and (max-width: 1199px) {
            .forgot-container {
                max-width: 440px;
                padding: 40px 35px;
            }

            .forgot-header h4 {
                font-size: 26px;
            }

            .icon-container {
                width: 70px;
                height: 70px;
            }

            .icon-container svg {
                width: 34px;
                height: 34px;
            }

            .form-control {
                height: 52px;
            }

            .btn-custom {
                height: 52px;
            }
        }

        /* Large Desktop (1200px+) */
        @media (min-width: 1200px) {
            .forgot-container {
                max-width: 450px;
                padding: 42px 38px;
            }

            .forgot-header h4 {
                font-size: 27px;
            }

            .icon-container {
                width: 72px;
                height: 72px;
            }

            .icon-container svg {
                width: 35px;
                height: 35px;
            }

            .form-control {
                height: 53px;
                font-size: 15px;
            }

            .btn-custom {
                height: 53px;
                font-size: 16px;
            }
        }

        /* Mobile Landscape (height < 500px) */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                padding: 10px;
            }

            .forgot-container {
                padding: 20px 25px;
                max-width: 500px;
            }

            .icon-container {
                width: 50px;
                height: 50px;
                margin-bottom: 12px;
            }

            .icon-container svg {
                width: 24px;
                height: 24px;
            }

            .forgot-header {
                margin-bottom: 20px;
            }

            .forgot-header h4 {
                font-size: 20px;
                margin-bottom: 6px;
            }

            .forgot-header p {
                font-size: 12px;
            }

            .form-group {
                margin-bottom: 18px;
            }

            .form-control {
                height: 44px;
            }

            .btn-group {
                margin-top: 18px;
            }

            .btn-custom {
                height: 44px;
                font-size: 14px;
            }

            .back-link {
                margin-top: 15px;
            }
        }

        /* Very Small Screens (< 320px) */
        @media (max-width: 319px) {
            .forgot-container {
                padding: 25px 20px;
                border-radius: 15px;
            }

            .forgot-header h4 {
                font-size: 20px;
            }

            .forgot-header p {
                font-size: 11px;
            }

            .icon-container {
                width: 55px;
                height: 55px;
            }

            .icon-container svg {
                width: 26px;
                height: 26px;
            }

            .form-control {
                height: 46px;
                font-size: 12px;
            }

            .btn-custom {
                height: 46px;
                font-size: 13px;
            }

            .btn-group {
                gap: 8px;
            }
        }

        /* Extra Large Screens (1400px+) */
        @media (min-width: 1400px) {
            .forgot-container {
                max-width: 460px;
                padding: 45px 40px;
            }

            .forgot-header h4 {
                font-size: 28px;
            }

            .icon-container {
                width: 75px;
                height: 75px;
            }

            .icon-container svg {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="icon-container">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h4>Forgot Password?</h4>
            <p>Don't worry! Enter your email or phone number and we'll send you a code to reset your password</p>
        </div>

        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert"><?= htmlentities($_SESSION['flash']); ?></div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <form action="send_code.php" method="POST">
            <div class="form-group">
                <div class="input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <input type="text" name="identifier" class="form-control" placeholder="Email or Phone Number" required>
            </div>
            
            <div class="btn-group">
                <a href="student_login.php" class="btn-custom btn-cancel">Cancel</a>
                <button type="submit" class="btn-custom btn-submit">Send Code</button>
            </div>
        </form>

        <div class="back-link">
            <a href="student_login.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Login
            </a>
        </div>
    </div>
</body>
</html>