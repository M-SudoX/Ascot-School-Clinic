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
        // Restore the consultation by updating is_archived flag
        $restore_stmt = $pdo->prepare("
            UPDATE consultations 
            SET is_archived = 0, archived_at = NULL 
            WHERE id = ?
        ");
        $restore_stmt->execute([$consultation_id]);
        
        echo json_encode(['success' => true, 'message' => 'Consultation restored successfully!']);
    } catch (PDOException $e) {
        error_log("Restore consultation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error restoring consultation record.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID.']);
}
?>