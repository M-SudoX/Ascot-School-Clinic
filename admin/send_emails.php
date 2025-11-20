<?php
require_once '../includes/db_connect.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendAnnouncementEmail($to, $name, $subject, $content, $sentBy) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bihasamaynard070@gmail.com'; 
        $mail->Password = 'zjopucbvhzfcuosv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('clinic@ascot.edu.ph', 'ASCOT Clinic');
        $mail->addAddress($to, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Email template
        $emailBody = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>$subject</title>
                <style>
                    body { 
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
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
                    }
                    .header { 
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                        color: white; 
                        padding: 30px 20px; 
                        text-align: center; 
                    }
                    .header h1 { 
                        margin: 0; 
                        font-size: 24px; 
                    }
                    .header p { 
                        margin: 5px 0 0 0; 
                        opacity: 0.9; 
                    }
                    .content { 
                        padding: 30px; 
                    }
                    .announcement { 
                        background: #f8f9fa; 
                        padding: 20px; 
                        border-radius: 10px; 
                        border-left: 4px solid #667eea; 
                        margin-bottom: 20px;
                    }
                    .announcement h2 { 
                        color: #1a3a5f; 
                        margin-top: 0; 
                    }
                    .sender-info { 
                        background: #e9ecef; 
                        padding: 15px; 
                        border-radius: 8px; 
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
                            <div>" . nl2br(htmlspecialchars($content)) . "</div>
                        </div>
                        <div class='sender-info'>
                            <p><strong>Sent by:</strong> $sentBy</p>
                            <p><strong>Date:</strong> " . date('F j, Y g:i A') . "</p>
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
        
        $mail->Body = $emailBody;
        
        // Alternative text for non-HTML email clients
        $mail->AltBody = "ASCOT Clinic Announcement\n\nSubject: $subject\n\n$content\n\nSent by: $sentBy\nDate: " . date('F j, Y g:i A');
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email error for $to: " . $mail->ErrorInfo);
        return false;
    }
}

try {
    // Get pending emails (limit to 10 per run to avoid timeout)
    $stmt = $pdo->prepare("
        SELECT ae.*, a.title, a.content, a.sent_by 
        FROM announcement_emails ae
        JOIN announcements a ON ae.announcement_id = a.id
        WHERE ae.status = 'pending'
        ORDER BY ae.id ASC
        LIMIT 10
    ");
    $stmt->execute();
    $pendingEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sentCount = 0;
    $failedCount = 0;
    $errors = [];
    
    foreach ($pendingEmails as $email) {
        $success = sendAnnouncementEmail(
            $email['recipient_email'],
            $email['recipient_name'],
            $email['title'],
            $email['content'],
            $email['sent_by']
        );
        
        // Update status
        $updateStmt = $pdo->prepare("
            UPDATE announcement_emails 
            SET status = ?, 
                sent_at = NOW(),
                error_message = ?
            WHERE id = ?
        ");
        
        if ($success) {
            $updateStmt->execute(['sent', '', $email['id']]);
            $sentCount++;
        } else {
            $updateStmt->execute(['failed', 'Failed to send email', $email['id']]);
            $failedCount++;
            $errors[] = $email['recipient_email'];
        }
        
        // Small delay to avoid overwhelming the SMTP server
        sleep(1);
    }
    
    // Update announcement counts
    if (!empty($pendingEmails)) {
        $announcementId = $pendingEmails[0]['announcement_id'];
        $updateAnnouncementStmt = $pdo->prepare("
            UPDATE announcements 
            SET email_sent_count = email_sent_count + ?, 
                email_failed_count = email_failed_count + ? 
            WHERE id = ?
        ");
        $updateAnnouncementStmt->execute([$sentCount, $failedCount, $announcementId]);
    }
    
    // Log results
    $logMessage = "Email processing completed: $sentCount sent, $failedCount failed";
    if (!empty($errors)) {
        $logMessage .= " - Errors: " . implode(', ', $errors);
    }
    error_log($logMessage);
    
    echo $logMessage;
    
} catch (Exception $e) {
    error_log("Email processing error: " . $e->getMessage());
    echo "Error processing emails: " . $e->getMessage();
}
?>