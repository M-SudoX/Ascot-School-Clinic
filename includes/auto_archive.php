<?php
// includes/auto_archive.php

function archiveExpiredAnnouncements($pdo) {
    try {
        $current_date = date('Y-m-d H:i:s');
        
        // Archive announcements that have expired
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET is_archived = 1, 
                archive_date = ? 
            WHERE (expiry_date IS NOT NULL AND expiry_date <= ?) 
            AND is_archived = 0
        ");
        
        $stmt->execute([$current_date, $current_date]);
        
        $archived_count = $stmt->rowCount();
        
        error_log("Auto-archive: Archived {$archived_count} expired announcements");
        
        return $archived_count;
        
    } catch (PDOException $e) {
        error_log("Error archiving expired announcements: " . $e->getMessage());
        return 0;
    }
}

function checkAndArchiveExpired($pdo) {
    // Only run once per hour to avoid performance issues
    $last_run = $_SESSION['last_archive_run'] ?? 0;
    $current_time = time();
    
    if ($current_time - $last_run > 3600) { // Run once per hour
        archiveExpiredAnnouncements($pdo);
        $_SESSION['last_archive_run'] = $current_time;
    }
}
?>