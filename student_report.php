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
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --sidebar-width: 280px;
            --header-height: 80px;
        }

        * {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ========== ENHANCED HEADER DESIGN ========== */
        .header {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: var(--header-height);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0.05;
            z-index: -1;
        }

        .header .logo-img {
             height: 80px;
             width: 80px;
             margin-top: -15px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }

        .header .logo-img:hover {
            transform: scale(1.05);
        }

        .header .college-info {
            text-align: center;
        }

        .header .college-info h4 {
            font-size: 1rem;
            margin-bottom: 0.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header .college-info p {
            font-size: 0.85rem;
            margin-bottom: 0;
            color: #7f8c8d;
            font-weight: 600;
            letter-spacing: 1px;
        }

        /* ========== ENHANCED SIDEBAR DESIGN ========== */
        .sidebar {
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.95) 0%, rgba(52, 73, 94, 0.98) 100%);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.1);
            box-shadow: 8px 0 32px rgba(0,0,0,0.2);
            min-height: calc(100vh - var(--header-height));
            padding: 30px 0;
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            z-index: 999;
            overflow-y: auto;
            margin-left: -1px;
            margin-top: -1px;
        }

        .sidebar .nav {
            padding: 0 20px;
        }

        .sidebar .nav-link {
            color: #ecf0f1 !important;
            padding: 15px 20px;
            margin: 8px 0;
            border-radius: 12px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.2) 0%, rgba(41, 128, 185, 0.2) 100%);
            border-left: 4px solid #3498db;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 15px;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .sidebar .nav-link:hover i {
            transform: scale(1.2);
        }

        .sidebar .nav-link.active i {
            color: #3498db;
        }

        .logout-btn .nav-link {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.2) 0%, rgba(192, 57, 43, 0.2) 100%);
            border: 1px solid rgba(231, 76, 60, 0.3);
            margin-top: 20px;
        }

        .logout-btn .nav-link:hover {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.3) 0%, rgba(192, 57, 43, 0.3) 100%);
            border-left: 4px solid #e74c3c;
            transform: translateX(8px);
        }

        /* ========== MOBILE SIDEBAR ENHANCEMENTS ========== */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1100;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1.3rem;
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            transition: all 0.3s ease;
        }

        .mobile-menu-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 998;
            backdrop-filter: blur(5px);
        }

        /* ========== MAIN CONTENT ENHANCEMENTS ========== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            background: rgba(248, 249, 250, 0.95);
            backdrop-filter: blur(10px);
            min-height: calc(100vh - var(--header-height));
            margin-top: var(--header-height);
        }

        /* ========== ORIGINAL REPORT STYLES (PRESERVED) ========== */
        .page-title {
            background: #ffda6a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
            font-weight: bold;
            font-size: 1.8rem;
        }

        .page-subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stats-icon {
            background: #27ae60;
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stats-icon i {
            font-size: 2rem;
            color: white;
        }

        .stats-info h5 {
            color: #666;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .stats-info h2 {
            color: #333;
            font-weight: bold;
            font-size: 2.5rem;
            margin: 0;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            width: 700px;
            height: 400px;
            margin: 0 auto;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            color: #333;
            font-weight: bold;
            font-size: 1.3rem;
            margin: 0;
        }

        .chart-month {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }

        .chart-month i {
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .chart-container {
                width: 100%;
                height: 350px;
            }
            
            .stats-card {
                flex-direction: column;
                text-align: center;
            }
        }

        /* ========== RESPONSIVE BREAKPOINTS ========== */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -100%;
                width: 300px;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar.active {
                left: 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media (max-width: 767.98px) {
            :root {
                --header-height: 70px;
            }

            .header {
                padding: 10px 0;
            }

            .header .logo-img {
                height: 40px;
            }

            .main-content {
                padding: 15px;
                margin-top: 70px;
            }

            .mobile-menu-btn {
                top: 15px;
                left: 15px;
                padding: 10px 14px;
                font-size: 1.2rem;
            }
        }

        /* ========== CUSTOM SCROLLBAR ========== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
        }
    </style>
</head>

<body>
    <!-- MOBILE MENU BUTTON -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ENHANCED HEADER -->
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
            <!-- ENHANCED SIDEBAR -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit"></i> Update Profile</a>
                    <a class="nav-link" href="schedule_consultation.php"><i class="fas fa-calendar-alt"></i> Schedule Consultation</a>
                    <a class="nav-link active" href="student_report.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <a class="nav-link" href="student_announcement.php"><i class="fas fa-bullhorn"></i> Announcement</a>
                    <a class="nav-link" href="activity_logs.php"><i class="fas fa-clipboard-list"></i> Activity Logs</a>
                </nav>

                <div class="logout-btn mt-3">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- MAIN CONTENT (ORIGINAL DESIGN PRESERVED) -->
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
        // MOBILE SIDEBAR FUNCTIONALITY
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                
                const icon = mobileMenuBtn.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.className = 'fas fa-times';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                } else {
                    icon.className = 'fas fa-bars';
                    mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
                }
            }
            
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
                mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                mobileMenuBtn.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
            }
            
            if (mobileMenuBtn && sidebar && sidebarOverlay) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
                
                const navLinks = sidebar.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991.98) {
                            closeSidebar();
                        }
                    });
                });
                
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                });
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 991.98) {
                    closeSidebar();
                }
            });

            // ORIGINAL CHART CODE (PRESERVED)
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
        });
    </script>
</body>
</html>