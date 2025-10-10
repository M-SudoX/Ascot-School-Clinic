<?php
// ==================== SESSION AT SECURITY ====================
session_start();
require 'includes/db_connect.php';

// ✅ SECURITY CHECK: TINITIGNAN KUNG NAKA-LOGIN ANG USER
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_number = $_SESSION['student_number'] ?? ($_SESSION['student_id'] ?? 'N/A');

// ✅ FETCH ANNOUNCEMENTS FROM DATABASE
$stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - ASCOT Online School Clinic</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <link href="assets/css/student_dashboard.css" rel="stylesheet">
    <link href="assets/css/student_announcement.css" rel="stylesheet">
</head>
<body>
    <!-- HEADER SECTION - SCHOOL BRANDING -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
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

    <!-- MAIN LAYOUT CONTAINER -->
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR NAVIGATION -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a class="nav-link active" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                </nav>

                <!-- LOGOUT BUTTON -->
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- MAIN CONTENT AREA -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
                    <p class="text-muted">Stay updated with the latest clinic announcements and notices</p>
                </div>

                <!-- ANNOUNCEMENTS LIST -->
                <div class="announcements-container">
                    <?php if (empty($announcements)): ?>
                        <div class="no-announcements">
                            <i class="fas fa-inbox"></i>
                            <h4>No Announcements Yet</h4>
                            <p>Check back later for updates from the clinic</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <div class="announcement-icon">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div class="announcement-meta">
                                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                        <span class="announcement-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if ($announcement['is_urgent'] ?? false): ?>
                                        <span class="badge-urgent">URGENT</span>
                                    <?php endif; ?>
                                </div>
                                <div class="announcement-body">
                                    <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                </div>
                                <?php if (!empty($announcement['category'])): ?>
                                    <div class="announcement-footer">
                                        <span class="announcement-category">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($announcement['category']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>