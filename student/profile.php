<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getValue($array, $keys, $default = 'N/A')
{
    foreach ((array)$keys as $key) {
        if (isset($array[$key]) && $array[$key] !== null && $array[$key] !== '') {
            return $array[$key];
        }
    }

    return $default;
}

function statusBadgeClass($status)
{
    $status = strtolower((string)$status);

    if (in_array($status, ['assigned', 'approved', 'active', 'verified'])) {
        return 'status-good';
    }

    if (in_array($status, ['pending', 'waiting'])) {
        return 'status-pending';
    }

    if (in_array($status, ['rejected', 'inactive', 'blocked'])) {
        return 'status-bad';
    }

    return 'status-neutral';
}

/* Load user account */
$user = [];

$user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result) ?: [];
mysqli_stmt_close($user_stmt);

/* Load student record */
$student = [];

$student_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_records
     WHERE user_id = ?
     AND is_deleted = 0
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($student_stmt, "i", $user_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result) ?: [];
mysqli_stmt_close($student_stmt);

$student_record_id = intval($student['id'] ?? 0);

/* Load compatibility / roommate preference */
$compatibility = [];

if ($student_record_id > 0) {
    $compat_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM roommate_preferences
         WHERE student_record_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($compat_stmt, "i", $student_record_id);
    mysqli_stmt_execute($compat_stmt);
    $compat_result = mysqli_stmt_get_result($compat_stmt);
    $compatibility = mysqli_fetch_assoc($compat_result) ?: [];
    mysqli_stmt_close($compat_stmt);
}

/* Load active room assignment */
$room = [];

