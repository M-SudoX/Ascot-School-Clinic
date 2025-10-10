<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// ✅ Check login
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// ✅ POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_SESSION['student_id'];
$consultation_date = $_POST['consultation_date'] ?? '';
$consultation_time = $_POST['consultation_time'] ?? '';
$reason_concern = $_POST['reason_concern'] ?? '';
$optional_notes = $_POST['optional_notes'] ?? '';
$errors = [];

// ✅ Validation
if (empty($consultation_date)) $errors[] = 'Date is required';
if (empty($consultation_time)) $errors[] = 'Time is required';
if (empty($reason_concern)) $errors[] = 'Reason/concern is required';
if (strlen(trim($reason_concern)) < 3) $errors[] = 'Please provide a valid reason';

// ✅ Return error if any
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    // ✅ Check time conflict (within 30 minutes)
    $check_sql = "SELECT COUNT(*) as count FROM consultation_requests
                  WHERE date = ? 
                  AND ABS(TIMESTAMPDIFF(MINUTE, time, ?)) < 30 
                  AND status != 'Rejected'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $consultation_date, $consultation_time);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'This time slot is already taken']);
        exit;
    }

    // ✅ Insert record
    $sql = "INSERT INTO consultation_requests (student_id, date, time, requested, notes, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $student_id, $consultation_date, $consultation_time, $reason_concern, $optional_notes);
    $stmt->execute();

    // ✅ Fetch the newly created record to return as JSON
    $new_id = $conn->insert_id;
    $fetch_sql = "SELECT * FROM consultation_requests WHERE id = ?";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("i", $new_id);
    $fetch_stmt->execute();
    $new_record = $fetch_stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => 'Consultation request submitted successfully!',
        'data' => $new_record
    ]);

} catch (Exception $e) {
    error_log('Consultation submission error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}
?>
