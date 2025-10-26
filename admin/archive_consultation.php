<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Get consultation ID from POST
$consultation_id = $_POST['id'] ?? 0;

if ($consultation_id) {
    try {
        // Archive the consultation by updating is_archived flag
        $archive_stmt = $pdo->prepare("
            UPDATE consultations 
            SET is_archived = 1, archived_at = NOW() 
            WHERE id = ?
        ");
        $archive_stmt->execute([$consultation_id]);
        
        echo json_encode(['success' => true, 'message' => 'Consultation archived successfully!']);
    } catch (PDOException $e) {
        error_log("Archive consultation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error archiving consultation record.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID.']);
}
?>