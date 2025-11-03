<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $consultation_id = $_POST['consultation_id'];
        $student_name = $_POST['student_name'];
        $address = $_POST['address'];
        $diagnosis = $_POST['diagnosis'];
        $recommendation = $_POST['recommendation'];
        $certificate_type = $_POST['certificate_type'];
        $date_issued = $_POST['date_issued'];
        
        // Additional fields for Laboratory Request Form
        $student_number = $_POST['student_number'] ?? '';
        $course_year = $_POST['course_year'] ?? '';
        $schedule = $_POST['schedule'] ?? '';
        $cellphone_number = $_POST['cellphone_number'] ?? '';
        $lab_tests = $_POST['lab_tests'] ?? [];
        $medical_officer = $_POST['medical_officer'] ?? '';

        // Get current college physician from database
        $physician_query = "SELECT name, title FROM college_physician WHERE id = 1 LIMIT 1";
        $physician_stmt = $pdo->query($physician_query);
        $college_physician = $physician_stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no physician found in database, use default
        if (!$college_physician) {
            $college_physician = [
                'name' => 'MARILYN R. GANTE, MD',
                'title' => 'College Physician'
            ];
        }

        // Store data in session for later use when printing
        $_SESSION['certificate_data'] = [
            'consultation_id' => $consultation_id,
            'student_name' => $student_name,
            'address' => $address,
            'diagnosis' => $diagnosis,
            'recommendation' => $recommendation,
            'certificate_type' => $certificate_type,
            'date_issued' => $date_issued,
            'student_number' => $student_number,
            'course_year' => $course_year,
            'schedule' => $schedule,
            'cellphone_number' => $cellphone_number,
            'lab_tests' => $lab_tests,
            'medical_officer' => $medical_officer,
            'college_physician' => $college_physician
        ];

        // Display certificate template WITHOUT saving to database yet
        displayCertificateTemplate($student_name, $address, $diagnosis, $recommendation, $certificate_type, $date_issued, 0, [
            'student_number' => $student_number,
            'course_year' => $course_year,
            'schedule' => $schedule,
            'cellphone_number' => $cellphone_number,
            'lab_tests' => $lab_tests,
            'medical_officer' => $medical_officer,
            'college_physician' => $college_physician
        ]);

    } catch (PDOException $e) {
        error_log("Certificate generation error: " . $e->getMessage());
        header("Location: view_records.php?error=certificate_failed");
        exit();
    }
} else {
    header("Location: view_records.php");
    exit();
}

