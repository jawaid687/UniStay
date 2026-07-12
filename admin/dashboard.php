<?php
session_start();

// Security: Allow both Admin and Super Admin to access Admin Dashboard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';
$institutional_id = isset($_SESSION['institutional_id']) ? $_SESSION['institutional_id'] : 'N/A';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'unknown';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f8f7;
            color: #1f2937;
        }

        .dashboard-container {
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #00897b;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            color: #004d40;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-home {
            background-color: #6c757d;
            color: white;
        }

        .btn-home:hover {
            background-color: #5a6268;
        }

        .btn-super {
            background-color: #00897b;
            color: white;
        }

        .btn-super:hover {
            background-color: #00695c;
        }

        .btn-team {
            background-color: #2563eb;
            color: white;
        }

        .btn-team:hover {
            background-color: #1d4ed8;
        }

        .btn-support {
            background-color: #7c3aed;
            color: white;
        }

        .btn-support:hover {
            background-color: #6d28d9;
        }

        .btn-recycle {
            background-color: #f59e0b;
            color: white;
        }

        .btn-recycle:hover {
            background-color: #d97706;
        }

        .btn-logout {
            background-color: #dc3545;
            color: white;
        }

        .btn-logout:hover {
            background-color: #b02a37;
        }

        .welcome-box {
            background: #e0f2f1;
            border-left: 5px solid #00897b;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 30px;
            color: #004d40;
        }

        .welcome-box h2 {
            margin-top: 0;
        }

        .role-badge {
            background-color: #00897b;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .module-card {
            background: #ffffff;
            border: 1px solid #d9eeee;
            border-top: 5px solid #00897b;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .module-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .module-card h3 {
            margin-top: 0;
            color: #004d40;
        }

        .module-card p {
            color: #555;
            line-height: 1.6;
        }

        .module-card a {
            display: inline-block;
            margin-top: 10px;
            background-color: #00897b;
            color: white;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 5px;
            font-weight: bold;
        }

        .module-card a:hover {
            background-color: #00695c;
        }

        .module-card .support-link {
            background-color: #7c3aed;
        }

        .module-card .support-link:hover {
            background-color: #6d28d9;
        }

        .module-card .recycle-link {
            background-color: #f59e0b;
        }

        .module-card .recycle-link:hover {
            background-color: #d97706;
        }

        .note-box {
            margin-top: 30px;
            background: #fff3cd;
            color: #664d03;
            border-left: 5px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin: 15px;
                padding: 20px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>

<body>

    <div class="dashboard-container">

        <div class="header">
            <h1>Admin Dashboard</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

                <a href="../index.php" class="btn btn-home">Home</a>

                <a href="../about.php" class="btn btn-team">Team</a>

                <a href="../support.php" class="btn btn-support">Support Page</a>

                <?php if ($role === 'super_admin'): ?>
                    <a href="../super-admin/dashboard.php" class="btn btn-super">Super Admin Portal</a>
                <?php endif; ?>

                <a href="recycle-bin.php" class="btn btn-recycle">Admin Recycle Bin</a>

                <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>

        <div class="welcome-box">
            <h2>Welcome, <?php echo htmlspecialchars($name); ?></h2>

            <p>
                ID Number:
                <strong><?php echo htmlspecialchars($institutional_id); ?></strong>
            </p>

            <p>
                Current Role:
                <span class="role-badge">
                    <?php echo strtoupper(htmlspecialchars($role)); ?>
                </span>
            </p>

            <?php if ($role === 'super_admin'): ?>
                <p>
                    You are accessing the Admin Dashboard as a Super Admin.
                    You have permission to control admin-level hostel management features.
                </p>
            <?php else: ?>
                <p>
                    You are accessing the Admin Dashboard as an Admin.
                    You can manage hostel operations assigned to the admin panel.
                </p>
            <?php endif; ?>
        </div>

        <h2 style="color:#004d40;">Hostel Management Modules</h2>

        <div class="module-grid">

            <div class="module-card">
                <h3>Room Management</h3>
                <p>
                <p>
                    View hostel rooms, hall-wise room list, capacity, available seats, occupied seats, and update room status.
                </p>
                </p>
                <a href="rooms.php" class="btn btn-green">Manage Rooms</a>
            </div>

            <div class="module-card">
                <h3>Student Records</h3>
                <p>
                   View student hostel records, room assignment details, compatibility information, and request history.
                <a href="student-records.php">Manage Students</a>
            </div>

            <div class="module-card">
                <h3>Support Messages</h3>
                <p>
                    View messages submitted from the UniStay Support page.
                    Admin can read, mark, and delete support messages.
                </p>
                <a href="support-messages.php" class="support-link">Open Messages</a>
            </div>

            <div class="module-card">
                <h3>Service Requests</h3>
                <p>
                    Monitor student maintenance complaints and service requests,
                    then assign or update their progress.
                </p>
                <a href="../maintenance/index.php">View Requests</a>
            </div>

            <div class="module-card">
                <h3>Admin Recycle Bin</h3>
                <p>
                    Restore or permanently delete records that were deleted from
                    admin-level modules such as Student Records.
                </p>
                <a href="recycle-bin.php" class="recycle-link">Open Recycle Bin</a>
            </div>

            <div class="module-card">
                <h3>Manage Users</h3>
                <p>
                    Approve, disable, update, or delete staff and student users only.
                </p>
                <a href="manage-users.php">Manage Users</a>
            </div>
            <div class="module-card">
                <h3>Student Requests</h3>
                <p>Open all student request modules such as room change, leave, and late entry applications.</p>
                <a href="student-requests.php">Open Student Requests</a>
            </div>

        </div>

        <div class="note-box">
            <strong>Note:</strong>
            This admin panel is accessible by both <strong>Admin</strong> and
            <strong>Super Admin</strong>. Student records deleted from the Admin Panel
            will go to the <strong>Admin Recycle Bin</strong>, where Admin and Super Admin
            can restore them. Support messages submitted from the Support page will appear
            in the <strong>Support Messages</strong> module.
        </div>

    </div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>