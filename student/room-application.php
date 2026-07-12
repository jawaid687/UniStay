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

$success_msg = '';
$error_msg = '';

if (isset($_GET['success']) && $_GET['success'] === 'submitted') {
    $success_msg = "Room preference submitted successfully. Your final room request is now waiting for admin approval.";
}

if (isset($_GET['success']) && $_GET['success'] === 'updated') {
    $success_msg = "Room preference updated successfully and sent for admin review.";
}

/* Get student account */
$user_stmt = mysqli_prepare(
    $conn,
    "SELECT name, institutional_id, is_approved
     FROM users
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

if (!$user || intval($user['is_approved']) !== 1) {
    header("Location: dashboard.php");
    exit();
}

$student_name = $user['name'] ?? 'Student';
$institutional_id = $user['institutional_id'] ?? '';

/* Get latest student record */
$student_record = null;

$record_stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_records
     WHERE user_id = ?
     AND is_deleted = 0
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($record_stmt, "i", $user_id);
mysqli_stmt_execute($record_stmt);
$record_result = mysqli_stmt_get_result($record_stmt);
$student_record = mysqli_fetch_assoc($record_result);
mysqli_stmt_close($record_stmt);

if (!$student_record) {
    $error_msg = "Please complete your basic hostel information from the Student Dashboard first.";
}

$student_record_id = $student_record ? intval($student_record['id']) : 0;

/* Check if already resident */
$is_resident = false;

if ($student_record) {
    $is_resident = (
        ($student_record['application_status'] ?? '') === 'assigned' &&
        !empty($student_record['room_no']) &&
        !empty($student_record['seat_no'])
    );
}

/* Get compatibility form data */
$compatibility = null;

if ($student_record) {
    $comp_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM roommate_preferences
         WHERE student_record_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($comp_stmt, "i", $student_record_id);
    mysqli_stmt_execute($comp_stmt);
    $comp_result = mysqli_stmt_get_result($comp_stmt);
    $compatibility = mysqli_fetch_assoc($comp_result);
    mysqli_stmt_close($comp_stmt);
}

$compatibility_completed = $compatibility ? true : false;

/* Load room types */
$room_types = [];

$type_result = mysqli_query(
    $conn,
    "SELECT room_type_id, type_name, capacity, monthly_fee
     FROM room_types
     ORDER BY monthly_fee ASC"
);

if ($type_result) {
    while ($row = mysqli_fetch_assoc($type_result)) {
        $room_types[] = $row;
    }
}

/* Get existing room preference */
$room_application = null;

if ($student_record) {
    $app_stmt = mysqli_prepare(
        $conn,
        "SELECT ra.*, rt.type_name, rt.capacity, rt.monthly_fee
         FROM room_applications ra
         JOIN room_types rt ON ra.preferred_room_type_id = rt.room_type_id
         WHERE ra.student_id = ?
         AND ra.student_record_id = ?
         ORDER BY ra.application_id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($app_stmt, "ii", $user_id, $student_record_id);
    mysqli_stmt_execute($app_stmt);
    $app_result = mysqli_stmt_get_result($app_stmt);
    $room_application = mysqli_fetch_assoc($app_result);
    mysqli_stmt_close($app_stmt);
}

/* Submit final room preference */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$student_record) {
        $error_msg = "Please complete your basic hostel information first.";
    } elseif (!$compatibility_completed) {
        $error_msg = "Please complete your compatibility form first.";
    } elseif ($is_resident) {
        $error_msg = "Your room is already assigned. You cannot submit a new room preference from here.";
    } elseif ($room_application) {
        $error_msg = "Your room preference has already been submitted. Please wait for admin review.";
    } else {
        $preferred_room_type_id = intval($_POST['preferred_room_type_id'] ?? 0);
        $budget = trim($_POST['budget'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        /* These values come from the compatibility form */
        $study_habit = $compatibility['study_habit'] ?? 'normal';
        $sleep_habit = $compatibility['sleep_time'] ?? 'medium';
        $cleanliness_level = $compatibility['cleanliness'] ?? 'normal';

        if ($preferred_room_type_id <= 0) {
            $error_msg = "Please select a preferred room type.";
        } elseif ($budget === '' || !is_numeric($budget) || floatval($budget) < 0) {
            $error_msg = "Please enter a valid budget.";
        } else {
            $type_check_stmt = mysqli_prepare(
                $conn,
                "SELECT room_type_id
                 FROM room_types
                 WHERE room_type_id = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($type_check_stmt, "i", $preferred_room_type_id);
            mysqli_stmt_execute($type_check_stmt);
            $type_check_result = mysqli_stmt_get_result($type_check_stmt);
            $type_exists = mysqli_fetch_assoc($type_check_result);
            mysqli_stmt_close($type_check_stmt);

            if (!$type_exists) {
                $error_msg = "Invalid room type selected.";
            } else {
                $budget_value = floatval($budget);

                if ($room_application && $room_application['application_status'] !== 'assigned') {
                    $update_stmt = mysqli_prepare(
                        $conn,
                        "UPDATE room_applications
                         SET preferred_room_type_id = ?,
                             budget = ?,
                             study_habit = ?,
                             sleep_habit = ?,
                             cleanliness_level = ?,
                             reason = ?,
                             application_status = 'pending',
                             admin_message = NULL,
                             reviewed_at = NULL
                         WHERE application_id = ?
                         AND student_id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "idssssii",
                        $preferred_room_type_id,
                        $budget_value,
                        $study_habit,
                        $sleep_habit,
                        $cleanliness_level,
                        $reason,
                        $room_application['application_id'],
                        $user_id
                    );

                    if (mysqli_stmt_execute($update_stmt)) {
                        mysqli_stmt_close($update_stmt);

                        $update_record_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE student_records
                             SET application_status = 'pending',
                                 admin_message = 'Your room request has been submitted successfully and is waiting for admin approval.'
                             WHERE id = ?
                             AND user_id = ?"
                        );

                        mysqli_stmt_bind_param($update_record_stmt, "ii", $student_record_id, $user_id);
                        mysqli_stmt_execute($update_record_stmt);
                        mysqli_stmt_close($update_record_stmt);

                        header("Location: room-application.php?success=updated");
                        exit();
                    } else {
                        $error_msg = "Failed to update room preference.";
                        mysqli_stmt_close($update_stmt);
                    }
                } elseif (!$room_application) {
                    $insert_stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO room_applications
                        (
                            student_id,
                            student_record_id,
                            preferred_room_type_id,
                            budget,
                            study_habit,
                            sleep_habit,
                            cleanliness_level,
                            reason,
                            application_status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                    );

                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        "iiidssss",
                        $user_id,
                        $student_record_id,
                        $preferred_room_type_id,
                        $budget_value,
                        $study_habit,
                        $sleep_habit,
                        $cleanliness_level,
                        $reason
                    );

                    if (mysqli_stmt_execute($insert_stmt)) {
                        mysqli_stmt_close($insert_stmt);

                        $update_record_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE student_records
                             SET application_status = 'pending',
                                 admin_message = 'Your room request has been submitted successfully and is waiting for admin approval.'
                             WHERE id = ?
                             AND user_id = ?"
                        );

                        mysqli_stmt_bind_param($update_record_stmt, "ii", $student_record_id, $user_id);
                        mysqli_stmt_execute($update_record_stmt);
                        mysqli_stmt_close($update_record_stmt);

                        header("Location: room-application.php?success=submitted");
                        exit();
                    } else {
                        $error_msg = "Failed to submit room preference.";
                        mysqli_stmt_close($insert_stmt);
                    }
                } else {
                    $error_msg = "Your room has already been assigned.";
                }
            }
        }
    }
}

