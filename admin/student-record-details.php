<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$record_id = intval($_GET['id'] ?? 0);

if ($record_id <= 0) {
    die("Invalid student record selected.");
}

/* Main student record */
$stmt = mysqli_prepare(
    $conn,
    "SELECT 
        sr.*,
        u.email AS user_email,
        u.name AS account_name,
        u.institutional_id AS account_institutional_id,
        reviewer.name AS reviewed_by_name
     FROM student_records sr
     LEFT JOIN users u ON sr.user_id = u.id
     LEFT JOIN users reviewer ON sr.reviewed_by = reviewer.id
     WHERE sr.id = ?
     AND sr.is_deleted = 0
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$student) {
    die("Student record not found.");
}

$user_id = intval($student['user_id'] ?? 0);

/* Compatibility */
$compatibility = null;

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM roommate_preferences
     WHERE student_record_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$comp_result = mysqli_stmt_get_result($stmt);
$compatibility = mysqli_fetch_assoc($comp_result);
mysqli_stmt_close($stmt);

/* Room preference */
$room_application = null;

$stmt = mysqli_prepare(
    $conn,
    "SELECT 
        ra.*,
        rt.type_name,
        rt.capacity,
        rt.monthly_fee
     FROM room_applications ra
     LEFT JOIN room_types rt ON ra.preferred_room_type_id = rt.room_type_id
     WHERE ra.student_record_id = ?
     ORDER BY ra.application_id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$app_result = mysqli_stmt_get_result($stmt);
$room_application = mysqli_fetch_assoc($app_result);
mysqli_stmt_close($stmt);

/* Room assignment */
$room_assignment = null;

$stmt = mysqli_prepare(
    $conn,
    "SELECT 
        ras.*,
        r.room_number,
        r.floor_number,
        r.room_status,
        rt.type_name,
        rt.capacity,
        rt.monthly_fee,
        admin.name AS assigned_by_name
     FROM room_assignments ras
     LEFT JOIN rooms r ON ras.room_id = r.room_id
     LEFT JOIN room_types rt ON r.room_type_id = rt.room_type_id
     LEFT JOIN users admin ON ras.assigned_by = admin.id
     WHERE ras.student_record_id = ?
     AND ras.assignment_status = 'active'
     ORDER BY ras.assignment_id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$assign_result = mysqli_stmt_get_result($stmt);
$room_assignment = mysqli_fetch_assoc($assign_result);
mysqli_stmt_close($stmt);

/* Payments */
$payments = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM hostel_payments
     WHERE student_record_id = ?
     OR user_id = ?
     ORDER BY created_at DESC"
);

mysqli_stmt_bind_param($stmt, "ii", $record_id, $user_id);
mysqli_stmt_execute($stmt);
$pay_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($pay_result)) {
    $payments[] = $row;
}

mysqli_stmt_close($stmt);

/* Leave applications */
$leave_applications = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_leave_applications
     WHERE student_record_id = ?
     ORDER BY created_at DESC"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$leave_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($leave_result)) {
    $leave_applications[] = $row;
}

mysqli_stmt_close($stmt);

/* Late entry applications */
$late_entry_applications = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_late_entry_applications
     WHERE student_record_id = ?
     ORDER BY created_at DESC"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$late_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($late_result)) {
    $late_entry_applications[] = $row;
}

mysqli_stmt_close($stmt);

/* Room change requests */
$room_change_requests = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_room_change_requests
     WHERE student_record_id = ?
     ORDER BY created_at DESC"
);

mysqli_stmt_bind_param($stmt, "i", $record_id);
mysqli_stmt_execute($stmt);
$change_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($change_result)) {
    $room_change_requests[] = $row;
}

mysqli_stmt_close($stmt);

