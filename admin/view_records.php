<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
require_once '../includes/db_connect.php';

// Fetch all consultation records from database
$records = [];
$search = $_GET['search'] ?? '';

try {
    // Build query with search
    $query = "
        SELECT c.*, si.fullname as student_name, si.student_number
        FROM consultations c 
        JOIN student_information si ON c.student_number = si.student_number 
    ";
    
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $query .= " WHERE (si.fullname LIKE ? OR c.diagnosis LIKE ? OR si.student_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY c.consultation_date DESC, c.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("View records error: " . $e->getMessage());
    $error = "Error loading records: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Records - ASCOT Clinic</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
    <link href="../admin/css/admin_dashboard.css" rel="stylesheet">
    <link href="../admin/css/view_records.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="studentMenu">
                        <a href="students.php" class="submenu-item">
                            <i class="fas fa-id-card"></i>
                            Students Profile
                        </a>
                        <a href="search_students.php" class="submenu-item">
                            <i class="fas fa-search"></i>
                            Search Students
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="consultationMenu">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultation</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="consultationMenu">
                        <a href="new_consultation.php" class="submenu-item">
                            <i class="fas fa-plus-circle"></i>
                            New Consultation
                        </a>
                        <a href="view_records.php" class="submenu-item active">
                            <i class="fas fa-folder-open"></i>
                            View Records
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="appointmentsMenu">
                        <i class="fas fa-calendar-check"></i>
                        <span>Appointments</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="appointmentsMenu">
                        <a href="calendar.php" class="submenu-item">
                            <i class="fas fa-calendar-alt"></i>
                            Calendar View
                        </a>
                        <a href="approvals.php" class="submenu-item">
                            <i class="fas fa-check-circle"></i>
                            Approvals
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="reportsMenu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="reportsMenu">
                        <a href="monthly_summary.php" class="submenu-item">
                            <i class="fas fa-file-invoice"></i>
                            Monthly Summary
                        </a>
                        <a href="health_trends.php" class="submenu-item">
                            <i class="fas fa-chart-line"></i>
                            Health Trends
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="adminMenu">
                        <a href="user_management.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            User Management
                        </a>
                        <a href="access_logs.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Access Logs
                        </a>
                    </div>
                </div>

                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-folder-open"></i> View Records</h1>
                <p>Manage and view all consultation records</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> Consultation record saved successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Text -->
            <div class="filter-text">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> 
                    Showing all consultation records. Use the search below to filter results.
                </small>
            </div>

            <!-- Search Section -->
            <form method="GET" class="search-section" id="searchForm">
                <div class="search-box">
                    <input type="text" name="search" id="searchInput" placeholder="Search by student name, diagnosis, or student ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="view_records.php" class="clear-btn">
                            <i class="fas fa-times"></i> Clear Search
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Records Count -->
            <div class="records-count">
                <span>Total Records: <strong><?php echo count($records); ?></strong></span>
                <?php if (!empty($search)): ?>
                    <span class="search-results">Search results for: "<strong><?php echo htmlspecialchars($search); ?></strong>"</span>
                <?php endif; ?>
            </div>

            <!-- Records Table -->
            <div class="records-table-container">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Diagnosis</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">
                                        <?php if (!empty($search)): ?>
                                            No consultation records found for "<?php echo htmlspecialchars($search); ?>"
                                        <?php else: ?>
                                            No consultation records found.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($search)): ?>
                                        <a href="view_records.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-list"></i> Show All Records
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($records as $record): ?>
                            <tr>
                                <td><?php echo $record['id']; ?></td>
                                <td><?php echo date('m-d-Y', strtotime($record['consultation_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($record['diagnosis']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn" data-id="<?php echo $record['id']; ?>" 
                                                data-bs-toggle="modal" data-bs-target="#recordModal"
                                                data-student="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                data-date="<?php echo date('F j, Y', strtotime($record['consultation_date'])); ?>"
                                                data-diagnosis="<?php echo htmlspecialchars($record['diagnosis']); ?>"
                                                data-symptoms="<?php echo htmlspecialchars($record['symptoms']); ?>"
                                                data-temperature="<?php echo htmlspecialchars($record['temperature']); ?>"
                                                data-blood-pressure="<?php echo htmlspecialchars($record['blood_pressure']); ?>"
                                                data-treatment="<?php echo htmlspecialchars($record['treatment']); ?>"
                                                data-heart-rate="<?php echo htmlspecialchars($record['heart_rate']); ?>"
                                                data-staff="<?php echo htmlspecialchars($record['attending_staff']); ?>"
                                                data-notes="<?php echo htmlspecialchars($record['physician_notes']); ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <a href="edit_consultation.php?id=<?php echo $record['id']; ?>" class="edit-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Record Details Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1" aria-labelledby="recordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="recordModalLabel">
                        <i class="fas fa-file-medical me-2"></i>Consultation Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Patient Information Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-user-injured me-2"></i>Patient Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Student Name:</span>
                                    <span class="detail-value" id="modalStudent">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Consultation Date:</span>
                                    <span class="detail-value" id="modalDate">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Assessment Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-stethoscope me-2"></i>Medical Assessment
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Symptoms:</span>
                                    <span class="detail-value" id="modalSymptoms">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label">Diagnosis:</span>
                                    <span class="detail-value" id="modalDiagnosis">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vital Signs Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-heartbeat me-2"></i>Vital Signs
                        </h6>
                        <div class="vital-signs-row">
                            <div class="vital-sign">
                                <div class="vital-icon">
                                    <i class="fas fa-thermometer-half"></i>
                                </div>
                                <div class="vital-info">
                                    <span class="vital-label">Temperature</span>
                                    <span class="vital-value" id="modalTemperature">-</span>
                                </div>
                            </div>
                            <div class="vital-sign">
                                <div class="vital-icon">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <div class="vital-info">
                                    <span class="vital-label">Blood Pressure</span>
                                    <span class="vital-value" id="modalBloodPressure">-</span>
                                </div>
                            </div>
                            <div class="vital-sign">
                                <div class="vital-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="vital-info">
                                    <span class="vital-label">Heart Rate</span>
                                    <span class="vital-value" id="modalHeartRate">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Treatment Section -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-pills me-2"></i>Treatment & Management
                        </h6>
                        <div class="detail-item">
                            <span class="detail-label">Treatment Given:</span>
                            <span class="detail-value" id="modalTreatment">-</span>
                        </div>
                    </div>

                    <!-- Staff Information -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-user-md me-2"></i>Staff Information
                        </h6>
                        <div class="detail-item">
                            <span class="detail-label">Attending Staff:</span>
                            <span class="detail-value" id="modalStaff">-</span>
                        </div>
                    </div>

                    <!-- Physician's Notes -->
                    <div class="detail-section">
                        <h6 class="section-title">
                            <i class="fas fa-notes-medical me-2"></i>Physician's Notes
                        </h6>
                        <div class="notes-container">
                            <div class="notes-content" id="modalNotes">
                                <span class="no-notes">No notes provided</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="printConsultationDetails()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dropdown toggle functionality
        document.querySelectorAll('.dropdown-btn').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const submenu = document.getElementById(targetId);
                const arrow = this.querySelector('.arrow');
                
                // Close other submenus
                document.querySelectorAll('.submenu').forEach(menu => {
                    if (menu.id !== targetId && menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                        if (otherBtn) {
                            otherBtn.querySelector('.arrow').classList.remove('rotate');
                        }
                    }
                });
                
                // Toggle current submenu
                submenu.classList.toggle('show');
                arrow.classList.toggle('rotate');
            });
        });

        // Record Modal functionality
        const recordModal = document.getElementById('recordModal');
        if (recordModal) {
            recordModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                // Patient Information
                document.getElementById('modalStudent').textContent = button.getAttribute('data-student') || 'Not specified';
                document.getElementById('modalDate').textContent = button.getAttribute('data-date') || 'Not specified';
                
                // Medical Assessment
                document.getElementById('modalSymptoms').textContent = button.getAttribute('data-symptoms') || 'Not specified';
                document.getElementById('modalDiagnosis').textContent = button.getAttribute('data-diagnosis') || 'Not specified';
                
                // Vital Signs
                document.getElementById('modalTemperature').textContent = button.getAttribute('data-temperature') || 'Not recorded';
                document.getElementById('modalBloodPressure').textContent = button.getAttribute('data-blood-pressure') || 'Not recorded';
                document.getElementById('modalHeartRate').textContent = button.getAttribute('data-heart-rate') || 'Not recorded';
                
                // Treatment
                document.getElementById('modalTreatment').textContent = button.getAttribute('data-treatment') || 'No treatment provided';
                
                // Staff Information
                document.getElementById('modalStaff').textContent = button.getAttribute('data-staff') || 'Not specified';
                
                // Physician's Notes
                const notes = button.getAttribute('data-notes');
                const notesElement = document.getElementById('modalNotes');
                if (notes && notes.trim() !== '') {
                    notesElement.innerHTML = `<p>${notes.replace(/\n/g, '<br>')}</p>`;
                    notesElement.classList.remove('no-notes');
                } else {
                    notesElement.innerHTML = '<span class="no-notes">No notes provided</span>';
                    notesElement.classList.add('no-notes');
                }
            });
        }

        // Print functionality
        function printConsultationDetails() {
            const modalContent = document.querySelector('.modal-content').cloneNode(true);
            const printWindow = window.open('', '_blank');
            
            // Remove buttons and add print styles
            const footer = modalContent.querySelector('.modal-footer');
            if (footer) footer.remove();
            
            const header = modalContent.querySelector('.modal-header');
            if (header) {
                header.classList.remove('bg-primary', 'text-white');
                header.classList.add('bg-light', 'text-dark');
            }
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Consultation Details - ASCOT Clinic</title>
                    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
                    <link href="../assets/webfonts/all.min.css" rel="stylesheet">
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px;
                            color: #333;
                        }
                        .detail-section { 
                            margin-bottom: 20px; 
                            border-bottom: 1px solid #eee; 
                            padding-bottom: 15px; 
                        }
                        .section-title { 
                            color: #2c3e50; 
                            font-weight: bold; 
                            margin-bottom: 10px;
                            font-size: 16px;
                        }
                        .detail-item { 
                            margin-bottom: 8px; 
                            display: flex;
                            justify-content: space-between;
                        }
                        .detail-label { 
                            font-weight: bold; 
                            color: #555;
                            min-width: 150px;
                        }
                        .detail-value { 
                            color: #333;
                            text-align: right;
                        }
                        .vital-sign { 
                            text-align: center; 
                            padding: 10px;
                            border: 1px solid #ddd;
                            border-radius: 5px;
                            margin: 5px;
                        }
                        .vital-icon { 
                            font-size: 20px; 
                            color: #3498db; 
                            margin-bottom: 5px; 
                        }
                        .vital-label { 
                            display: block; 
                            font-size: 12px; 
                            color: #666; 
                        }
                        .vital-value { 
                            display: block; 
                            font-size: 14px; 
                            font-weight: bold; 
                            color: #2c3e50; 
                        }
                        .notes-container { 
                            background: #f8f9fa; 
                            padding: 15px; 
                            border-radius: 5px;
                            border-left: 4px solid #3498db;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #3498db;
                            padding-bottom: 15px;
                        }
                        .print-header h3 {
                            color: #2c3e50;
                            margin-bottom: 5px;
                        }
                        .print-header p {
                            color: #666;
                            margin: 0;
                        }
                        @media print {
                            body { margin: 0; }
                            .detail-section { break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h3>ASCOT Clinic - Consultation Record</h3>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    ${modalContent.innerHTML}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }

        // Quick search with Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });

        // Auto-focus on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>