    <?php
    session_start();
    require '../includes/db_connect.php';

    // ✅ Only logged-in admin can access
    if (!isset($_SESSION['admin_id'])) {
        header("Location: admin_login.php");
        exit();
    }

    // ===============================
    // ✅ HANDLE APPROVE / REJECT / RESCHEDULE
    // ===============================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id = $_POST['consultation_id'] ?? '';

        if ($action && $id) {
            try {
                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE consultation_requests SET status = 'Approved' WHERE id = ?");
                    $stmt->execute([$id]);

                } elseif ($action === 'reject') {
                    $stmt = $pdo->prepare("UPDATE consultation_requests SET status = 'Rejected' WHERE id = ?");
                    $stmt->execute([$id]);

                } elseif ($action === 'reschedule') {
                    $newDate = $_POST['new_date'] ?? '';
                    $newTime = $_POST['new_time'] ?? '';
                    if ($newDate && $newTime) {
                        $stmt = $pdo->prepare("UPDATE consultation_requests SET date = ?, time = ?, status = 'Rescheduled' WHERE id = ?");
                        $stmt->execute([$newDate, $newTime, $id]);
                    }
                }
                $_SESSION['success_message'] = "Consultation updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
            }
        }

        header("Location: approvals.php");
        exit();
    }

    // ===============================
    // ✅ FETCH CONSULTATION REQUESTS
    // ===============================
    $stmt = $pdo->prepare("
        SELECT c.id, u.fullname, c.requested, c.date, c.time, c.status
        FROM consultation_requests c
        JOIN users u ON c.student_id = u.id
        ORDER BY c.date DESC, c.time DESC
    ");
    $stmt->execute();
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $success_message = $_SESSION['success_message'] ?? '';
    $error_message = $_SESSION['error_message'] ?? '';
    unset($_SESSION['success_message'], $_SESSION['error_message']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Approvals - ASCOT Clinic</title>

        <!-- BOOTSTRAP / FONT AWESOME / CUSTOM STYLES -->
        <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/webfonts/all.min.css" rel="stylesheet">
        <link href="../admin/css/admin_dashboard.css" rel="stylesheet">
        <link href="../admin/css/approvals.css" rel="stylesheet">
    </head>
    <body>
        <!-- HEADER -->
        <header class="top-header">
            <div class="container-fluid">
                <div class="header-content d-flex align-items-center">
                    <img src="../img/logo.png" alt="ASCOT Logo" class="logo-img">
                    <div class="school-info">
                        <div class="republic">Republic of the Philippines</div>
                        <h1 class="school-name">AURORA STATE COLLEGE OF TECHNOLOGY</h1>
                        <div class="clinic-title">ONLINE SCHOOL CLINIC</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- DASHBOARD -->
        <div class="dashboard-container">
            <!-- SIDEBAR -->
            <aside class="sidebar">
                <nav class="sidebar-nav">
                    <a href="admin_dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i><span>Dashboard</span>
                    </a>

                    <div class="nav-group">
                        <button class="nav-item dropdown-btn" data-target="studentMenu">
                            <i class="fas fa-user-graduate"></i>
                            <span>Student Management</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="submenu" id="studentMenu">
                            <a href="students.php" class="submenu-item"><i class="fas fa-id-card"></i>Students Profile</a>
                            <a href="search_students.php" class="submenu-item"><i class="fas fa-search"></i>Search Students</a>
                        </div>
                    </div>

                    <div class="nav-group">
                        <button class="nav-item dropdown-btn" data-target="consultationMenu">
                            <i class="fas fa-stethoscope"></i>
                            <span>Consultation</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="submenu" id="consultationMenu">
                            <a href="view_records.php" class="submenu-item"><i class="fas fa-folder-open"></i>View Records</a>
                        </div>
                    </div>

                    <div class="nav-group">
                        <button class="nav-item dropdown-btn" data-target="appointmentsMenu">
                            <i class="fas fa-calendar-check"></i>
                            <span>Appointments</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="submenu show" id="appointmentsMenu">
                            <a href="calendar_view.php" class="submenu-item"><i class="fas fa-calendar-alt"></i>Calendar View</a>
                            <a href="approvals.php" class="submenu-item active"><i class="fas fa-check-circle"></i>Approvals</a>
                        </div>
                    </div>



                    <div class="nav-group">
                        <button class="nav-item dropdown-btn" data-target="reportsMenu">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                            <i class="fas fa-chevron-down arrow"></i>
                        </button>
                        <div class="submenu" id="reportsMenu">
                            <a href="monthly_summary.php" class="submenu-item"><i class="fas fa-file-invoice"></i>Monthly Summary</a>
                        </div>
                    </div>
                    <div class="nav-group">
                    <button class="nav-item dropdown-btn" data-target="adminMenu">
                        <i class="fas fa-cog"></i>
                        <span>Admin Tools</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </button>
                    <div class="submenu" id="adminMenu">
                        <a href="user_management.php" class="submenu-item">
                            <i class="fas fa-users-cog"></i>
                            User Management
                        </a>
                        <a href="access_logs.php" class="submenu-item">
                            <i class="fas fa-clipboard-list"></i>
                            Access Logs
                        </a>
                    </div>
                </div>

                    <br><br><br>
                    <a href="../logout.php" class="nav-item logout">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                    </a>
                </nav>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="main-content">
                <?php if ($success_message): ?>
                    <div class="alert alert-success text-center"><?= htmlspecialchars($success_message); ?></div>
                <?php elseif ($error_message): ?>
                    <div class="alert alert-danger text-center"><?= htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="approvals-container">
                    <div class="approvals-header">
                        <h2 class="approvals-title">
                            <i class="fas fa-check-circle"></i> CONSULTATION REQUESTS
                            <span class="pending-badge"><?= count($consultations); ?></span>
                        </h2>
                    </div>

                    <table class="approvals-table">
                        <thead>
                            <tr>
                                <th>NAME</th>
                                <th>CONCERN</th>
                                <th>REQUESTED DATE</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($consultations)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No consultation requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($consultations as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['fullname']); ?></strong></td>
                                        <td><?= htmlspecialchars($c['requested']); ?></td>
                                        <td><?= date('M d, Y g:i A', strtotime($c['date'].' '.$c['time'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?=
                                                $c['status'] === 'Pending' ? 'warning' :
                                                ($c['status'] === 'Approved' ? 'success' :
                                                ($c['status'] === 'Rejected' ? 'danger' :
                                                ($c['status'] === 'Rescheduled' ? 'info' : 'secondary')))
                                            ?>">
                                                <?= htmlspecialchars($c['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="consultation_id" value="<?= $c['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-approve" title="Approve">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="consultation_id" value="<?= $c['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-decline" title="Reject">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </form>

                                            <!-- Reschedule -->
                                            <button class="btn-reschedule" title="Reschedule"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rescheduleModal"
                                                data-id="<?= $c['id']; ?>"
                                                data-name="<?= htmlspecialchars($c['fullname']); ?>">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>

        <!-- RESCHEDULE MODAL -->
        <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="consultation_id" id="reschedule_id">

                    <div class="modal-header">
                        <h5 class="modal-title">Reschedule Consultation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label>New Date:</label>
                        <input type="date" name="new_date" class="form-control" required>
                        <label class="mt-2">New Time:</label>
                        <input type="time" name="new_time" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- BOOTSTRAP JS -->
        <script src="../assets/js/bootstrap.bundle.min.js"></script>
        <script>
            const rescheduleModal = document.getElementById('rescheduleModal');
            rescheduleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                document.getElementById('reschedule_id').value = id;
            });
        </script>
    </body>
    </html>
