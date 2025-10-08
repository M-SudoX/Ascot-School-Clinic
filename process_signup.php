<?php
session_start();
require_once 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullname) || empty($student_number) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'All fields are required.';
        header("Location: signup.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match.';
        header("Location: signup.php");
        exit();
    }

    // Password validation
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long.';
        header("Location: signup.php");
        exit();
    }

    if (!preg_match('/[!@#$%^&*]/', $password)) {
        $_SESSION['error'] = 'Password must contain at least one special character (!@#$%^&*).';
        header("Location: signup.php");
        exit();
    }

    try {
        // Check if student number or email already exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE student_number = ? OR email = ?");
        $check_stmt->execute([$student_number, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Student number or email already exists.';
            header("Location: signup.php");
            exit();
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // First, check if role column exists
        $check_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $role_column_exists = $check_column->rowCount() > 0;

        // Insert into users table (with or without role column)
        if ($role_column_exists) {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, student_number, email, password, role) 
                                   VALUES (?, ?, ?, ?, 'student')");
            $stmt->execute([$fullname, $student_number, $email, $hashed_password]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (fullname, student_number, email, password) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$fullname, $student_number, $email, $hashed_password]);
        }
        
        $user_id = $pdo->lastInsertId();

        // Insert into student_information with ALL required fields
        try {
            $current_school_year = date('Y') . '-' . (date('Y') + 1);
            
            // Check if user_id column exists in student_information
            $check_user_id_column = $pdo->query("SHOW COLUMNS FROM student_information LIKE 'user_id'");
            $user_id_column_exists = $check_user_id_column->rowCount() > 0;
            
            if ($user_id_column_exists) {
                $stmt2 = $pdo->prepare("INSERT INTO student_information 
                                       (user_id, fullname, student_number, address, age, sex, civil_status, blood_type, father_name, course_year, cellphone_number, date, school_year, created_at, updated_at) 
                                       VALUES (?, ?, ?, '', 0, '', '', '', '', '', '', CURDATE(), ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt2->execute([$user_id, $fullname, $student_number, $current_school_year]);
            } else {
                $stmt2 = $pdo->prepare("INSERT INTO student_information 
                                       (fullname, student_number, address, age, sex, civil_status, blood_type, father_name, course_year, cellphone_number, date, school_year, created_at, updated_at) 
                                       VALUES (?, ?, '', 0, '', '', '', '', '', '', CURDATE(), ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt2->execute([$fullname, $student_number, $current_school_year]);
            }
        } catch (PDOException $e) {
            // Log the error but continue
            error_log("Student information insert failed: " . $e->getMessage());
            // Don't stop registration if this fails
        }

        // Set session variables
        $_SESSION['student_id'] = $user_id;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['student_number'] = $student_number;
        
        // Set role in session if column exists
        if ($role_column_exists) {
            $_SESSION['role'] = 'student';
        }

        $_SESSION['success'] = 'Account created successfully! You can now log in.';
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        header("Location: signup.php");
        exit();
    }
} else {
    header("Location: signup.php");
    exit();
}
?>