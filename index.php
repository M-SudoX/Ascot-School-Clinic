<?php
// FRONTEND: HTML + CSS + JavaScript + Bootstrap
// BACKEND: PHP + PDO + MySQL + Sessions
// SECURITY: Parameter Binding + Input Validation + Session Protection
// DESIGN: Responsive Layout + Professional Styling + Intuitive UI

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration for 24-hour timeout
ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_set_cookie_params(86400);
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Fetch active announcements from database
$announcements = [];
try {
    $stmt = $pdo->prepare("
        SELECT title, content, created_at, sent_by, attachment 
        FROM announcements 
        WHERE is_active = 1 AND post_on_front = 1 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error but don't break the page
    error_log("Error fetching announcements: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aurora State College of Technology - Online School Clinic</title>

  <!-- Bootstrap CSS -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <style>
    /* Global page styling */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      margin: 0;
      background-color: #f8f9fa;
      font-family: "Poppins", sans-serif;
      overflow-x: hidden;
    }

    .split {
      display: flex;
      flex: 1;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      position: relative;
    }

    /* Layout for large screens */
    @media (min-width: 768px) {
      body {
        flex-direction: row;
      }
      .split {
        width: 50%;
        height: 100vh;
      }
    }

    .left {
      background: linear-gradient(135deg, #ffda6a, #fff7da);
      color: #fff;
      text-align: center;
      position: relative;
    }

    .right {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .right::before {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.3);
    }

    .logo-left {
      width: 120px;
      height: 120px;
      margin-bottom: 15px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 0 20px rgba(0,0,0,0.2);
      border: 3px solid white;
    }

    h5 {
      font-size: 1.1rem;
      font-weight: 600;
      line-height: 1.5;
      letter-spacing: 0.5px;
      margin-bottom: 25px;
      color: #333;
    }

    label {
      font-weight: 500;
      color: #333;
    }

    select {
      border-radius: 8px;
      padding: 10px;
      border: 2px solid #e9ecef;
      transition: all 0.3s ease;
    }

    select:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .btn-next {
      background: linear-gradient(135deg, #0d6efd, #0b5ed7);
      color: white;
      font-weight: 500;
      padding: 12px 30px;
      border-radius: 8px;
      transition: all 0.3s ease;
      border: none;
      font-size: 1rem;
    }

    .btn-next:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(13, 110, 253, 0.4);
    }

    /* Announcements Section - Positioned on right side */
    .announcements-container {
      position: relative;
      z-index: 10;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 30px;
      width: 90%;
      max-width: 500px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 20px 40px rgba(0,0,0,0.15);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .announcements-header {
      text-align: center;
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 3px solid #667eea;
      background: linear-gradient(135deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .announcements-header h3 {
      font-weight: 700;
      margin: 0;
      font-size: 1.8rem;
      margin-bottom: 8px;
    }

    .announcements-header p {
      color: #666;
      margin: 0;
      font-size: 1rem;
      font-weight: 500;
    }

    .announcement-item {
      background: white;
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 20px;
      border-left: 5px solid #667eea;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      border: 1px solid #f0f0f0;
      position: relative;
      overflow: hidden;
    }

    .announcement-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
      transition: left 0.5s ease;
    }

    .announcement-item:hover::before {
      left: 100%;
    }

    .announcement-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }

    .announcement-title {
      font-weight: 700;
      color: #1a3a5f;
      margin-bottom: 12px;
      font-size: 1.3rem;
      line-height: 1.4;
      border-bottom: 2px solid #f8f9fa;
      padding-bottom: 8px;
    }

    .announcement-content {
      color: #555;
      margin-bottom: 15px;
      line-height: 1.7;
      font-size: 1rem;
    }

    .announcement-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      color: #888;
      padding-top: 15px;
      border-top: 2px solid #f8f9fa;
    }

    .announcement-sender {
      font-weight: 600;
      color: #667eea;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .announcement-date {
      color: #999;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .no-announcements {
      text-align: center;
      padding: 60px 20px;
      color: #666;
    }

    .no-announcements i {
      font-size: 5rem;
      margin-bottom: 25px;
      color: #e9ecef;
      opacity: 0.7;
    }

    .no-announcements h4 {
      color: #888;
      margin-bottom: 15px;
      font-weight: 600;
      font-size: 1.5rem;
    }

    .no-announcements p {
      color: #999;
      font-size: 1.1rem;
      line-height: 1.6;
    }

    /* Attachment Styling */
    .attachment-container {
      margin-top: 15px;
    }

    .image-attachment img {
      transition: transform 0.3s ease;
      border: 3px solid #f8f9fa;
      border-radius: 8px;
      max-height: 300px;
      width: auto;
      cursor: pointer;
    }

    .image-attachment img:hover {
      transform: scale(1.02);
    }

    .video-attachment video {
      background: #000;
      border-radius: 8px;
      max-height: 300px;
    }

    .attachment-indicator {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      padding: 10px 15px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-top: 10px;
      border: 1px solid #dee2e6;
      font-weight: 500;
      color: #495057;
      transition: all 0.3s ease;
    }

    .attachment-indicator:hover {
      background: linear-gradient(135deg, #e9ecef, #dee2e6);
      transform: translateX(5px);
    }

    .attachment-indicator i {
      color: #667eea;
      font-size: 1.1rem;
    }

    /* Modal styling */
    .modal-content {
      border-radius: 15px;
      border: none;
    }

    .modal-header {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      border-bottom: none;
    }

    .modal-header .btn-close {
      filter: invert(1);
    }

    /* Login form styling */
    .login-form {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.1);
      border: 1px solid rgba(255, 255, 255, 0.3);
      max-width: 400px;
      width: 90%;
    }

    .form-label {
      font-weight: 600;
      color: #333;
      margin-bottom: 10px;
      font-size: 1.1rem;
    }

    /* Scrollbar styling for announcements */
    .announcements-container::-webkit-scrollbar {
      width: 8px;
    }

    .announcements-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .announcements-container::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-radius: 10px;
    }

    .announcements-container::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #5a6fd8, #6a4a9a);
    }

    /* Mobile responsiveness */
    @media (max-width: 767px) {
      body {
        flex-direction: column;
      }
      
      .split {
        width: 100%;
        height: 50vh;
        padding: 30px 15px;
      }
      
      .announcements-container {
        max-width: 95%;
        max-height: 60vh;
        padding: 20px;
        margin: 10px;
      }
      
      .announcement-item {
        padding: 20px;
      }
      
      .announcement-title {
        font-size: 1.2rem;
      }
      
      .announcements-header h3 {
        font-size: 1.5rem;
      }
      
      .login-form {
        padding: 25px;
      }
      
      .logo-left {
        width: 100px;
        height: 100px;
      }
      
      h5 {
        font-size: 1rem;
      }

      .image-attachment img,
      .video-attachment video {
        max-height: 200px;
      }
    }

    /* Animation for page load */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .announcements-container,
    .login-form {
      animation: fadeInUp 0.8s ease-out;
    }

    /* School info styling */
    .school-info {
      margin-bottom: 30px;
    }

    .school-info h5 {
      background: linear-gradient(135deg, #333, #555);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-weight: 700;
    }
  </style>
</head>

<body>
  <!-- LEFT SIDE - Login Form -->
  <div class="split left">
    <div class="school-info">
      <img src="img/logo.png" alt="ASCOT Logo" class="logo-left" />
      <h5>
        AURORA STATE COLLEGE OF TECHNOLOGY<br>
        ONLINE SCHOOL CLINIC
      </h5>
    </div>

    <form method="POST" action="process_user_type.php" class="login-form">
      <label for="userType" class="form-label">Select user type to log in</label>
      <select class="form-select mb-4" id="userType" name="userType" required>
        <option value="" disabled selected>Select user type</option>
        <option value="student">STUDENT</option>
        <option value="admin">ADMIN</option>
      </select>
      <div class="d-grid">
        <button type="submit" class="btn btn-next">
          <i class="fas fa-arrow-right me-2"></i>Next
        </button>
      </div>
    </form>
  </div>

  <!-- RIGHT SIDE - Announcements Display -->
  <div class="split right">
    <div class="announcements-container">
      <div class="announcements-header">
        <h3><i class="fas fa-bullhorn me-2"></i>Latest Announcements</h3>
        <p>Stay updated with the latest news and updates</p>
      </div>

      <?php if (empty($announcements)): ?>
        <div class="no-announcements">
          <i class="fas fa-bullhorn"></i>
          <h4>No Announcements Available</h4>
          <p>Check back later for updates from the school clinic.</p>
        </div>
      <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
          <div class="announcement-item">
            <div class="announcement-title">
              <i class="fas fa-bullhorn me-2" style="color: #667eea;"></i>
              <?php echo htmlspecialchars($announcement['title'] ?? 'No Title'); ?>
            </div>
            <div class="announcement-content">
              <?php echo nl2br(htmlspecialchars($announcement['content'] ?? 'No content available.')); ?>
            </div>
            
            <?php if (!empty($announcement['attachment'])): 
              $file_path = 'uploads/announcements/' . $announcement['attachment'];
              $file_extension = strtolower(pathinfo($announcement['attachment'], PATHINFO_EXTENSION));
              $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
              $video_extensions = ['mp4', 'avi', 'mov', 'wmv', 'webm'];
            ?>
              <div class="attachment-container">
                <?php if (in_array($file_extension, $image_extensions)): ?>
                  <!-- Display Image -->
                  <div class="image-attachment">
                    <img src="<?php echo $file_path; ?>" 
                         alt="Announcement Image" 
                         class="img-fluid"
                         onclick="openModal('<?php echo $file_path; ?>', 'image')">
                    <div class="attachment-indicator">
                      <i class="fas fa-image"></i>
                      <span>Image Attachment</span>
                    </div>
                  </div>
                <?php elseif (in_array($file_extension, $video_extensions)): ?>
                  <!-- Display Video -->
                  <div class="video-attachment">
                    <video controls class="w-100">
                      <source src="<?php echo $file_path; ?>" type="video/<?php echo $file_extension; ?>">
                      Your browser does not support the video tag.
                    </video>
                    <div class="attachment-indicator">
                      <i class="fas fa-video"></i>
                      <span>Video Attachment</span>
                    </div>
                  </div>
                <?php else: ?>
                  <!-- Display generic file attachment -->
                  <div class="file-attachment">
                    <div class="attachment-indicator" onclick="downloadFile('<?php echo $file_path; ?>', '<?php echo $announcement['attachment']; ?>')" style="cursor: pointer;">
                      <i class="fas fa-paperclip"></i>
                      <span>Download Attachment: <?php echo htmlspecialchars($announcement['attachment']); ?></span>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            
            <div class="announcement-meta">
              <span class="announcement-sender">
                <i class="fas fa-user-circle"></i> 
                <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Administrator'); ?>
              </span>
              <span class="announcement-date">
                <i class="fas fa-calendar-alt"></i> 
                <?php 
                  if (isset($announcement['created_at'])) {
                    echo date('F j, Y \a\t g:i A', strtotime($announcement['created_at']));
                  } else {
                    echo 'Recently';
                  }
                ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Image Modal -->
  <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Announcement Attachment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImage" src="" alt="Full size image" class="img-fluid">
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Add some interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      // Add loading animation to announcement items
      const announcementItems = document.querySelectorAll('.announcement-item');
      announcementItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
      });
      
      // Smooth scroll for announcements container
      const announcementsContainer = document.querySelector('.announcements-container');
      if (announcementsContainer) {
        announcementsContainer.addEventListener('wheel', function(e) {
          if (e.deltaY !== 0) {
            e.preventDefault();
            this.scrollTop += e.deltaY;
          }
        });
      }
    });

    // Function to open image in modal
    function openModal(filePath, type) {
      if (type === 'image') {
        document.getElementById('modalImage').src = filePath;
        var imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        imageModal.show();
      }
    }

    // Function to download files
    function downloadFile(filePath, fileName) {
      const link = document.createElement('a');
      link.href = filePath;
      link.download = fileName;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Auto-play videos when they come into view
    document.addEventListener('DOMContentLoaded', function() {
      const videos = document.querySelectorAll('video');
      
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.play();
          } else {
            entry.target.pause();
          }
        });
      }, { threshold: 0.5 });

      videos.forEach(video => {
        observer.observe(video);
      });
    });
  </script>
</body>
</html>