if ($student_record_id > 0) {
    $room_stmt = mysqli_prepare(
        $conn,
        "SELECT 
            ra.assignment_id,
            ra.seat_no AS assigned_seat_no,
            ra.assigned_date,
            ra.assignment_status,
            r.room_id,
            r.room_number,
            r.floor_number,
            h.hall_code,
            h.hall_name,
            h.gender_type,
            rt.type_name,
            rt.capacity,
            rt.monthly_fee
         FROM room_assignments ra
         LEFT JOIN rooms r ON ra.room_id = r.room_id
         LEFT JOIN halls h ON r.hall_id = h.hall_id
         LEFT JOIN room_types rt ON r.room_type_id = rt.room_type_id
         WHERE ra.student_record_id = ?
         AND ra.assignment_status = 'active'
         ORDER BY ra.assignment_id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($room_stmt, "i", $student_record_id);
    mysqli_stmt_execute($room_stmt);
    $room_result = mysqli_stmt_get_result($room_stmt);
    $room = mysqli_fetch_assoc($room_result) ?: [];
    mysqli_stmt_close($room_stmt);
}

/* Load latest room application */
$room_application = [];

if ($student_record_id > 0) {
    $app_stmt = mysqli_prepare(
        $conn,
        "SELECT 
            ra.*,
            rt.type_name AS preferred_room_type
         FROM room_applications ra
         LEFT JOIN room_types rt ON ra.preferred_room_type_id = rt.room_type_id
         WHERE ra.student_record_id = ?
         ORDER BY ra.application_id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($app_stmt, "i", $student_record_id);
    mysqli_stmt_execute($app_stmt);
    $app_result = mysqli_stmt_get_result($app_stmt);
    $room_application = mysqli_fetch_assoc($app_result) ?: [];
    mysqli_stmt_close($app_stmt);
}

/* Load latest room change request */
$room_change = [];

if ($student_record_id > 0) {
    $change_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM student_room_change_requests
         WHERE student_record_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($change_stmt, "i", $student_record_id);
    mysqli_stmt_execute($change_stmt);
    $change_result = mysqli_stmt_get_result($change_stmt);
    $room_change = mysqli_fetch_assoc($change_result) ?: [];
    mysqli_stmt_close($change_stmt);
}

$display_name = getValue($student, 'full_name', getValue($user, ['name', 'full_name', 'username'], 'Student'));
$email = getValue($user, 'email');
$student_id = getValue($student, 'institutional_id');
$application_status = getValue($student, 'application_status', 'N/A');

$room_no = getValue($room, 'room_number', getValue($student, 'room_no'));
$seat_no = getValue($room, 'assigned_seat_no', getValue($student, 'seat_no'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - UniStay</title>
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
            background: #ffffff;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
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
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 10px 16px;
            border-radius: 7px;
            font-weight: bold;
            display: inline-block;
            font-size: 14px;
        }

        .btn-green {
            background: #00897b;
            color: white;
        }

        .profile-hero {
            background: linear-gradient(135deg, #00897b, #0f766e);
            color: white;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 25px;
        }

        .profile-hero h2 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .profile-hero p {
            margin: 5px 0;
            font-size: 16px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .status-good {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-bad {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-neutral {
            background: #e2e8f0;
            color: #334155;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .profile-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-radius: 12px;
            padding: 20px;
        }

        .profile-card h3 {
            margin-top: 0;
            color: #004d40;
            border-bottom: 1px solid #d9eeee;
            padding-bottom: 10px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px dashed #dbeafe;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            color: #004d40;
        }

        .info-value {
            color: #1f2937;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .empty-note {
            background: #fff8e1;
            color: #664d03;
            padding: 14px;
            border-radius: 8px;
            border-left: 5px solid #f59e0b;
            line-height: 1.7;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .profile-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .profile-card h3,
        body.dark-mode .info-label {
            color: #7dd3fc;
        }

        body.dark-mode .info-value {
            color: #e5e7eb;
        }

        body.dark-mode .info-row {
            border-bottom-color: #334155;
        }

        body.dark-mode .profile-card h3 {
            border-bottom-color: #334155;
        }

        body.dark-mode .status-good {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-pending {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-bad {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        body.dark-mode .status-neutral {
            background: #cbd5e1 !important;
            color: #1e293b !important;
        }

        @media (max-width: 850px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 4px;
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
        <h1>My Profile</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="dashboard.php" class="btn btn-green">Back to Dashboard</a>
        </div>
    </div>

    <div class="profile-hero">
        <h2><?php echo h($display_name); ?></h2>
        <p><strong>Student ID:</strong> <?php echo h($student_id); ?></p>
        <p><strong>Email:</strong> <?php echo h($email); ?></p>

        <span class="status-badge <?php echo h(statusBadgeClass($application_status)); ?>">
            <?php echo h($application_status); ?>
        </span>
    </div>

    <?php if (!$student): ?>
        <div class="empty-note">
            Student profile information has not been submitted yet.
        </div>
    <?php else: ?>

        <div class="profile-grid">

            <div class="profile-card">
                <h3>Account Information</h3>

                <div class="info-row">
                    <div class="info-label">Name</div>
                    <div class="info-value"><?php echo h(getValue($user, ['name', 'full_name', 'username'], $display_name)); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo h($email); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Role</div>
                    <div class="info-value"><?php echo h(getValue($user, 'role')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Account Status</div>
                    <div class="info-value"><?php echo h(getValue($user, ['status', 'account_status', 'is_verified'], 'Active')); ?></div>
                </div>
            </div>

            <div class="profile-card">
                <h3>Academic Information</h3>

                <div class="info-row">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo h(getValue($student, 'full_name')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Student ID</div>
                    <div class="info-value"><?php echo h(getValue($student, 'institutional_id')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo h(getValue($student, 'department')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Batch / Semester</div>
                    <div class="info-value">
                        <?php echo h(getValue($student, 'batch')); ?> /
                        <?php echo h(getValue($student, 'semester')); ?>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <h3>Room Information</h3>

                <div class="info-row">
                    <div class="info-label">Room No</div>
                    <div class="info-value"><?php echo h($room_no); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Seat No</div>
                    <div class="info-value"><?php echo h($seat_no); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Hall</div>
                    <div class="info-value"><?php echo h(getValue($room, 'hall_name')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Room Type</div>
                    <div class="info-value"><?php echo h(getValue($room, 'type_name')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Monthly Fee</div>
                    <div class="info-value">
                        <?php
                        $fee = getValue($room, 'monthly_fee');
                        echo $fee !== 'N/A' ? h(number_format((float)$fee, 2)) . ' TK' : 'N/A';
                        ?>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <h3>Compatibility Information</h3>

                <div class="info-row">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'gender')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Preferred Hall</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'preferred_hall')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Study Habit</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'study_habit')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Sleep Time</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'sleep_time')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Wake Time</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'wake_time')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Cleanliness</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'cleanliness')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Noise Tolerance</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'noise_tolerance')); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Personality</div>
                    <div class="info-value"><?php echo h(getValue($compatibility, 'personality')); ?></div>
                </div>
            </div>

            <div class="profile-card full-width">
                <h3>Request Status</h3>

                <div class="info-row">
                    <div class="info-label">Latest Room Application</div>
                    <div class="info-value">
                        <?php if ($room_application): ?>
                            <?php echo h(getValue($room_application, 'application_status')); ?>
                            |
                            Preferred Type:
                            <?php echo h(getValue($room_application, 'preferred_room_type')); ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Latest Room Change</div>
                    <div class="info-value">
                        <?php if ($room_change): ?>
                            <?php echo h(getValue($room_change, 'status')); ?>
                            |
                            Preferred Room:
                            <?php echo h(getValue($room_change, 'preferred_room_no', 'Any suitable room')); ?>
                            |
                            Approved Room:
                            <?php echo h(getValue($room_change, 'approved_room_no', 'N/A')); ?>
                        <?php else: ?>
                            No room change request yet.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Admin Message</div>
                    <div class="info-value">
                        <?php
                        echo h(
                            getValue(
                                $room_change,
                                'admin_message',
                                getValue($student, 'admin_message', 'No admin message yet.')
                            )
                        );
                        ?>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>