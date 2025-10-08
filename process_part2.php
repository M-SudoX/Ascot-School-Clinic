<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['student_number'])) {
    header("Location: student_login.php");
    exit();
}
$student_number = $_SESSION['student_number'];

// collect posted values
$medical_attention  = trim($_POST['medical_attention'] ?? '');
$conditions_posted   = $_POST['conditions'] ?? null; // may be null if none checked
$hospitalization     = trim($_POST['hospitalization'] ?? '');
$hosp_year           = trim($_POST['hosp_year'] ?? '');
$surgery             = trim($_POST['surgery'] ?? '');
$surgery_details     = trim($_POST['surgery_details'] ?? '');
$food_allergies      = trim($_POST['food_allergies'] ?? '');
$medicine_allergies  = trim($_POST['medicine_allergies'] ?? '');

// fetch existing record so we can keep conditions if user didn't check anything
$check = $pdo->prepare("SELECT * FROM medical_history WHERE student_number = :student_number LIMIT 1");
$check->execute(['student_number' => $student_number]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

// decide conditions string
if (is_array($conditions_posted)) {
    $conditions = implode(',', $conditions_posted);
} else {
    // keep existing if no new selection and row exists, otherwise empty
    $conditions = $existing['conditions'] ?? '';
}

if ($existing) {
    $update = $pdo->prepare("UPDATE medical_history SET
        medical_attention = :medical_attention,
        conditions = :conditions,
        hospitalization = :hospitalization,
        hosp_year = :hosp_year,
        surgery = :surgery,
        surgery_details = :surgery_details,
        food_allergies = :food_allergies,
        medicine_allergies = :medicine_allergies
        WHERE student_number = :student_number");
    $update->execute([
        'medical_attention' => $medical_attention,
        'conditions' => $conditions,
        'hospitalization' => $hospitalization,
        'hosp_year' => $hosp_year,
        'surgery' => $surgery,
        'surgery_details' => $surgery_details,
        'food_allergies' => $food_allergies,
        'medicine_allergies' => $medicine_allergies,
        'student_number' => $student_number
    ]);
} else {
    $insert = $pdo->prepare("INSERT INTO medical_history
      (student_number, medical_attention, conditions, hospitalization, hosp_year, surgery, surgery_details, food_allergies, medicine_allergies)
      VALUES (:student_number, :medical_attention, :conditions, :hospitalization, :hosp_year, :surgery, :surgery_details, :food_allergies, :medicine_allergies)");
    $insert->execute([
        'student_number' => $student_number,
        'medical_attention' => $medical_attention,
        'conditions' => $conditions,
        'hospitalization' => $hospitalization,
        'hosp_year' => $hosp_year,
        'surgery' => $surgery,
        'surgery_details' => $surgery_details,
        'food_allergies' => $food_allergies,
        'medicine_allergies' => $medicine_allergies
    ]);
}

header("Location: student_dashboard.php?success=2");
exit();
