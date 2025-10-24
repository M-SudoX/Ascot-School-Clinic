<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once '../includes/db_connect.php';

// Manual include ng PHPMailer classes
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to automatically expire announcements
function expireAnnouncements($pdo) {
    try {
        $currentDateTime = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET is_active = 0, 
                status = 'inactive',
                updated_at = NOW()
            WHERE expiry_date IS NOT NULL 
            AND expiry_date <= ? 
            AND is_active = 1
            AND status = 'active'
        ");
        $stmt->execute([$currentDateTime]);
        
        $expiredCount = $stmt->rowCount();
        if ($expiredCount > 0) {
            error_log("Automatically expired $expiredCount announcements at $currentDateTime");
        }
        
        return $expiredCount;
    } catch (PDOException $e) {
        error_log("Error expiring announcements: " . $e->getMessage());
        return 0;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $sentBy = $_POST['sentBy'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';
    $announcementType = $_POST['announcementType'] ?? [];
    $emailRecipientType = $_POST['emailRecipientType'] ?? 'all';
    $emailSpecificStudents = $_POST['emailSpecificStudents'] ?? [];
    $expiryDate = $_POST['expiryDate'] ?? '';
    $expiryTime = $_POST['expiryTime'] ?? '';
    
    // Validate required fields
    if (empty($sentBy) || empty($subject) || empty($content)) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if we are posting on front page
            $postOnFront = in_array('front', $announcementType) ? 1 : 0;
            $sendEmail = in_array('email', $announcementType) ? 1 : 0;

            // Handle file upload
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/announcements/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = time() . '_' . basename($_FILES['attachment']['name']);
                $filePath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $filePath)) {
                    $attachment = $fileName;
                }
            }

            // Calculate expiry datetime
            $expiryDatetime = null;
            $isActive = 1; // Default to active
            if (!empty($expiryDate) && !empty($expiryTime)) {
                $expiryDatetime = $expiryDate . ' ' . $expiryTime . ':00';
                
                // Check if expiry is in the future
                $currentDateTime = date('Y-m-d H:i:s');
                if ($expiryDatetime <= $currentDateTime) {
                    $isActive = 0; // Set to inactive if expiry is in past
                }
            }

            // Insert into announcements table
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, sent_by, attachment, is_active, post_on_front, send_email, expiry_date, created_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $status = $isActive ? 'active' : 'inactive';
            $stmt->execute([$subject, $content, $sentBy, $attachment, $isActive, $postOnFront, $sendEmail, $expiryDatetime, $status]);
            $announcementId = $pdo->lastInsertId();

            // Handle email recipients if email is selected
            if ($sendEmail) {
                // Get recipient students based on selection
                $recipientStudents = [];
                
                if ($emailRecipientType === 'all') {
                    // Get all verified users with valid email - FIXED: Use DISTINCT to avoid duplicates
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT u.id, u.fullname, u.email 
                        FROM users u
                        WHERE u.email IS NOT NULL 
                        AND u.email != '' 
                        AND TRIM(u.email) != ''
                        AND u.email LIKE '%@%'
                        AND u.is_verified = 1
                    ");
                    $stmt->execute();
                    $recipientStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else if ($emailRecipientType === 'specific' && !empty($emailSpecificStudents)) {
                    // Get specific selected students - FIXED: Use DISTINCT to avoid duplicates
                    $placeholders = str_repeat('?,', count($emailSpecificStudents) - 1) . '?';
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT u.id, u.fullname, u.email 
                        FROM users u
                        WHERE u.id IN ($placeholders)
                        AND u.email IS NOT NULL 
                        AND u.email != '' 
                        AND TRIM(u.email) != ''
                        AND u.email LIKE '%@%'
                        AND u.is_verified = 1
                    ");
                    $stmt->execute($emailSpecificStudents);
                    $recipientStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                // Insert into announcement_recipients and announcement_emails
                foreach ($recipientStudents as $student) {
                    // Insert into announcement_recipients
                    $stmt = $pdo->prepare("
                        INSERT INTO announcement_recipients (announcement_id, student_id, recipient_type, created_at)
                        VALUES (?, ?, 'email', NOW())
                    ");
                    $stmt->execute([$announcementId, $student['id']]);
                    
                    // Insert into announcement_emails
                    $stmt = $pdo->prepare("
                        INSERT INTO announcement_emails (announcement_id, recipient_email, recipient_name, status, sent_at, error_message)
                        VALUES (?, ?, ?, 'pending', NOW(), '')
                    ");
                    $stmt->execute([$announcementId, $student['email'], $student['fullname']]);
                }
                
                // USE PHPMailer FOR EMAIL SENDING
                $emailSentCount = 0;
                $emailFailedCount = 0;
                $errors = [];
                
                // Get current date and time for email
                $currentDateTime = date('F j, Y g:i A');
                
                foreach ($recipientStudents as $student) {
                    $to = $student['email'];
                    $name = $student['fullname'];
                    
                    try {
                        // Create PHPMailer instance
                        $mail = new PHPMailer(true);
                        
                        // Server settings for Gmail
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'cachemeifucan05@gmail.com';
                        $mail->Password = 'zusittxqokhgzotm';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                        
                        // Recipients
                        $mail->setFrom('cachemeifucan05@gmail.com', 'ASCOT Clinic');
                        $mail->addAddress($to, $name);
                        
                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        
                        // Add expiry notice to email content if applicable
                        $emailContent = $content;
                        if (!empty($expiryDatetime)) {
                            $expiryFormatted = date('F j, Y g:i A', strtotime($expiryDatetime));
                            $emailContent .= "\n\nThis announcement will expire on: $expiryFormatted";
                        }
                        
                        // Email template with current date and time
                        $emailMessage = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <title>$subject</title>
                            <style>
                                body { 
                                    font-family: Arial, sans-serif; 
                                    line-height: 1.6; 
                                    color: #333; 
                                    margin: 0; 
                                    padding: 0; 
                                    background: #f5f6fa; 
                                }
                                .container { 
                                    max-width: 600px; 
                                    margin: 0 auto; 
                                    background: white; 
                                    border-radius: 10px;
                                    overflow: hidden;
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                                }
                                .header { 
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                    color: white; 
                                    padding: 30px 20px; 
                                    text-align: center; 
                                }
                                .header h1 { 
                                    margin: 0 0 10px 0;
                                    font-size: 24px;
                                }
                                .header p {
                                    margin: 0;
                                    opacity: 0.9;
                                }
                                .content { 
                                    padding: 30px; 
                                }
                                .announcement { 
                                    background: #f8f9fa; 
                                    padding: 20px; 
                                    border-radius: 8px; 
                                    border-left: 4px solid #667eea; 
                                    margin-bottom: 20px;
                                }
                                .announcement h2 { 
                                    color: #1a3a5f; 
                                    margin-top: 0; 
                                    border-bottom: 2px solid #e9ecef;
                                    padding-bottom: 10px;
                                }
                                .sender-info { 
                                    background: #e9ecef; 
                                    padding: 15px; 
                                    border-radius: 8px; 
                                    font-size: 14px; 
                                    margin-top: 20px;
                                }
                                .expiry-notice {
                                    background: #fff3cd;
                                    border: 1px solid #ffeaa7;
                                    color: #856404;
                                    padding: 12px 15px;
                                    border-radius: 8px;
                                    margin-top: 15px;
                                    font-size: 14px;
                                }
                                .footer { 
                                    text-align: center; 
                                    padding: 20px; 
                                    color: #666; 
                                    font-size: 12px; 
                                    background: white; 
                                    border-top: 1px solid #e9ecef;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                                    <p>Online School Clinic</p>
                                </div>
                                <div class='content'>
                                    <div class='announcement'>
                                        <h2>$subject</h2>
                                        <div>" . nl2br(htmlspecialchars($emailContent)) . "</div>
                                    </div>
                                    " . (!empty($expiryDatetime) ? "
                                    <div class='expiry-notice'>
                                        <strong>⚠️ Expiry Notice:</strong> This announcement will expire on " . date('F j, Y g:i A', strtotime($expiryDatetime)) . "
                                    </div>
                                    " : "") . "
                                    <div class='sender-info'>
                                        <p><strong>Sent by:</strong> $sentBy</p>
                                        <p><strong>Date:</strong> $currentDateTime</p>
                                    </div>
                                </div>
                                <div class='footer'>
                                    <p>This is an automated message from ASCOT Online Clinic System.</p>
                                    <p>Please do not reply to this email.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $mail->Body = $emailMessage;
                        $mail->AltBody = "ASCOT Clinic Announcement\n\nSubject: $subject\n\n$emailContent\n\nSent by: $sentBy\nDate: $currentDateTime";
                        
                        // Send email
                        if ($mail->send()) {
                            $emailSentCount++;
                            
                            // Update email status to sent
                            $stmt = $pdo->prepare("
                                UPDATE announcement_emails 
                                SET status = 'sent', 
                                    sent_at = NOW(),
                                    error_message = ''
                                WHERE announcement_id = ? AND recipient_email = ?
                            ");
                            $stmt->execute([$announcementId, $to]);
                        } else {
                            throw new Exception('Failed to send email');
                        }
                        
                    } catch (Exception $e) {
                        $emailFailedCount++;
                        $errorMsg = $e->getMessage();
                        $errors[] = "Failed to send to $to: $errorMsg";
                        
                        // Update email status to failed
                        $stmt = $pdo->prepare("
                            UPDATE announcement_emails 
                            SET status = 'failed', 
                                error_message = ?
                            WHERE announcement_id = ? AND recipient_email = ?
                        ");
                        $stmt->execute([$errorMsg, $announcementId, $to]);
                    }
                    
                    // Small delay to avoid overwhelming the SMTP server
                    usleep(300000); // 0.3 second delay
                }
                
                // Update announcement with email counts
                $stmt = $pdo->prepare("
                    UPDATE announcements 
                    SET email_sent_count = ?, 
                        email_failed_count = ?
                    WHERE id = ?
                ");
                $stmt->execute([$emailSentCount, $emailFailedCount, $announcementId]);
                
                // Log any errors
                if (!empty($errors)) {
                    error_log("Email sending errors for announcement $announcementId: " . implode(" | ", $errors));
                }
            }

            // Commit transaction
            $pdo->commit();

            $_SESSION['success_message'] = "Announcement created successfully!" . 
                ($sendEmail ? " Emails sent: $emailSentCount, Failed: $emailFailedCount" : "") .
                (!empty($expiryDatetime) ? " This announcement will automatically expire on " . date('F j, Y g:i A', strtotime($expiryDatetime)) . "." : "");
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_message = "Error saving announcement: " . $e->getMessage();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_message = "Error sending emails: " . $e->getMessage();
        }
    }
}

// Auto-expire announcements on page load
$expiredCount = expireAnnouncements($pdo);

// Fetch active students from database for the form - FIXED: Get unique students by grouping or using DISTINCT
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.fullname, u.student_number, u.email, 
               (SELECT si.course_year FROM student_information si WHERE si.student_number = u.student_number ORDER BY si.id DESC LIMIT 1) as course_year 
        FROM users u 
        WHERE u.is_verified = 1 
        ORDER BY u.fullname
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Announcement - ASCOT Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 100px;
        }

        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1002;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .school-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0.2rem 0;
        }

        .clinic-title {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 110px;
            left: 20px;
            z-index: 1001;
            background: #667eea;
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.1);
            background: #764ba2;
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 100px;
            left: 0;
            height: calc(100vh - 100px);
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #667eea;
            border-left: 4px solid #667eea;
        }

        .nav-item i {
            width: 25px;
            margin-right: 1rem;
        }

        .nav-item span {
            flex: 1;
        }

        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .nav-item .arrow.rotate {
            transform: rotate(180deg);
        }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #f8f9fa;
        }

        .submenu.show {
            max-height: 500px;
        }

        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem 0.75rem 3.5rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .submenu-item:hover {
            background: #e9ecef;
            color: #667eea;
        }

        .submenu-item.active {
            color: #667eea;
            font-weight: 500;
        }

        .submenu-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        .nav-item.logout {
            color: #dc3545;
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            margin-left: 280px;
            width: calc(100% - 280px);
        }

        .notification-dropdown {
            position: relative;
        }

        .notification-btn {
            background: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #444;
            padding: 0.5rem 1rem;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            color: #667eea;
            transform: scale(1.1);
        }

        .notification-btn .badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            border-radius: 50%;
            padding: 3px 6px;
            min-width: 20px;
        }

        .notification-menu {
            display: none;
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 10px;
            width: 300px;
            z-index: 999;
        }

        .notification-menu.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-menu .notif-title {
            font-weight: bold;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .notification-menu .notif-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 250px;
            overflow-y: auto;
        }

        .notification-menu .notif-list li {
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            font-size: 0.9rem;
            color: #333;
            transition: background 0.3s ease;
        }

        .notification-menu .notif-list li:hover {
            background: #f8f9fa;
        }

        .notification-menu .notif-list li i {
            color: #667eea;
            margin-right: 0.5rem;
        }

        .notification-menu .view-all {
            display: block;
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
            color: #667eea;
            text-decoration: none;
            border-top: 1px solid #e9ecef;
            font-weight: 500;
        }

        .notification-menu .view-all:hover {
            background: #f8f9fa;
        }

        .content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-top: 1rem;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .content-header h2 {
            color: #1a3a5f;
            font-size: 24px;
        }

        .announcement-form {
            max-width: 800px;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .form-section-title {
            font-size: 1.1rem;
            color: #1a3a5f;
            margin-bottom: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section-title i {
            color: #667eea;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .radio-option label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .checkbox-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .checkbox-option label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
        }

        .file-upload-input::file-selector-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 1rem;
            transition: background 0.3s ease;
        }

        .file-upload-input::file-selector-button:hover {
            background: #5a6fd8;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .email-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0;
            margin-top: 10px;
            display: none;
            overflow: hidden;
        }

        .email-preview.active {
            display: block;
        }

        .preview-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            text-align: center;
            margin: 0;
        }

        .preview-content {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .recipient-count {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            border-left: 4px solid #667eea;
        }

        .email-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .student-selection {
            display: none;
            margin-top: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .student-selection.active {
            display: block;
        }
        
        .student-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background: white;
            margin-top: 10px;
        }
        
        .student-checkbox {
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }
        
        .student-checkbox:hover {
            background-color: #f8f9fa;
        }
        
        .student-checkbox:last-child {
            border-bottom: none;
        }
        
        .student-checkbox label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        
        .select-all {
            background: #e9ecef;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selected-count {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .student-name {
            font-weight: 600;
            color: #1a3a5f;
        }
        
        .student-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .expiry-time-container {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .expiry-date-group, .expiry-time-group {
            flex: 1;
        }

        .time-presets {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .time-preset-btn {
            padding: 4px 8px;
            font-size: 0.8rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .time-preset-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .expiry-preview {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #667eea;
            font-size: 0.9rem;
        }

        .expiry-preview .label {
            font-weight: 600;
            color: #1a3a5f;
        }

        .expiry-preview .value {
            color: #495057;
        }

        .expiry-preview .time-remaining {
            color: #e67e22;
            font-weight: 500;
            margin-top: 5px;
        }

        .expiry-preview .expired-notice {
            color: #dc3545;
            font-weight: 500;
            margin-top: 5px;
        }

        .student-search {
            margin-bottom: 15px;
        }

        .email-recipient-selection {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }

        .email-recipient-selection.active {
            display: block;
        }

        @media (max-width: 992px) {
            .school-name {
                font-size: 1rem;
            }

            .logo-img {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 80px;
            }

            .top-header {
                padding: 0.5rem 0;
            }

            .mobile-menu-toggle {
                display: block;
                top: 85px;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 80px;
                height: calc(100vh - 80px);
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 80px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 1rem;
                width: 100%;
                margin-left: 0;
            }

            .notification-menu {
                width: 280px;
                right: -40px;
            }

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.85rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .content-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .student-list {
                max-height: 200px;
            }

            .expiry-time-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .expiry-date-group, .expiry-time-group {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .notification-menu {
                width: 250px;
                right: -20px;
            }

            .content {
                padding: 1rem;
            }

            .radio-group, .checkbox-group {
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Fixed Header -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                 <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Fixed Sidebar -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="studentMenu">
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="search_students.php" class="submenu-item">
                            <i class="fas fa-search"></i>
                            Search Students
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="appointmentsMenu">
                        <a href="calendar_view.php" class="submenu-item">
                            <i class="fas fa-calendar-alt"></i>
                            Calendar View
                        </a>
                        <a href="#" class="submenu-item">
                            <i class="fas fa-check-circle"></i>
                            Approvals
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="reportsMenu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="reportsMenu">
                        <a href="#" class="submenu-item">
                            <i class="fas fa-file-invoice"></i>
                            Monthly Summary
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="adminMenu">
                        <a href="users_logs.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            Users Logs
                        </a>
                        <a href="#" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Back up & Restore
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="announcementMenu">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcement</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="announcementMenu">
                        <a href="new_announcement.php" class="submenu-item active">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="announcement_history.php" class="submenu-item">
                            <i class="fas fa-history"></i>
                            History
                        </a>
                    </div>
                </div>
                
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div style="display:flex; justify-content:flex-end; margin-bottom: 1.5rem;">
                <div class="notification-dropdown">
                    <button class="notification-btn" id="notifBtn">
                        <i class="fas fa-bell"></i>
                        <span class="badge" id="notifCount">3</span>
                    </button>
                    <div class="notification-menu" id="notifMenu">
                        <p class="notif-title">Notifications</p>
                        <ul class="notif-list">
                            <li><i class="fas fa-user-plus"></i> New student registered</li>
                            <li><i class="fas fa-calendar-check"></i> Appointment pending approval</li>
                            <li><i class="fas fa-stethoscope"></i> Consultation completed</li>
                        </ul>
                        <a href="#" class="view-all">View all</a>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="content-header">
                    <h2>Create Announcement</h2>
                    <button type="button" class="btn btn-outline-primary" id="previewEmailBtn">
                        <i class="fas fa-eye"></i> Preview Email
                    </button>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom: 2rem;">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom: 2rem;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($expiredCount > 0): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert" style="margin-bottom: 2rem;">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo $expiredCount; ?> announcement(s) have been automatically expired.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form class="announcement-form" id="announcementForm" method="POST" action="" enctype="multipart/form-data">
                    <!-- Sent By Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-user"></i>
                            Sent By
                        </h3>
                        <div class="form-group">
                            <select class="form-select" id="sentBy" name="sentBy" required>
                                <option value="">Select sender...</option>
                                <option value="Admin User">Admin User</option>
                                <option value="Dr. James Smith">Dr. James Smith</option>
                                <option value="Nurse Maria Santos">Nurse Maria Santos</option>
                                <option value="Clinic Administrator">Clinic Administrator</option>
                            </select>
                        </div>
                    </div>

                    <!-- Type of Announcement Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-bullhorn"></i>
                            Type of Announcement
                        </h3>
                        <div class="checkbox-group">
                            <div class="checkbox-option">
                                <input type="checkbox" id="sendEmail" name="announcementType[]" value="email">
                                <label for="sendEmail">Send an Email</label>
                            </div>
                            <div class="checkbox-option">
                                <input type="checkbox" id="postFront" name="announcementType[]" value="front" checked>
                                <label for="postFront">Post on Front Page</label>
                            </div>
                        </div>
                        
                        <!-- Email Recipient Selection (Only shows when Send Email is checked) -->
                        <div class="email-recipient-selection" id="emailRecipientSelection">
                            <h4 style="color: #1a3a5f; margin-bottom: 15px; font-size: 1rem;">
                                <i class="fas fa-users me-2"></i>Email Recipients
                            </h4>
                            
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" id="emailAllStudents" name="emailRecipientType" value="all" checked>
                                    <label for="emailAllStudents">All Students</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" id="emailSpecificStudents" name="emailRecipientType" value="specific">
                                    <label for="emailSpecificStudents">Specific Students</label>
                                </div>
                            </div>
                            
                            <!-- Specific Students Selection for Email -->
                            <div class="student-selection" id="emailSpecificStudentsSelection">
                                <div class="student-search">
                                    <input type="text" class="form-control" id="emailStudentSearch" placeholder="Search students by name, ID, or course...">
                                </div>
                                
                                <div class="select-all">
                                    <input type="checkbox" id="selectEmailAllStudents">
                                    <label for="selectEmailAllStudents">Select All Students</label>
                                    <span class="selected-count" id="emailSelectedCount">0 selected</span>
                                </div>
                                
                                <div class="student-list">
                                    <?php if (!empty($students)): ?>
                                        <?php foreach ($students as $student): ?>
                                            <div class="student-checkbox">
                                                <input type="checkbox" name="emailSpecificStudents[]" value="<?php echo $student['id']; ?>" 
                                                       id="email_student_<?php echo $student['id']; ?>" class="email-student-checkbox-input">
                                                <label for="email_student_<?php echo $student['id']; ?>">
                                                    <div class="student-info">
                                                        <span class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></span>
                                                        <span class="student-details">
                                                            <?php echo htmlspecialchars($student['student_number']); ?> - 
                                                            <?php echo htmlspecialchars($student['course_year'] ?? 'Not specified'); ?>
                                                            <?php if (!empty($student['email'])): ?>
                                                                <br><small class="text-success">✓ Has email: <?php echo htmlspecialchars($student['email']); ?></small>
                                                            <?php else: ?>
                                                                <br><small class="text-danger">✗ No email address</small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            No students found in the database. 
                                            <br><small>Please check your database connection or add students first.</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="recipient-count" id="emailRecipientCount" style="margin-top: 15px;">
                                <i class="fas fa-users me-2"></i>Email recipients: 0 students
                            </div>
                        </div>
                        
                        <div class="email-warning" id="emailWarning" style="display: none; margin-top: 15px;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Sending emails may take several minutes depending on the number of recipients.
                        </div>
                    </div>

                    <!-- Subject Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-heading"></i>
                            Subject
                        </h3>
                        <div class="form-group">
                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Enter announcement subject..." required>
                        </div>
                    </div>

                    <!-- Content Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-file-alt"></i>
                            Content
                        </h3>
                        <div class="form-group">
                            <textarea class="form-control" id="content" name="content" placeholder="Enter announcement content..." required rows="6"></textarea>
                        </div>
                    </div>

                    <!-- Expiry Date & Time Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-clock"></i>
                            Expiry Settings
                        </h3>
                        <div class="form-group">
                            <label class="form-label">Announcement Expiry Date & Time (Optional)</label>
                            
                            <div class="expiry-time-container">
                                <div class="expiry-date-group">
                                    <label class="form-label small">Date</label>
                                    <input type="date" class="form-control" id="expiryDate" name="expiryDate" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="expiry-time-group">
                                    <label class="form-label small">Time</label>
                                    <input type="time" class="form-control" id="expiryTime" name="expiryTime" value="23:59">
                                    
                                    <div class="time-presets">
                                        <button type="button" class="time-preset-btn" data-time="08:00">8:00 AM</button>
                                        <button type="button" class="time-preset-btn" data-time="12:00">12:00 PM</button>
                                        <button type="button" class="time-preset-btn" data-time="17:00">5:00 PM</button>
                                        <button type="button" class="time-preset-btn" data-time="23:59">End of Day</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="expiry-preview" id="expiryPreview" style="display: none;">
                                <div>
                                    <span class="label">Expires on:</span>
                                    <span class="value" id="expiryPreviewText"></span>
                                </div>
                                <div class="time-remaining" id="timeRemaining"></div>
                                <div class="expired-notice" id="expiredNotice" style="display: none;">
                                    ⚠️ This expiry time has already passed
                                </div>
                            </div>
                            
                            <small class="form-text text-muted">
                                Leave empty if announcement should not expire. Minimum date is today.
                                Announcements will automatically expire after the specified date and time.
                            </small>
                        </div>
                    </div>

                    <!-- File Upload Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-paperclip"></i>
                            Upload File or Image/Video
                        </h3>
                        <div class="form-group">
                            <input type="file" class="file-upload-input" id="attachment" name="attachment" 
                                   accept="image/*,video/*,.pdf,.doc,.docx">
                            <small class="form-text text-muted">Accepted formats: Images (JPG, PNG, GIF, WEBP), Videos (MP4, AVI, MOV, WMV, WEBM), Documents (PDF, DOC, DOCX). Max size: 10MB</small>
                        </div>
                    </div>

                    <!-- Email Preview -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-envelope"></i>
                            Email Preview
                        </h3>
                        <div class="email-preview" id="emailPreview">
                            <div class="preview-header">
                                <h4 class="mb-1">Email Preview</h4>
                                <small class="opacity-75">This is how your email will look to recipients</small>
                            </div>
                            <div class="preview-content" id="previewContent">
                                <p class="text-muted">Fill out the subject and content to see preview...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="cancelBtn">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i> Send Announcement
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dropdown functionality
        document.querySelectorAll('.dropdown-btn').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const submenu = document.getElementById(targetId);
                const arrow = this.querySelector('.arrow');

                document.querySelectorAll('.submenu').forEach(menu => {
                    if (menu.id !== targetId && menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                        if (otherBtn) {
                            otherBtn.querySelector('.arrow').classList.remove('rotate');
                        }
                    }
                });

                submenu.classList.toggle('show');
                arrow.classList.toggle('rotate');
            });
        });

        // Mobile menu functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
        });

        // Close sidebar when clicking submenu items on mobile
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.submenu-item').forEach(item => {
                item.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                });
            });
        }

        // Notification dropdown
        const notifBtn = document.getElementById('notifBtn');
        const notifMenu = document.getElementById('notifMenu');

        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifMenu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!notifMenu.contains(e.target) && !notifBtn.contains(e.target)) {
                notifMenu.classList.remove('show');
            }
        });

        // Email recipient selection functionality
        function toggleEmailRecipientSelection() {
            const sendEmailCheckbox = document.getElementById('sendEmail');
            const emailRecipientSelection = document.getElementById('emailRecipientSelection');
            const emailWarning = document.getElementById('emailWarning');
            
            if (sendEmailCheckbox && sendEmailCheckbox.checked) {
                emailRecipientSelection.classList.add('active');
                emailWarning.style.display = 'block';
                updateEmailRecipientCount();
                toggleEmailSpecificStudentSelection();
            } else {
                emailRecipientSelection.classList.remove('active');
                emailWarning.style.display = 'none';
            }
        }

        function toggleEmailSpecificStudentSelection() {
            const emailRecipientType = document.querySelector('input[name="emailRecipientType"]:checked');
            const emailSpecificSelection = document.getElementById('emailSpecificStudentsSelection');
            
            if (emailRecipientType && emailRecipientType.value === 'specific') {
                emailSpecificSelection.classList.add('active');
                updateEmailSelectedCount();
            } else {
                emailSpecificSelection.classList.remove('active');
            }
            updateEmailRecipientCount();
        }

        function updateEmailSelectedCount() {
            const selectedCheckboxes = document.querySelectorAll('.email-student-checkbox-input:checked');
            const selectedCount = selectedCheckboxes.length;
            const emailSelectedCount = document.getElementById('emailSelectedCount');
            
            if (emailSelectedCount) {
                emailSelectedCount.textContent = selectedCount + ' selected';
            }
            
            const totalStudents = document.querySelectorAll('.email-student-checkbox-input').length;
            const selectAll = document.getElementById('selectEmailAllStudents');
            
            if (selectAll) {
                if (selectedCount === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if (selectedCount === totalStudents) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }
        }

        function setupEmailSelectAll() {
            const selectAll = document.getElementById('selectEmailAllStudents');
            const studentCheckboxes = document.querySelectorAll('.email-student-checkbox-input');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    studentCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateEmailSelectedCount();
                    updateEmailRecipientCount();
                });
            }
        }

        // Update email recipient count using AJAX
        function updateEmailRecipientCount() {
            const sendEmailCheckbox = document.getElementById('sendEmail');
            const emailRecipientCount = document.getElementById('emailRecipientCount');
            
            if (!sendEmailCheckbox || !sendEmailCheckbox.checked) {
                if (emailRecipientCount) {
                    emailRecipientCount.innerHTML = 
                        `<i class="fas fa-users me-2"></i>Email recipients: 0 students (email not selected)`;
                }
                return;
            }
            
            const emailRecipientType = document.querySelector('input[name="emailRecipientType"]:checked');
            
            if (emailRecipientType && emailRecipientType.value === 'specific') {
                const selectedStudents = document.querySelectorAll('.email-student-checkbox-input:checked');
                const selectedIds = Array.from(selectedStudents).map(cb => cb.value);
                
                if (selectedIds.length > 0) {
                    // Fetch count from server for specific students
                    fetch(`get_recipient_count.php?type=specific&specific_students=${selectedIds.join(',')}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                emailRecipientCount.innerHTML = 
                                    `<i class="fas fa-users me-2"></i>Email recipients: ${data.count} students`;
                            } else {
                                emailRecipientCount.innerHTML = 
                                    `<i class="fas fa-users me-2"></i>Email recipients: 0 students (error)`;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            emailRecipientCount.innerHTML = 
                                `<i class="fas fa-users me-2"></i>Email recipients: ${selectedIds.length} students`;
                        });
                } else {
                    emailRecipientCount.innerHTML = 
                        `<i class="fas fa-users me-2"></i>Email recipients: 0 students`;
                }
            } else {
                // For "all students", fetch from server
                const type = emailRecipientType ? emailRecipientType.value : 'all';
                
                fetch(`get_recipient_count.php?type=${type}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            emailRecipientCount.innerHTML = 
                                `<i class="fas fa-users me-2"></i>Email recipients: ${data.count} students`;
                        } else {
                            emailRecipientCount.innerHTML = 
                                `<i class="fas fa-users me-2"></i>Email recipients: 0 students (error)`;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        emailRecipientCount.innerHTML = 
                            `<i class="fas fa-users me-2"></i>Email recipients: 0 students (error)`;
                    });
            }
        }

        function initEmailStudentSearch() {
            const searchInput = document.getElementById('emailStudentSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const studentCheckboxes = document.querySelectorAll('.student-checkbox');
                
                studentCheckboxes.forEach(checkbox => {
                    const studentName = checkbox.querySelector('.student-name').textContent.toLowerCase();
                    const studentDetails = checkbox.querySelector('.student-details').textContent.toLowerCase();
                    const isVisible = studentName.includes(searchTerm) || studentDetails.includes(searchTerm);
                    
                    checkbox.style.display = isVisible ? 'block' : 'none';
                });
            });
        }

        function updateEmailPreview() {
            const subject = document.getElementById('subject').value || 'Announcement Subject';
            const content = document.getElementById('content').value || 'Announcement content will appear here...';
            const sentBy = document.getElementById('sentBy').value || 'Administrator';
            const expiryDate = document.getElementById('expiryDate').value;
            const expiryTime = document.getElementById('expiryTime').value;
            const previewContent = document.getElementById('previewContent');
            
            if (!previewContent) return;
            
            const currentDate = new Date();
            const formattedDate = currentDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let emailContent = content;
            let expiryNotice = '';
            
            if (expiryDate && expiryTime) {
                const expiryDateTime = new Date(expiryDate + 'T' + expiryTime);
                const expiryFormatted = expiryDateTime.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                emailContent += "\n\nThis announcement will expire on: " + expiryFormatted;
                expiryNotice = `
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px 15px; border-radius: 8px; margin-top: 15px; font-size: 14px;">
                        <strong>⚠️ Expiry Notice:</strong> This announcement will expire on ${expiryFormatted}
                    </div>
                `;
            }
            
            const previewHTML = `
                <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 100%;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center;">
                        <h2 style="margin: 0; font-size: 20px;">AURORA STATE COLLEGE OF TECHNOLOGY</h2>
                        <p style="margin: 5px 0 0 0; opacity: 0.9;">Online School Clinic</p>
                    </div>
                    <div style="padding: 20px; background: #f8f9fa;">
                        <h3 style="color: #1a3a5f; font-size: 18px; margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">${subject}</h3>
                        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; margin-bottom: 15px;">
                            ${emailContent.replace(/\n/g, '<br>')}
                        </div>
                        ${expiryNotice}
                        <div style="background: #e9ecef; padding: 12px; border-radius: 8px; font-size: 14px;">
                            <p style="margin: 0;"><strong>Sent by:</strong> ${sentBy}</p>
                            <p style="margin: 5px 0 0 0;"><strong>Date:</strong> ${formattedDate}</p>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 15px; color: #666; font-size: 12px; background: white; border-top: 1px solid #e9ecef;">
                        <p style="margin: 0;">This is an automated message from ASCOT Online Clinic System.</p>
                        <p style="margin: 5px 0 0 0;">Please do not reply to this email.</p>
                    </div>
                </div>
            `;
            
            previewContent.innerHTML = previewHTML;
        }

        function updateExpiryPreview() {
            const dateInput = document.getElementById('expiryDate');
            const timeInput = document.getElementById('expiryTime');
            const preview = document.getElementById('expiryPreview');
            const previewText = document.getElementById('expiryPreviewText');
            const timeRemaining = document.getElementById('timeRemaining');
            const expiredNotice = document.getElementById('expiredNotice');

            if (dateInput && timeInput && dateInput.value && timeInput.value) {
                const expiryDateTime = new Date(dateInput.value + 'T' + timeInput.value);
                const now = new Date();
                
                const formattedDate = expiryDateTime.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                if (previewText) previewText.textContent = formattedDate;
                
                const timeDiff = expiryDateTime - now;
                if (timeDiff > 0) {
                    const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    
                    let remainingText = '';
                    if (days > 0) {
                        remainingText = `${days} day${days > 1 ? 's' : ''} and ${hours} hour${hours > 1 ? 's' : ''} from now`;
                    } else if (hours > 0) {
                        remainingText = `${hours} hour${hours > 1 ? 's' : ''} from now`;
                    } else {
                        const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                        remainingText = `${minutes} minute${minutes > 1 ? 's' : ''} from now`;
                    }
                    
                    if (timeRemaining) {
                        timeRemaining.textContent = `⏰ ${remainingText}`;
                        timeRemaining.style.color = '#e67e22';
                        timeRemaining.style.display = 'block';
                    }
                    if (expiredNotice) {
                        expiredNotice.style.display = 'none';
                    }
                } else {
                    if (timeRemaining) {
                        timeRemaining.style.display = 'none';
                    }
                    if (expiredNotice) {
                        expiredNotice.style.display = 'block';
                    }
                }
                
                if (preview) preview.style.display = 'block';
            } else {
                if (preview) preview.style.display = 'none';
            }
        }

        function setupTimePresets() {
            const presetButtons = document.querySelectorAll('.time-preset-btn');
            const timeInput = document.getElementById('expiryTime');
            
            presetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (timeInput) {
                        timeInput.value = this.getAttribute('data-time');
                        updateExpiryPreview();
                    }
                });
            });
        }

        function setMinimumDate() {
            const dateInput = document.getElementById('expiryDate');
            if (dateInput) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
                
                if (!dateInput.value) {
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    dateInput.value = tomorrow.toISOString().split('T')[0];
                }
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize email functionality
            const sendEmailCheckbox = document.getElementById('sendEmail');
            if (sendEmailCheckbox) {
                sendEmailCheckbox.addEventListener('change', toggleEmailRecipientSelection);
            }

            // Email recipient type change
            document.querySelectorAll('input[name="emailRecipientType"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    toggleEmailSpecificStudentSelection();
                    updateEmailRecipientCount();
                });
            });

            // Email student checkbox changes
            document.querySelectorAll('.email-student-checkbox-input').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateEmailSelectedCount();
                    updateEmailRecipientCount();
                });
            });

            // Initialize functions
            toggleEmailRecipientSelection();
            setupEmailSelectAll();
            initEmailStudentSearch();
            updateEmailSelectedCount();
            updateEmailRecipientCount();
            
            // Initialize expiry time functionality
            setMinimumDate();
            setupTimePresets();
            
            const dateInput = document.getElementById('expiryDate');
            const timeInput = document.getElementById('expiryTime');
            
            if (dateInput) dateInput.addEventListener('change', updateExpiryPreview);
            if (timeInput) timeInput.addEventListener('change', updateExpiryPreview);
            
            updateExpiryPreview();
        });

        // Email preview functionality
        const previewEmailBtn = document.getElementById('previewEmailBtn');
        const emailPreview = document.getElementById('emailPreview');
        const subjectInput = document.getElementById('subject');
        const contentInput = document.getElementById('content');
        const sentBySelect = document.getElementById('sentBy');
        const expiryDateInput = document.getElementById('expiryDate');
        const expiryTimeInput = document.getElementById('expiryTime');

        if (subjectInput) subjectInput.addEventListener('input', updateEmailPreview);
        if (contentInput) contentInput.addEventListener('input', updateEmailPreview);
        if (sentBySelect) sentBySelect.addEventListener('change', updateEmailPreview);
        if (expiryDateInput) expiryDateInput.addEventListener('change', updateEmailPreview);
        if (expiryTimeInput) expiryTimeInput.addEventListener('change', updateEmailPreview);

        if (previewEmailBtn) {
            previewEmailBtn.addEventListener('click', function() {
                if (emailPreview) {
                    emailPreview.classList.toggle('active');
                    if (emailPreview.classList.contains('active')) {
                        updateEmailPreview();
                        this.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Preview';
                    } else {
                        this.innerHTML = '<i class="fas fa-eye"></i> Preview Email';
                    }
                }
            });
        }

        // Form submission
        const announcementForm = document.getElementById('announcementForm');
        const cancelBtn = document.getElementById('cancelBtn');

        if (announcementForm) {
            announcementForm.addEventListener('submit', function(e) {
                const sentBy = document.getElementById('sentBy')?.value;
                const subject = document.getElementById('subject')?.value;
                const content = document.getElementById('content')?.value;
                const sendEmail = document.getElementById('sendEmail')?.checked;
                const postFront = document.getElementById('postFront')?.checked;
                const expiryDate = document.getElementById('expiryDate')?.value;
                const expiryTime = document.getElementById('expiryTime')?.value;
                
                if (!sendEmail && !postFront) {
                    e.preventDefault();
                    alert('Please select at least one announcement type (Send Email or Post on Front Page)');
                    return;
                }
                
                if (!sentBy) {
                    e.preventDefault();
                    alert('Please select a sender');
                    return;
                }
                
                if (!subject) {
                    e.preventDefault();
                    alert('Please enter a subject');
                    return;
                }
                
                if (!content) {
                    e.preventDefault();
                    alert('Please enter announcement content');
                    return;
                }
                
                // Check if expiry date is in the past
                if (expiryDate && expiryTime) {
                    const expiryDateTime = new Date(expiryDate + 'T' + expiryTime);
                    const now = new Date();
                    if (expiryDateTime <= now) {
                        if (!confirm('The expiry date and time you selected has already passed. This announcement will be created as expired. Do you want to continue?')) {
                            e.preventDefault();
                            return;
                        }
                    }
                }
                
                if (sendEmail) {
                    const emailRecipientType = document.querySelector('input[name="emailRecipientType"]:checked');
                    if (!emailRecipientType) {
                        e.preventDefault();
                        alert('Please select email recipient type');
                        return;
                    }
                    
                    if (emailRecipientType.value === 'specific') {
                        const selectedStudents = document.querySelectorAll('.email-student-checkbox-input:checked').length;
                        if (selectedStudents === 0) {
                            e.preventDefault();
                            alert('Please select at least one student for email announcement');
                            return;
                        }
                    }
                    
                    if (!confirm('Are you sure you want to send this email announcement?')) {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    submitBtn.disabled = true;
                }
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
                    window.location.href = 'admin_dashboard.php';
                }
            });
        }

        // File upload validation
        const fileInput = document.getElementById('attachment');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    if (fileSize > 10) {
                        alert('File size should be less than 10MB');
                        this.value = '';
                    }
                }
            });
        }
    </script>
</body>
</html>