function displayCertificateTemplate($student_name, $address, $diagnosis, $recommendation, $certificate_type, $date_issued, $certificate_id, $lab_data = []) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $certificate_type; ?> - ASCOT Clinic</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Times New Roman', Times, serif;
                background: white;
                padding: 0;
                margin: 0;
                line-height: 1.4;
                font-size: 12pt;
                min-height: 100vh;
            }
            
            .page {
                width: 21cm;
                height: 29.7cm;
                margin: 0 auto;
                padding: 1.2cm;
                position: relative;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                border: 1px solid #ddd;
            }
            
            /* UPDATED HEADER STYLE - WITH LOGO AND YELLOW STRIPE */
            .header-container {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
                position: relative;
                background: transparent;
                border: none;
                padding: 2px 0;
                border-radius: 0;
            }
            
            .yellow-stripe {
                width: 6px;
                height: 100%;
                background: #ffd700;
                position: absolute;
                left: 0;
                top: 0;
            }
            
            .logo-container {
                flex-shrink: 0;
                margin-right: 8px;
                padding-top: 0;
                margin-left: 25px; /* CHANGED: Increased significantly to move logo right */
                position: relative;
                left: 10px; /* CHANGED: Additional positioning */
            }
            
            .ascot-logo {
                width: 65px;
                height: 65px;
                object-fit: contain;
                margin-right: -8px; /* CHANGED: Reduced negative margin significantly */
                position: relative;
                left: 5px; /* CHANGED: Additional positioning */
            }
            
            .header-content {
                flex: 1;
                text-align: center;
                padding: 0 5px;
                margin-left: 10px; /* CHANGED: Added margin to push content right */
            }
            
            .republic {
                font-size: 11pt;
                font-weight: bold;
                margin-bottom: 1px;
                letter-spacing: 0.3px;
                color: #000;
            }
            
            .school-name {
                font-size: 12pt;
                font-weight: bold;
                margin: 1px 0;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                color: #000;
            }
            
            .clinic-name {
                font-size: 11pt;
                font-weight: bold;
                margin-bottom: 1px;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                color: #000;
            }
            
            .address {
                font-size: 9pt;
                margin-bottom: 0;
                font-style: italic;
                color: #333;
            }
            
            /* UPDATED CERTIFICATE TITLE - NO BACKGROUND */
            .certificate-title {
                text-align: center;
                font-size: 14pt;
                font-weight: bold;
                margin: 15px 0;
                text-transform: uppercase;
                text-decoration: underline;
                letter-spacing: 0.8px;
                color: #2c3e50;
                padding: 5px;
                background: transparent;
                border-radius: 0;
                box-shadow: none;
            }
            
            .form-content {
                margin: 15px 0;
                padding: 0 8px;
            }
            
            .form-row {
                display: flex;
                margin-bottom: 12px;
                align-items: flex-end;
            }
            
            .form-field {
                flex: 1;
                margin-right: 15px;
            }
            
            .form-field:last-child {
                margin-right: 0;
            }
            
            .field-label {
                font-weight: bold;
                font-size: 11pt;
                margin-bottom: 2px;
                display: block;
                color: #2c3e50;
            }
            
            .field-line {
                border-bottom: 1px solid #000;
                padding-bottom: 2px;
                min-height: 18px;
                background: white;
            }
            
            .step-section {
                margin: 25px 0;
                padding: 12px;
                background: white;
                border-radius: 4px;
                border-left: 3px solid #ffda6a;
            }
            
            .step-title {
                font-weight: bold;
                margin-bottom: 12px;
                font-size: 11pt;
                text-transform: uppercase;
                color: #2c3e50;
            }
            
            .lab-tests {
                margin-left: 15px;
            }
            
            .lab-test {
                margin: 8px 0;
                display: flex;
                align-items: center;
                padding: 3px 0;
            }
            
            .test-name {
                margin-right: 8px;
                font-size: 11pt;
                min-width: 180px;
                color: #2c3e50;
                font-weight: 500;
            }
            
            .test-line {
                border-bottom: 1px solid #000;
                flex: 1;
                margin-left: 4px;
                padding-bottom: 1px;
                background: white;
            }
            
            .form-footer {
                position: absolute;
                bottom: 1.2cm;
                left: 1.2cm;
                font-size: 8pt;
                color: #666;
                background: white;
                padding: 4px 8px;
                border-radius: 3px;
                border: 1px solid #ffda6a;
            }
            
            .print-controls {
                text-align: center;
                margin: 15px auto;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 4px;
                max-width: 21cm;
                border: 2px solid #ffda6a;
            }
            
            .print-btn, .close-btn {
                padding: 8px 16px;
                margin: 0 8px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 11pt;
                transition: all 0.3s ease;
            }
            
            .print-btn {
                background: #007bff;
                color: white;
            }
            
            .print-btn:hover {
                background: #0056b3;
                transform: translateY(-1px);
            }
            
            .close-btn {
                background: #6c757d;
                color: white;
            }
            
            .close-btn:hover {
                background: #545b62;
                transform: translateY(-1px);
            }
            
            /* Medical Certificate Specific Styles */
            .content {
                padding: 0 8px;
            }
            
            .date-section {
                margin-bottom: 12px;
                text-align: right;
                padding: 8px;
                background: white;
                border-radius: 4px;
            }
            
            .body-text {
                margin-bottom: 12px;
                text-align: justify;
                line-height: 1.5;
            }
            
            .section {
                margin-bottom: 12px;
                padding: 8px;
                background: white;
                border-radius: 4px;
            }
            
            .section-label {
                font-weight: bold;
                margin-bottom: 4px;
                display: block;
                color: #2c3e50;
            }
            
            .section-content {
                margin-left: 0;
                padding-left: 0;
            }
            
            .closing-statement {
                margin: 15px 0;
                text-align: justify;
                font-style: italic;
                padding: 8px;
                background: white;
                border-radius: 4px;
            }
            
            .signature-area {
                text-align: right;
                margin-top: 40px;
                padding: 15px;
                background: white;
                border-radius: 4px;
                margin-right: 100px;
            }
            
            .signature-name {
                font-weight: bold;
                font-size: 11pt;
                margin-top: 15px;
                text-decoration: underline;
                color: #2c3e50;
            }
            
            .signature-title {
                font-size: 9pt;
                margin-top: 2px;
                color: #666;
            }
            
            .form-number {
                position: absolute;
                bottom: 1.2cm;
                left: 1.2cm;
                font-size: 7pt;
                color: #666;
                background: white;
                padding: 3px 6px;
                border-radius: 2px;
                border: 1px solid #ffda6a;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                    margin: 0;
                    background: white !important;
                }
                
                .page {
                    width: 21cm;
                    height: 29.7cm;
                    margin: 0;
                    padding: 1.2cm;
                    box-shadow: none;
                    border: none;
                    background: white;
                }
                
                .print-controls {
                    display: none;
                }
                
                .header-container {
                    background: transparent !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .yellow-stripe {
                    background: #ffd700 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .logo-container {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .certificate-title {
                    background: transparent !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .step-section, .date-section, .section, .closing-statement, 
                .signature-area, .form-footer, .form-number {
                    background: white !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>
    <body>
        <?php if ($certificate_type === 'Laboratory Request Form'): ?>
            <!-- LABORATORY REQUEST FORM -->
            <div class="page">
                <!-- Header Section with Logo and Yellow Stripe -->
                <div class="header-container">
                    <div class="yellow-stripe"></div>
                    <div class="logo-container">
                        <img src="../img/logo.png" alt="ASCOT Logo" class="ascot-logo">
                    </div>
                    <div class="header-content">
                        <div class="republic">Republic of the Philippines</div>
                        <div class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</div>
                        <div class="clinic-name">HEALTH SERVICES UNIT</div>
                        <div class="address">Zabali, Baler, Aurora – Philippines</div>
                    </div>
                </div>

                <!-- Title -->
                <div class="certificate-title">
                    Laboratory Request Form
                </div>

                <!-- Form Content -->
                <div class="form-content">
                    <!-- First Row: FORM NO. and Student Number -->
                    <div class="form-row">
                        <div class="form-field" style="flex: 0.4;">
                            <div class="field-label">FORM NO.</div>
                            <div class="field-line"><?php echo str_pad($certificate_id, 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div class="form-field" style="flex: 0.6;">
                            <div class="field-label">Student Number:</div>
                            <div class="field-line"><?php echo htmlspecialchars($lab_data['student_number']); ?></div>
                        </div>
                    </div>

                    <!-- Second Row: NAME and COURSE & YEAR -->
                    <div class="form-row">
                        <div class="form-field" style="flex: 0.5;">
                            <div class="field-label">NAME:</div>
                            <div class="field-line"><?php echo htmlspecialchars($student_name); ?></div>
                        </div>
                        <div class="form-field" style="flex: 0.5;">
                            <div class="field-label">COURSE & YEAR:</div>
                            <div class="field-line"><?php echo htmlspecialchars($lab_data['course_year']); ?></div>
                        </div>
                    </div>

                    <!-- Third Row: SCHEDULE and Cellphone Number -->
                    <div class="form-row">
                        <div class="form-field" style="flex: 0.5;">
                            <div class="field-label">SCHEDULE:</div>
                            <div class="field-line"><?php echo htmlspecialchars($lab_data['schedule']); ?></div>
                        </div>
                        <div class="form-field" style="flex: 0.5;">
                            <div class="field-label">Cellphone Number:</div>
                            <div class="field-line"><?php echo htmlspecialchars($lab_data['cellphone_number']); ?></div>
                        </div>
                    </div>

                    <!-- Step Section -->
                    <div class="step-section">
                        <div class="step-title">STEP 1. Submission of Laboratory Specimen</div>
                        
                        <div class="lab-tests">
                            <div class="lab-test">
                                <span class="test-name">CBC</span>
                                <span class="test-line"></span>
                            </div>
                            <div class="lab-test">
                                <span class="test-name">CXR</span>
                                <span class="test-line"></span>
                            </div>
                            <div class="lab-test">
                                <span class="test-name">U/A</span>
                                <span class="test-line"></span>
                            </div>
                            <div class="lab-test">
                                <span class="test-name">PREGNANCY TEST (for female)</span>
                                <span class="test-line"></span>
                            </div>
                        </div>
                    </div>
                    <!-- Medical Examination Officer -->
                    <div class="form-row" style="margin-top: 30px;">
                        <div class="form-field" style="flex: 0.6;">
                            <div class="field-label">Medical Examination Officer:</div>
                            <div class="field-line"><?php echo htmlspecialchars($lab_data['medical_officer']); ?></div>
                        </div>
                    </div>
                </div>
                <!-- Footer -->
                <div class="form-footer">
                    HSU-F007<br>
                    Rev. 00 (21.10.2022)
                </div>
            </div>

        <?php else: ?>
            <!-- MEDICAL AND DENTAL CERTIFICATES -->
            <div class="page">
                <!-- Header Section with Logo and Yellow Stripe -->
                <div class="header-container">
                    <div class="yellow-stripe"></div>
                    <div class="logo-container">
                        <img src="../img/logo.png" alt="ASCOT Logo" class="ascot-logo">
                    </div>
                    <div class="header-content">
                        <div class="republic">Republic of the Philippines</div>
                        <div class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</div>
                        <div class="clinic-name">HEALTH SERVICES UNIT</div>
                        <div class="address">Zabali, Baler, Aurora – Philippines</div>
                    </div>
                </div>

                <!-- UPDATED CERTIFICATE TITLE - NO BACKGROUND -->
                <div class="certificate-title">
                    <?php echo htmlspecialchars($certificate_type); ?>
                </div>

                <div class="content">
                    <?php if ($certificate_type === 'Medical Certificate'): ?>
                        <!-- MEDICAL CERTIFICATE - HSU-F005 -->
                        <div class="date-section">
                            <strong>DATE:</strong> <?php echo date('M. j, Y', strtotime($date_issued)); ?>
                        </div>
                        
                        <div class="body-text">
                            This is to certify that <?php echo htmlspecialchars($student_name); ?>, a resident of <?php echo htmlspecialchars($address); ?>, Aurora was seen and examined at ASCOT Clinic on <?php echo date('F j, Y', strtotime($date_issued)); ?> with the following diagnosis: <?php echo htmlspecialchars($diagnosis); ?>
                        </div>
                        
                        <div style="min-height: 30px; margin: 8px 0;"></div>
                        
                        <div style="min-height: 15px; margin: 8px 0;"></div>
                        
                        <div class="section">
                            <div class="section-label">Recommendation:</div>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($recommendation)); ?>
                            </div>
                        </div>
                        
                        <div style="min-height: 30px; margin: 12px 0;"></div>
                        
                        <div class="closing-statement">
                            This certification is issued upon the request of the above-mentioned name for whatever purposes it may serve.
                        </div>
                        
                        <div style="min-height: 40px; margin: 15px 0;"></div>

                        <div class="signature-area">
                            <div class="signature-name"><?php echo htmlspecialchars($lab_data['college_physician']['name']); ?></div>
                            <div class="signature-title"><?php echo htmlspecialchars($lab_data['college_physician']['title']); ?></div>
                        </div>

                        <div class="form-number">
                            HSU-F005<br>
                            Rev. 00 (21.10.2022)
                        </div>

                    <?php elseif ($certificate_type === 'Dental Certificate'): ?>
                        <!-- DENTAL CERTIFICATE - HSU-F006 -->
                        <div class="date-section">
                            <strong>DATE:</strong> <?php echo date('F j, Y', strtotime($date_issued)); ?>
                        </div>
                        
                        <div class="body-text">
                            To whom it may concern,
                        </div>
                        
                        <div style="min-height: 8px; margin: 4px 0;"></div>
                        
                        <div class="body-text">
                            This is to certify that <?php echo htmlspecialchars($student_name); ?>, a resident of Barangay <?php echo htmlspecialchars($address); ?> visited the clinic for the following procedure:
                        </div>
                        
                        <div style="min-height: 20px; margin: 8px 0;"></div>
                        
                        <div class="body-text">
                            <?php echo nl2br(htmlspecialchars($diagnosis)); ?>
                        </div>
                        
                        <div style="min-height: 20px; margin: 8px 0;"></div>
                        
                        <div class="section">
                            <div class="section-label">Remarks/Recommendation:</div>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($recommendation)); ?>
                            </div>
                        </div>
                        
                        <div style="min-height: 30px; margin: 12px 0;"></div>
                        
                        <div class="closing-statement">
                            This certification is issued upon the request of the above-mentioned name for whatever purposes it may serve.
                        </div>
                        
                        <div style="min-height: 60px; margin: 20px 0;"></div>

                        <div class="signature-area">
                            <div class="signature-name" style="text-decoration: none;">_________________________</div>
                            <div class="signature-title">College Dentist</div>
                            <div class="signature-title">License No._________________________</div>
                        </div>

                        <div class="form-number">
                            HSU-F006<br>
                            Rev. 00 (21.10.2022)
                        </div>

                    <?php else: ?>
                        <!-- GENERIC CERTIFICATE -->
                        <div class="date-section">
                            <strong>DATE:</strong> <?php echo date('F j, Y', strtotime($date_issued)); ?>
                        </div>
                        
                        <div class="body-text">
                            This is to certify that <strong><?php echo htmlspecialchars($student_name); ?></strong>, a resident of <strong><?php echo htmlspecialchars($address); ?></strong>, Aurora was seen and examined at ASCOT Clinic.
                        </div>
                        
                        <div class="section">
                            <div class="section-label">Details:</div>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($diagnosis)); ?>
                            </div>
                        </div>
                        
                        <div class="section">
                            <div class="section-label">Recommendation:</div>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($recommendation)); ?>
                            </div>
                        </div>
                        
                        <div class="closing-statement">
                            This <?php echo strtolower(htmlspecialchars($certificate_type)); ?> is issued upon the request of the above-mentioned name for whatever purposes it may serve.
                        </div>

                        <div class="signature-area">
                            <div class="signature-name"><?php echo htmlspecialchars($lab_data['college_physician']['name']); ?></div>
                            <div class="signature-title"><?php echo htmlspecialchars($lab_data['college_physician']['title']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Print Controls -->
        <div class="print-controls">
            <button class="print-btn" onclick="saveAndPrint()">
                🖨️ Print Certificate
            </button>
            <button class="close-btn" onclick="window.close()">
                ❌ Close Window
            </button>
        </div>

        <script>
            function saveAndPrint() {
                // Send AJAX request to save certificate to database
                fetch('save_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'save_certificate': 'true'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // After saving, trigger print
                        window.print();
                    } else {
                        alert('Error saving certificate: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving certificate');
                });
            }

            window.onload = function() {
                // Remove auto-print since we only want to print after saving
                // setTimeout(function() {
                //     window.print();
                // }, 500);
            };
        </script>
    </body>
    </html>
    <?php
}
?>