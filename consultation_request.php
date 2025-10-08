<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $time = $_POST['time'];
    $concern = $_POST['concern'];
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO consultation_requests 
            (student_id, date, time, concern, notes, status) 
            VALUES (:student_id, :date, :time, :concern, :notes, 'Pending')");

        $stmt->execute([
            ':student_id' => $student_id,
            ':date' => $date,
            ':time' => $time,
            ':concern' => $concern,
            ':notes' => $notes
        ]);

        echo "<script>alert('Consultation request submitted successfully!'); window.location.href='student_dashboard.php';</script>";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
