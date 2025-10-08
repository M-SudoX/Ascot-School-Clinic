<?php
session_start();

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $concern = $_POST['concern'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // For demo purposes, we'll use session to store consultations
    // In real application, you'd save to database
    if (!isset($_SESSION['consultations'])) {
        $_SESSION['consultations'] = [];
    }
    
    // Generate simple ID
    $id = count($_SESSION['consultations']) + 1;
    
    // Add new consultation request
    $_SESSION['consultations'][] = [
        'id' => $id,
        'date' => $date,
        'time' => $time,
        'concern' => $concern,
        'notes' => $notes,
        'status' => 'Pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $success_message = 'Your consultation request has been submitted successfully!';
    
    // Optional: Database saving code (uncomment if you have database)
    /*
    try {
        $conn = new mysqli("localhost", "username", "password", "database_name");
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $stmt = $conn->prepare("INSERT INTO consultation_requests (student_id, date, time, concern, notes, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $student_id = $_SESSION['student_id'] ?? 1; // Replace with actual student ID
        $stmt->bind_param("issss", $student_id, $date, $time, $concern, $notes);
        
        if ($stmt->execute()) {
            $success_message = 'Your consultation request has been saved to database successfully!';
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $error_message = 'Database Error: ' . $e->getMessage();
    }
    */
}

// Get existing consultations
$consultations = $_SESSION['consultations'] ?? [];

// Format time for display
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Format date for display
function formatDate($date) {
    return date('M d', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Consultation</title>

  <!-- Bootstrap 5 (offline) -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (offline icons) -->
  <link href="assets/webfonts/all.min.css" rel="stylesheet">

  <!-- Custom CSS para sa consultation page -->
  <link href="assets/css/schedule_consultation.css" rel="stylesheet">
</head>
<body>
  <!-- Header section (logo at school info) -->
  <div class="header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <!-- Logo -->
        <div class="col-auto">
          <div class="logo">
            <img src="img/logo.png" alt="Aurora State College of Technology Logo" class="logo-img">
          </div>
        </div>
        <!-- School Information -->
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

  <!-- Main Container (sidebar + content) -->
  <div class="main-container">
    <!-- Sidebar Menu -->
    <div class="sidebar">
      <nav class="nav-menu">
        <!-- Dashboard link -->
        <a class="nav-link" href="student_dashboard.php" id="dashboard-link">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
        <!-- Update Profile -->
        <a class="nav-link" href="update_profile.php" id="profile-link">
          <i class="fas fa-user-edit"></i>
          <span>Update Profile</span>
        </a>
        <!-- Schedule Consultation -->
        <a class="nav-link active" href="schedule_consultation.php">
          <i class="fas fa-calendar-plus"></i>
          <span>Schedule Consultation</span>
        </a>
        <div class="nav-divider"></div>
        <!-- Logout -->
        <a class="nav-link logout-link" href="logout.php">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </nav>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
      <!-- Content Header (mini logo + school info) -->
      <div class="content-header">
        <div class="header-info-section">
          <div class="content-logo">
            <img src="img/logo.png" alt="Aurora State College Logo"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <i class="fas fa-university" style="display: none;"></i>
          </div>
          <div class="content-header-text">
            <h4 class="republic-small">Republic of the Philippines</h4>
            <h2 class="college-name-small">AURORA STATE COLLEGE OF TECHNOLOGY</h2>
            <p class="location-text">Zabali, Baler, Aurora</p>
          </div>
        </div>
      </div>

      <!-- Alert messages (Success/Error) -->
      <div id="alertContainer" class="alert-container">
        <?php if ($success_message): ?>
          <div class="alert alert-success custom-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Consultation Form -->
      <div class="consultation-form-container">
        <form id="consultationForm" method="POST" action="schedule_consultation.php">
          <!-- Date & Time row -->
          <div class="form-row">
            <!-- Consultation Date -->
            <div class="form-group">
              <label for="consultationDate">Date:</label>
              <input type="date" id="consultationDate" name="date" class="form-control" 
                     min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <!-- Consultation Time -->
            <div class="form-group">
              <label for="consultationTime">Time:</label>
              <select id="consultationTime" name="time" class="form-control" required>
                <option value="">Select Time</option>
                <!-- Listahan ng oras -->
                <option value="08:00">8:00 AM</option>
                <option value="08:30">8:30 AM</option>
                <option value="09:00">9:00 AM</option>
                <option value="09:30">9:30 AM</option>
                <option value="10:00">10:00 AM</option>
                <option value="10:30">10:30 AM</option>
                <option value="11:00">11:00 AM</option>
                <option value="13:00">1:00 PM</option>
                <option value="13:30">1:30 PM</option>
                <option value="14:00">2:00 PM</option>
                <option value="14:30">2:30 PM</option>
                <option value="15:00">3:00 PM</option>
                <option value="15:30">3:30 PM</option>
                <option value="16:00">4:00 PM</option>
              </select>
            </div>
          </div>

          <!-- Concern -->
          <div class="form-group">
            <label for="reasonConcern">Reason/Concern:</label>
            <select id="reasonConcern" name="concern" class="form-control" required>
              <option value="">Select Concern</option>
              <option value="Medicine">Medicine</option>
              <option value="Medical Clearance">Medical Clearance</option>
              <option value="General Consultation">General Consultation</option>
              <option value="First Aid">First Aid</option>
              <option value="Health Checkup">Health Checkup</option>
              <option value="Emergency">Emergency</option>
            </select>
          </div>

          <!-- Optional Notes -->
          <div class="form-group">
            <label for="optionalNote">Optional notes:</label>
            <textarea id="optionalNote" name="notes" class="form-control" rows="4"
                      placeholder="Please describe your concern in detail..."></textarea>
          </div>

          <!-- Submit Button -->
          <div class="form-actions">
            <button type="submit" class="btn-request">
              <i class="fas fa-paper-plane"></i> REQUEST
            </button>
          </div>
        </form>
      </div>

      <!-- Consultation Schedule Table -->
      <div class="consultation-schedule">
        <h3 class="schedule-title">CONSULTATION SCHEDULE</h3>
        <div class="schedule-table-container">
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Concern</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="scheduleTableBody">
              <?php if (empty($consultations)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">No consultation requests yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($consultations as $consultation): ?>
                  <tr>
                    <td><?php echo formatDate($consultation['date']); ?></td>
                    <td><?php echo formatTime($consultation['time']); ?></td>
                    <td><?php echo htmlspecialchars($consultation['concern']); ?></td>
                    <td>
                      <span class="status-<?php echo strtolower($consultation['status']); ?>">
                        <?php echo $consultation['status']; ?>
                      </span>
                    </td>
                    <td>
                      <button class="btn-action btn-view" onclick="viewConsultation(<?php echo $consultation['id']; ?>)">
                        <i class="fas fa-eye"></i>
                      </button>
                      <?php if ($consultation['status'] === 'Pending'): ?>
                        <button class="btn-action btn-edit" onclick="editConsultation(<?php echo $consultation['id']; ?>)">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-cancel" onclick="cancelConsultation(<?php echo $consultation['id']; ?>)">
                          <i class="fas fa-times"></i>
                        </button>
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

  <!-- Modal para sa Consultation Details -->
  <div id="consultationModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4>Consultation Details</h4>
        <span class="close" onclick="closeModal()">&times;</span>
      </div>
      <div class="modal-body" id="modalBody"></div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- Mobile Menu Toggle (para sa small screens) -->
  <div class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
  </div>
  <div class="sidebar-overlay" onclick="closeSidebar()"></div>

  <!-- Bootstrap Bundle JS (offline) -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>

  <script>
    // Consultation data para sa JavaScript
    const consultations = <?php echo json_encode($consultations); ?>;

    // Function para ipakita ang consultation details sa modal
    function viewConsultation(id) {
      const consultation = consultations.find(c => c.id == id);
      if (!consultation) return;
      
      const modal = document.getElementById('consultationModal');
      const modalBody = document.getElementById('modalBody');
      
      modalBody.innerHTML = `
        <div class="consultation-details">
          <div class="detail-row">
            <strong>Date:</strong> ${new Date(consultation.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
          </div>
          <div class="detail-row">
            <strong>Time:</strong> ${formatTimeJS(consultation.time)}
          </div>
          <div class="detail-row">
            <strong>Concern:</strong> ${consultation.concern}
          </div>
          <div class="detail-row">
            <strong>Status:</strong> <span class="status-${consultation.status.toLowerCase()}">${consultation.status}</span>
          </div>
          ${consultation.notes ? `<div class="detail-row"><strong>Notes:</strong> ${consultation.notes}</div>` : ''}
          <div class="detail-row">
            <strong>Requested:</strong> ${new Date(consultation.created_at).toLocaleDateString()}
          </div>
        </div>
      `;
      modal.style.display = 'block';
    }

    // Helper function para sa time formatting
    function formatTimeJS(time) {
      return new Date('2000-01-01 ' + time).toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
      });
    }

    // Placeholder para sa edit functionality
    function editConsultation(id) {
      alert('Edit consultation functionality will be implemented soon.');
    }

    // Cancel consultation (with confirmation)
    function cancelConsultation(id) {
      if (confirm('Are you sure you want to cancel this consultation?')) {
        // Here you would make an AJAX call to cancel the consultation
        alert('Cancel functionality will be implemented soon.');
      }
    }

    // Close modal
    function closeModal() {
      document.getElementById('consultationModal').style.display = 'none';
    }

    // Show/Hide sidebar sa mobile view
    function toggleSidebar() {
      document.querySelector('.sidebar').classList.toggle('show');
      document.querySelector('.sidebar-overlay').classList.toggle('show');
    }

    function closeSidebar() {
      document.querySelector('.sidebar').classList.remove('show');
      document.querySelector('.sidebar-overlay').classList.remove('show');
    }

    // Auto close modal kapag nag-click sa labas
    window.onclick = function(event) {
      const modal = document.getElementById('consultationModal');
      if (event.target == modal) modal.style.display = 'none';
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        if (alert.classList.contains('show')) {
          alert.classList.remove('show');
          setTimeout(() => alert.remove(), 150);
        }
      });
    }, 5000);
  </script>

  <style>
    /* Custom Success Alert Color */
    .custom-success {
      background-color: #007bff !important; /* Blue background */
      color: #fff !important;              /* White text */
      border: 1px solid #0056b3 !important; /* Darker blue border */
    }

    /* Additional CSS for consultation details modal */
    .consultation-details .detail-row {
      margin-bottom: 10px;
      padding: 8px 0;
      border-bottom: 1px solid #eee;
    }
    
    .consultation-details .detail-row:last-child {
      border-bottom: none;
    }
    
    .status-pending {
      background: #fff3cd;
      color: #856404;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .status-approved {
      background: #d4edda;
      color: #155724;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .status-cancelled {
      background: #f8d7da;
      color: #721c24;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
    }
  </style>
</body>
</html>
