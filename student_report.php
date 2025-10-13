<?php
session_start();
require 'includes/db_connect.php'; // ✅ Make sure this path is correct

// ✅ Security check - ensure student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// ✅ Fetch student_number (link between users and consultations)
$stmt = $pdo->prepare("SELECT student_number FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student_number = $stmt->fetchColumn();

if (!$student_number) {
    die("Student record not found.");
}

// ✅ Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');

// ✅ Get start and end of the current month
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// ✅ Fetch consultations from consultations table (added by admin)
$query = "
    SELECT consultation_date 
    FROM consultations
    WHERE student_number = :student_number
      AND consultation_date BETWEEN :start AND :end
";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'student_number' => $student_number,
    'start' => $monthStart,
    'end' => $monthEnd
]);
$consultations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ✅ Determine how many weeks are in this month (can be 4 or 5)
$firstDay = new DateTime($monthStart);
$lastDay = new DateTime($monthEnd);
$numWeeks = ceil(($lastDay->format('d') + $firstDay->format('N') - 1) / 7);

// ✅ Initialize week data dynamically
$consultationData = array_fill(0, $numWeeks, 0);

// ✅ Count consultations per week
foreach ($consultations as $consultDate) {
    $day = (int)date('j', strtotime($consultDate));
    $weekNum = (int)ceil(($day + date('N', strtotime(date('Y-m-01')))) / 7);
    if ($weekNum >= 1 && $weekNum <= $numWeeks) {
        $consultationData[$weekNum - 1]++;
    }
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
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link active" href="student_report.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
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
                    <i class="fas fa-chart-line"></i> Consultation Reports
                </div>
                <p class="page-subtitle">Track your weekly consultation activity this month</p>

                <!-- STATS CARD -->
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-calendar-check"></i></div>
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
        const numWeeks = consultationData.length;
        const weekLabels = Array.from({length: numWeeks}, (_, i) => `Week ${i + 1}`);

        const ctx = document.getElementById('consultChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: weekLabels,
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
                            label: context => `Consultations: ${context.parsed.y}`
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
