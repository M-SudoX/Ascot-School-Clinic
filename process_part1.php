<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['student_number'])) {
    header("Location: student_login.php");
    exit();
}
$student_number = $_SESSION['student_number'];

    // This is the efficient and secure code that saves student information in the database

// sanitize / get posted values
$fullname         = trim($_POST['fullname'] ?? '');
$address          = trim($_POST['address'] ?? '');
$age              = trim($_POST['age'] ?? '');
$sex              = trim($_POST['sex'] ?? '');
$civil_status     = trim($_POST['civil_status'] ?? '');
$blood_type       = trim($_POST['blood_type'] ?? '');
$father_name      = trim($_POST['father_name'] ?? '');
$date             = trim($_POST['date'] ?? '');
$school_year      = trim($_POST['school_year'] ?? '');
$course_year      = trim($_POST['course_year'] ?? '');
$cellphone_number = trim($_POST['cellphone_number'] ?? '');

// check if row exists
$check = $pdo->prepare("SELECT id FROM student_information WHERE student_number = :student_number LIMIT 1");
$check->execute(['student_number' => $student_number]);
$exists = $check->fetch(PDO::FETCH_ASSOC);

if ($exists) {
    $update = $pdo->prepare("UPDATE student_information SET
        fullname = :fullname, address = :address, age = :age, sex = :sex,
        civil_status = :civil_status, blood_type = :blood_type, father_name = :father_name,
        date = :date, school_year = :school_year, course_year = :course_year, cellphone_number = :cellphone_number
        WHERE student_number = :student_number");
    $update->execute([
        'fullname' => $fullname,
        'address' => $address,
        'age' => $age,
        'sex' => $sex,
        'civil_status' => $civil_status,
        'blood_type' => $blood_type,
        'father_name' => $father_name,
        'date' => $date,
        'school_year' => $school_year,
        'course_year' => $course_year,
        'cellphone_number' => $cellphone_number,
        'student_number' => $student_number
    ]);
} else {
    $insert = $pdo->prepare("INSERT INTO student_information
      (student_number, fullname, address, age, sex, civil_status, blood_type, father_name, date, school_year, course_year, cellphone_number)
      VALUES (:student_number, :fullname, :address, :age, :sex, :civil_status, :blood_type, :father_name, :date, :school_year, :course_year, :cellphone_number)");
    $insert->execute([
        'student_number' => $student_number,
        'fullname' => $fullname,
        'address' => $address,
        'age' => $age,
        'sex' => $sex,
        'civil_status' => $civil_status,
        'blood_type' => $blood_type,
        'father_name' => $father_name,
        'date' => $date,
        'school_year' => $school_year,
        'course_year' => $course_year,
        'cellphone_number' => $cellphone_number
    ]);
}

// update session values used in dashboard (optional)
$_SESSION['fullname'] = $fullname;
$_SESSION['course_year'] = $course_year;
$_SESSION['cellphone_number'] = $cellphone_number;

// go back to dashboard (or update_profile.php if you prefer)
header("Location: student_dashboard.php?success=1");
exit();
