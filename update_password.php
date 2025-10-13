<?php
require 'includes/db_connect.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm'] ?? '';

if (!$token || $password !== $confirm) {
    die('Passwords do not match.');
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('Invalid or expired token.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
    ->execute([$hash, $user['id']]);

echo "<script>alert('Password updated successfully!'); window.location='studentlogin.php';</script>";
