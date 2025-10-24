<?php
session_start();
include 'admin_logger.php'; // Your existing logger
include '../includes/db_connect.php';   // The new config file

// Log admin access
logAdminAction($_SESSION['admin_name'] ?? 'Admin User', 'Accessed Backup & Restore module');

$success_message = '';
$error_message = '';

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle POST requests for backup or restore
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- BACKUP ACTION ---
    if (isset($_POST['action']) && $_POST['action'] == 'backup') {
        $password = $_POST['backup_password'];
        if (empty($password)) {
            $_SESSION['error_message'] = "Password is required to create a backup.";
            header("Location: backup_restore.php");
            exit;
        }

        // Build the mysqldump command
        // Note: --password=... has no space
        $command = sprintf(
            'mysqldump --host=%s --user=%s %s %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            (DB_PASS ? '--password=' . escapeshellarg(DB_PASS) : ''),
            escapeshellarg(DB_NAME)
        );

        $sqlContent = shell_exec($command . ' 2>&1'); // Capture STDOUT and STDERR

        // Check for mysqldump errors
        if ($sqlContent === null || strpos($sqlContent, 'command not found') !== false || strpos($sqlContent, 'Access denied') !== false) {
            $_SESSION['error_message'] = "Backup failed. Ensure 'mysqldump' is in your server's PATH and credentials are correct. Error: " . htmlspecialchars($sqlContent);
            header("Location: backup_restore.php");
            exit;
        }

        // Encrypt the SQL content
        $iv_len = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_len);
        $encrypted_sql = openssl_encrypt($sqlContent, ENCRYPTION_METHOD, $password, 0, $iv);
        
        if ($encrypted_sql === false) {
             $_SESSION['error_message'] = "Encryption failed.";
             header("Location: backup_restore.php");
             exit;
        }

        // Prepend IV to encrypted data, then compress
        $compressed_data = gzcompress($iv . $encrypted_sql);

        // Force download
        $filename = DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql.gz.enc';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($compressed_data));
        echo $compressed_data;
        exit;
    }

    // --- RESTORE ACTION ---
    if (isset($_POST['action']) && $_POST['action'] == 'restore') {
        $password = $_POST['restore_password'];
        
        if (empty($password)) {
            $_SESSION['error_message'] = "Password is required to restore a backup.";
            header("Location: backup_restore.php");
            exit;
        }

        if (empty($_FILES['backup_file']['tmp_name'])) {
            $_SESSION['error_message'] = "Please select a backup file to restore.";
            header("Location: backup_restore.php");
            exit;
        }

        try {
            $file_content = file_get_contents($_FILES['backup_file']['tmp_name']);
            
            // Decompress
            $data = @gzuncompress($file_content);
            if ($data === false) {
                throw new Exception("File is not compressed or is corrupt.");
            }

            // Extract IV
            $iv_len = openssl_cipher_iv_length(ENCRYPTION_METHOD);
            $iv = substr($data, 0, $iv_len);
            $encrypted_sql = substr($data, $iv_len);

            // Decrypt
            $sql = openssl_decrypt($encrypted_sql, ENCRYPTION_METHOD, $password, 0, $iv);
            if ($sql === false) {
                throw new Exception("Invalid password or corrupt backup file.");
            }

            // Save decrypted SQL to a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'sql');
            file_put_contents($tempFile, $sql);

            // Build the mysql restore command
            $command = sprintf(
                'mysql --host=%s --user=%s %s %s < %s',
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                (DB_PASS ? '--password=' . escapeshellarg(DB_PASS) : ''),
                escapeshellarg(DB_NAME),
                escapeshellarg($tempFile)
            );

            $output = shell_exec($command . ' 2>&1'); // Capture output and errors
            unlink($tempFile); // Delete the temporary file

            if ($output !== null && (strpos($output, 'command not found') !== false || strpos($output, 'Access denied') !== false || strpos($output, 'ERROR') !== false)) {
                 throw new Exception("Restore failed. Ensure 'mysql' is in your server's PATH and credentials are correct. Error: " . htmlspecialchars($output));
            }

            $_SESSION['success_message'] = "Database restored successfully!";
            logAdminAction($_SESSION['admin_name'] ?? 'Admin User', 'Successfully restored database');

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile); // Clean up on failure
            }
        }

        header("Location: backup_restore.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - ASCOT Clinic</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 100px;
        }
        /* Header Styles */
        .top-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 100px;
        }
        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .school-info {
            flex: 1;
        }
        .republic {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        .school-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin: 0.2rem 0;
        }
        .clinic-title {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 100px;
            left: 20px;
            z-index: 1001;
            background: #667eea;
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .mobile-menu-toggle:hover {
            transform: scale(1.1);
            background: #764ba2;
        }
        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 100px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 999;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .nav-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #667eea;
            border-left: 4px solid #667eea;
        }
        .nav-item i {
            width: 25px;
            margin-right: 1rem;
        }
        .nav-item span {
            flex: 1;
        }
        .nav-item .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
    
        }
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #f8f9fa;
        }
        .submenu.show {
            max-height: 500px;
        }
        .submenu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem 0.75rem 3.5rem;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .submenu-item:hover {
            background: #e9ecef;
            color: #667eea;
        }
        /* Style for active submenu item */
        .submenu-item.active {
            background: #e9ecef;
            color: #667eea;
            font-weight: 500;
        }
        .submenu-item i {
            width: 20px;
            margin-right: 0.75rem;
        }
        .nav-item.logout {
            color: #dc3545;
            margin-top: auto;
        }
        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            margin-left: 280px;
            margin-top: 0;
        }

        /* Dashboard Card */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.2rem;
            color: #444;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: #667eea;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
            .sidebar {
                position: fixed;
                left: 0;
                top: 100px;
                height: calc(100vh - 100px);
                z-index: 1000;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 100px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .sidebar-overlay.active {
                display: block;
            }
            .main-content {
                padding: 1rem;
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php" class="nav-item"> <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="studentMenu">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Management</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="studentMenu"> <a href="students.php" class="submenu-item">
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
                    <div class="submenu" id="consultationMenu">
                        <a href="view_records.php" class="submenu-item">
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
                        <a href="calendar_view.php" class="submenu-item">
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
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu show" id="adminMenu"> <a href="users_logs.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            Users Logs
                        </a>
                        <a href="backup_restore.php" class="submenu-item active"> <i class="fas fa-database"></i> Back up & Restore
                        </a>
                    </div>
                </div>

                <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="announcementMenu">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcement</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="announcementMenu">
                        <a href="new_announcement.php" class="submenu-item">
                            <i class="fas fa-plus-circle"></i>
                            New Announcement
                        </a>
                        <a href="announcement_history.php" class="submenu-item">
                            <i class="fas fa-history"></i>
                            History
                        </a>
                    </div>
                </div>
                
                <a href="admin_login.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <h2 class="mb-4">Backup & Restore</h2>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; // Error messages may contain HTML chars, so not escaping fully. Be careful if they include user input. ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="dashboard-card">
                <h3 class="section-title">
                    <i class="fas fa-download"></i>
                    Create Database Backup
                </h3>
                <p class="text-muted">Create a secure, password-protected, and compressed backup of the entire database. This file will be downloaded to your computer.</p>
                <hr class="my-3">
                <form action="backup_restore.php" method="POST">
                    <div class="mb-3">
                        <label for="backup_password" class="form-label"><strong>Backup Password</strong></label>
                        <input type="password" class="form-control" id="backup_password" name="backup_password" required>
                        <div class="form-text">This password will be required to restore the backup. Do not forget it.</div>
                    </div>
                    <button type="submit" name="action" value="backup" class="btn btn-primary" style="background: #667eea; border: none;">
                        <i class="fas fa-download me-2"></i>Create & Download Backup
                    </button>
                </form>
            </div>

            <div class="dashboard-card">
                <h3 class="section-title text-danger">
                    <i class="fas fa-upload"></i>
                    Restore Database from Backup
                </h3>
                <p class="text-danger">
                    <strong>Warning:</strong> Restoring from a backup will completely overwrite the current database. All existing data will be permanently lost and replaced with the data from the backup file.
                </p>
                <hr class="my-3">
                <form action="backup_restore.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="backup_file" class="form-label"><strong>Backup File (.sql.gz.enc)</strong></label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".enc" required>
                    </div>
                    <div class="mb-3">
                        <label for="restore_password" class="form-label"><strong>Backup Password</strong></label>
                        <input type="password" class="form-control" id="restore_password" name="restore_password" required>
                        <div class="form-text">The password used when the backup was created.</div>
                    </div>
                    <button type="submit" name="action" value="restore" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This will overwrite the entire database.');">
                        <i class="fas fa-upload me-2"></i>Restore Database
                    </button>
                </form>
            </div>

        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dropdown functionality
        document.querySelectorAll('.dropdown-btn').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const submenu = document.getElementById(targetId);
                const arrow = this.querySelector('.arrow');
                
                // Check if the clicked menu is already open
                const isAlreadyOpen = submenu.classList.contains('show');

                // Close all other submenus
                document.querySelectorAll('.submenu.show').forEach(menu => {
                    if (menu.id !== targetId) {
                        menu.classList.remove('show');
                        const otherBtn = document.querySelector(`[data-target="${menu.id}"]`);
                        if (otherBtn) {
                            otherBtn.querySelector('.arrow').style.transform = 'rotate(0deg)';
                        }
                    }
                });

                // Toggle the clicked submenu
                if (!isAlreadyOpen) {
                    submenu.classList.add('show');
                    arrow.style.transform = 'rotate(180deg)';
                } else {
                    submenu.classList.remove('show');
                    arrow.style.transform = 'rotate(0deg)';
                }
            });
        });

        // Pre-open the active menu on page load (Admin Tools)
        document.addEventListener('DOMContentLoaded', function() {
            const activeSubmenu = document.querySelector('.submenu-item.active');
            if (activeSubmenu) {
                const submenu = activeSubmenu.closest('.submenu');
                if (submenu) {
                    submenu.classList.add('show');
                    const dropdownBtn = document.querySelector(`[data-target="${submenu.id}"]`);
                    if (dropdownBtn) {
                        dropdownBtn.querySelector('.arrow').style.transform = 'rotate(180deg)';
                    }
                }
            }
        });


        // Mobile menu functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                const icon = mobileMenuToggle.querySelector('i');
                if (icon) {
                    icon.classList.replace('fa-times', 'fa-bars');
                }
            });
        }
    </script>
</body>
</html>