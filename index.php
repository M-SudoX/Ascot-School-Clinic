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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Aurora State College of Technology - Online School Clinic</title>

  <!-- Bootstrap CSS -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <style>
    /* ========== GLOBAL STYLES ========== */
    :root {
      --primary-color: #667eea;
      --secondary-color: #764ba2;
      --accent-color: #ffda6a;
      --text-dark: #2c3e50;
      --text-light: #6c757d;
      --bg-light: #f8f9fa;
      --white: #ffffff;
      --shadow: 0 8px 32px rgba(0,0,0,0.1);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      background-color: var(--bg-light);
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* ========== LAYOUT STYLES ========== */
    .main-container {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    @media (min-width: 992px) {
      .main-container {
        flex-direction: row;
      }
    }

    .split-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.5rem;
      position: relative;
      overflow: hidden;
    }

    .login-section {
      background: linear-gradient(135deg, var(--accent-color), #fff7da);
      min-height: 50vh;
    }

    .announcements-section {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      min-height: 50vh;
      position: relative;
    }

    .announcements-section::before {
      content: "";
      position: absolute;
      inset: 0;
      background: #5b5fc7;
      backdrop-filter: blur(2px);
    }

    @media (min-width: 992px) {
      .split-section {
        height: 100vh;
        padding: 3rem 2rem;
      }
      
      .login-section {
        min-height: 100vh;
      }
      
      .announcements-section {
        min-height: 100vh;
      }
    }

    /* ========== LOGIN SECTION STYLES ========== */
    .school-brand {
      text-align: center;
      margin-bottom: 2.5rem;
      animation: fadeInUp 0.8s ease-out;
    }

    .logo-img {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
      transition: var(--transition);
    }

    .logo-img:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }

    .school-title {
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--text-dark);
      line-height: 1.4;
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, var(--text-dark), #495057);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .school-subtitle {
      font-size: 1.1rem;
      color: var(--text-light);
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 2.5rem;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255, 255, 255, 0.3);
      max-width: 450px;
      width: 100%;
      animation: slideInLeft 0.8s ease-out;
    }

    .form-label {
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1rem;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-label i {
      color: var(--primary-color);
    }

    .form-select {
      border-radius: 12px;
      padding: 1rem 1.25rem;
      border: 2px solid #e9ecef;
      font-size: 1rem;
      font-weight: 500;
      transition: var(--transition);
      background-color: var(--white);
    }

    .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15);
      transform: translateY(-2px);
    }

    .btn-login {
      background: #ffda6a;
      color: #555;
      font-weight: 600;
      padding: 1rem 2rem;
      border-radius: 12px;
      border: none;
      font-size: 1.1rem;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      width: 100%;
      margin-top: 1.5rem;
    }

    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: (110deg, #ffda6a 50%, #fff7da 50%);
      background: #ffda6a;
    }

    .btn-login:active {
      transform: translateY(-1px);
    }

    /* ========== ANNOUNCEMENTS SECTION STYLES ========== */
    .announcements-container {
      position: relative;
      z-index: 10;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 2.5rem;
      width: 100%;
      max-width: 550px;
      max-height: 85vh;
      overflow-y: auto;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255, 255, 255, 0.3);
      animation: slideInRight 0.8s ease-out;
    }

    .announcements-header {
      text-align: center;
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 3px solid var(--primary-color);
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .announcements-header h2 {
      font-weight: 800;
      margin: 0;
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }

    .announcements-header p {
      color: var(--text-light);
      margin: 0;
      font-size: 1.1rem;
      font-weight: 500;
    }

    .announcement-item {
      background: var(--white);
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      border-left: 6px solid var(--primary-color);
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      transition: var(--transition);
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
      background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.05), transparent);
      transition: left 0.6s ease;
    }

    .announcement-item:hover::before {
      left: 100%;
    }

    .announcement-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }

    .announcement-title {
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1rem;
      font-size: 1.3rem;
      line-height: 1.4;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .announcement-title i {
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .announcement-content {
      color: var(--text-light);
      margin-bottom: 1.5rem;
      line-height: 1.7;
      font-size: 1rem;
    }

    .announcement-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      color: var(--text-light);
      padding-top: 1.25rem;
      border-top: 2px solid #f8f9fa;
    }

    .announcement-sender {
      font-weight: 600;
      color: var(--primary-color);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .announcement-date {
      color: #999;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* ========== ATTACHMENT STYLES ========== */
    .attachment-container {
      margin-top: 1.25rem;
    }

    .image-attachment img {
      transition: var(--transition);
      border: 3px solid #f8f9fa;
      border-radius: 12px;
      max-height: 300px;
      width: auto;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .image-attachment img:hover {
      transform: scale(1.02);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .video-attachment video {
      background: #000;
      border-radius: 12px;
      max-height: 300px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .attachment-indicator {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      padding: 0.75rem 1rem;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      margin-top: 0.75rem;
      border: 1px solid #dee2e6;
      font-weight: 600;
      color: var(--text-dark);
      transition: var(--transition);
      cursor: pointer;
    }

    .attachment-indicator:hover {
      background: linear-gradient(135deg, #e9ecef, #dee2e6);
      transform: translateX(5px);
      color: var(--primary-color);
    }

    .attachment-indicator i {
      color: var(--primary-color);
      font-size: 1.1rem;
    }

    /* ========== NO ANNOUNCEMENTS STYLES ========== */
    .no-announcements {
      text-align: center;
      padding: 3rem 2rem;
      color: var(--text-light);
    }

    .no-announcements i {
      font-size: 4rem;
      margin-bottom: 1.5rem;
      color: #e9ecef;
      opacity: 0.7;
    }

    .no-announcements h4 {
      color: var(--text-light);
      margin-bottom: 1rem;
      font-weight: 600;
      font-size: 1.5rem;
    }

    .no-announcements p {
      color: #999;
      font-size: 1.1rem;
      line-height: 1.6;
    }

    /* ========== MODAL STYLES ========== */
    .modal-content {
      border-radius: 20px;
      border: none;
      box-shadow: var(--shadow);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: var(--white);
      border-bottom: none;
      border-radius: 20px 20px 0 0;
      padding: 1.5rem 2rem;
    }

    .modal-header .btn-close {
      filter: invert(1);
      opacity: 0.8;
      transition: var(--transition);
    }

    .modal-header .btn-close:hover {
      opacity: 1;
      transform: scale(1.1);
    }

    /* ========== SCROLLBAR STYLING ========== */
    .announcements-container::-webkit-scrollbar {
      width: 8px;
    }

    .announcements-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .announcements-container::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      border-radius: 10px;
    }

    .announcements-container::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #5a6fd8, #6a4a9a);
    }

    /* ========== ANIMATIONS ========== */
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

    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    /* ========== RESPONSIVE DESIGN ========== */
    @media (max-width: 768px) {
      .split-section {
        padding: 1.5rem 1rem;
        min-height: auto;
      }
      
      .login-card {
        padding: 2rem 1.5rem;
        margin: 1rem 0;
      }
      
      .announcements-container {
        padding: 1.5rem;
        max-height: 70vh;
        margin: 1rem 0;
      }
      
      .logo-img {
        width: 120px;
        height: 120px;
      }
      
      .school-title {
        font-size: 1.2rem;
      }
      
      .school-subtitle {
        font-size: 1rem;
      }
      
      .announcements-header h2 {
        font-size: 1.6rem;
      }
      
      .announcement-item {
        padding: 1.5rem;
      }
      
      .announcement-title {
        font-size: 1.1rem;
      }
      
      .form-label {
        font-size: 1.1rem;
      }
      
      .image-attachment img,
      .video-attachment video {
        max-height: 200px;
      }
    }

    @media (max-width: 576px) {
      .split-section {
        padding: 1rem 0.75rem;
      }
      
      .login-card,
      .announcements-container {
        padding: 1.5rem 1rem;
        border-radius: 20px;
      }
      
      .logo-img {
        width: 100px;
        height: 100px;
      }
      
      .school-title {
        font-size: 1.1rem;
      }
      
      .announcements-header h2 {
        font-size: 1.4rem;
      }
      
      .announcement-meta {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
      }
      
      .announcement-item {
        padding: 1.25rem;
      }
    }

    @media (max-width: 400px) {
      .school-title {
        font-size: 1rem;
      }
      
      .announcements-header h2 {
        font-size: 1.3rem;
      }
      
      .announcement-title {
        font-size: 1rem;
      }
    }

    /* ========== ACCESSIBILITY & INTERACTION ========== */
    .focus-visible {
      outline: 3px solid var(--primary-color);
      outline-offset: 2px;
    }

    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    /* Loading state */
    .loading {
      opacity: 0.7;
      pointer-events: none;
    }

    /* Success state */
    .success-message {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white;
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      text-align: center;
      animation: fadeInUp 0.5s ease-out;
    }
  </style>
</head>

<body>
  <div class="main-container">
    <!-- LOGIN SECTION -->
    <section class="split-section login-section" aria-labelledby="login-title">
      <div class="container-fluid">
        <div class="row justify-content-center">
          <div class="col-12 col-md-10 col-lg-8 col-xl-6">
            <div class="school-brand">
              <img src="img/logo.png" alt="ASCOT Logo" class="logo-img" />
              <h1 class="school-title">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
              <p class="school-subtitle">ONLINE SCHOOL CLINIC</p>
            </div>

            <form method="POST" action="process_user_type.php" class="login-card">
              <h2 id="login-title" class="sr-only">Login Form</h2>
              
              <label for="userType" class="form-label">
                <i class="fas fa-user-circle"></i>
                Select user type to log in
              </label>
              
              <select class="form-select mb-4" id="userType" name="userType" required aria-required="true">
                <option value="" disabled selected>Choose your role...</option>
                <option value="student">👨‍🎓 STUDENT</option>
                <option value="admin">👨‍💼 ADMIN</option>
              </select>
              
              <button type="submit" class="btn btn-login" aria-label="Continue to login">
                <span>Continue to Login</span>
                <i class="fas fa-arrow-right"></i>
              </button>
              
              <div>
                  <i>
                  </i>                
                </small>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>

    <!-- ANNOUNCEMENTS SECTION -->
    <section class="split-section announcements-section" aria-labelledby="announcements-title">
      <div class="announcements-container">
        <header class="announcements-header">
          <h2 id="announcements-title">
            <i class="fas fa-bullhorn me-2"></i>Latest Announcements
          </h2>
          <p>Stay updated with campus news and important updates</p>
        </header>

        <?php if (empty($announcements)): ?>
          <div class="no-announcements" role="status" aria-live="polite">
            <i class="fas fa-bullhorn" aria-hidden="true"></i>
            <h4>No Announcements Available</h4>
            <p>Check back later for updates from the school clinic.</p>
          </div>
        <?php else: ?>
          <div class="announcements-list" role="list">
            <?php foreach ($announcements as $index => $announcement): ?>
              <article class="announcement-item" role="listitem">
                <h3 class="announcement-title">
                  <i class="fas fa-bullhorn" aria-hidden="true"></i>
                  <?php echo htmlspecialchars($announcement['title'] ?? 'No Title'); ?>
                </h3>
                
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
                             alt="Announcement attachment: <?php echo htmlspecialchars($announcement['title']); ?>"
                             class="img-fluid"
                             onclick="openModal('<?php echo $file_path; ?>', 'image')"
                             tabindex="0"
                             role="button"
                             aria-label="View full size image">
                        <div class="attachment-indicator" onclick="openModal('<?php echo $file_path; ?>', 'image')">
                          <i class="fas fa-image" aria-hidden="true"></i>
                          <span>View Image Attachment</span>
                        </div>
                      </div>
                    <?php elseif (in_array($file_extension, $video_extensions)): ?>
                      <!-- Display Video -->
                      <div class="video-attachment">
                        <video controls class="w-100" aria-label="Video attachment">
                          <source src="<?php echo $file_path; ?>" type="video/<?php echo $file_extension; ?>">
                          Your browser does not support the video tag.
                        </video>
                        <div class="attachment-indicator">
                          <i class="fas fa-video" aria-hidden="true"></i>
                          <span>Video Attachment</span>
                        </div>
                      </div>
                    <?php else: ?>
                      <!-- Display generic file attachment -->
                      <div class="file-attachment">
                        <div class="attachment-indicator" onclick="downloadFile('<?php echo $file_path; ?>', '<?php echo $announcement['attachment']; ?>')">
                          <i class="fas fa-paperclip" aria-hidden="true"></i>
                          <span>Download: <?php echo htmlspecialchars($announcement['attachment']); ?></span>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                
                <footer class="announcement-meta">
                  <span class="announcement-sender">
                    <i class="fas fa-user-circle" aria-hidden="true"></i> 
                    <?php echo htmlspecialchars($announcement['sent_by'] ?? 'Administrator'); ?>
                  </span>
                  <span class="announcement-date">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i> 
                    <?php 
                      if (isset($announcement['created_at'])) {
                        echo date('F j, Y \a\t g:i A', strtotime($announcement['created_at']));
                      } else {
                        echo 'Recently';
                      }
                    ?>
                  </span>
                </footer>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <!-- IMAGE MODAL -->
  <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true" aria-labelledby="imageModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h3 class="modal-title" id="imageModalLabel">Announcement Attachment</h3>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImage" src="" alt="" class="img-fluid rounded">
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Enhanced JavaScript for better user experience
    document.addEventListener('DOMContentLoaded', function() {
      // Add staggered animation to announcement items
      const announcementItems = document.querySelectorAll('.announcement-item');
      announcementItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.style.animation = 'fadeInUp 0.6s ease-out forwards';
      });

      // Smooth scrolling for announcements container with momentum
      const announcementsContainer = document.querySelector('.announcements-container');
      if (announcementsContainer) {
        let isScrolling = false;
        
        announcementsContainer.addEventListener('wheel', function(e) {
          if (!isScrolling) {
            isScrolling = true;
            const delta = e.deltaY * 2; // Increase scroll speed
            this.scrollBy({ top: delta, behavior: 'smooth' });
            
            setTimeout(() => {
              isScrolling = false;
            }, 100);
          }
          e.preventDefault();
        }, { passive: false });
      }

      // Enhanced focus management for accessibility
      const focusableElements = document.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      focusableElements.forEach(element => {
        element.addEventListener('focus', function() {
          this.classList.add('focus-visible');
        });
        
        element.addEventListener('blur', function() {
          this.classList.remove('focus-visible');
        });
      });

      // Form enhancement
      const loginForm = document.querySelector('.login-card form');
      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          const submitBtn = this.querySelector('button[type="submit"]');
          const originalText = submitBtn.innerHTML;
          
          // Show loading state
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
          submitBtn.classList.add('loading');
          
          // Simulate processing time
          setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.classList.remove('loading');
          }, 1500);
        });
      }

      // Keyboard navigation for announcements
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          const modal = bootstrap.Modal.getInstance(document.getElementById('imageModal'));
          if (modal) {
            modal.hide();
          }
        }
      });
    });

    // Enhanced modal function
    function openModal(filePath, type) {
      if (type === 'image') {
        const modalImage = document.getElementById('modalImage');
        modalImage.src = filePath;
        modalImage.alt = 'Full size announcement image';
        
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        modal.show();
        
        // Focus management for accessibility
        document.getElementById('imageModal').addEventListener('shown.bs.modal', function() {
          this.querySelector('.btn-close').focus();
        });
      }
    }

    // Enhanced download function
    function downloadFile(filePath, fileName) {
      try {
        const link = document.createElement('a');
        link.href = filePath;
        link.download = fileName;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show download feedback
        showToast('Download started: ' + fileName);
      } catch (error) {
        showToast('Download failed. Please try again.', 'error');
        console.error('Download error:', error);
      }
    }

    // Toast notification function
    function showToast(message, type = 'success') {
      // Create toast element
      const toast = document.createElement('div');
      toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'} position-fixed`;
      toast.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: fadeInUp 0.3s ease-out;
      `;
      toast.innerHTML = `
        <div class="d-flex align-items-center">
          <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
          <span>${message}</span>
        </div>
      `;
      
      document.body.appendChild(toast);
      
      // Remove toast after 3 seconds
      setTimeout(() => {
        toast.style.animation = 'fadeInUp 0.3s ease-out reverse';
        setTimeout(() => {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 300);
      }, 3000);
    }

    // Auto-play videos when they come into view (with error handling)
    document.addEventListener('DOMContentLoaded', function() {
      const videos = document.querySelectorAll('video');
      
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.play().catch(error => {
              console.log('Auto-play prevented:', error);
            });
          } else {
            entry.target.pause();
          }
        });
      }, { threshold: 0.5 });

      videos.forEach(video => {
        // Add error handling for video elements
        video.addEventListener('error', function() {
          console.error('Video loading error:', this.src);
        });
        
        observer.observe(video);
      });
    });

    // Touch device enhancements
    if ('ontouchstart' in window) {
      document.documentElement.classList.add('touch-device');
      
      // Increase tap targets for mobile
      const tapTargets = document.querySelectorAll('.attachment-indicator, .btn-login');
      tapTargets.forEach(target => {
        target.style.minHeight = '44px';
        target.style.minWidth = '44px';
      });
    }
  </script>
</body>
</html>