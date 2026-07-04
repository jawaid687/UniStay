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
   RESTORE STUDENT APPLICATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_student_record') {
    $student_record_id = intval($_POST['student_record_id'] ?? 0);

    if ($student_record_id <= 0) {
        $error_msg = "Invalid student application selected.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE student_records
             SET is_deleted = 0,
                 deleted_at = NULL,
                 deleted_by = NULL,
                 deleted_from = NULL
             WHERE id = ?
             AND is_deleted = 1
             AND deleted_from = 'admin_panel'"
        );

        mysqli_stmt_bind_param($stmt, "i", $student_record_id);

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
            $success_msg = "Student application restored successfully.";
        } else {
            $error_msg = "Failed to restore student application.";
        }

        mysqli_stmt_close($stmt);
    }
}

/* -------------------------------------------------
   PERMANENT DELETE STUDENT APPLICATION
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'permanent_delete_student_record') {
    $student_record_id = intval($_POST['student_record_id'] ?? 0);

    if ($student_record_id <= 0) {
        $error_msg = "Invalid student application selected.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM student_records
             WHERE id = ?
             AND is_deleted = 1
             AND deleted_from = 'admin_panel'"
        );

        mysqli_stmt_bind_param($stmt, "i", $student_record_id);

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
            $success_msg = "Student application permanently deleted.";
        } else {
            $error_msg = "Failed to permanently delete student application.";
        }

        mysqli_stmt_close($stmt);
    }
}

/* -------------------------------------------------
   FETCH DELETED APPLICATIONS
------------------------------------------------- */
$query = "SELECT 
              sr.*,
              u.email AS user_email,
              d.name AS deleted_by_name
          FROM student_records sr
          LEFT JOIN users u
              ON sr.user_id = u.id OR sr.institutional_id = u.institutional_id
          LEFT JOIN users d
              ON sr.deleted_by = d.id
          WHERE sr.is_deleted = 1
          AND sr.deleted_from = 'admin_panel'
          ORDER BY sr.deleted_at DESC, sr.id DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Recycle Bin - UniStay</title>
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
            transition: all 0.3s ease;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-dashboard {
            background: var(--primary-color);
            color: white;
        }

        .btn-students {
            background: #007bff;
            color: white;
        }

        .btn-restore {
            background: #28a745;
            color: white;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .info-box {
            background: var(--info-box-bg);
            color: var(--info-box-text);
            border-left: 5px solid var(--primary-color);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
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
            min-width: 1250px;
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

        .recycle-table tbody tr:hover {
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

        .small-text {
            color: var(--muted-text);
            font-size: 13px;
            line-height: 1.5;
        }

        .action-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 150px;
        }

        .action-group .btn {
            width: 100%;
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
        <h1>Admin Recycle Bin</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
            <a href="student-records.php" class="btn btn-students">Student Applications</a>
        </div>
    </div>

    <div class="info-box">
        Logged in as: <strong><?php echo h($name); ?></strong>
        |
        Role: <strong><?php echo h($role); ?></strong>
        <br>
        Deleted student hostel applications from the Admin Panel appear here. You can restore them or permanently delete them.
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
        <table class="recycle-table">
            <thead>
                <tr>
                    <th>Student Info</th>
                    <th>Academic Info</th>
                    <th>Guardian Info</th>
                    <th>Address / Reason</th>
                    <th>Status</th>
                    <th>Room / Seat</th>
                    <th>Deleted Info</th>
                    <th>Actions</th>
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
                                Deleted At:<br>
                                <strong><?php echo h($row['deleted_at'] ?? 'N/A'); ?></strong>

                                <br><br>

                                Deleted By:<br>
                                <strong><?php echo h($row['deleted_by_name'] ?? 'Unknown'); ?></strong>
                            </td>

                            <td>
                                <div class="action-group">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="restore_student_record">
                                        <input type="hidden" name="student_record_id" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="btn btn-restore">Restore</button>
                                    </form>

                                    <form method="POST" onsubmit="return confirm('Are you sure? This application will be permanently deleted and cannot be restored.');">
                                        <input type="hidden" name="action" value="permanent_delete_student_record">
                                        <input type="hidden" name="student_record_id" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="btn btn-delete">Permanent Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">
                            Admin Recycle Bin is empty.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>