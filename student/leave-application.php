<?php
require_once 'resident_guard.php';

$user_id = intval($_SESSION['user_id']);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$success_msg = '';
$error_msg = '';

$app_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_records
     WHERE user_id = ?
     AND is_deleted = 0
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($app_stmt, "i", $user_id);
mysqli_stmt_execute($app_stmt);
$app_result = mysqli_stmt_get_result($app_stmt);
$application = mysqli_fetch_assoc($app_result);
mysqli_stmt_close($app_stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_from = $_POST['leave_from'] ?? '';
    $leave_to = $_POST['leave_to'] ?? '';
    $destination = trim($_POST['destination'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');

    if ($leave_from === '' || $leave_to === '' || $destination === '' || $reason === '' || $guardian_phone === '') {
        $error_msg = "Please fill in all required fields.";
    } elseif ($leave_to < $leave_from) {
        $error_msg = "Leave end date cannot be before leave start date.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO student_leave_applications
            (
                user_id, student_record_id, student_name, institutional_id,
                room_no, seat_no, leave_from, leave_to, destination,
                reason, guardian_phone, status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iisssssssss",
            $user_id,
            $application['id'],
            $application['full_name'],
            $application['institutional_id'],
            $application['room_no'],
            $application['seat_no'],
            $leave_from,
            $leave_to,
            $destination,
            $reason,
            $guardian_phone
        );

        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Leave application submitted successfully.";
        } else {
            $error_msg = "Failed to submit leave application.";
        }

        mysqli_stmt_close($stmt);
    }
}

$requests = [];

$list_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_leave_applications
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

mysqli_stmt_bind_param($list_stmt, "i", $user_id);
mysqli_stmt_execute($list_stmt);
$list_result = mysqli_stmt_get_result($list_stmt);

while ($row = mysqli_fetch_assoc($list_result)) {
    $requests[] = $row;
}

mysqli_stmt_close($list_stmt);

function statusClass($status) {
    if ($status === 'approved') return 'approved';
    if ($status === 'rejected') return 'rejected';
    return 'pending';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Application - UniStay</title>
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
            max-width: 1050px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            color: #4c1d95;
        }

        h2 {
            color: #4c1d95;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #7c3aed;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }

        .btn-gray {
            background: #64748b;
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

        .format-box {
            background: #f5f3ff;
            border-left: 5px solid #7c3aed;
            padding: 18px;
            border-radius: 8px;
            line-height: 1.8;
            margin-bottom: 25px;
            color: #3b0764;
        }

        .form-card,
        .request-card {
            background: #f8fafc;
            border: 1px solid #e9d5ff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group.full {
            grid-column: span 2;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #4c1d95;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }

        .pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .approved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .application-text {
            margin-top: 15px;
            background: white;
            border: 1px solid #e9d5ff;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.8;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .form-card,
        body.dark-mode .request-card,
        body.dark-mode .application-text {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode label {
            color: #c4b5fd;
        }

        body.dark-mode input,
        body.dark-mode textarea {
            background: #1e293b;
            color: #e5e7eb;
            border-color: #334155;
        }

        @media (max-width: 750px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }
        }
        /* ===============================
   DARK MODE VISIBILITY FIX
   Leave Application Page
================================ */

body.dark-mode .format-box {
    background: #1e1b4b !important;
    color: #e0e7ff !important;
    border-left: 5px solid #8b5cf6 !important;
}

body.dark-mode .format-box strong {
    color: #ffffff !important;
}

body.dark-mode .alert-success {
    background: #14532d !important;
    color: #dcfce7 !important;
    border-left: 5px solid #22c55e !important;
}

body.dark-mode .alert-error {
    background: #7f1d1d !important;
    color: #fee2e2 !important;
    border-left: 5px solid #ef4444 !important;
}

body.dark-mode .request-card {
    background: #0f172a !important;
    color: #e5e7eb !important;
    border: 1px solid #334155 !important;
}

body.dark-mode .application-text {
    background: #111827 !important;
    color: #f8fafc !important;
    border: 1px solid #475569 !important;
}

body.dark-mode .application-text strong {
    color: #ffffff !important;
}

body.dark-mode .pending {
    background: #fed7aa !important;
    color: #7c2d12 !important;
}

body.dark-mode .approved {
    background: #bbf7d0 !important;
    color: #14532d !important;
}

body.dark-mode .rejected {
    background: #fecaca !important;
    color: #7f1d1d !important;
}

body.dark-mode input,
body.dark-mode textarea {
    background: #1e293b !important;
    color: #ffffff !important;
    border: 1px solid #475569 !important;
}

body.dark-mode input::placeholder,
body.dark-mode textarea::placeholder {
    color: #94a3b8 !important;
}

body.dark-mode label {
    color: #c4b5fd !important;
}

body.dark-mode .form-card {
    background: #0f172a !important;
    border: 1px solid #334155 !important;
}

body.dark-mode h1,
body.dark-mode h2 {
    color: #c4b5fd !important;
}
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Leave Application</h1>

        <div>
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="dashboard.php" class="btn btn-gray">Dashboard</a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <div class="format-box">
        <strong>Pre-made Application Format:</strong><br>
        To,<br>
        The Hall Authority,<br>
        UniStay Hostel Management System.<br><br>

        Subject: Application for Leave.<br><br>

        I am <?php echo h($application['full_name']); ?>,
        ID <?php echo h($application['institutional_id']); ?>,
        Room <?php echo h($application['room_no']); ?>,
        Seat <?php echo h($application['seat_no']); ?>.
        I would like to request leave by filling the form below.
    </div>

    <div class="form-card">
        <h2>Submit Leave Application</h2>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Leave From *</label>
                    <input type="date" name="leave_from" required>
                </div>

                <div class="form-group">
                    <label>Leave To *</label>
                    <input type="date" name="leave_to" required>
                </div>

                <div class="form-group">
                    <label>Destination *</label>
                    <input type="text" name="destination" placeholder="Where will you go?" required>
                </div>

                <div class="form-group">
                    <label>Guardian Phone *</label>
                    <input type="text" name="guardian_phone" placeholder="Guardian contact number" required>
                </div>

                <div class="form-group full">
                    <label>Reason *</label>
                    <textarea name="reason" placeholder="Write your reason for leave..." required></textarea>
                </div>
            </div>

            <button type="submit" class="btn">Submit Leave Application</button>
        </form>
    </div>

    <h2>My Leave Applications</h2>

    <?php if (empty($requests)): ?>
        <div class="request-card">
            No leave application submitted yet.
        </div>
    <?php else: ?>
        <?php foreach ($requests as $req): ?>
            <div class="request-card">
                <strong>Status:</strong>
                <span class="status <?php echo h(statusClass($req['status'])); ?>">
                    <?php echo h($req['status']); ?>
                </span>

                <div class="application-text">
                    To,<br>
                    The Hall Authority,<br>
                    UniStay Hostel Management System.<br><br>

                    <strong>Subject:</strong> Application for Leave.<br><br>

                    I am <?php echo h($req['student_name']); ?>,
                    ID <?php echo h($req['institutional_id']); ?>,
                    Room <?php echo h($req['room_no']); ?>,
                    Seat <?php echo h($req['seat_no']); ?>.
                    I request leave from <strong><?php echo h($req['leave_from']); ?></strong>
                    to <strong><?php echo h($req['leave_to']); ?></strong>.
                    My destination is <strong><?php echo h($req['destination']); ?></strong>.
                    Reason: <?php echo nl2br(h($req['reason'])); ?><br>
                    Guardian Phone: <?php echo h($req['guardian_phone']); ?>.
                    <br><br>
                    <strong>Admin Message:</strong>
                    <?php echo h($req['admin_message'] ?? 'No admin message yet.'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>