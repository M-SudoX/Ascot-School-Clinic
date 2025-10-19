<?php
session_start();
require_once '../includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

$type = $_GET['type'] ?? 'all';

try {
    switch ($type) {
        case 'all':
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE email IS NOT NULL AND email != '' AND status = 'active'");
            break;
        case 'specific':
            // Example: BSIT students only
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE email IS NOT NULL AND email != '' AND status = 'active' AND course = 'BSIT'");
            break;
        case 'attendees':
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT s.id) as count 
                FROM students s
                INNER JOIN appointments a ON s.id = a.student_id 
                WHERE s.email IS NOT NULL AND s.email != '' 
                AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            break;
        default:
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE email IS NOT NULL AND email != '' AND status = 'active'");
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => $result['count']]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}
?>