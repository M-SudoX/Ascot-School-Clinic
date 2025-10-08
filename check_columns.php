<?php
session_start();
require 'includes/db_connect.php';

echo "<h3>Checking student_information table structure:</h3>";

try {
    $stmt = $pdo->query("DESCRIBE student_information");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Check if student data exists
echo "<h3>Checking if student data exists:</h3>";
if (isset($_SESSION['student_number'])) {
    $student_number = $_SESSION['student_number'];
    $stmt = $pdo->prepare("SELECT * FROM student_information WHERE student_number = ?");
    $stmt->execute([$student_number]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo "Student found: " . print_r($student, true);
    } else {
        echo "No student found with number: " . $student_number;
    }
} else {
    echo "No student session found";
}
?>