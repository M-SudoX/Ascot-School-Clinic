<?php
// includes/activity_logger.php - SPECIFIC ACTIONS ONLY
function logActivity($pdo, $student_id, $action) {
    try {
        // ✅ DUPLICATE PREVENTION: Check for same action within 2 minutes
        $check_stmt = $pdo->prepare("
            SELECT id FROM activity_logs 
            WHERE student_id = ? AND action = ? 
            AND log_date >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            LIMIT 1
        ");
        $check_stmt->execute([$student_id, $action]);
        $existing_log = $check_stmt->fetch();
        
        // ✅ If duplicate found within 2 minutes, don't log again
        if ($existing_log) {
            return false;
        }
        
        // ✅ Insert the activity log
        $stmt = $pdo->prepare("INSERT INTO activity_logs (student_id, action, log_date) VALUES (?, ?, NOW())");
        $stmt->execute([$student_id, $action]);
        return true;
        
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}
?>