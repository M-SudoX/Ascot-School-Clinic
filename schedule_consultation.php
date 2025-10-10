<?php
session_start();
require 'includes/db_connect.php';

// ✅ Temporary session (for testing)
if (!isset($_SESSION['student_id'])) {
    $_SESSION['student_id'] = 1;
}

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
        $stmt = $pdo->prepare("INSERT INTO consultation_requests (student_id, date, time, requested, notes, status)
                               VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$student_id, $date, $time, $concern, $notes]);
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
        $stmt = $pdo->prepare("UPDATE consultation_requests 
                               SET date = ?, time = ?, requested = ?, notes = ?
                               WHERE id = ? AND status IN ('Pending', 'Approved')");
        $stmt->execute([$date, $time, $concern, $notes, $id]);
        $_SESSION['success_message'] = 'Consultation updated successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ CANCEL CONSULTATION (DELETE RECORD)
================================= */
if (isset($_GET['cancel'])) {
    $id = $_GET['cancel'];
    try {
        $stmt = $pdo->prepare("DELETE FROM consultation_requests WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = 'Consultation cancelled and deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting consultation: ' . $e->getMessage();
    }

    header("Location: schedule_consultation.php");
    exit();
}

/* ===============================
   ✅ DISPLAY MESSAGES
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
$stmt = $pdo->prepare("SELECT * FROM consultation_requests WHERE student_id = ? ORDER BY date DESC");
$stmt->execute([$student_id]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Helper functions
function formatTime($time) { return date('g:i A', strtotime($time)); }
function formatDate($date) { return date('M d', strtotime($date)); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Consultation</title>

  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/webfonts/all.min.css" rel="stylesheet">
  <link href="assets/css/schedule_consultation.css" rel="stylesheet">
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
          <div class="college-info">
            <h4>Republic of the Philippines</h4>
            <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
            <p>ONLINE SCHOOL CLINIC</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar + Main -->
  <div class="main-container">
    <div class="sidebar">
      <nav class="nav-menu">
        <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
        <a class="nav-link active" href="schedule_consultation.php"><i class="fas fa-calendar-plus"></i> Schedule Consultation</a>
        <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
        <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
      </nav>
      <div class="nav-divider"></div>
      <a class="nav-link logout-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <div class="header-info-section"></div>

      <!-- Alerts -->
      <div id="alertContainer" class="alert-container">
        <?php if ($success_message): ?>
          <div class="alert alert-success custom-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php elseif ($error_message): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Consultation Form -->
      <div class="consultation-form-container">
        <form method="POST" action="">
          <input type="hidden" name="action" value="create">
          <div class="form-row">
            <div class="form-group">
              <label>Date:</label>
              <input type="date" name="date" class="form-control" min="<?= date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
              <label>Time:</label>
              <select name="time" class="form-control" required>
                <option value="">Select Time</option>
                <option value="08:00">8:00 AM</option>
                <option value="08:30">8:30 AM</option>
                <option value="09:00">9:00 AM</option>
                <option value="09:30">9:30 AM</option>
                <option value="10:00">10:00 AM</option>
                <option value="13:00">1:00 PM</option>
                <option value="14:00">2:00 PM</option>
                <option value="15:00">3:00 PM</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Reason/Concern:</label>
            <select name="concern" class="form-control" required>
              <option value="">Select Concern</option>
              <option value="Medicine">Medicine</option>
              <option value="Medical Clearance">Medical Clearance</option>
              <option value="General Consultation">General Consultation</option>
              <option value="First Aid">First Aid</option>
              <option value="Health Checkup">Health Checkup</option>
              <option value="Emergency">Emergency</option>
            </select>
          </div>
          <div class="form-group">
            <label>Optional Notes:</label>
            <textarea name="notes" class="form-control" rows="4"></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-request"><i class="fas fa-paper-plane"></i> REQUEST</button>
          </div>
        </form>
      </div>

      <!-- Consultation Table -->
      <div class="consultation-schedule">
        <h3 class="schedule-title">CONSULTATION SCHEDULE</h3>
        <div class="schedule-table-container">
          <table class="schedule-table">
            <thead>
              <tr><th>Date</th><th>Time</th><th>Concern</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($consultations)): ?>
                <tr><td colspan="5" class="text-center text-muted">No consultation requests yet.</td></tr>
              <?php else: ?>
                <?php foreach ($consultations as $c): ?>
                  <tr>
                    <td><?= formatDate($c['date']); ?></td>
                    <td><?= formatTime($c['time']); ?></td>
                    <td><?= htmlspecialchars($c['requested']); ?></td>
                    <td><span class="status-<?= strtolower($c['status']); ?>"><?= htmlspecialchars($c['status']); ?></span></td>
                    <td>
                      <button class="btn-action btn-view" onclick='viewConsultation(<?= json_encode($c); ?>)'><i class="fas fa-eye"></i></button>
                      <?php if ($c['status'] === 'Pending' || $c['status'] === 'Approved'): ?>
                        <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($c); ?>)'><i class="fas fa-edit"></i></button>
                      <?php endif; ?>
                      <?php if ($c['status'] === 'Pending'): ?>
                        <a href="?cancel=<?= $c['id']; ?>" class="btn-action btn-cancel" onclick="return confirm('Cancel and delete this consultation?')"><i class="fas fa-times"></i></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- View Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5>Consultation Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewBody"></div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="consultation_id" id="edit_consultation_id">
        <div class="modal-header">
          <h5>Edit Consultation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label>Date:</label>
          <input type="date" id="edit_date" name="edit_date" class="form-control" required>
          <label class="mt-2">Time:</label>
          <input type="time" id="edit_time" name="edit_time" class="form-control" required>
          <label class="mt-2">Concern:</label>
          <input type="text" id="edit_concern" name="edit_concern" class="form-control" required>
          <label class="mt-2">Notes:</label>
          <textarea id="edit_notes" name="edit_notes" class="form-control"></textarea>
        </div>
        <div class="modal-footer">
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
        <p><strong>Date:</strong> ${c.date}</p>
        <p><strong>Time:</strong> ${c.time}</p>
        <p><strong>Concern:</strong> ${c.requested}</p>
        <p><strong>Status:</strong> ${c.status}</p>
        ${c.notes ? `<p><strong>Notes:</strong> ${c.notes}</p>` : ''}
        <p><strong>Created:</strong> ${c.created_at}</p>`;
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

    // ✅ FIX: Always remove modal backdrop after closing
    ['viewModal', 'editModal'].forEach(id => {
      const el = document.getElementById(id);
      el.addEventListener('hidden.bs.modal', () => {
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
      });
    });
  </script>

  <style>
    .status-pending { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
    .status-approved { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
    .status-rejected { background: #f5c6cb; color: #842029; padding: 4px 8px; border-radius: 4px; font-size: 12px; }

    .modal-backdrop.show { opacity: 0 !important; display: none !important; }
  </style>
</body>
</html>
