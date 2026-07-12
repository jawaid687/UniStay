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
    $entry_date = $_POST['entry_date'] ?? '';
    $expected_entry_time = $_POST['expected_entry_time'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');

    if ($entry_date === '' || $expected_entry_time === '' || $reason === '' || $guardian_phone === '') {
        $error_msg = "Please fill in all required fields.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO student_late_entry_applications
            (
                user_id, student_record_id, student_name, institutional_id,
                room_no, seat_no, entry_date, expected_entry_time,
                reason, guardian_phone, status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iissssssss",
            $user_id,
            $application['id'],
            $application['full_name'],
            $application['institutional_id'],
            $application['room_no'],
            $application['seat_no'],
            $entry_date,
            $expected_entry_time,
            $reason,
            $guardian_phone
        );

        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Late entry application submitted successfully.";
        } else {
            $error_msg = "Failed to submit late entry application.";
        }

        mysqli_stmt_close($stmt);
    }
}

$requests = [];

$list_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_late_entry_applications
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
    <title>Late Entry Application - UniStay</title>
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
            border-bottom: 3px solid #0ea5e9;
            padding-bottom: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            color: #075985;
        }

        h2 {
            color: #075985;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #0ea5e9;
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
            background: #e0f2fe;
            border-left: 5px solid #0ea5e9;
            padding: 18px;
            border-radius: 8px;
            line-height: 1.8;
            margin-bottom: 25px;
            color: #075985;
        }

        .form-card,
        .request-card {
            background: #f8fafc;
            border: 1px solid #bae6fd;
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
            color: #075985;
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
            border: 1px solid #bae6fd;
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
            color: #7dd3fc;
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
   Late Entry Application Page
================================ */

body.dark-mode .format-box {
    background: #082f49 !important;
    color: #e0f2fe !important;
    border-left: 5px solid #0ea5e9 !important;
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
    color: #7dd3fc !important;
}

body.dark-mode .form-card {
    background: #0f172a !important;
    border: 1px solid #334155 !important;
}

body.dark-mode h1,
body.dark-mode h2 {
    color: #7dd3fc !important;
}

/* Fix date and time picker icons in Chrome dark mode */
body.dark-mode input[type="date"]::-webkit-calendar-picker-indicator,
body.dark-mode input[type="time"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(1.8) !important;
    opacity: 1 !important;
    cursor: pointer;
}

/* Make time input text clearly visible */
body.dark-mode input[type="time"] {
    color-scheme: dark !important;
    color: #ffffff !important;
}

/* Make date input text clearly visible */
body.dark-mode input[type="date"] {
    color-scheme: dark !important;
    color: #ffffff !important;
}
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Late Entry Application</h1>

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

        Subject: Application for Late Entry Permission.<br><br>

        I am <?php echo h($application['full_name']); ?>,
        ID <?php echo h($application['institutional_id']); ?>,
        Room <?php echo h($application['room_no']); ?>,
        Seat <?php echo h($application['seat_no']); ?>.
        I would like to request permission for late entry by filling the form below.
    </div>

    <div class="form-card">
        <h2>Submit Late Entry Application</h2>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Entry Date *</label>
                    <input type="date" name="entry_date" required>
                </div>

                <div class="form-group">
                    <label>Expected Entry Time *</label>
                    <input type="time" name="expected_entry_time" required>
                </div>

                <div class="form-group">
                    <label>Guardian Phone *</label>
                    <input type="text" name="guardian_phone" placeholder="Guardian contact number" required>
                </div>

                <div class="form-group full">
                    <label>Reason *</label>
                    <textarea name="reason" placeholder="Write your reason for late entry..." required></textarea>
                </div>
            </div>

            <button type="submit" class="btn">Submit Late Entry Application</button>
        </form>
    </div>

    <h2>My Late Entry Applications</h2>

    <?php if (empty($requests)): ?>
        <div class="request-card">
            No late entry application submitted yet.
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

                    <strong>Subject:</strong> Application for Late Entry Permission.<br><br>

                    I am <?php echo h($req['student_name']); ?>,
                    ID <?php echo h($req['institutional_id']); ?>,
                    Room <?php echo h($req['room_no']); ?>,
                    Seat <?php echo h($req['seat_no']); ?>.
                    I request permission for late entry on
                    <strong><?php echo h($req['entry_date']); ?></strong>
                    at <strong><?php echo h($req['expected_entry_time']); ?></strong>.
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