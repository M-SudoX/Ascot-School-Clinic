<?php
session_start();
require_once '../includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Unauthorized access']);
    exit;
}

// Get recipient type from request
$type = $_GET['type'] ?? 'all';
$specific_students = $_GET['specific_students'] ?? '';

try {
    $count = 0;
    
    switch ($type) {
        case 'all':
            // Count all active students with valid email
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
                // If specific student IDs are provided via GET (for preview)
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
            } else {
                // Count all active students (for initial load)
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
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            break;
            
        case 'attendees':
            // Count students with appointments in last 30 days and valid email
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
                AND a.status IN ('completed', 'approved', 'scheduled') -- Only count valid appointments
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            break;
            
        default:
            // Default to all students
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
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'count' => $count,
        'type' => $type
    ]);
    
} catch (PDOException $e) {
    // Log error and return failure response
    error_log("Error getting recipient count: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'count' => 0, 
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
}
?>