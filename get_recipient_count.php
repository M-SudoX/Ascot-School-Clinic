<?php
session_start();
require_once '../includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $recipient_type = $_GET['type'] ?? 'all';
    $specific_students = $_GET['specific_students'] ?? '';
    
    $count = 0;
    
    switch ($recipient_type) {
        case 'all':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM students 
                WHERE email IS NOT NULL 
                AND email != '' 
                AND TRIM(email) != ''
                AND email LIKE '%@%'
                AND status = 'active'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            break;
            
        case 'specific':
            if (!empty($specific_students)) {
                $student_ids = explode(',', $specific_students);
                $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM students 
                    WHERE id IN ($placeholders)
                    AND email IS NOT NULL 
                    AND email != '' 
                    AND TRIM(email) != ''
                    AND email LIKE '%@%'
                    AND status = 'active'
                ");
                $stmt->execute($student_ids);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = $result['count'];
            }
            break;
            
        case 'attendees':
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT s.id) as count 
                FROM students s
                INNER JOIN appointments a ON s.id = a.student_id 
                WHERE s.email IS NOT NULL 
                AND s.email != '' 
                AND TRIM(s.email) != ''
                AND s.email LIKE '%@%'
                AND s.status = 'active'
                AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            break;
    }
    
    echo json_encode(['success' => true, 'count' => $count]);
    
} catch (PDOException $e) {
    error_log("Error getting recipient count: " . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0]);
}
?>