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
        // Restore the consultation
        $stmt = $pdo->prepare("UPDATE consultations SET is_archived = 0 WHERE id = ?");
        $stmt->execute([$consultation_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Consultation restored successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Consultation not found or already active.']);
        }
    } catch (PDOException $e) {
        error_log("Restore consultation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error restoring consultation.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID.']);
}
?>