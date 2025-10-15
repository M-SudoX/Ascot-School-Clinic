<?php
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

/* ===============================
   ✅ CREATE NEW CONSULTATION
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $student_id = $_SESSION['student_id'];
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $concern = $_POST['concern'] ?? '';
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO consultation_requests (student_id, date, time, requested, notes, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$student_id, $date, $time, $concern, $notes]);
        
        // ✅ SPECIFIC ACTION: Consultation Scheduled
        logActivity($pdo, $student_id, "Scheduled consultation: " . $concern);
        
        $_SESSION['success_message'] = 'Your consultation request has been submitted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database Error: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ EDIT CONSULTATION
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['consultation_id'];
    $date = $_POST['edit_date'];
    $time = $_POST['edit_time'];
    $concern = $_POST['edit_concern'];
    $notes = $_POST['edit_notes'];

    try {
        $stmt = $pdo->prepare("UPDATE consultation_requests SET date = ?, time = ?, requested = ?, notes = ? WHERE id = ? AND status IN ('Pending', 'Approved')");
        $stmt->execute([$date, $time, $concern, $notes, $id]);
        
        // ✅ SPECIFIC ACTION: Consultation Edited
        logActivity($pdo, $student_id, "Edited consultation: " . $concern);
        
        $_SESSION['success_message'] = 'Consultation updated successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ CANCEL CONSULTATION
================================= */
if (isset($_GET['cancel'])) {
    $id = $_GET['cancel'];
    try {
        // Kunin muna ang consultation details bago i-delete
        $get_stmt = $pdo->prepare("SELECT requested FROM consultation_requests WHERE id = ?");
        $get_stmt->execute([$id]);
        $consultation = $get_stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM consultation_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        
        // ✅ SPECIFIC ACTION: Consultation Cancelled
        if ($consultation) {
            logActivity($pdo, $student_id, "Cancelled consultation: " . $consultation['requested']);
        }
        
        $_SESSION['success_message'] = 'Consultation cancelled and deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

// ... (REST OF YOUR EXISTING SCHEDULE_CONSULTATION CODE) ...

/* ===============================
   ✅ CANCEL CONSULTATION (DELETE RECORD)
================================= */
if (isset($_GET['cancel'])) {
    $id = $_GET['cancel'];
    try {
        $stmt = $pdo->prepare("DELETE FROM consultation_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        
        // ✅ I-LOG ANG PAG-CANCEL NG CONSULTATION
        logActivity($pdo, $student_id, "Cancelled consultation request");
        
        $_SESSION['success_message'] = 'Consultation cancelled and deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ DISPLAY MESSAGES - FIXED VARIABLES
================================= */
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

/* ===============================
   ✅ FETCH CONSULTATIONS
================================= */
$student_id = $_SESSION['student_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM consultation_requests WHERE student_id = ? ORDER BY date DESC");
    $stmt->execute([$student_id]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $consultations = [];
    $error_message = "Error fetching consultations: " . $e->getMessage();
}

// ✅ Helper functions
function formatTime($time) { 
    return date('g:i A', strtotime($time)); 
}

function formatDate($date) { 
    return date('M d', strtotime($date)); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule Consultation - ASCOT Online School Clinic</title>

  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/schedule_consultation.css" rel="stylesheet">
  
  <style>
    .status-pending { 
        background: #fff3cd; 
        color: #856404; 
        padding: 4px 8px; 
        border-radius: 4px; 
        font-size: 12px; 
        font-weight: bold;
    }
    .status-approved { 
        background: #d4edda; 
        color: #155724; 
        padding: 4px 8px; 
        border-radius: 4px; 
        font-size: 12px; 
        font-weight: bold;
    }
    .status-rejected { 
        background: #f5c6cb; 
        color: #842029; 
        padding: 4px 8px; 
        border-radius: 4px; 
        font-size: 12px; 
        font-weight: bold;
    }
    .status-completed { 
        background: #cce7ff; 
        color: #004085; 
        padding: 4px 8px; 
        border-radius: 4px; 
        font-size: 12px; 
        font-weight: bold;
    }

    .modal-backdrop.show { 
        opacity: 0.5 !important; 
    }
    
    .btn-action {
        border: none;
        background: none;
        padding: 5px;
        margin: 0 2px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    
    .btn-view { color: #17a2b8; }
    .btn-edit { color: #ffc107; }
    .btn-cancel { color: #dc3545; }
    
    .btn-action:hover {
        transform: scale(1.1);
        background: #f8f9fa;
    }
    
    .consultation-form-container {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .consultation-schedule {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .schedule-title {
        color: #333;
        border-bottom: 2px solid #ffda6a;
        padding-bottom: 10px;
        margin-bottom: 20px;
        font-weight: bold;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-auto">
          <div class="logo">
            <img src="img/logo.png" alt="Aurora State College of Technology Logo" class="logo-img">
          </div>
        </div>
        <div class="col">
          <div class="college-info text-center">
            <h4>Republic of the Philippines</h4>
            <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
            <p>ONLINE SCHOOL CLINIC</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar + Main -->
  <div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR NAVIGATION -->
        <div class="col-md-3 col-lg-2 sidebar">
            <nav class="nav flex-column">
                <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                <a class="nav-link active" href="schedule_consultation.php"><i class="fas fa-calendar-plus"></i> Schedule Consultation</a>
                <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                <a class="nav-link" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
            </nav>
            <div class="logout-btn mt-3">
                <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="header-info-section">
                <h3>Schedule Consultation</h3>
                <p>Book your medical consultation with the school clinic</p>
            </div>

            <!-- Alerts - FIXED: Using properly initialized variables -->
            <div id="alertContainer" class="alert-container">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong> <?= htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> <?= htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Consultation Form -->
            <div class="consultation-form-container">
                <h4 class="mb-4">New Consultation Request</h4>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><strong>Date:</strong></label>
                                <input type="date" name="date" class="form-control" 
                                       min="<?= date('Y-m-d'); ?>" 
                                       value="<?= date('Y-m-d'); ?>" 
                                       required>
                                <small class="form-text text-muted">Select your preferred date</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><strong>Time:</strong></label>
                                <select name="time" class="form-control" required>
                                    <option value="">Select Time</option>
                                    <option value="08:00">8:00 AM</option>
                                    <option value="08:30">8:30 AM</option>
                                    <option value="09:00">9:00 AM</option>
                                    <option value="09:30">9:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="13:00">1:00 PM</option>
                                    <option value="13:30">1:30 PM</option>
                                    <option value="14:00">2:00 PM</option>
                                    <option value="14:30">2:30 PM</option>
                                    <option value="15:00">3:00 PM</option>
                                    <option value="15:30">3:30 PM</option>
                                </select>
                                <small class="form-text text-muted">Choose your preferred time</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label"><strong>Reason/Concern:</strong></label>
                        <select name="concern" class="form-control" required>
                            <option value="">Select Concern</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Medical Clearance">Medical Clearance</option>
                            <option value="General Consultation">General Consultation</option>
                            <option value="First Aid">First Aid</option>
                            <option value="Health Checkup">Health Checkup</option>
                            <option value="Emergency">Emergency</option>
                            <option value="Dental Checkup">Dental Checkup</option>
                            <option value="Mental Health Consultation">Mental Health Consultation</option>
                            <option value="Vaccination">Vaccination</option>
                            <option value="Other">Other</option>
                        </select>
                        <small class="form-text text-muted">What is the reason for your consultation?</small>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label"><strong>Additional Notes (Optional):</strong></label>
                        <textarea name="notes" class="form-control" rows="4" 
                                  placeholder="Please provide any additional information about your condition or concerns..."></textarea>
                        <small class="form-text text-muted">Any details that might help the medical staff</small>
                    </div>
                    
                    <div class="form-actions text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> SUBMIT CONSULTATION REQUEST
                        </button>
                    </div>
                </form>
            </div>

            <!-- Consultation Table -->
            <div class="consultation-schedule">
                <h3 class="schedule-title">YOUR CONSULTATION SCHEDULE</h3>
                
                <?php if (empty($consultations)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No consultation requests yet</h5>
                        <p class="text-muted">Schedule your first consultation using the form above.</p>
                    </div>
                <?php else: ?>
                    <div class="schedule-table-container">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Concern</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars(formatDate($c['date'])); ?></strong></td>
                                        <td><?= htmlspecialchars(formatTime($c['time'])); ?></td>
                                        <td><?= htmlspecialchars($c['requested']); ?></td>
                                        <td>
                                            <span class="status-<?= strtolower($c['status']); ?>">
                                                <?= htmlspecialchars($c['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-action btn-view" 
                                                    onclick='viewConsultation(<?= json_encode($c); ?>)'
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($c['status'] === 'Pending' || $c['status'] === 'Approved'): ?>
                                                <button class="btn-action btn-edit" 
                                                        onclick='openEditModal(<?= json_encode($c); ?>)'
                                                        title="Edit Consultation">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($c['status'] === 'Pending'): ?>
                                                <a href="?cancel=<?= $c['id']; ?>" 
                                                   class="btn-action btn-cancel" 
                                                   onclick="return confirm('Are you sure you want to cancel this consultation?')"
                                                   title="Cancel Consultation">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>

  <!-- View Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Consultation Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewBody">
            <!-- Content will be loaded by JavaScript -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="consultation_id" id="edit_consultation_id">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Edit Consultation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><strong>Date:</strong></label>
            <input type="date" id="edit_date" name="edit_date" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><strong>Time:</strong></label>
            <input type="time" id="edit_time" name="edit_time" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><strong>Concern:</strong></label>
            <input type="text" id="edit_concern" name="edit_concern" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><strong>Notes:</strong></label>
            <textarea id="edit_notes" name="edit_notes" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));

    function viewConsultation(c) {
      const body = document.getElementById('viewBody');
      body.innerHTML = `
        <div class="consultation-details">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Date:</strong><br>${c.date}</p>
                    <p><strong>Time:</strong><br>${c.time}</p>
                    <p><strong>Status:</strong><br><span class="status-${c.status.toLowerCase()}">${c.status}</span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Concern:</strong><br>${c.requested}</p>
                    <p><strong>Created:</strong><br>${c.created_at}</p>
                </div>
            </div>
            ${c.notes ? `
            <div class="row mt-3">
                <div class="col-12">
                    <p><strong>Additional Notes:</strong></p>
                    <div class="alert alert-info">${c.notes}</div>
                </div>
            </div>` : ''}
        </div>
      `;
      viewModal.show();
    }

    function openEditModal(c) {
      document.getElementById('edit_consultation_id').value = c.id;
      document.getElementById('edit_date').value = c.date;
      document.getElementById('edit_time').value = c.time;
      document.getElementById('edit_concern').value = c.requested;
      document.getElementById('edit_notes').value = c.notes || '';
      editModal.show();
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
          }
        }, 5000);
      });
    });
  </script>
</body>
</html>