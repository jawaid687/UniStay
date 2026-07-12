<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$student_name = $_SESSION['name'] ?? 'Student';

$success_msg = '';
$error_msg = '';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeText($value)
{
    return strtolower(trim((string)$value));
}

/*
    Load assigned student record.
*/
$student_record = null;

$record_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_records
     WHERE user_id = ?
     AND is_deleted = 0
     AND application_status = 'assigned'
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($record_stmt, "i", $user_id);
mysqli_stmt_execute($record_result = $record_stmt);
$record_result = mysqli_stmt_get_result($record_stmt);
$student_record = mysqli_fetch_assoc($record_result);
mysqli_stmt_close($record_stmt);

if (!$student_record) {
    $error_msg = "Room change request is only available after your room has been assigned.";
}

/*
    Load active room assignment.
*/
$active_assignment = null;
$current_room_id = null;
$current_room_no = $student_record['room_no'] ?? '';
$current_seat_no = $student_record['seat_no'] ?? '';

if ($student_record) {
    $assign_stmt = mysqli_prepare(
        $conn,
        "SELECT 
            ra.assignment_id,
            ra.room_id,
            ra.seat_no,
            r.room_number
         FROM room_assignments ra
         JOIN rooms r ON ra.room_id = r.room_id
         WHERE ra.student_record_id = ?
         AND ra.assignment_status = 'active'
         ORDER BY ra.assignment_id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($assign_stmt, "i", $student_record['id']);
    mysqli_stmt_execute($assign_stmt);
    $assign_result = mysqli_stmt_get_result($assign_stmt);
    $active_assignment = mysqli_fetch_assoc($assign_result);
    mysqli_stmt_close($assign_stmt);

    if ($active_assignment) {
        $current_room_id = intval($active_assignment['room_id']);
        $current_room_no = $active_assignment['room_number'];
        $current_seat_no = $active_assignment['seat_no'];
    }
}

/*
    Check if student already has a pending room change request.
*/
$pending_request = null;

if ($student_record) {
    $pending_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM student_room_change_requests
         WHERE student_record_id = ?
         AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($pending_stmt, "i", $student_record['id']);
    mysqli_stmt_execute($pending_stmt);
    $pending_result = mysqli_stmt_get_result($pending_stmt);
    $pending_request = mysqli_fetch_assoc($pending_result);
    mysqli_stmt_close($pending_stmt);
}

/*
    Load student compatibility profile for filtering rooms.
*/
$compatibility = null;

if ($student_record) {
    $compat_stmt = mysqli_prepare(
        $conn,
        "SELECT gender, preferred_hall
         FROM roommate_preferences
         WHERE student_record_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($compat_stmt, "i", $student_record['id']);
    mysqli_stmt_execute($compat_stmt);
    $compat_result = mysqli_stmt_get_result($compat_stmt);
    $compatibility = mysqli_fetch_assoc($compat_result);
    mysqli_stmt_close($compat_stmt);
}

$student_gender = normalizeText($compatibility['gender'] ?? '');
$student_preferred_hall = normalizeText($compatibility['preferred_hall'] ?? '');

/*
    Load available rooms for preferred target room dropdown.
*/
$available_rooms = [];

$room_sql = "
    SELECT *
    FROM view_available_rooms
    ORDER BY
        CASE WHEN LOWER(hall_code) = ? THEN 0 ELSE 1 END,
        hall_code ASC,
        floor_number ASC,
        room_number ASC
";

$room_stmt = mysqli_prepare($conn, $room_sql);
mysqli_stmt_bind_param($room_stmt, "s", $student_preferred_hall);
mysqli_stmt_execute($room_stmt);
$room_result = mysqli_stmt_get_result($room_stmt);

while ($room = mysqli_fetch_assoc($room_result)) {
    $room_gender = normalizeText($room['gender_type'] ?? '');

    if ($student_gender !== '' && $room_gender !== '' && $student_gender !== $room_gender) {
        continue;
    }

    if ($current_room_id && intval($room['room_id']) === intval($current_room_id)) {
        continue;
    }

    $available_rooms[] = $room;
}

mysqli_stmt_close($room_stmt);

/*
    Submit room change request.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student_record && !$pending_request) {
    $requested_room_id = intval($_POST['requested_room_id'] ?? 0);
    $preferred_seat_no = trim($_POST['preferred_seat_no'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    $preferred_room_no = null;

    if ($reason === '') {
        $error_msg = "Please write a reason for your room change request.";
    } else {
        if ($requested_room_id > 0) {
            $selected_room = null;

            $selected_stmt = mysqli_prepare(
                $conn,
                "SELECT 
                    v.room_id,
                    v.room_number,
                    v.hall_code,
                    v.hall_name,
                    v.gender_type,
                    v.type_name,
                    v.available_seats
                 FROM view_available_rooms v
                 WHERE v.room_id = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($selected_stmt, "i", $requested_room_id);
            mysqli_stmt_execute($selected_stmt);
            $selected_result = mysqli_stmt_get_result($selected_stmt);
            $selected_room = mysqli_fetch_assoc($selected_result);
            mysqli_stmt_close($selected_stmt);

            if (!$selected_room) {
                $error_msg = "Selected preferred room is not available.";
            } else {
                $selected_gender = normalizeText($selected_room['gender_type'] ?? '');

                if ($student_gender !== '' && $selected_gender !== '' && $student_gender !== $selected_gender) {
                    $error_msg = "You cannot request a room from a different gender hall.";
                } else {
                    $preferred_room_no = $selected_room['room_number'];
                }
            }
        }

        if ($error_msg === '') {
            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO student_room_change_requests
                (
                    user_id,
                    student_record_id,
                    student_name,
                    institutional_id,
                    current_room_no,
                    current_seat_no,
                    current_room_id,
                    preferred_room_no,
                    requested_room_id,
                    preferred_seat_no,
                    reason,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );

            mysqli_stmt_bind_param(
                $insert_stmt,
                "iissssisiss",
                $user_id,
                $student_record['id'],
                $student_record['full_name'],
                $student_record['institutional_id'],
                $current_room_no,
                $current_seat_no,
                $current_room_id,
                $preferred_room_no,
                $requested_room_id,
                $preferred_seat_no,
                $reason
            );

            if (mysqli_stmt_execute($insert_stmt)) {
                $success_msg = "Room change request submitted successfully. Please wait for admin review.";

                mysqli_stmt_close($insert_stmt);

                $pending_stmt = mysqli_prepare(
                    $conn,
                    "SELECT *
                     FROM student_room_change_requests
                     WHERE student_record_id = ?
                     AND status = 'pending'
                     ORDER BY id DESC
                     LIMIT 1"
                );

                mysqli_stmt_bind_param($pending_stmt, "i", $student_record['id']);
                mysqli_stmt_execute($pending_stmt);
                $pending_result = mysqli_stmt_get_result($pending_stmt);
                $pending_request = mysqli_fetch_assoc($pending_result);
                mysqli_stmt_close($pending_stmt);
            } else {
                $error_msg = "Failed to submit room change request.";
                mysqli_stmt_close($insert_stmt);
            }
        }
    }
}

/*
    Load request history.
*/
$request_history = [];

if ($student_record) {
    $history_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM student_room_change_requests
         WHERE student_record_id = ?
         ORDER BY created_at DESC"
    );

    mysqli_stmt_bind_param($history_stmt, "i", $student_record['id']);
    mysqli_stmt_execute($history_stmt);
    $history_result = mysqli_stmt_get_result($history_stmt);

    while ($row = mysqli_fetch_assoc($history_result)) {
        $request_history[] = $row;
    }

    mysqli_stmt_close($history_stmt);
}

function statusClass($status)
{
    if ($status === 'approved') return 'status-approved';
    if ($status === 'rejected') return 'status-rejected';
    return 'status-pending';
}

function statusLabel($status)
{
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];

    return $labels[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Room Change Request - UniStay</title>
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
            max-width: 1100px;
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

        .btn-dashboard {
            background: #00897b;
            color: white;
        }

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-submit {
            background: #00897b;
            color: white;
            margin-top: 12px;
        }

        .info-box {
            background: #e0f2f1;
            color: #004d40;
            border-left: 5px solid #00897b;
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

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-box {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            padding: 12px;
            border-radius: 8px;
            line-height: 1.6;
        }

        .detail-box strong {
            display: block;
            color: #004d40;
            margin-bottom: 4px;
        }

        .form-box {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .form-box h2,
        .history-box h2 {
            margin-top: 0;
            color: #004d40;
        }

        label {
            display: block;
            font-weight: bold;
            color: #004d40;
            margin-bottom: 6px;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            color: #1f2937;
            margin-bottom: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .small-note {
            display: block;
            margin-top: -8px;
            margin-bottom: 14px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .history-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-left: 5px solid #00897b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 14px;
            line-height: 1.7;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 999px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
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

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .form-box,
        body.dark-mode .detail-box,
        body.dark-mode .history-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .form-box h2,
        body.dark-mode .history-box h2,
        body.dark-mode .detail-box strong,
        body.dark-mode label {
            color: #7dd3fc;
        }

        body.dark-mode .info-box {
            background: #134e4a;
            color: #e5e7eb;
            border-left-color: #14b8a6;
        }

        body.dark-mode select,
        body.dark-mode input,
        body.dark-mode textarea {
            background: #1e293b;
            color: white;
            border-color: #475569;
        }

        body.dark-mode select option {
            background: #1e293b;
            color: white;
        }

        body.dark-mode .small-note {
            color: #cbd5e1;
        }

        body.dark-mode .status-pending {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-approved {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-rejected {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<script>
(function () {
    const theme =
        localStorage.getItem('theme') ||
        localStorage.getItem('unistayTheme') ||
        localStorage.getItem('themeMode');

    const darkMode =
        theme === 'dark' ||
        localStorage.getItem('darkMode') === 'true' ||
        localStorage.getItem('darkMode') === 'enabled';

    if (darkMode) {
        document.body.classList.add('dark-mode');
    }
})();
</script>

<div class="container">

    <div class="header">
        <h1>Room Change Request</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="dashboard.php" class="btn btn-dashboard">Student Dashboard</a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <?php if ($student_record): ?>

        <div class="info-box">
            This page is for assigned residents only. You can request a room change, and admin will check compatibility with the target room before approval.
        </div>

        <div class="details-grid">
            <div class="detail-box">
                <strong>Name</strong>
                <?php echo h($student_record['full_name']); ?>
            </div>

            <div class="detail-box">
                <strong>Student ID</strong>
                <?php echo h($student_record['institutional_id']); ?>
            </div>

            <div class="detail-box">
                <strong>Current Room / Seat</strong>
                Room <?php echo h($current_room_no ?: 'N/A'); ?> |
                Seat <?php echo h($current_seat_no ?: 'N/A'); ?>
            </div>
        </div>

        <?php if ($pending_request): ?>
            <div class="info-box">
                You already have a pending room change request. Please wait for admin review before submitting another request.
            </div>
        <?php else: ?>
            <div class="form-box">
                <h2>Submit Room Change Request</h2>

                <form method="POST">
                    <label>Preferred Target Room</label>
                    <select name="requested_room_id">
                        <option value="">Any suitable room</option>

                        <?php foreach ($available_rooms as $room): ?>
                            <?php
                            $isPreferredHall = (
                                $student_preferred_hall !== '' &&
                                normalizeText($room['hall_code'] ?? '') === $student_preferred_hall
                            );
                            ?>

                            <option value="<?php echo intval($room['room_id']); ?>">
                                Room <?php echo h($room['room_number']); ?>
                                - <?php echo h($room['hall_name'] ?? 'Hall N/A'); ?>
                                - <?php echo h($room['type_name']); ?>
                                - Available Seats: <?php echo h($room['available_seats']); ?>
                                <?php echo $isPreferredHall ? ' - Preferred Hall' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="small-note">
                        Optional. If you are not sure, select “Any suitable room” and admin will choose using compatibility.
                    </small>

                    <label>Preferred Seat No</label>
                    <input type="text" name="preferred_seat_no" placeholder="Example: 1 or leave blank">

                    <label>Reason for Room Change</label>
                    <textarea name="reason" placeholder="Write why you want to change your room..." required></textarea>

                    <button type="submit" class="btn btn-submit">
                        Submit Room Change Request
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="history-box">
            <h2>Room Change Request History</h2>

            <?php if (empty($request_history)): ?>
                <div class="info-box">
                    No room change request history found.
                </div>
            <?php else: ?>
                <?php foreach ($request_history as $request): ?>
                    <div class="history-card">
                        <p>
                            <span class="status-badge <?php echo h(statusClass($request['status'])); ?>">
                                <?php echo h(statusLabel($request['status'])); ?>
                            </span>
                        </p>

                        <p>
                            <strong>Current Room:</strong>
                            Room <?php echo h($request['current_room_no']); ?> |
                            Seat <?php echo h($request['current_seat_no']); ?>
                        </p>

                        <p>
                            <strong>Preferred Room:</strong>
                            <?php echo h($request['preferred_room_no'] ?: 'Any suitable room'); ?>
                            |
                            <strong>Preferred Seat:</strong>
                            <?php echo h($request['preferred_seat_no'] ?: 'Any'); ?>
                        </p>

                        <p>
                            <strong>Reason:</strong>
                            <?php echo nl2br(h($request['reason'])); ?>
                        </p>

                        <?php if ($request['status'] === 'approved'): ?>
                            <p>
                                <strong>Approved Room:</strong>
                                <?php echo h($request['approved_room_no'] ?? 'N/A'); ?>
                                |
                                <strong>Approved Seat:</strong>
                                <?php echo h($request['approved_seat_no'] ?? 'N/A'); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($request['admin_message'])): ?>
                            <p>
                                <strong>Admin Message:</strong>
                                <?php echo h($request['admin_message']); ?>
                            </p>
                        <?php endif; ?>

                        <p>
                            <strong>Submitted At:</strong>
                            <?php echo h($request['created_at']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="info-box">
            Room change request is locked because your room has not been assigned yet.
        </div>
    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>