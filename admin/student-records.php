<?php
session_start();
require_once '../includes/db.php';

// Admin + Super Admin can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Admin';
$role = $_SESSION['role'] ?? 'admin';

$success_msg = '';
$error_msg = '';

// -----------------------------
// UPDATE APPLICATION STATUS
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_application') {
    $student_record_id = intval($_POST['student_record_id']);
    $application_status = trim($_POST['application_status']);
    $room_no = trim($_POST['room_no'] ?? '');
    $seat_no = trim($_POST['seat_no'] ?? '');
    $admin_message = trim($_POST['admin_message'] ?? '');

    $allowed_status = ['pending', 'waiting', 'assigned', 'rejected'];

    if (!in_array($application_status, $allowed_status)) {
        $error_msg = "Invalid application status selected.";
    } elseif ($application_status === 'assigned' && (empty($room_no) || empty($seat_no))) {
        $error_msg = "Room No and Seat No are required when assigning a hostel seat.";
    } else {
        if ($application_status !== 'assigned') {
            $room_no = '';
            $seat_no = '';
        }

        if ($application_status === 'waiting' && empty($admin_message)) {
            $admin_message = "Please wait for further notice.";
        }

        if ($application_status === 'rejected' && empty($admin_message)) {
            $admin_message = "Your application was rejected. Please contact hostel authority.";
        }

        if ($application_status === 'assigned' && empty($admin_message)) {
            $admin_message = "Your hostel seat has been assigned.";
        }

        $stmt = mysqli_prepare(
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
            $stmt,
            "ssssii",
            $application_status,
            $room_no,
            $seat_no,
            $admin_message,
            $current_user_id,
            $student_record_id
        );

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0) {
            $success_msg = "Student application updated successfully.";
        } else {
            $error_msg = "Failed to update student application.";
        }

        mysqli_stmt_close($stmt);
    }
}

// -----------------------------
// MOVE STUDENT APPLICATION TO ADMIN RECYCLE BIN
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student_record') {
    $student_record_id = intval($_POST['student_record_id']);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE student_records
         SET is_deleted = 1,
             deleted_at = NOW(),
             deleted_by = ?,
             deleted_from = 'admin_panel'
         WHERE id = ? AND is_deleted = 0"
    );

    mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $student_record_id);

    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
        $success_msg = "Student application moved to Admin Recycle Bin.";
    } else {
        $error_msg = "Failed to move student application to recycle bin.";
    }

    mysqli_stmt_close($stmt);
}

// Fetch active student applications
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
            background-color: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.1);
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
            padding: 9px 14px;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-home {
            background-color: #64748b;
            color: white;
        }

        .btn-dashboard {
            background-color: #00897b;
            color: white;
        }

        .btn-recycle {
            background-color: #f59e0b;
            color: white;
        }

        .btn-logout {
            background-color: #dc2626;
            color: white;
        }

        .btn-update {
            background-color: #007bff;
            color: white;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .info-box {
            background: #e0f2f1;
            border-left: 5px solid #00897b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #004d40;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1350px;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background-color: #f1f8f7;
            color: #004d40;
            white-space: nowrap;
        }

        tr:hover {
            background-color: #f9fdfc;
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
            background-color: #f59e0b;
        }

        .badge-waiting {
            background-color: #0284c7;
        }

        .badge-assigned {
            background-color: #28a745;
        }

        .badge-rejected {
            background-color: #dc3545;
        }

        .review-form {
            min-width: 260px;
        }

        .review-form select,
        .review-form input,
        .review-form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 13px;
        }

        .review-form textarea {
            min-height: 70px;
            resize: vertical;
        }

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        form {
            margin: 0;
        }

        .small-text {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
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
        Logged in as: <strong><?php echo htmlspecialchars($name); ?></strong>
        |
        Role: <strong><?php echo htmlspecialchars($role); ?></strong>
        <br>
        This page shows hostel applications submitted by students. Admin can assign room/seat, put the student on waiting, or reject the application.
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Student Info</th>
                    <th>Academic Info</th>
                    <th>Guardian Info</th>
                    <th>Address / Reason</th>
                    <th>Status</th>
                    <th>Room / Seat</th>
                    <th>Admin Review</th>
                    <th>Delete</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>

                        <?php
                        $status_class = 'badge-pending';

                        if ($row['application_status'] === 'waiting') {
                            $status_class = 'badge-waiting';
                        } elseif ($row['application_status'] === 'assigned') {
                            $status_class = 'badge-assigned';
                        } elseif ($row['application_status'] === 'rejected') {
                            $status_class = 'badge-rejected';
                        }
                        ?>

                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                ID: <?php echo htmlspecialchars($row['institutional_id']); ?><br>
                                Email: <?php echo htmlspecialchars($row['user_email'] ?? 'N/A'); ?><br>
                                Phone: <?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                Department: <?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?><br>
                                Batch: <?php echo htmlspecialchars($row['batch'] ?? 'N/A'); ?><br>
                                Semester: <?php echo htmlspecialchars($row['semester'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                Guardian: <?php echo htmlspecialchars($row['guardian_name'] ?? 'N/A'); ?><br>
                                Phone: <?php echo htmlspecialchars($row['guardian_phone'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                <strong>Address:</strong><br>
                                <span class="small-text">
                                    <?php echo nl2br(htmlspecialchars($row['address'] ?? 'N/A')); ?>
                                </span>
                                <br><br>
                                <strong>Reason:</strong><br>
                                <span class="small-text">
                                    <?php echo nl2br(htmlspecialchars($row['reason_for_hostel'] ?? 'N/A')); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($row['application_status']); ?>
                                </span>
                                <br><br>
                                <span class="small-text">
                                    <?php echo nl2br(htmlspecialchars($row['admin_message'] ?? 'No message yet.')); ?>
                                </span>
                            </td>

                            <td>
                                Room No:
                                <strong><?php echo htmlspecialchars($row['room_no'] ?? 'N/A'); ?></strong>
                                <br>
                                Seat No:
                                <strong><?php echo htmlspecialchars($row['seat_no'] ?? 'N/A'); ?></strong>
                            </td>

                            <td>
                                <form method="POST" class="review-form">
                                    <input type="hidden" name="action" value="update_application">
                                    <input type="hidden" name="student_record_id" value="<?php echo intval($row['id']); ?>">

                                    <label>Status</label>
                                    <select name="application_status" required>
                                        <option value="pending" <?php if ($row['application_status'] === 'pending') echo 'selected'; ?>>Pending</option>
                                        <option value="waiting" <?php if ($row['application_status'] === 'waiting') echo 'selected'; ?>>Waiting</option>
                                        <option value="assigned" <?php if ($row['application_status'] === 'assigned') echo 'selected'; ?>>Assigned</option>
                                        <option value="rejected" <?php if ($row['application_status'] === 'rejected') echo 'selected'; ?>>Rejected</option>
                                    </select>

                                    <label>Room No</label>
                                    <input type="text" name="room_no" value="<?php echo htmlspecialchars($row['room_no'] ?? ''); ?>" placeholder="Example: A-203">

                                    <label>Seat No</label>
                                    <input type="text" name="seat_no" value="<?php echo htmlspecialchars($row['seat_no'] ?? ''); ?>" placeholder="Example: 2">

                                    <label>Admin Message</label>
                                    <textarea name="admin_message" placeholder="Write message for student"><?php echo htmlspecialchars($row['admin_message'] ?? ''); ?></textarea>

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

<script src="../assets/js/theme.js"></script>
</body>
</html>