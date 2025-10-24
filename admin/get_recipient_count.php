<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? 'all';
    $specificStudents = $_GET['specific_students'] ?? '';
    
    if ($type === 'specific' && !empty($specificStudents)) {
        $studentIds = explode(',', $specificStudents);
        $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE id IN ($placeholders) 
            AND email IS NOT NULL 
            AND email != '' 
            AND TRIM(email) != ''
            AND email LIKE '%@%'
            AND is_verified = 1
        ");
        $stmt->execute($studentIds);
    } else {
        // Count all verified users with valid email
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE email IS NOT NULL 
            AND email != '' 
            AND TRIM(email) != ''
            AND email LIKE '%@%'
            AND is_verified = 1
        ");
        $stmt->execute();
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => $result['count'] ?? 0
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'error' => $e->getMessage()
    ]);
}
?>