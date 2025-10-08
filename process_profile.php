<?php
// process_profile.php

include("db_connect.php"); // <-- connection file mo

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect Part I data
    $fullname        = $_POST['fullname'];
    $address         = $_POST['address'];
    $age             = $_POST['age'];
    $gender          = $_POST['gender'];
    $civil_status    = $_POST['civil_status'];
    $blood_type      = $_POST['blood_type'];
    $parent_guardian = $_POST['parent_guardian'];
    $student_number  = $_POST['student_number'];
    $date            = $_POST['date'];
    $school_year     = $_POST['school_year'];
    $course_year     = $_POST['course_year'];
    $contact         = $_POST['contact'];

    // Collect Part II data
    $medical_attention = $_POST['medical_attention'] ?? '';
    $conditions        = isset($_POST['conditions']) ? implode(",", $_POST['conditions']) : '';
    $hospitalization   = $_POST['hospitalization'] ?? '';
    $hosp_year         = $_POST['hosp_year'];
    $surgery           = $_POST['surgery'] ?? '';
    $surgery_details   = $_POST['surgery_details'];
    $food_allergies    = $_POST['food_allergies'];
    $medicine_allergies= $_POST['medicine_allergies'];

    // Check if student record already exists
    $check_sql = "SELECT * FROM student_profile WHERE student_number = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $student_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // UPDATE if record exists
        $update_sql = "UPDATE student_profile SET 
            fullname=?, address=?, age=?, gender=?, civil_status=?, blood_type=?, 
            parent_guardian=?, date=?, school_year=?, course_year=?, contact=?,
            medical_attention=?, conditions=?, hospitalization=?, hosp_year=?, 
            surgery=?, surgery_details=?, food_allergies=?, medicine_allergies=?
            WHERE student_number=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssisssssssssssssssss",
            $fullname, $address, $age, $gender, $civil_status, $blood_type,
            $parent_guardian, $date, $school_year, $course_year, $contact,
            $medical_attention, $conditions, $hospitalization, $hosp_year,
            $surgery, $surgery_details, $food_allergies, $medicine_allergies,
            $student_number
        );
        if ($stmt->execute()) {
            echo "<script>alert('Profile updated successfully!'); window.location='update_profile.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    } else {
        // INSERT if new record
        $insert_sql = "INSERT INTO student_profile 
            (fullname, address, age, gender, civil_status, blood_type, parent_guardian, 
             student_number, date, school_year, course_year, contact, 
             medical_attention, conditions, hospitalization, hosp_year, surgery, 
             surgery_details, food_allergies, medicine_allergies) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssissssssssssssssss",
            $fullname, $address, $age, $gender, $civil_status, $blood_type,
            $parent_guardian, $student_number, $date, $school_year, $course_year, $contact,
            $medical_attention, $conditions, $hospitalization, $hosp_year, $surgery,
            $surgery_details, $food_allergies, $medicine_allergies
        );
        if ($stmt->execute()) {
            echo "<script>alert('Profile saved successfully!'); window.location='update_profile.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    $stmt->close();
    $conn->close();
}
?>
