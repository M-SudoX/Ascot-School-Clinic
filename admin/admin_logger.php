<?php
// admin_logger.php - For logging admin actions
function logAdminAction($adminName, $action) {
    // ✅ COMPLETELY HUWAG i-log ang mga viewed at accessed actions
    $lowerAction = strtolower($action);
    if (strpos($lowerAction, 'viewed') !== false || 
        strpos($lowerAction, 'accessed') !== false) {
        return false; // Exit agad, wag i-save sa database
    }
    
    // ✅ PREVENT DUPLICATE LOGGING - Check sa database kung may existing similar action sa last 5 minutes
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "ascot_clinic_db";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        return false;
    }
    
    // ✅ Check muna kung may existing similar action sa last 5 minutes
    $checkStmt = $conn->prepare("
        SELECT id FROM admin_logs 
        WHERE admin_name = ? 
        AND action = ? 
        AND log_date >= NOW() - INTERVAL 5 MINUTE
        LIMIT 1
    ");
    $checkStmt->bind_param("ss", $adminName, $action);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    // ✅ Kung may existing na, wag na mag-log ulit
    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        return false;
    }
    $checkStmt->close();
    
    // ✅ Kung wala pa, saka mag-insert
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_name, action, log_date) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $adminName, $action);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}
?>