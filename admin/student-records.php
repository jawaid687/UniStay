<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Admin';
$role = $_SESSION['role'] ?? 'admin';

$success_msg = '';
$error_msg = '';
$info_msg = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------
   UPDATE STUDENT APPLICATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_application') {
    $student_record_id = intval($_POST['student_record_id'] ?? 0);
    $application_status = trim($_POST['application_status'] ?? '');
    $room_no = trim($_POST['room_no'] ?? '');
    $seat_no = trim($_POST['seat_no'] ?? '');
    $admin_message = trim($_POST['admin_message'] ?? '');

    /*
        Important:
        Admin Review dropdown will NOT have pending.
        Pending is only automatic display status.
    */
    $allowed_status = ['waiting', 'assigned', 'rejected'];

    if ($student_record_id <= 0) {
        $error_msg = "Invalid student application selected.";
    } elseif ($application_status === '') {
        $error_msg = "Please select a review decision.";
    } elseif (!in_array($application_status, $allowed_status)) {
        $error_msg = "Invalid review decision selected.";
    } elseif ($application_status === 'assigned' && ($room_no === '' || $seat_no === '')) {
        $error_msg = "Room No and Seat No are required when assigning a hostel seat.";
    } else {
        if ($application_status !== 'assigned') {
            $room_no = '';
            $seat_no = '';
        }

        if ($admin_message === '') {
            $admin_message = null;
        }

        /*
            First check current data.
            This prevents always showing success when nothing changed.
        */
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT application_status, room_no, seat_no, admin_message
             FROM student_records
             WHERE id = ? AND is_deleted = 0
             LIMIT 1"
        );

        mysqli_stmt_bind_param($check_stmt, "i", $student_record_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $current_status, $current_room_no, $current_seat_no, $current_admin_message);

        if (mysqli_stmt_fetch($check_stmt)) {
            mysqli_stmt_close($check_stmt);

            $current_room_no = $current_room_no ?? '';
            $current_seat_no = $current_seat_no ?? '';
            $current_admin_message_compare = $current_admin_message ?? '';
            $new_admin_message_compare = $admin_message ?? '';

            if (
                $current_status === $application_status &&
                $current_room_no === $room_no &&
                $current_seat_no === $seat_no &&
                $current_admin_message_compare === $new_admin_message_compare
            ) {
                $info_msg = "No changes were made.";
            } else {
                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE student_records
                     SET application_status = ?,
                         room_no = ?,
                         seat_no = ?,
                         admin_message = ?,
                         reviewed_by = ?,
                         reviewed_at = NOW()
                     WHERE id = ? AND is_deleted = 0"
                );

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssii",
                    $application_status,
                    $room_no,
                    $seat_no,
                    $admin_message,
                    $current_user_id,
                    $student_record_id
                );

                if (mysqli_stmt_execute($update_stmt)) {
                    $success_msg = "Student application updated successfully.";
                } else {
                    $error_msg = "Failed to update student application.";
                }

                mysqli_stmt_close($update_stmt);
            }
        } else {
            mysqli_stmt_close($check_stmt);
            $error_msg = "Student application not found.";
        }
    }
}

