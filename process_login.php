<?php

// PDO was used to prevent the system from being hacked using SQL injection!
session_start();

// I-connect ang database connection file
require_once 'includes/db_connect.php';

// Suriin kung ang request method ay POST (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin at linisin ang student_number mula sa form
    $student_number = trim($_POST['student_number'] ?? '');
    // Kunin ang password (hindi kailangang i-trim dahil baka may spaces ang password)
    $password = $_POST['password'] ?? '';

    // Basic validation - siguraduhing hindi blanko ang fields
    if (empty($student_number) || empty($password)) {
        // Mag-set ng error message sa session
        $_SESSION['error'] = 'Please enter both ID Number and Password.';
        // I-redirect pabalik sa login page
        header("Location: student_login.php");
        exit();
    }

    try {
        //Secure database queries with parameter binding

        $stmt = $pdo->prepare("SELECT id, student_number, fullname, password FROM users WHERE student_number = :student_number LIMIT 1");
        $stmt->bindParam(':student_number', $student_number, PDO::PARAM_STR);
        
        // I-execute ang query
        $stmt->execute();
        
        // Kunin ang resulta bilang associative array
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // DEBUG: Check kung may user na nakuha
        if (!$user) {
            // WALANG USER NA EXIST - mag-log ng error at magpakita ng generic message
            error_log("LOGIN FAILED: User not found - " . $student_number);
            $_SESSION['error'] = 'Invalid ID Number or Password.';
            header("Location: student_login.php");
            exit();
        }

        // DEBUG: Mag-log ng impormasyon para sa troubleshooting
        error_log("Login attempt - Student: " . $student_number);
        error_log("Stored hash: " . $user['password']);
        error_log("Password length: " . strlen($user['password']));

        // CRITICAL: Password verification gamit ang password_verify()
        // Ito ay secure na paraan para i-compare ang plain text password sa hashed password
        // Ang password_verify() ay:
        // 1. Timing-attack resistant
        // 2. Hindi vulnerable sa side-channel attacks
        // 3. Built-in na security feature ng PHP

        if (password_verify($password, $user['password'])) {
            // SUCCESS: Tamang credentials - itakda ang session variables
            $_SESSION['student_id'] = $user['id'];          // User ID para sa database operations
            $_SESSION['fullname'] = $user['fullname'];      // Full name para ipakita sa UI
            $_SESSION['student_number'] = $user['student_number']; // Student number para sa reference
            $_SESSION['last_login'] = time();               // Timestamp ng login para sa security

            // Mag-log ng successful login
            error_log("LOGIN SUCCESS: " . $student_number);
            
            // I-redirect sa dashboard page
            header("Location: student_dashboard.php");
            exit();
        } else {
            // FAIL: Mali ang password
            error_log("LOGIN FAILED: Wrong password for - " . $student_number);
            $_SESSION['error'] = 'Invalid ID Number or Password.';
            header("Location: student_login.php");
            exit();
        }

    } catch (PDOException $e) {
        // Exception handling - kapag may error sa database
        error_log("Login system error: " . $e->getMessage());
        $_SESSION['error'] = 'System error. Please try again later.';
        header("Location: student_login.php");
        exit();
    }
} else {
    // Not POST request - kung diretso na-access ang file na ito
    header("Location: student_login.php");
    exit();
}
?>