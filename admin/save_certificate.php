<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_certificate'])) {
    try {
        if (!isset($_SESSION['certificate_data'])) {
            throw new Exception('No certificate data found');
        }

        $data = $_SESSION['certificate_data'];

        // Insert certificate into database
        $stmt = $pdo->prepare("
            INSERT INTO certificates 
            (consultation_id, student_name, certificate_type, diagnosis, recommendation, date_issued) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['consultation_id'],
            $data['student_name'],
            $data['certificate_type'],
            $data['diagnosis'],
            $data['recommendation'],
            $data['date_issued']
        ]);

        $certificate_id = $pdo->lastInsertId();

        // Clear the session data after saving
        unset($_SESSION['certificate_data']);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'certificate_id' => $certificate_id]);

    } catch (Exception $e) {
        error_log("Certificate save error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>