function statusClass($status) {
    if ($status === 'assigned' || $status === 'approved' || $status === 'paid' || $status === 'active') {
        return 'status-good';
    }

    if ($status === 'rejected' || $status === 'cancelled') {
        return 'status-bad';
    }

    return 'status-waiting';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Full Record - UniStay</title>
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
            max-width: 1250px;
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

        .btn-back {
            background: #64748b;
            color: white;
        }

        .btn-dashboard {
            background: #00897b;
            color: white;
        }

        .summary-box {
            background: linear-gradient(135deg, #00897b, #00695c);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .section {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-left: 5px solid #00897b;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .section h2 {
            margin-top: 0;
            color: #004d40;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .detail-box {
            background: white;
            border: 1px solid #d9eeee;
            padding: 13px;
            border-radius: 8px;
            line-height: 1.6;
            min-height: 70px;
        }

        .detail-box strong {
            display: block;
            color: #004d40;
            margin-bottom: 5px;
        }

        .wide {
            grid-column: span 3;
        }

        .status-pill {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }

        .status-good {
            background: #dcfce7;
            color: #166534;
        }

        .status-waiting {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-bad {
            background: #fee2e2;
            color: #991b1b;
        }

        .record-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .record-table th,
        .record-table td {
            text-align: left;
            padding: 11px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .record-table th {
            background: #eaf5f4;
            color: #004d40;
        }

        .empty-box {
            background: #fff8e1;
            border-left: 5px solid #f59e0b;
            color: #664d03;
            padding: 15px;
            border-radius: 8px;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .section,
        body.dark-mode .detail-box {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .section h2,
        body.dark-mode .detail-box strong,
        body.dark-mode .record-table th {
            color: #7dd3fc;
        }

        body.dark-mode .record-table th {
            background: #1e293b;
        }

        body.dark-mode .record-table td {
            color: #e5e7eb;
        }

        body.dark-mode .status-good {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-waiting {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-bad {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .wide {
                grid-column: span 1;
            }

            .record-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Student Full Record</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="student-records.php" class="btn btn-back">Back to Student Records</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
        </div>
    </div>

    <div class="summary-box">
        <strong><?php echo h($student['full_name']); ?></strong><br>
        ID: <?php echo h($student['institutional_id']); ?><br>
        Status:
        <span class="status-pill <?php echo h(statusClass($student['application_status'])); ?>">
            <?php echo h($student['application_status']); ?>
        </span>
        <br>
        Room:
        <?php echo h($student['room_no'] ?: 'Not assigned'); ?>
        |
        Seat:
        <?php echo h($student['seat_no'] ?: 'Not assigned'); ?>
    </div>

    <div class="section">
        <h2>1. Basic Hostel Information</h2>

        <div class="details-grid">
            <div class="detail-box">
                <strong>Full Name</strong>
                <?php echo h($student['full_name']); ?>
            </div>

            <div class="detail-box">
                <strong>Institutional ID</strong>
                <?php echo h($student['institutional_id']); ?>
            </div>

            <div class="detail-box">
                <strong>Email</strong>
                <?php echo h($student['user_email'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Department</strong>
                <?php echo h($student['department'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Batch</strong>
                <?php echo h($student['batch'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Semester</strong>
                <?php echo h($student['semester'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Phone</strong>
                <?php echo h($student['phone'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Guardian Name</strong>
                <?php echo h($student['guardian_name'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Guardian Phone</strong>
                <?php echo h($student['guardian_phone'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box wide">
                <strong>Address</strong>
                <?php echo nl2br(h($student['address'] ?? 'N/A')); ?>
            </div>

            <div class="detail-box wide">
                <strong>Reason for Hostel</strong>
                <?php echo nl2br(h($student['reason_for_hostel'] ?? 'N/A')); ?>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>2. Room Assignment Information</h2>

        <div class="details-grid">
            <div class="detail-box">
                <strong>Application Status</strong>
                <span class="status-pill <?php echo h(statusClass($student['application_status'])); ?>">
                    <?php echo h($student['application_status']); ?>
                </span>
            </div>

            <div class="detail-box">
                <strong>Room No</strong>
                <?php echo h($student['room_no'] ?: 'Not assigned'); ?>
            </div>

            <div class="detail-box">
                <strong>Seat No</strong>
                <?php echo h($student['seat_no'] ?: 'Not assigned'); ?>
            </div>

            <div class="detail-box">
                <strong>Reviewed By</strong>
                <?php echo h($student['reviewed_by_name'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Reviewed At</strong>
                <?php echo h($student['reviewed_at'] ?? 'N/A'); ?>
            </div>

            <div class="detail-box">
                <strong>Assigned Through New Module</strong>
                <?php echo $room_assignment ? 'Yes' : 'No / Old Record'; ?>
            </div>

            <div class="detail-box wide">
                <strong>Admin Message</strong>
                <?php echo nl2br(h($student['admin_message'] ?? 'No message.')); ?>
            </div>
        </div>

        <?php if ($room_assignment): ?>
            <br>
            <div class="details-grid">
                <div class="detail-box">
                    <strong>Room Type</strong>
                    <?php echo h($room_assignment['type_name'] ?? 'N/A'); ?>
                </div>

                <div class="detail-box">
                    <strong>Floor</strong>
                    <?php echo h($room_assignment['floor_number'] ?? 'N/A'); ?>
                </div>

                <div class="detail-box">
                    <strong>Monthly Fee</strong>
                    <?php echo number_format((float)($room_assignment['monthly_fee'] ?? 0)); ?> TK
                </div>

                <div class="detail-box">
                    <strong>Assigned By</strong>
                    <?php echo h($room_assignment['assigned_by_name'] ?? 'N/A'); ?>
                </div>

                <div class="detail-box">
                    <strong>Assigned Date</strong>
                    <?php echo h($room_assignment['assigned_date'] ?? 'N/A'); ?>
                </div>

                <div class="detail-box">
                    <strong>Assignment Status</strong>
                    <?php echo h($room_assignment['assignment_status'] ?? 'N/A'); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>3. Compatibility Information</h2>

        <?php if (!$compatibility): ?>
            <div class="empty-box">No compatibility form found.</div>
        <?php else: ?>
            <div class="details-grid">
                <div class="detail-box">
                    <strong>Gender</strong>
                    <?php echo h($compatibility['gender']); ?>
                </div>

                <div class="detail-box">
                    <strong>Preferred Hall</strong>
                    <?php echo h($compatibility['preferred_hall']); ?>
                </div>

                <div class="detail-box">
                    <strong>Sleep Time</strong>
                    <?php echo h($compatibility['sleep_time']); ?>
                </div>

                <div class="detail-box">
                    <strong>Wake Time</strong>
                    <?php echo h($compatibility['wake_time']); ?>
                </div>

                <div class="detail-box">
                    <strong>Study Habit</strong>
                    <?php echo h($compatibility['study_habit']); ?>
                </div>

                <div class="detail-box">
                    <strong>Cleanliness</strong>
                    <?php echo h($compatibility['cleanliness']); ?>
                </div>

                <div class="detail-box">
                    <strong>Noise Tolerance</strong>
                    <?php echo h($compatibility['noise_tolerance']); ?>
                </div>

                <div class="detail-box">
                    <strong>Guest Preference</strong>
                    <?php echo h($compatibility['guest_preference']); ?>
                </div>

                <div class="detail-box">
                    <strong>Personality</strong>
                    <?php echo h($compatibility['personality']); ?>
                </div>

                <div class="detail-box">
                    <strong>Religion Sensitive</strong>
                    <?php echo h($compatibility['religion_sensitive']); ?>
                </div>

                <div class="detail-box wide">
                    <strong>Additional Note</strong>
                    <?php echo nl2br(h($compatibility['additional_note'] ?? 'No note.')); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>4. Room Preference Information</h2>

        <?php if (!$room_application): ?>
            <div class="empty-box">No room preference found.</div>
        <?php else: ?>
            <div class="details-grid">
                <div class="detail-box">
                    <strong>Preferred Room Type</strong>
                    <?php echo h($room_application['type_name']); ?>
                </div>

                <div class="detail-box">
                    <strong>Capacity</strong>
                    <?php echo h($room_application['capacity']); ?> Student(s)
                </div>

                <div class="detail-box">
                    <strong>Monthly Fee</strong>
                    <?php echo number_format((float)$room_application['monthly_fee']); ?> TK
                </div>

                <div class="detail-box">
                    <strong>Student Budget</strong>
                    <?php echo number_format((float)$room_application['budget']); ?> TK
                </div>

                <div class="detail-box">
                    <strong>Room Preference Status</strong>
                    <span class="status-pill <?php echo h(statusClass($room_application['application_status'])); ?>">
                        <?php echo h($room_application['application_status']); ?>
                    </span>
                </div>

                <div class="detail-box">
                    <strong>Submitted At</strong>
                    <?php echo h($room_application['created_at']); ?>
                </div>

                <div class="detail-box">
                    <strong>Study Habit</strong>
                    <?php echo h($room_application['study_habit']); ?>
                </div>

                <div class="detail-box">
                    <strong>Sleep Habit</strong>
                    <?php echo h($room_application['sleep_habit']); ?>
                </div>

                <div class="detail-box">
                    <strong>Cleanliness</strong>
                    <?php echo h($room_application['cleanliness_level']); ?>
                </div>

                <div class="detail-box wide">
                    <strong>Additional Note / Reason</strong>
                    <?php echo nl2br(h($room_application['reason'] ?? 'No note.')); ?>
                </div>

                <div class="detail-box wide">
                    <strong>Admin Message</strong>
                    <?php echo nl2br(h($room_application['admin_message'] ?? 'No message.')); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>5. Payment Records</h2>

        <?php if (empty($payments)): ?>
            <div class="empty-box">No payment record found.</div>
        <?php else: ?>
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Semester</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?php echo h($pay['semester']); ?></td>
                            <td><?php echo number_format((float)$pay['amount']); ?> TK</td>
                            <td><?php echo number_format((float)$pay['paid_amount']); ?> TK</td>
                            <td><?php echo number_format((float)$pay['due_amount']); ?> TK</td>
                            <td>
                                <span class="status-pill <?php echo h(statusClass($pay['payment_status'])); ?>">
                                    <?php echo h($pay['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo h($pay['paid_at'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>6. Leave Applications</h2>

        <?php if (empty($leave_applications)): ?>
            <div class="empty-box">No leave application found.</div>
        <?php else: ?>
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Leave From</th>
                        <th>Leave To</th>
                        <th>Destination</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Admin Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_applications as $leave): ?>
                        <tr>
                            <td><?php echo h($leave['leave_from']); ?></td>
                            <td><?php echo h($leave['leave_to']); ?></td>
                            <td><?php echo h($leave['destination']); ?></td>
                            <td><?php echo h($leave['reason']); ?></td>
                            <td>
                                <span class="status-pill <?php echo h(statusClass($leave['status'])); ?>">
                                    <?php echo h($leave['status']); ?>
                                </span>
                            </td>
                            <td><?php echo h($leave['admin_message'] ?? 'No message.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>7. Late Entry Applications</h2>

        <?php if (empty($late_entry_applications)): ?>
            <div class="empty-box">No late entry application found.</div>
        <?php else: ?>
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Entry Date</th>
                        <th>Expected Time</th>
                        <th>Reason</th>
                        <th>Guardian Phone</th>
                        <th>Status</th>
                        <th>Admin Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($late_entry_applications as $late): ?>
                        <tr>
                            <td><?php echo h($late['entry_date']); ?></td>
                            <td><?php echo h($late['expected_entry_time']); ?></td>
                            <td><?php echo h($late['reason']); ?></td>
                            <td><?php echo h($late['guardian_phone']); ?></td>
                            <td>
                                <span class="status-pill <?php echo h(statusClass($late['status'])); ?>">
                                    <?php echo h($late['status']); ?>
                                </span>
                            </td>
                            <td><?php echo h($late['admin_message'] ?? 'No message.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>8. Room Change Requests</h2>

        <?php if (empty($room_change_requests)): ?>
            <div class="empty-box">No room change request found.</div>
        <?php else: ?>
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Current Room/Seat</th>
                        <th>Preferred Room/Seat</th>
                        <th>Approved Room/Seat</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Admin Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($room_change_requests as $change): ?>
                        <tr>
                            <td>
                                Room <?php echo h($change['current_room_no']); ?>,
                                Seat <?php echo h($change['current_seat_no']); ?>
                            </td>
                            <td>
                                Room <?php echo h($change['preferred_room_no'] ?? 'N/A'); ?>,
                                Seat <?php echo h($change['preferred_seat_no'] ?? 'N/A'); ?>
                            </td>
                            <td>
                                Room <?php echo h($change['approved_room_no'] ?? 'N/A'); ?>,
                                Seat <?php echo h($change['approved_seat_no'] ?? 'N/A'); ?>
                            </td>
                            <td><?php echo h($change['reason']); ?></td>
                            <td>
                                <span class="status-pill <?php echo h(statusClass($change['status'])); ?>">
                                    <?php echo h($change['status']); ?>
                                </span>
                            </td>
                            <td><?php echo h($change['admin_message'] ?? 'No message.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>