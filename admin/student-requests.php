<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Count leave applications */
$leave_pending = 0;
$late_pending = 0;

$leave_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM student_leave_applications WHERE status = 'pending'");
if ($leave_result) {
    $row = mysqli_fetch_assoc($leave_result);
    $leave_pending = intval($row['total'] ?? 0);
}

$late_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM student_late_entry_applications WHERE status = 'pending'");
if ($late_result) {
    $row = mysqli_fetch_assoc($late_result);
    $late_pending = intval($row['total'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Requests - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 25px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: 25px auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 3px solid #00897b;
            padding-bottom: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
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
            border: none;
            cursor: pointer;
            padding: 9px 14px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            font-size: 14px;
        }

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-dashboard {
            background: #00897b;
            color: white;
        }

        .btn-logout {
            background: #dc2626;
            color: white;
        }

        .info-box {
            background: #e0f2f1;
            color: #004d40;
            border-left: 5px solid #00897b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.7;
        }

        .request-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .request-card {
            background: #ffffff;
            border: 1px solid #d9eeee;
            border-top: 5px solid #00897b;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            min-height: 230px;
        }

        .request-card h3 {
            margin-top: 0;
            color: #004d40;
            font-size: 22px;
        }

        .request-card p {
            line-height: 1.7;
            color: #475569;
            margin-bottom: 18px;
        }

        .request-card a {
            background: #00897b;
            color: white;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }

        .badge {
            display: inline-block;
            background: #f59e0b;
            color: #111827;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .card-purple {
            border-top-color: #7c3aed;
        }

        .card-purple h3 {
            color: #5b21b6;
        }

        .card-purple a {
            background: #7c3aed;
        }

        .card-blue {
            border-top-color: #0ea5e9;
        }

        .card-blue h3 {
            color: #075985;
        }

        .card-blue a {
            background: #0ea5e9;
        }

        .card-gray {
            border-top-color: #64748b;
        }

        .card-gray h3 {
            color: #334155;
        }

        .card-gray a {
            background: #64748b;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .request-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1 {
            color: #7dd3fc;
        }

        body.dark-mode .info-box {
            background: #134e4a;
            color: #e5e7eb;
            border-left-color: #14b8a6;
        }

        body.dark-mode .request-card h3 {
            color: #7dd3fc;
        }

        body.dark-mode .request-card p {
            color: #cbd5e1;
        }

        body.dark-mode .badge {
            background: #fde68a;
            color: #78350f;
        }

        /* Fix only yellow badge text visibility */
        .container .request-grid .request-card .badge {
            background: #fde68a !important;
            color: #78350f !important;
            font-weight: 900 !important;
        }

        body.dark-mode .container .request-grid .request-card .badge {
            background: #fde68a !important;
            color: #78350f !important;
            font-weight: 900 !important;
        }

        /* Final fix: keep badges normal, align action buttons bottom-center */
        .request-card {
            display: flex !important;
            flex-direction: column !important;
        }

        .request-card .badge {
            display: inline-block !important;
            width: auto !important;
            max-width: fit-content !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
            background: #fde68a !important;
            color: #78350f !important;
            font-weight: 900 !important;
            font-size: 13px !important;
            margin-bottom: 12px !important;
        }

        .request-card p {
            margin-bottom: 18px !important;
        }

        .request-card a {
            margin-top: auto !important;
            align-self: center !important;
            color: #ffffff !important;
            font-weight: 800 !important;
        }

        /* Make request card clickable without changing design */
        .card-blue {
    border-top: 5px solid #2563eb !important;
}

.clickable-card {
    cursor: pointer;
}
    </style>
</head>

<body>

    <div class="container">

        <div class="header">
            <h1>Student Requests</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="../index.php" class="btn btn-home">Home</a>
                <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
                <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>

        <div class="info-box">
            Welcome, <strong><?php echo h($admin_name); ?></strong>.
            This page contains all student request modules in one place.
        </div>

        <div class="request-grid">
            <div class="request-card card-green clickable-card" onclick="window.location.href='room-applications.php'">
                <h3>Room Applications</h3>

                <span class="badge">Final Room Requests</span>

                <p>
                    Review student room preferences and assign available rooms and seats.
                </p>

                <a href="room-applications.php" onclick="event.stopPropagation();">
                    Open Room Applications
                </a>
            </div>
            <div class="request-card card-blue clickable-card" onclick="window.location.href='room-change-requests.php'">
                <h3>Room Change Requests</h3>

                <span class="badge">Resident Room Swap</span>

                <p>
                    Review room change requests from assigned residents and check compatibility with target rooms.
                </p>

                <a href="room-change-requests.php" onclick="event.stopPropagation();">
                    Open Room Change Requests
                </a>
            </div>

            <div class="request-card card-purple clickable-card" onclick="window.location.href='leave-applications.php'">
                <h3>Leave Applications</h3>

                <?php if ($leave_pending > 0): ?>
                    <span class="badge"><?php echo $leave_pending; ?> Pending</span>
                <?php else: ?>
                    <span class="badge">No Pending</span>
                <?php endif; ?>

                <p>
                    Review, approve, or reject hostel leave applications submitted by assigned residents.
                </p>

                <a href="leave-applications.php" onclick="event.stopPropagation();">Open Leave Applications</a>
            </div>

            <div class="request-card card-blue">
                <h3>Late Entry Applications</h3>
                <?php if ($late_pending > 0): ?>
                    <span class="badge"><?php echo $late_pending; ?> Pending</span>
                <?php else: ?>
                    <span class="badge">No Pending</span>
                <?php endif; ?>

                <p>
                    Review, approve, or reject late entry permission requests submitted by assigned residents.
                </p>
                <a href="late-entry-applications.php">Open Late Entry Applications</a>
            </div>

        </div>

    </div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>