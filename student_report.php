<?php
session_start();
require 'includes/db_connect.php'; // ✅ Make sure this path is correct

// ✅ Security check - ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_number = $_SESSION['student_number'] ?? '';

// ✅ Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');

// ✅ Get start and end of current month
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// ✅ Fetch all approved consultations this month for this student
$query = "
    SELECT date 
    FROM consultation_requests
    WHERE student_id = :student_id 
      AND status = 'Approved'
      AND date BETWEEN :start AND :end
";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'student_id' => $student_id,
    'start' => $monthStart,
    'end' => $monthEnd
]);
$consultations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ✅ Initialize 4-week array
$consultationData = [0, 0, 0, 0];

foreach ($consultations as $consultDate) {
    $day = date('j', strtotime($consultDate));

    // Determine week number of the month (1–4)
    if ($day <= 7) $week = 0;
    elseif ($day <= 14) $week = 1;
    elseif ($day <= 21) $week = 2;
    else $week = 3;

    $consultationData[$week]++;
}

$totalConsults = array_sum($consultationData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report - ASCOT Online School Clinic</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <link href="assets/css/student_report.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* ✅ Keep your design exactly as before */
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            width: 700px;
            height: 400px;
            margin: 0 auto;
        }
        @media (max-width: 768px) {
            .chart-container {
                width: 100%;
                height: 350px;
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
                    <div class="college-info">
                        <h4>Republic of the Philippines</h4>
                        <h4>AURORA STATE COLLEGE OF TECHNOLOGY</h4>
                        <p>ONLINE SCHOOL CLINIC</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LAYOUT -->
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="update_profile.php">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </a>
                    <a class="nav-link" href="schedule_consultation.php">
                        <i class="fas fa-calendar-alt"></i> Schedule Consultation
                    </a>
                    <a class="nav-link active" href="student_report.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <a class="nav-link" href="student_announcement.php">
                        <i class="fas fa-bullhorn"></i> Announcement
                    </a>
                </nav>

                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-title">
                    <i class="fas fa-chart-line"></i>
                    Consultation Reports
                </div>
                <p class="page-subtitle">Track your weekly consultation activity this month</p>

                <!-- STATS CARD -->
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-info">
                        <h5>Total Consultations This Month</h5>
                        <h2><?php echo $totalConsults; ?></h2>
                    </div>
                </div>
                <br>

                <!-- CHART CONTAINER -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Weekly Consultation History</h3>
                        <div class="chart-month">
                            <i class="fas fa-calendar-alt"></i>
                            <strong><?php echo date('F Y'); ?></strong>
                        </div>
                    </div>
                    <canvas id="consultChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        const consultationData = <?php echo json_encode($consultationData); ?>;

        const ctx = document.getElementById('consultChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Consultations',
                    data: consultationData,
                    backgroundColor: 'rgba(249, 204, 67, 0.8)',
                    borderColor: '#f9cc43',
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBackgroundColor: '#f6b93b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(30, 60, 114, 0.9)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        borderColor: '#f9cc43',
                        borderWidth: 2,
                        callbacks: {
                            label: function(context) {
                                return 'Consultations: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Consultations',
                            font: { size: 14, weight: 'bold' },
                            color: '#1e3c72'
                        },
                        ticks: { stepSize: 1, font: { size: 12 }, color: '#666' },
                        grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Weeks',
                            font: { size: 14, weight: 'bold' },
                            color: '#1e3c72'
                        },
                        ticks: { font: { size: 12 }, color: '#666' },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>
