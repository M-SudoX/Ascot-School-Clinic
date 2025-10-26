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
    <title>Student Report - ASCOT Clinic</title>
    
    <!-- Bootstrap -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="assets/webfonts/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding-top: 80px;
            line-height: 1.6;
        }

        /* Header Styles - SAME AS DASHBOARD */
        .top-header {
            background: 
            linear-gradient(90deg, 
                #ffda6a 0%, 
                #ffda6a 30%, 
                #FFF5CC 70%, 
                #ffffff 100%);
            padding: 0.75rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 80px;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }

        .logo-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .school-info {
            flex: 1;
        }

        .republic {
            font-size: 0.7rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            color: #555;
        }

        .school-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0.1rem 0;
            line-height: 1.2;
            color: #555;
        }

        .clinic-title {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
            color: #555;
        }

        /* Mobile Menu Toggle - SAME AS DASHBOARD */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 95px;
            left: 20px;
            z-index: 1025;
            background: var(--primary);
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
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        /* Dashboard Container - SAME AS DASHBOARD */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }

        /* Sidebar Styles - SAME AS DASHBOARD */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1020;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.9rem 1.25rem;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #f8f9fa;
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, transparent 100%);
            color: #555;
            border-left: 8px solid #ffda6a;
        }

        .nav-item i {
            width: 22px;
            margin-right: 0.9rem;
            font-size: 1.1rem;
            color: #555;
        }

        .nav-item span {
            flex: 1;
            color: #555;
        }

        .nav-item.logout {
            color: var(--danger);
            margin-top: auto;
        }

        .nav-item.logout:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        /* Main Content - SAME AS DASHBOARD */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
            margin-left: 260px;
            margin-top: 0;
        }

        /* Sidebar Overlay for Mobile - SAME AS DASHBOARD */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1019;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* WELCOME SECTION - SAME AS DASHBOARD */
        .welcome-section {
            background: linear-gradient(110deg, #fff7da 50%, #fff7da 50%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(206, 224, 144, 0.2);
            border-left: 10px solid #ffda6a;
        }

        .welcome-content h1 {
            color: #555;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            color: var(--gray);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* KEEPING YOUR ORIGINAL REPORT STYLES */
        .page-title {
            background: linear-gradient(135deg, rgba(255, 218, 106, 0.9) 0%, rgba(255, 247, 222, 0.95) 100%);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            color: #555;
            font-weight: 800;
            font-size: 1.8rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .page-subtitle {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .stats-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stats-icon {
            background: linear-gradient(135deg, #ffda6a, #ffda6a);
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: #555;
        }

        .stats-icon i {
            font-size: 2rem;
            color: #555;
        }

        .stats-info h5 {
            color: #7f8c8d;
            margin-bottom: 10px;
            font-size: 1rem;
            font-weight: 600;
        }

        .stats-info h2 {
            color: #555;
            font-weight: 800;
            font-size: 2.5rem;
            margin: 0;
        }

        .chart-container {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.95) 100%);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            width: 700px;
            height: 400px;
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,0.2);
            color: #555;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            color: #555;
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0;
        }

        .chart-month {
            background: linear-gradient(135deg, #ffda6a, #ffda6a);
            color: #555;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .chart-month i {
            margin-right: 5px;
        }

        /* Responsive Design - COMBINED FROM BOTH */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 992px) {
            .school-name {
                font-size: 1rem;
            }

            .logo-img {
                width: 50px;
                height: 50px;
            }

            .chart-container {
                width: 100%;
                height: 350px;
                color: #555;
            }
            
            .stats-card {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .top-header {
                height: 70px;
                padding: 0.5rem 0;
            }
            
            .mobile-menu-toggle {
                display: block;
                top: 85px;
                left: 20px;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
                z-index: 1020;
                transform: translateX(-100%);
                overflow-y: auto;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                top: 70px;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                padding: 2rem 1.25rem 1.25rem;
                width: 100%;
                margin-left: 0;
            }

            .header-content {
                padding: 0 1rem;
            }

            .school-name {
                font-size: 0.9rem;
            }

            .republic, .clinic-title {
                font-size: 0.65rem;
            }

            .page-title {
                font-size: 1.5rem;
                padding: 15px;
            }

            .chart-container {
                padding: 20px;
                height: 300px;
            }

            .mobile-menu-toggle {
                top: 80px;
                width: 45px;
                height: 45px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.75rem 1rem 1rem;
            }
            
            .stats-card {
                padding: 20px;
            }

            .stats-info h2 {
                font-size: 2rem;
            }

            .chart-container {
                padding: 15px;
                height: 250px;
            }

            .chart-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img {
                width: 40px;
                height: 40px;
            }
            
            .school-name {
                font-size: 0.8rem;
            }
            
            .republic, .clinic-title {
                font-size: 0.6rem;
            }
            
            .mobile-menu-toggle {
                width: 45px;
                height: 45px;
                top: 80px;
                left: 15px;
            }
            
            .main-content {
                padding: 1.5rem 1rem 1rem;
            }
        }

        @media (max-width: 375px) {
            .mobile-menu-toggle {
                top: 75px;
                left: 15px;
                width: 40px;
                height: 40px;
            }
            
            .main-content {
                padding: 1.25rem 0.75rem 0.75rem;
            }
        }

        /* Animations */
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

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>

<body>
    <!-- Mobile Menu Toggle Button - SAME AS DASHBOARD -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile - SAME AS DASHBOARD -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Header - SAME AS DASHBOARD -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="header-content">
                <img src="img/logo.png" alt="ASCOT Logo" class="logo-img">
                <div class="school-info">
                    <div class="republic">Republic of the Philippines</div>
                    <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                    <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Sidebar - SAME AS DASHBOARD -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="student_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <a href="update_profile.php" class="nav-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>

                <a href="schedule_consultation.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule Consultation</span>
                </a>

                <a href="student_report.php" class="nav-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>

                <a href="student_announcement.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcement</span>
                </a>

                <a href="activity_logs.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Activity Logs</span>
                </a>
                
                <a href="logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- WELCOME SECTION - SAME AS DASHBOARD -->
            <div class="welcome-section fade-in">
                <div class="welcome-content">
                    <h1>Your Consultation Reports 📊</h1>
                    <p>Track your monthly consultation activity and statistics</p>
                </div>
            </div>

            <div class="page-title fade-in">
                <i class="fas fa-chart-line"></i> Consultation Reports
            </div>
            <p class="page-subtitle">Track your weekly consultation activity this month</p>

            <!-- STATS CARD -->
            <div class="stats-card fade-in">
                <div class="stats-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stats-info">
                    <h5>Total Consultations This Month</h5>
                    <h2><?php echo $totalConsults; ?></h2>
                </div>
            </div>

            <!-- CHART CONTAINER -->
            <div class="chart-container fade-in">
                <div class="chart-header">
                    <h3 class="chart-title">Weekly Consultation History</h3>
                    <div class="chart-month">
                        <i class="fas fa-calendar-alt"></i>
                        <strong><?php echo date('F Y'); ?></strong>
                    </div>
                </div>
                <canvas id="consultChart"></canvas>
            </div>
        </main>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // MOBILE MENU FUNCTIONALITY - SAME AS DASHBOARD
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
            });

            // Close sidebar when clicking nav items on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', function() {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                    });
                });
            }

            // Add loading animations
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
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