/* -------------------------------------------------
   MOVE APPLICATION TO ADMIN RECYCLE BIN
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_student_record') {
    $student_record_id = intval($_POST['student_record_id'] ?? 0);

    if ($student_record_id <= 0) {
        $error_msg = "Invalid student application selected.";
    } else {
        $delete_stmt = mysqli_prepare(
            $conn,
            "UPDATE student_records
             SET is_deleted = 1,
                 deleted_at = NOW(),
                 deleted_by = ?,
                 deleted_from = 'admin_panel'
             WHERE id = ? AND is_deleted = 0"
        );

        mysqli_stmt_bind_param($delete_stmt, "ii", $current_user_id, $student_record_id);

        if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) === 1) {
            $success_msg = "Student application moved to Admin Recycle Bin.";
        } else {
            $error_msg = "Failed to move student application to recycle bin.";
        }

        mysqli_stmt_close($delete_stmt);
    }
}

/* -------------------------------------------------
   FETCH ACTIVE APPLICATIONS
------------------------------------------------- */
$query = "SELECT sr.*, u.email AS user_email
          FROM student_records sr
          LEFT JOIN users u
          ON sr.user_id = u.id OR sr.institutional_id = u.institutional_id
          WHERE sr.is_deleted = 0
          ORDER BY
          CASE
              WHEN sr.application_status = 'pending' THEN 1
              WHEN sr.application_status = 'waiting' THEN 2
              WHEN sr.application_status = 'assigned' THEN 3
              WHEN sr.application_status = 'rejected' THEN 4
              ELSE 5
          END,
          sr.id DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Applications - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
            background: var(--card-color);
            color: var(--text-color);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 5px 18px var(--shadow-color);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            color: var(--heading-color);
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-dashboard {
            background: var(--primary-color);
            color: white;
        }

        .btn-recycle {
            background: #f59e0b;
            color: white;
        }

        .btn-logout,
        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .btn-update {
            background: var(--primary-color);
            color: white;
            width: 100%;
            margin-top: 6px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .info-box {
            background: var(--info-box-bg);
            border-left: 5px solid var(--primary-color);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: var(--info-box-text);
        }

        .info-box strong {
            color: var(--info-box-text);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #dc3545;
        }

        .alert-info {
            background: #fff8e1;
            color: #664d03;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #f59e0b;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1350px;
            border-collapse: collapse;
            margin-top: 25px;
            background: var(--card-color);
            color: var(--text-color);
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: var(--table-header);
            color: var(--heading-color);
            white-space: nowrap;
        }

        .application-table tbody tr:hover {
            background: transparent !important;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: inline-block;
            text-transform: uppercase;
        }

        .badge-pending {
            background: #f59e0b;
            color: #000;
        }

        .badge-waiting {
            background: #0284c7;
        }

        .badge-assigned {
            background: #28a745;
        }

        .badge-rejected {
            background: #dc3545;
        }

        .review-form {
            min-width: 280px;
        }

        .review-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .review-form select,
        .review-form input,
        .review-form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 13px;
            background: #ffffff;
            color: #1f2937;
        }

        body.dark-mode .review-form select,
        body.dark-mode .review-form input,
        body.dark-mode .review-form textarea {
            background: #1e293b;
            color: #e5e7eb;
            border: 1px solid #334155;
        }

        .review-form textarea {
            min-height: 70px;
            resize: vertical;
        }

        .assigned-fields {
            display: none;
        }

        .small-text {
            color: var(--muted-text);
            font-size: 13px;
            line-height: 1.5;
        }

        .current-status-text {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted-text);
        }

        form {
            margin: 0;
        }

        @media (max-width: 900px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Student Applications</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
            <a href="recycle-bin.php" class="btn btn-recycle">Admin Recycle Bin</a>
            <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <div class="info-box">
        Logged in as: <strong><?php echo h($name); ?></strong>
        |
        Role: <strong><?php echo h($role); ?></strong>
        <br>
        Pending is automatic. Admin can only choose Waiting, Assigned, or Rejected from the review dropdown.
    </div>

    <?php if ($success_msg !== ''): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg !== ''): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <?php if ($info_msg !== ''): ?>
        <div class="alert-info"><?php echo h($info_msg); ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="application-table">
            <thead>
                <tr>
                    <th>Student Info</th>
                    <th>Academic Info</th>
                    <th>Guardian Info</th>
                    <th>Address / Reason</th>
                    <th>Current Status</th>
                    <th>Room / Seat</th>
                    <th>Admin Review</th>
                    <th>Delete</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>

                        <?php
                        $current_status = $row['application_status'] ?? 'pending';

                        $status_class = 'badge-pending';

                        if ($current_status === 'waiting') {
                            $status_class = 'badge-waiting';
                        } elseif ($current_status === 'assigned') {
                            $status_class = 'badge-assigned';
                        } elseif ($current_status === 'rejected') {
                            $status_class = 'badge-rejected';
                        }

                        $admin_message_display = !empty($row['admin_message'])
                            ? nl2br(h($row['admin_message']))
                            : '<span class="small-text">No admin message.</span>';
                        ?>

                        <tr>
                            <td>
                                <strong><?php echo h($row['full_name'] ?? 'N/A'); ?></strong><br>
                                ID: <?php echo h($row['institutional_id'] ?? 'N/A'); ?><br>
                                Email: <?php echo h($row['user_email'] ?? 'N/A'); ?><br>
                                Phone: <?php echo h($row['phone'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                Department: <?php echo h($row['department'] ?? 'N/A'); ?><br>
                                Batch: <?php echo h($row['batch'] ?? 'N/A'); ?><br>
                                Semester: <?php echo h($row['semester'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                Guardian: <?php echo h($row['guardian_name'] ?? 'N/A'); ?><br>
                                Phone: <?php echo h($row['guardian_phone'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                <strong>Address:</strong><br>
                                <span class="small-text">
                                    <?php echo nl2br(h($row['address'] ?? 'N/A')); ?>
                                </span>
                                <br><br>

                                <strong>Reason:</strong><br>
                                <span class="small-text">
                                    <?php echo nl2br(h($row['reason_for_hostel'] ?? 'N/A')); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo h($current_status); ?>
                                </span>

                                <br><br>
                                <?php echo $admin_message_display; ?>
                            </td>

                            <td>
                                Room No:
                                <strong><?php echo !empty($row['room_no']) ? h($row['room_no']) : 'N/A'; ?></strong>
                                <br>
                                Seat No:
                                <strong><?php echo !empty($row['seat_no']) ? h($row['seat_no']) : 'N/A'; ?></strong>
                            </td>

                            <td>
                                <form method="POST" class="review-form">
                                    <input type="hidden" name="action" value="update_application">
                                    <input type="hidden" name="student_record_id" value="<?php echo intval($row['id']); ?>">

                                    <label>Review Decision</label>
                                    <select name="application_status" class="status-select" required>
                                        <option value="" selected disabled>Select Decision</option>
                                        <option value="waiting">Waiting</option>
                                        <option value="assigned">Assigned</option>
                                        <option value="rejected">Rejected</option>
                                    </select>

                                    <div class="current-status-text">
                                        Current status: <strong><?php echo h($current_status); ?></strong>
                                    </div>
                                    <br>

                                    <div class="assigned-fields">
                                        <label>Room No</label>
                                        <input
                                            type="text"
                                            name="room_no"
                                            value="<?php echo h($row['room_no'] ?? ''); ?>"
                                            placeholder="Example: A-203"
                                        >

                                        <label>Seat No</label>
                                        <input
                                            type="text"
                                            name="seat_no"
                                            value="<?php echo h($row['seat_no'] ?? ''); ?>"
                                            placeholder="Example: 2"
                                        >
                                    </div>

                                    <label>Admin Message</label>
                                    <textarea name="admin_message" placeholder="Write message for student"><?php echo h($row['admin_message'] ?? ''); ?></textarea>

                                    <button type="submit" class="btn btn-update">Update</button>
                                </form>
                            </td>

                            <td>
                                <form method="POST" onsubmit="return confirm('Move this student application to Admin Recycle Bin? You can restore it later.');">
                                    <input type="hidden" name="action" value="delete_student_record">
                                    <input type="hidden" name="student_record_id" value="<?php echo intval($row['id']); ?>">
                                    <button type="submit" class="btn btn-delete">Delete</button>
                                </form>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">
                            No student applications found yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function updateAssignedFields(form, shouldClear) {
    const select = form.querySelector('.status-select');
    const assignedFields = form.querySelector('.assigned-fields');
    const roomInput = form.querySelector('input[name="room_no"]');
    const seatInput = form.querySelector('input[name="seat_no"]');

    if (!select || !assignedFields) return;

    if (select.value === 'assigned') {
        assignedFields.style.display = 'block';

        if (roomInput) roomInput.required = true;
        if (seatInput) seatInput.required = true;
    } else {
        assignedFields.style.display = 'none';

        if (roomInput) {
            roomInput.required = false;
            if (shouldClear) roomInput.value = '';
        }

        if (seatInput) {
            seatInput.required = false;
            if (shouldClear) seatInput.value = '';
        }
    }
}

document.querySelectorAll('.review-form').forEach(function(form) {
    const select = form.querySelector('.status-select');

    updateAssignedFields(form, false);

    if (select) {
        select.addEventListener('change', function() {
            updateAssignedFields(form, true);
        });
    }
});
</script>

<script src="../assets/js/theme.js"></script>
</body>
</html>