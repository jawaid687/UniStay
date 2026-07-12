<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';

$success_msg = '';
$error_msg = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = intval($_POST['application_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $admin_message = trim($_POST['admin_message'] ?? '');

    $allowed_status = ['pending', 'approved', 'rejected'];

    if ($application_id <= 0) {
        $error_msg = "Invalid leave application selected.";
    } elseif (!in_array($status, $allowed_status)) {
        $error_msg = "Invalid status selected.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE student_leave_applications
             SET status = ?,
                 admin_message = ?,
                 reviewed_at = NOW()
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "ssi", $status, $admin_message, $application_id);

        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Leave application updated successfully.";
        } else {
            $error_msg = "Failed to update leave application.";
        }

        mysqli_stmt_close($stmt);
    }
}

$filter = $_GET['filter'] ?? 'all';

$where = "";
if ($filter === 'pending') {
    $where = "WHERE status = 'pending'";
} elseif ($filter === 'approved') {
    $where = "WHERE status = 'approved'";
} elseif ($filter === 'rejected') {
    $where = "WHERE status = 'rejected'";
}

$applications = [];

$query = "
    SELECT *
    FROM student_leave_applications
    $where
    ORDER BY created_at DESC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $applications[] = $row;
    }
}

function statusClass($status) {
    if ($status === 'approved') return 'status-approved';
    if ($status === 'rejected') return 'status-rejected';
    return 'status-pending';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Applications - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 1200px;
            margin: 25px auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .header h1 {
            margin: 0;
            color: #4c1d95;
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

        .btn-submit {
            background: #7c3aed;
            color: white;
        }

        .btn-logout {
            background: #dc2626;
            color: white;
        }

        .info-box {
            background: #f5f3ff;
            color: #3b0764;
            border-left: 5px solid #7c3aed;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .filter-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .filter-link {
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 999px;
            font-weight: bold;
            background: #ede9fe;
            color: #5b21b6;
        }

        .filter-link.active {
            background: #7c3aed;
            color: white;
        }

        .application-card {
            background: #f8fafc;
            border: 1px solid #e9d5ff;
            border-left: 5px solid #7c3aed;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .application-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .application-top h2 {
            margin: 0;
            color: #4c1d95;
        }

        .status-badge {
            padding: 7px 12px;
            border-radius: 999px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }

        .status-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }

        .detail-box {
            background: white;
            border: 1px solid #e9d5ff;
            padding: 12px;
            border-radius: 8px;
        }

        .detail-box strong {
            display: block;
            color: #4c1d95;
            margin-bottom: 4px;
        }

        .application-text {
            background: white;
            border: 1px solid #e9d5ff;
            border-radius: 8px;
            padding: 15px;
            line-height: 1.8;
            margin-bottom: 16px;
        }

        .review-form {
            background: white;
            border: 1px solid #e9d5ff;
            border-radius: 10px;
            padding: 16px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 220px 1fr auto;
            gap: 12px;
            align-items: end;
        }

        label {
            display: block;
            font-weight: bold;
            color: #4c1d95;
            margin-bottom: 6px;
        }

        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        textarea {
            min-height: 70px;
            resize: vertical;
        }

        .empty-box {
            background: #fff8e1;
            border-left: 5px solid #f59e0b;
            color: #664d03;
            padding: 18px;
            border-radius: 8px;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .application-card,
        body.dark-mode .detail-box,
        body.dark-mode .application-text,
        body.dark-mode .review-form {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .application-top h2,
        body.dark-mode .detail-box strong,
        body.dark-mode label {
            color: #c4b5fd;
        }

        body.dark-mode .info-box {
            background: #1e1b4b;
            color: #e0e7ff;
            border-left-color: #8b5cf6;
        }

        body.dark-mode select,
        body.dark-mode textarea {
            background: #1e293b;
            color: white;
            border-color: #475569;
        }

        body.dark-mode .status-badge.status-pending {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-badge.status-approved {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-badge.status-rejected {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        @media (max-width: 900px) {
            .details-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Leave Applications</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="student-requests.php" class="btn btn-dashboard">Student Requests</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <div class="info-box">
        Logged in as <strong><?php echo h($admin_name); ?></strong>.
        Admin can approve, reject, or keep leave applications pending.
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <div class="filter-box">
        <a href="leave-applications.php?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="leave-applications.php?filter=pending" class="filter-link <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="leave-applications.php?filter=approved" class="filter-link <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="leave-applications.php?filter=rejected" class="filter-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-box">
            No leave applications found.
        </div>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="application-card">
                <div class="application-top">
                    <h2><?php echo h($app['student_name']); ?> - Leave Request</h2>

                    <span class="status-badge <?php echo h(statusClass($app['status'])); ?>">
                        <?php echo h($app['status']); ?>
                    </span>
                </div>

                <div class="details-grid">
                    <div class="detail-box">
                        <strong>Student ID</strong>
                        <?php echo h($app['institutional_id']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Room / Seat</strong>
                        Room <?php echo h($app['room_no']); ?>, Seat <?php echo h($app['seat_no']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Submitted At</strong>
                        <?php echo h($app['created_at']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Leave From</strong>
                        <?php echo h($app['leave_from']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Leave To</strong>
                        <?php echo h($app['leave_to']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Guardian Phone</strong>
                        <?php echo h($app['guardian_phone']); ?>
                    </div>
                </div>

                <div class="application-text">
                    To,<br>
                    The Hall Authority,<br>
                    UniStay Hostel Management System.<br><br>

                    <strong>Subject:</strong> Application for Leave.<br><br>

                    I am <?php echo h($app['student_name']); ?>,
                    ID <?php echo h($app['institutional_id']); ?>,
                    Room <?php echo h($app['room_no']); ?>,
                    Seat <?php echo h($app['seat_no']); ?>.
                    I request leave from <strong><?php echo h($app['leave_from']); ?></strong>
                    to <strong><?php echo h($app['leave_to']); ?></strong>.
                    My destination is <strong><?php echo h($app['destination']); ?></strong>.
                    Reason: <?php echo nl2br(h($app['reason'])); ?>.
                </div>

                <form method="POST" class="review-form">
                    <input type="hidden" name="application_id" value="<?php echo intval($app['id']); ?>">

                    <div class="form-grid">
                        <div>
                            <label>Status</label>
                            <select name="status" required>
                                <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $app['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label>Admin Message</label>
                            <textarea name="admin_message" placeholder="Write message for student..."><?php echo h($app['admin_message'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-submit">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>