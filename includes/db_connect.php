<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== DATABASE CONFIG =====
$host = "localhost";
$db   = "ascot_clinic_db";
$user = "root";
$pass = "";

// Define constants so other scripts (like backup_restore.php) can use them
if (!defined('DB_HOST')) define('DB_HOST', $host);
if (!defined('DB_NAME')) define('DB_NAME', $db);
if (!defined('DB_USER')) define('DB_USER', $user);
if (!defined('DB_PASS')) define('DB_PASS', $pass);

// Optional: encryption method constant for backup/restore
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

// ===== CREATE PDO CONNECTION =====
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
