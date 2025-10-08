<?php
session_start(); 

// ✅ Check kung naka-login ang user (student).
// Kung wala siyang session 'student_id', ibig sabihin hindi siya naka-login.
if (!isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// ✅ Include database connection file (db_connect.php)
// para makakonekta sa MySQL database.
require_once 'db_connect.php';

// ✅ Check kung ang request ay galing sa POST method (ibig sabihin may sinubmit na form).
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ✅ Kunin ang form data na pinasa ng user
    $student_id = $_SESSION['student_id'];  // galing sa session
    $consultation_date = $_POST['consultation_date'] ?? '';
    $consultation_time = $_POST['consultation_time'] ?? '';
    $reason_concern = $_POST['reason_concern'] ?? '';
    $optional_notes = $_POST['optional_notes'] ?? '';
    
    // ✅ Error checking array
    $errors = [];
    
    // ✅ Validation para sa consultation date
    if (empty($consultation_date)) {
        $errors[] = 'Date is required';
    } else {
        // Check kung ang napiling date ay hindi nakaraan (past date)
        $selected_date = new DateTime($consultation_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($selected_date < $today) {
            $errors[] = 'Date cannot be in the past';
        }
    }
    
    // ✅ Validation para sa oras
    if (empty($consultation_time)) {
        $errors[] = 'Time is required';
    }
    
    // ✅ Validation para sa reason/concern
    if (empty($reason_concern)) {
        $errors[] = 'Reason/concern is required';
    } else if (strlen(trim($reason_concern)) < 10) {
        $errors[] = 'Please provide more details (minimum 10 characters)';
    }
    
    // ✅ Check kung may conflict sa existing appointment (within 30 minutes)
    if (!empty($consultation_date) && !empty($consultation_time)) {
        $datetime_str = $consultation_date . ' ' . $consultation_time;
        $requested_datetime = new DateTime($datetime_str);
        
        $check_sql = "SELECT COUNT(*) as count FROM consultations 
                      WHERE consultation_date = ? 
                      AND ABS(TIMESTAMPDIFF(MINUTE, consultation_time, ?)) < 30 
                      AND status != 'cancelled'";
        
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $consultation_date, $consultation_time);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = 'This time slot conflicts with existing appointments';
        }
    }
    
    // ✅ Kung may error, ibalik agad sa user
    if (!empty($errors)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit();
    }
    
    try {
        // ✅ Insert consultation request sa database
        $sql = "INSERT INTO consultations (
                    student_id, 
                    consultation_date, 
                    consultation_time, 
                    reason_concern, 
                    optional_notes, 
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", 
            $student_id, 
            $consultation_date, 
            $consultation_time, 
            $reason_concern, 
            $optional_notes
        );
        
        if ($stmt->execute()) {
            // ✅ Success response ibabalik as JSON
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Consultation request submitted successfully!',
                'consultation_id' => $conn->insert_id
            ]);
            
            // Optional: pwede kang magpadala ng email sa clinic/admin
            // sendNotificationEmail($student_id, $consultation_date, $consultation_time, $reason_concern);
            
        } else {
            throw new Exception('Failed to insert consultation request');
        }
        
    } catch (Exception $e) {
        // ✅ Kung may error habang nag-iinsert
        error_log('Consultation submission error: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again.'
        ]);
    }
    
} else {
    // ✅ Kung hindi POST request ang dumating (hal. GET), reject
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// ✅ Function para mag-send ng email notification sa admin/clinic
function sendNotificationEmail($student_id, $date, $time, $concern) {
    global $conn;
    
    // Kunin ang student info (pangalan at email)
    $sql = "SELECT first_name, last_name, email FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    if ($student) {
        $to = "clinic@ascot.edu.ph"; // Email ng clinic
        $subject = "New Consultation Request - " . $student['first_name'] . " " . $student['last_name'];
        
        // ✅ Email message content (HTML format)
        $message = "
        <html>
        <head>
            <title>New Consultation Request</title>
        </head>
        <body>
            <h2>New Consultation Request</h2>
            <p><strong>Student:</strong> {$student['first_name']} {$student['last_name']}</p>
            <p><strong>Email:</strong> {$student['email']}</p>
            <p><strong>Date:</strong> {$date}</p>
            <p><strong>Time:</strong> {$time}</p>
            <p><strong>Concern:</strong> {$concern}</p>
            <p>Please review and approve/deny this request in the admin panel.</p>
        </body>
        </html>
        ";
        // ✅ Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@ascot.edu.ph" . "\r\n";
        
        // ✅ Send email
        mail($to, $subject, $message, $headers);
    }
}
?>