/* Refresh latest room preference */
$room_application = null;

if ($student_record) {
    $app_stmt = mysqli_prepare(
        $conn,
        "SELECT ra.*, rt.type_name, rt.capacity, rt.monthly_fee
         FROM room_applications ra
         JOIN room_types rt ON ra.preferred_room_type_id = rt.room_type_id
         WHERE ra.student_id = ?
         AND ra.student_record_id = ?
         ORDER BY ra.application_id DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param($app_stmt, "ii", $user_id, $student_record_id);
    mysqli_stmt_execute($app_stmt);
    $app_result = mysqli_stmt_get_result($app_stmt);
    $room_application = mysqli_fetch_assoc($app_result);
    mysqli_stmt_close($app_stmt);
}

function statusLabel($status)
{
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'assigned' => 'Assigned'
    ];

    return $labels[$status] ?? $status;
}

$current_room_type = $room_application['preferred_room_type_id'] ?? '';
$current_budget = $room_application['budget'] ?? '';
$current_reason = $room_application['reason'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Room Preference - UniStay</title>
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
            max-width: 1050px;
            margin: 30px auto;
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
            padding: 10px 15px;
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
            width: 100%;
            margin-top: 10px;
            padding: 13px;
        }

        .info-box {
            background: #e0f2f1;
            color: #004d40;
            border-left: 5px solid #00897b;
            padding: 16px;
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

        .form-card,
        .status-card,
        .locked-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
        }

        .form-card h2,
        .status-card h2,
        .locked-card h2 {
            margin-top: 0;
            color: #004d40;
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
            color: #004d40;
        }

        select,
        input,
        textarea {
            width: 100%;
            padding: 11px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
            font-size: 15px;
            background: white;
            color: #1f2937;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .status-pill {
            display: inline-block;
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

        .status-assigned {
            background: #dbeafe;
            color: #1e40af;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .detail-table th,
        .detail-table td {
            text-align: left;
            padding: 11px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-table th {
            width: 35%;
            color: #004d40;
            background: #eaf5f4;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .form-card,
        body.dark-mode .status-card,
        body.dark-mode .locked-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .form-card h2,
        body.dark-mode .status-card h2,
        body.dark-mode .locked-card h2,
        body.dark-mode label,
        body.dark-mode .detail-table th {
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
            color: #ffffff;
            border-color: #475569;
        }

        body.dark-mode select option {
            background: #1e293b;
            color: #ffffff;
        }

        body.dark-mode .detail-table th {
            background: #1e293b;
        }

        body.dark-mode .detail-table td {
            color: #e5e7eb;
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

        body.dark-mode .status-assigned {
            background: #bfdbfe !important;
            color: #1e3a8a !important;
        }

        @media (max-width: 750px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="header">
            <h1>Room Preference</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="dashboard.php" class="btn btn-dashboard">Student Dashboard</a>
                <a href="../index.php" class="btn btn-home">Home</a>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert-success"><?php echo h($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert-error"><?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Student:</strong> <?php echo h($student_name); ?><br>
            <strong>ID:</strong> <?php echo h($institutional_id); ?><br>
            This is the final step of your room request. Your compatibility information will be used for smart room allocation.
        </div>

        <?php if (!$student_record): ?>

            <div class="locked-card">
                <h2>Basic Information Required</h2>
                <p>Please complete your basic hostel information from the Student Dashboard first.</p>
            </div>

        <?php elseif (!$compatibility_completed): ?>

            <div class="locked-card">
                <h2>Compatibility Form Required</h2>
                <p>Please complete your compatibility form first. After that, you can submit your room preference.</p>
                <a href="compatibility-form.php" class="btn btn-dashboard">Complete Compatibility Form</a>
            </div>

        <?php else: ?>

            <?php if ($room_application): ?>
                <div class="status-card">
                    <h2>Current Room Preference Status</h2>

                    <span class="status-pill status-<?php echo h($room_application['application_status']); ?>">
                        <?php echo h(statusLabel($room_application['application_status'])); ?>
                    </span>

                    <table class="detail-table">
                        <tr>
                            <th>Preferred Room Type</th>
                            <td><?php echo h($room_application['type_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Capacity</th>
                            <td><?php echo h($room_application['capacity']); ?> Student(s)</td>
                        </tr>
                        <tr>
                            <th>Monthly Fee</th>
                            <td><?php echo number_format((float)$room_application['monthly_fee']); ?> TK</td>
                        </tr>
                        <tr>
                            <th>Your Budget</th>
                            <td><?php echo number_format((float)$room_application['budget']); ?> TK</td>
                        </tr>
                        <tr>
                            <th>Study Habit</th>
                            <td><?php echo h($room_application['study_habit']); ?></td>
                        </tr>
                        <tr>
                            <th>Sleep Habit</th>
                            <td><?php echo h($room_application['sleep_habit']); ?></td>
                        </tr>
                        <tr>
                            <th>Cleanliness</th>
                            <td><?php echo h($room_application['cleanliness_level']); ?></td>
                        </tr>
                        <tr>
                            <th>Additional Note</th>
                            <td><?php echo nl2br(h($room_application['reason'] ?? 'No note added.')); ?></td>
                        </tr>
                        <tr>
                            <th>Admin Message</th>
                            <td><?php echo h($room_application['admin_message'] ?? 'No message yet.'); ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!$is_resident && !$room_application): ?>
                <div class="form-card">
                    <h2>Submit Room Preference</h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Preferred Room Type *</label>
                                <select name="preferred_room_type_id" required>
                                    <option value="">Select Room Type</option>

                                    <?php foreach ($room_types as $type): ?>
                                        <option value="<?php echo intval($type['room_type_id']); ?>"
                                            <?php echo intval($current_room_type) === intval($type['room_type_id']) ? 'selected' : ''; ?>>
                                            <?php echo h($type['type_name']); ?>
                                            - Capacity: <?php echo h($type['capacity']); ?>
                                            - <?php echo number_format((float)$type['monthly_fee']); ?> TK
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Your Budget *</label>
                                <input type="number" name="budget" value="<?php echo h($current_budget); ?>" placeholder="Example: 14000" required>
                            </div>

                            <div class="form-group full">
                                <label>Additional Note</label>
                                <textarea name="reason" placeholder="Write any room preference or important note..."><?php echo h($current_reason); ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-submit">
                            Submit Final Room Request
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <?php if ($is_resident): ?>
                    <div class="locked-card">
                        <h2>Room Already Assigned</h2>
                        <p>Your room has already been assigned. For future room changes, use the Room Change Request option.</p>
                    </div>
                <?php else: ?>
                    <div class="locked-card">
                        <h2>Room Preference Submitted</h2>
                        <p>
                            Your final room request has been submitted successfully and is now waiting for admin review.
                            You cannot update this form after submission.
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>