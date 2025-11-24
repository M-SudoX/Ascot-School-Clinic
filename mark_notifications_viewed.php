<?php
// mark_notifications_viewed.php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$student_id = $_SESSION['student_id'];

try {
    // Mark notifications as viewed in session
    $_SESSION['notifications_viewed'] = true;
    
    // Update the last viewed time for announcements
    $stmt = $pdo->prepare("
        INSERT INTO announcement_views (student_id, last_viewed) 
        VALUES (?, NOW()) 
        ON DUPLICATE KEY UPDATE last_viewed = NOW()
    ");
    $stmt->execute([$student_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}