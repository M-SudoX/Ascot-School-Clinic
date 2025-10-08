<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Fetch student information
$student = [];
try {
    $student_stmt = $pdo->prepare("
        SELECT si.*, u.email 
        FROM student_information si 
        JOIN users u ON si.student_number = u.student_number 
        WHERE si.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Student fetch error: " . $e->getMessage());
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $symptoms = trim($_POST['symptoms'] ?? '');
        $temperature = trim($_POST['temperature'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $blood_pressure = trim($_POST['blood_pressure'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $heart_rate = trim($_POST['heart_rate'] ?? '');
        $attending_staff = trim($_POST['attending_staff'] ?? '');
        $consultation_date = trim($_POST['consultation_date'] ?? date('Y-m-d'));
        $physician_notes = trim($_POST['physician_notes'] ?? '');
        
        // Validate required fields
        if (empty($symptoms) || empty($diagnosis) || empty($attending_staff)) {
            throw new Exception("Please fill in all required fields: Symptoms, Diagnosis, and Attending Staff.");
        }
        
        if (!$student) {
            throw new Exception("Student information not found.");
        }
        
        // Insert consultation record
        $insert_stmt = $pdo->prepare("
            INSERT INTO consultations (
                student_number, symptoms, temperature, diagnosis, 
                blood_pressure, treatment, heart_rate, attending_staff, 
                consultation_date, physician_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insert_stmt->execute([
            $student['student_number'],
            $symptoms,
            $temperature,
            $diagnosis,
            $blood_pressure,
            $treatment,
            $heart_rate,
            $attending_staff,
            $consultation_date,
            $physician_notes
        ]);
        
        $success = "Consultation record saved successfully!";
        
        // Clear form after successful submission
        $_POST = array();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Consultation save error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Consultation - ASCOT Clinic</title>
    
    <!-- Bootstrap & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../admin/css/add_consultation.css" rel="stylesheet">
    
    <style>
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
        }
        .success-alert {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #28a745;
            animation: slideIn 0.5s ease-out;
        }
        .success-alert i {
            color: #28a745;
            margin-right: 0.5rem;
        }
        .success-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .success-btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .success-btn.primary {
            background: #28a745;
            color: white;
        }
        .success-btn.secondary {
            background: #6c757d;
            color: white;
        }
        .success-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../img/logo.png" alt="Logo" class="logo-img">
                </div>
                <div class="col">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Layout -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar position-relative">
                <nav class="nav flex-column">
                    <!-- Dashboard -->
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>

                    <!-- Student Management -->
                    <a class="nav-link d-flex justify-content-between align-items-center"
                       data-bs-toggle="collapse" href="#studentMenu" role="button"
                       aria-expanded="false" aria-controls="studentMenu">
                        <span><i class="fas fa-users"></i> Student Management</span>
                        <i class="fas fa-caret-down rotate-icon"></i>
                    </a>
                    <div class="collapse" id="studentMenu">
                        <ul class="list-unstyled ms-3">
                            <li><a href="students.php" class="nav-link">• Students</a></li>
                            <li><a href="search_students.php" class="nav-link">• Search Students</a></li>
                        </ul>
                    </div>

                    <!-- Consultation -->
                    <a class="nav-link d-flex justify-content-between align-items-center active"
                       data-bs-toggle="collapse" href="#consultationMenu" role="button"
                       aria-expanded="true" aria-controls="consultationMenu">
                        <span><i class="fas fa-notes-medical"></i> Consultation</span>
                        <i class="fas fa-caret-down rotate-icon"></i>
                    </a>
                    <div class="collapse show" id="consultationMenu">
                        <ul class="list-unstyled ms-3">
                            <li><a href="consultation_history.php" class="nav-link">• Consultation History</a></li>
                        </ul>
                    </div>

                    <!-- Admin Tools -->
                    <a class="nav-link" href="#">
                        <i class="fas fa-tools"></i> Admin Tools
                    </a>

                    <!-- Logout in Sidebar -->
                    <div class="logout-sidebar">
                        <a class="nav-link text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-plus-circle"></i> ADD NEW CONSULTATION</h1>
                    <div class="header-buttons">
                        <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Consultation History
                        </a>
                    </div>
                </div>

                <!-- Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="success-alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-lg"></i>
                            <div>
                                <h5 class="mb-1" style="color: #155724;">Success!</h5>
                                <p class="mb-2"><?php echo $success; ?></p>
                                <div class="success-actions">
                                    <a href="consultation_history.php?id=<?php echo $student_id; ?>" class="success-btn primary">
                                        <i class="fas fa-history"></i> View Consultation History
                                    </a>
                                    <a href="add_consultation.php?id=<?php echo $student_id; ?>" class="success-btn secondary">
                                        <i class="fas fa-plus"></i> Add Another Consultation
                                    </a>
                                    <a href="students.php" class="success-btn secondary">
                                        <i class="fas fa-users"></i> Back to Students
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Error:</strong> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($student): ?>
                    <!-- Student Information -->
                    <div class="student-info-card">
                        <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                        <div class="student-details">
                            <span>ID: <?php echo htmlspecialchars($student['student_number']); ?></span>
                            <span>Course: <?php echo htmlspecialchars($student['course_year']); ?></span>
                            <span>Age/Sex: <?php echo htmlspecialchars($student['age']); ?>/<?php echo htmlspecialchars($student['sex']); ?></span>
                        </div>
                    </div>

                    <!-- Consultation Form -->
                    <form method="POST" class="consultation-form" id="consultationForm">
                        <div class="form-container">
                            <!-- Medical Information Section -->
                            <div class="medical-info-section">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="symptoms" class="required-field">Symptoms:</label>
                                        <input type="text" id="symptoms" name="symptoms" class="form-control" 
                                               placeholder="Enter symptoms" 
                                               value="<?php echo htmlspecialchars($_POST['symptoms'] ?? ''); ?>" 
                                               required>
                                        <div class="error-message" id="symptomsError">Please enter symptoms</div>
                                    </div>
                                    <div class="form-group">
                                        <label for="temperature">Temperature:</label>
                                        <input type="text" id="temperature" name="temperature" class="form-control" 
                                               placeholder="e.g., 36.5°C" 
                                               value="<?php echo htmlspecialchars($_POST['temperature'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="diagnosis" class="required-field">Diagnosis:</label>
                                        <input type="text" id="diagnosis" name="diagnosis" class="form-control" 
                                               placeholder="Enter diagnosis" 
                                               value="<?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?>" 
                                               required>
                                        <div class="error-message" id="diagnosisError">Please enter diagnosis</div>
                                    </div>
                                    <div class="form-group">
                                        <label for="blood_pressure">Blood Pressure:</label>
                                        <input type="text" id="blood_pressure" name="blood_pressure" class="form-control" 
                                               placeholder="e.g., 120/80 mmHg" 
                                               value="<?php echo htmlspecialchars($_POST['blood_pressure'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="treatment">Treatment Given:</label>
                                        <input type="text" id="treatment" name="treatment" class="form-control" 
                                               placeholder="Enter treatment" 
                                               value="<?php echo htmlspecialchars($_POST['treatment'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="heart_rate">Heart Rate:</label>
                                        <input type="text" id="heart_rate" name="heart_rate" class="form-control" 
                                               placeholder="e.g., 72 bpm" 
                                               value="<?php echo htmlspecialchars($_POST['heart_rate'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="divider"></div>

                            <!-- Staff and Date Section -->
                            <div class="staff-date-section">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="attending_staff" class="required-field">Attending Staff:</label>
                                        <input type="text" id="attending_staff" name="attending_staff" class="form-control" 
                                               placeholder="Enter staff name" 
                                               value="<?php echo htmlspecialchars($_POST['attending_staff'] ?? ''); ?>" 
                                               required>
                                        <div class="error-message" id="staffError">Please enter attending staff name</div>
                                    </div>
                                    <div class="form-group">
                                        <label for="consultation_date" class="required-field">Consultation Date:</label>
                                        <input type="date" id="consultation_date" name="consultation_date" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['consultation_date'] ?? date('Y-m-d')); ?>" 
                                               required>
                                        <div class="error-message" id="dateError">Please select consultation date</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="divider"></div>

                            <!-- Physician's Notes -->
                            <div class="physician-notes-section">
                                <div class="form-group">
                                    <label for="physician_notes">Physician's notes:</label>
                                    <textarea id="physician_notes" name="physician_notes" class="form-control" rows="4" 
                                              placeholder="Enter additional notes..."><?php echo htmlspecialchars($_POST['physician_notes'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <div class="form-actions">
                                <button type="submit" class="save-btn" id="submitBtn">
                                    <i class="fas fa-save"></i> Save Consultation
                                </button>
                                <button type="reset" class="reset-btn">
                                    <i class="fas fa-redo"></i> Reset Form
                                </button>
                            </div>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h4>Student Not Found</h4>
                        <p>The requested student record could not be found.</p>
                        <a href="students.php" class="btn btn-primary">Back to Students</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('consultationForm');
            const submitBtn = document.getElementById('submitBtn');
            const requiredFields = form.querySelectorAll('[required]');
            
            // Real-time validation
            requiredFields.forEach(field => {
                field.addEventListener('input', function() {
                    validateField(this);
                });
                
                field.addEventListener('blur', function() {
                    validateField(this);
                });
            });
            
            function validateField(field) {
                const errorElement = document.getElementById(field.id + 'Error');
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    if (errorElement) errorElement.style.display = 'block';
                    return false;
                } else {
                    field.classList.remove('is-invalid');
                    if (errorElement) errorElement.style.display = 'none';
                    return true;
                }
            }
            
            // Form submission
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
                
                return true;
            });
            
            // Auto-format inputs
            const tempInput = document.getElementById('temperature');
            if (tempInput) {
                tempInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && !value.includes('°')) {
                        value = value.replace(/[CF]$/i, '').trim();
                        this.value = value + '°C';
                    }
                });
            }
            
            const bpInput = document.getElementById('blood_pressure');
            if (bpInput) {
                bpInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && !value.toLowerCase().includes('mmhg')) {
                        value = value.replace(/[^0-9\/]/g, '');
                        if (value) this.value = value + ' mmHg';
                    }
                });
            }
            
            const hrInput = document.getElementById('heart_rate');
            if (hrInput) {
                hrInput.addEventListener('blur', function() {
                    let value = this.value.trim();
                    if (value && !value.toLowerCase().includes('bpm')) {
                        value = value.replace(/\D/g, '');
                        if (value) this.value = value + ' bpm';
                    }
                });
            }

            // Auto-hide success message after 10 seconds
            const successAlert = document.querySelector('.success-alert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 10000);
            }
        });
    </script>
</body>
</html>