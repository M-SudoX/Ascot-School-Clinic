<?php
session_start();
require 'includes/db_connect.php';
require 'includes/activity_logger.php';

// ✅ Check kung naka-login ang student
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// ✅ I-log ang pag-visit sa activity logs page (automatic duplicate prevention na)
logActivity($pdo, $student_id, "Viewed activity logs");

// ✅ Fetch ONLY SPECIFIC ACTION LOGS - hindi kasama ang viewed/accessed/login/logout
try {
    $stmt = $pdo->prepare("
        SELECT id, action, log_date 
        FROM activity_logs 
        WHERE student_id = :student_id 
        AND action NOT LIKE '%viewed%' 
        AND action NOT LIKE '%accessed%' 
        AND action NOT LIKE '%logged in%' 
        AND action NOT LIKE '%logged out%'
        ORDER BY log_date DESC
    ");
    $stmt->execute([':student_id' => $student_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Activity logs table not found. Please contact administrator.";
    $logs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - ASCOT Online School Clinic</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <link href="assets/css/student_dashboard.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Poppins", sans-serif;
        }
        .activity-table {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        table th {
            background: #ffda6a;
            text-align: center;
        }
        table td {
            vertical-align: middle;
        }
        .main-content h3 {
            color: #333;
            font-weight: 600;
        }
        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        /* Center aligned action cells */
        .action-cell {
            text-align: center;
            padding: 12px;
        }
        .action-content {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .action-icon {
            width: 16px;
        }
        .text-muted-empty {
            color: #6c757d;
            font-style: italic;
            padding: 40px 0;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }
            table {
                font-size: 14px;
            }
            .action-content {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
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

    <!-- CONTENT -->
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link" href="student_report.php"><i class="fas fa-chart-bar"></i> Report</a>
                    <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                    <a class="nav-link active" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </nav>
                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="activity-table mt-5">
                    <h3>Activity Logs</h3>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-info">
                            <strong>Info:</strong> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <table class="table table-bordered mt-4">
                        <thead>
                            <tr>
                                <th class="text-center">ID</th>
                                <th class="text-center">Action</th>
                                <th class="text-center">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-center"><?php echo htmlspecialchars($log['id']); ?></td>
                                        <td class="action-cell">
                                            <div class="action-content">
                                                <?php 
                                                // Add icons based on action type
                                                $action = htmlspecialchars($log['action']);
                                                $icon = '';
                                                
                                                if (strpos($action, 'profile') !== false) {
                                                    $icon = '<i class="fas fa-user-edit text-primary action-icon"></i>';
                                                } elseif (strpos($action, 'medical') !== false) {
                                                    $icon = '<i class="fas fa-file-medical text-info action-icon"></i>';
                                                } elseif (strpos($action, 'password') !== false) {
                                                    $icon = '<i class="fas fa-key text-warning action-icon"></i>';
                                                } elseif (strpos($action, 'consultation') !== false) {
                                                    if (strpos($action, 'Scheduled') !== false) {
                                                        $icon = '<i class="fas fa-calendar-plus text-success action-icon"></i>';
                                                    } elseif (strpos($action, 'Edited') !== false) {
                                                        $icon = '<i class="fas fa-edit text-primary action-icon"></i>';
                                                    } elseif (strpos($action, 'Cancelled') !== false) {
                                                        $icon = '<i class="fas fa-times-circle text-danger action-icon"></i>';
                                                    } else {
                                                        $icon = '<i class="fas fa-calendar-check text-info action-icon"></i>';
                                                    }
                                                } else {
                                                    $icon = '<i class="fas fa-history text-secondary action-icon"></i>';
                                                }
                                                
                                                echo $icon;
                                                ?>
                                                <span><?php echo $action; ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo date('M d, Y h:i A', strtotime($log['log_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted-empty">
                                        <i class="fas fa-clipboard-list fa-2x mb-3 d-block"></i>
                                        No important activities recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>