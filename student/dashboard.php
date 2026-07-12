<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$name = $_SESSION['name'] ?? 'Student';
$institutional_id = $_SESSION['institutional_id'] ?? 'N/A';

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if (isset($_GET['limited'])) {
    $error_msg = "This feature is only available for approved hall residents. Your room request must be approved and room/seat must be assigned first.";
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ===============================
   CHECK ACCOUNT APPROVAL
================================ */

$user_stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, email, institutional_id, phone, is_verified, is_approved 
     FROM users 
     WHERE id = ? 
     LIMIT 1"
);

mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$current_user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

if (!$current_user || intval($current_user['is_approved']) !== 1) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Access Restricted - UniStay</title>
        <link rel="stylesheet" href="../assets/css/theme.css">
        <style>
            body {
                margin: 0;
                padding: 20px;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f4f8f7;
            }

            .box {
                max-width: 650px;
                margin: 80px auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
                border-left: 6px solid #dc2626;
            }

            h1 {
                margin-top: 0;
                color: #991b1b;
            }

            p {
                line-height: 1.7;
                color: #374151;
            }

            .btn {
                display: inline-block;
                margin-top: 15px;
                padding: 10px 16px;
                background: #dc2626;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
            }

            .room-action-buttons {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                margin-top: 28px;
            }

            .room-action-buttons .btn {
                margin: 0;
            }

            .btn-room-change {
                display: inline-block;
                text-decoration: none;
                padding: 10px 18px;
                border-radius: 8px;
                font-weight: 700;
                background: transparent;
                color: #7dd3fc !important;
                border: 1px solid #00897b;
                transition: 0.25s ease;
            }

            .btn-room-change:hover {
                background: #00897b;
                color: white !important;
                transform: translateY(-1px);
            }

            .portal-card {
                min-height: 315px !important;
                display: grid !important;
                grid-template-rows: auto 1fr auto !important;
                align-items: start !important;
            }

            .portal-card h3 {
                margin-bottom: 18px !important;
            }

            .portal-card p {
                margin: 0 !important;
                align-self: start !important;
            }

            .portal-card .btn,
            .portal-card .btn-green {
                align-self: end !important;
                justify-self: start !important;
                margin-top: 25px !important;
            }
            /* Force all resident portal buttons to align */
.portal-card {
    position: relative !important;
    min-height: 315px !important;
    padding-bottom: 95px !important;
}

.portal-card > a.btn,
.portal-card > a.btn-green,
.portal-card .room-action-buttons {
    position: absolute !important;
    left: 30px !important;
    bottom: 32px !important;
    margin: 0 !important;
}

.portal-card .room-action-buttons a {
    margin: 0 !important;
}
        </style>
    </head>

    <body>
        <div class="box">
            <h1>Account Not Approved</h1>
            <p>
                Your account is registered, but it has not been approved by the admin yet.
                You cannot access the student dashboard until your account is approved.
            </p>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </body>

    </html>
<?php
    exit();
}

/* ===============================
   CHECK EXISTING APPLICATION
================================ */

$application = null;

$check_stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM student_records 
     WHERE is_deleted = 0 
     AND (user_id = ? OR institutional_id = ?)
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($check_stmt, "is", $user_id, $institutional_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$application = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

/* ===============================
   CHECK COMPATIBILITY FORM
================================ */

$has_compatibility_form = false;

if ($application) {
    $pref_stmt = mysqli_prepare(
        $conn,
        "SELECT id FROM roommate_preferences WHERE user_id = ? LIMIT 1"
    );

    if ($pref_stmt) {
        mysqli_stmt_bind_param($pref_stmt, "i", $user_id);
        mysqli_stmt_execute($pref_stmt);
        $pref_result = mysqli_stmt_get_result($pref_stmt);

        if (mysqli_fetch_assoc($pref_result)) {
            $has_compatibility_form = true;
        }

        mysqli_stmt_close($pref_stmt);
    }

    if ($has_compatibility_form && intval($application['compatibility_completed'] ?? 0) === 0) {
        $sync_stmt = mysqli_prepare(
            $conn,
            "UPDATE student_records 
             SET compatibility_completed = 1 
             WHERE id = ? AND user_id = ?"
        );

        if ($sync_stmt) {
            mysqli_stmt_bind_param($sync_stmt, "ii", $application['id'], $user_id);
            mysqli_stmt_execute($sync_stmt);
            mysqli_stmt_close($sync_stmt);

            $application['compatibility_completed'] = 1;
        }
    }
}

$compatibility_completed = $application ? intval($application['compatibility_completed'] ?? 0) : 0;

/* ===============================
   SUBMIT / RESUBMIT HOSTEL APPLICATION
================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_application') {
    $department = trim($_POST['department'] ?? '');
    $batch = trim($_POST['batch'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $reason_for_hostel = trim($_POST['reason_for_hostel'] ?? '');

    if (
        empty($department) ||
        empty($batch) ||
        empty($semester) ||
        empty($phone) ||
        empty($guardian_name) ||
        empty($guardian_phone) ||
        empty($address) ||
        empty($reason_for_hostel)
    ) {
        $error_msg = "Please fill in all required fields.";
    } else {
        if ($application) {
            if ($application['application_status'] === 'assigned') {
                $error_msg = "Your hostel seat is already assigned. You cannot edit this application now.";
            } else {
                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE student_records
                     SET department = ?,
                         batch = ?,
                         semester = ?,
                         phone = ?,
                         guardian_name = ?,
                         guardian_phone = ?,
                         address = ?,
                         reason_for_hostel = ?,
                         application_status = 'pending',
                         admin_message = NULL,
                         reviewed_by = NULL,
                         reviewed_at = NULL
                     WHERE id = ? AND user_id = ?"
                );

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssssssii",
                    $department,
                    $batch,
                    $semester,
                    $phone,
                    $guardian_name,
                    $guardian_phone,
                    $address,
                    $reason_for_hostel,
                    $application['id'],
                    $user_id
                );

                if (mysqli_stmt_execute($update_stmt)) {
                    mysqli_stmt_close($update_stmt);

                    if ($compatibility_completed === 0) {
                        header("Location: compatibility-form.php?required=1");
                        exit();
                    }

                    $_SESSION['success_msg'] = "Your room request has been submitted again for admin review.";
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error_msg = "Failed to update room request.";
                    mysqli_stmt_close($update_stmt);
                }
            }
        } else {
            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO student_records
                 (
                    user_id,
                    full_name,
                    institutional_id,
                    department,
                    batch,
                    semester,
                    phone,
                    guardian_name,
                    guardian_phone,
                    address,
                    reason_for_hostel,
                    application_status,
                    compatibility_completed,
                    created_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?)"
            );

            mysqli_stmt_bind_param(
                $insert_stmt,
                "issssssssssi",
                $user_id,
                $name,
                $institutional_id,
                $department,
                $batch,
                $semester,
                $phone,
                $guardian_name,
                $guardian_phone,
                $address,
                $reason_for_hostel,
                $user_id
            );

            if (mysqli_stmt_execute($insert_stmt)) {
                mysqli_stmt_close($insert_stmt);

                header("Location: compatibility-form.php?after_application=1");
                exit();
            } else {
                $error_msg = "Failed to submit hostel application.";
                mysqli_stmt_close($insert_stmt);
            }
        }
    }
}

/* ===============================
   REFRESH APPLICATION
================================ */

$refresh_stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM student_records 
     WHERE is_deleted = 0 
     AND (user_id = ? OR institutional_id = ?)
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($refresh_stmt, "is", $user_id, $institutional_id);
mysqli_stmt_execute($refresh_stmt);
$refresh_result = mysqli_stmt_get_result($refresh_stmt);
$application = mysqli_fetch_assoc($refresh_result);
mysqli_stmt_close($refresh_stmt);

$status = $application['application_status'] ?? 'not_submitted';
$compatibility_completed = $application ? intval($application['compatibility_completed'] ?? 0) : 0;

$is_resident = (
    $application &&
    $status === 'assigned' &&
    !empty($application['room_no']) &&
    !empty($application['seat_no'])
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - UniStay</title>
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
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #00897b;
            padding-bottom: 15px;
            margin-bottom: 25px;
            gap: 15px;
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

        .btn-logout {
            background-color: #dc2626;
            color: white;
        }

        .btn-submit {
            background-color: #00897b;
            color: white;
            width: 100%;
            margin-top: 10px;
            padding: 12px;
        }

        .btn-blue {
            background-color: #2563eb;
            color: white;
            margin-top: 12px;
        }

        .btn-green {
            background-color: #00897b;
            color: white;
            margin-top: 12px;
        }

        .btn-disabled {
            background-color: #94a3b8;
            color: white;
            cursor: not-allowed;
            margin-top: 12px;
        }

        .info-box {
            background: #e0f2f1;
            border-left: 5px solid #00897b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #004d40;
        }

        .status-box {
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.7;
        }

        .status-pending {
            background: #fff8e1;
            color: #664d03;
            border-left: 5px solid #f59e0b;
        }

        .status-incomplete {
            background: #eff6ff;
            color: #1e3a8a;
            border-left: 5px solid #2563eb;
        }

        .status-waiting {
            background: #e0f2fe;
            color: #075985;
            border-left: 5px solid #0284c7;
        }

        .status-assigned {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
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

        .form-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .form-card h2 {
            margin-top: 0;
            color: #004d40;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .form-group.full {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            color: #1f2937;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .portal-card {
            background: #ffffff;
            border: 1px solid #d9eeee;
            border-top: 5px solid #00897b;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .portal-card.locked {
            border-top-color: #94a3b8;
            opacity: 0.82;
        }

        .portal-card h3 {
            margin-top: 0;
            color: #004d40;
        }

        .portal-card p {
            color: #555;
            line-height: 1.6;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 25px;
        }

        .detail-table th,
        .detail-table td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-table th {
            color: #004d40;
            width: 35%;
            background: #eaf5f4;
        }

        body.dark-mode .container,
        body.dark-mode .form-card,
        body.dark-mode .portal-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .form-group label,
        body.dark-mode .header h1,
        body.dark-mode .form-card h2,
        body.dark-mode .portal-card h3,
        body.dark-mode .detail-table th {
            color: #7dd3fc;
        }

        body.dark-mode .portal-card p,
        body.dark-mode .detail-table td {
            color: #cbd5e1;
        }

        body.dark-mode .detail-table th {
            background: #1e293b;
        }

        body.dark-mode input,
        body.dark-mode textarea {
            background: #1e293b;
            color: #e5e7eb;
            border-color: #334155;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="header">
            <h1>Student Dashboard</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="../index.php" class="btn btn-home">Home</a>
                <a href="../about.php" class="btn btn-home">Team</a>
                <a href="../support.php" class="btn btn-home">Support</a>
                <a href="profile.php" class="btn btn-home">My Profile</a>
                <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>

        <div class="info-box">
            Welcome, <strong><?php echo h($name); ?></strong>
            |
            ID: <strong><?php echo h($institutional_id); ?></strong>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success"><?php echo h($success_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <?php if (!$application): ?>

            <div class="status-box status-pending">
                <strong>No room request found.</strong><br>
                You have an approved student account, but you have not requested a hostel room yet.
                Please submit your room request below. After submission, you must complete the Roommate Compatibility Form.
            </div>

            <div class="form-card">
                <h2>Submit Room Request</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="submit_application">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Department *</label>
                            <input type="text" name="department" required>
                        </div>

                        <div class="form-group">
                            <label>Batch *</label>
                            <input type="text" name="batch" required>
                        </div>

                        <div class="form-group">
                            <label>Semester *</label>
                            <input type="text" name="semester" required>
                        </div>

                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="text" name="phone" required>
                        </div>

                        <div class="form-group">
                            <label>Guardian Name *</label>
                            <input type="text" name="guardian_name" required>
                        </div>

                        <div class="form-group">
                            <label>Guardian Phone *</label>
                            <input type="text" name="guardian_phone" required>
                        </div>

                        <div class="form-group full">
                            <label>Address *</label>
                            <textarea name="address" required></textarea>
                        </div>

                        <div class="form-group full">
                            <label>Reason for Hostel Seat *</label>
                            <textarea name="reason_for_hostel" required></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-submit">Submit Room Request</button>
                </form>
            </div>

            <h2>Limited Student Portal</h2>

            <div class="portal-grid">
                <div class="portal-card">
                    <h3>My Profile</h3>
                    <p>View your account and student information.</p>
                    <a href="profile.php" class="btn btn-blue">View Profile</a>
                </div>

                <div class="portal-card">
                    <h3>Contact Support</h3>
                    <p>Contact admin or hostel authority for help.</p>
                    <a href="../support.php" class="btn btn-blue">Contact Support</a>
                </div>

                <div class="portal-card">
                    <h3>General Notices</h3>
                    <p>View general hostel announcements and university notices.</p>
                    <a href="notices.php" class="btn btn-blue">View Notices</a>
                </div>
            </div>

        <?php else: ?>

            <?php if ($compatibility_completed === 0): ?>

                <div class="status-box status-incomplete">
                    <strong>Your room request is not fully complete.</strong><br>
                    Your first room request form is saved, but you must complete the Roommate Compatibility Form before admin can process your request.
                    <br>
                    <a href="compatibility-form.php?required=1" class="btn btn-blue">
                        Complete Roommate Compatibility Form
                    </a>
                </div>

            <?php elseif ($status === 'pending'): ?>

                <div class="status-box status-pending">
                    <strong>Your room request has been submitted successfully.</strong><br>
                    Your request is now waiting for admin approval. You currently have limited student access.
                </div>

            <?php elseif ($status === 'waiting'): ?>

                <div class="status-box status-waiting">
                    <strong>Your room request has been reviewed by the admin.</strong><br>
                    Your request is valid, but room/seat allocation is currently on hold.
                    <br>
                    Message from authority:
                    <strong><?php echo h($application['admin_message'] ?? 'Please wait for further notice.'); ?></strong>
                </div>

            <?php elseif ($status === 'rejected'): ?>

                <div class="status-box status-rejected">
                    <strong>Your room request was rejected.</strong><br>
                    Message from authority:
                    <strong><?php echo h($application['admin_message'] ?? 'Please contact the hostel office.'); ?></strong>
                    <br><br>
                    You can update your information and submit a new room request below.
                </div>

            <?php elseif ($is_resident): ?>

                <div class="status-box status-assigned">
                    <strong>Congratulations! You are now a hall resident.</strong><br>
                    Room No:
                    <strong><?php echo h($application['room_no']); ?></strong>
                    |
                    Seat No:
                    <strong><?php echo h($application['seat_no']); ?></strong>
                </div>

            <?php endif; ?>

            <h2>Your Room Request Details</h2>

            <table class="detail-table">
                <tr>
                    <th>Full Name</th>
                    <td><?php echo h($application['full_name']); ?></td>
                </tr>
                <tr>
                    <th>Institutional ID</th>
                    <td><?php echo h($application['institutional_id']); ?></td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td><?php echo h($application['department']); ?></td>
                </tr>
                <tr>
                    <th>Batch</th>
                    <td><?php echo h($application['batch']); ?></td>
                </tr>
                <tr>
                    <th>Semester</th>
                    <td><?php echo h($application['semester']); ?></td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td><?php echo h($application['phone']); ?></td>
                </tr>
                <tr>
                    <th>Guardian Name</th>
                    <td><?php echo h($application['guardian_name']); ?></td>
                </tr>
                <tr>
                    <th>Guardian Phone</th>
                    <td><?php echo h($application['guardian_phone']); ?></td>
                </tr>
                <tr>
                    <th>Application Status</th>
                    <td><?php echo strtoupper(h($application['application_status'])); ?></td>
                </tr>
                <tr>
                    <th>Compatibility Form</th>
                    <td><?php echo $compatibility_completed === 1 ? 'COMPLETED' : 'NOT COMPLETED'; ?></td>
                </tr>
                <tr>
                    <th>Room No</th>
                    <td><?php echo $is_resident ? h($application['room_no']) : 'Not available until room is assigned'; ?></td>
                </tr>
                <tr>
                    <th>Seat No</th>
                    <td><?php echo $is_resident ? h($application['seat_no']) : 'Not available until room is assigned'; ?></td>
                </tr>
                <tr>
                    <th>Admin Message</th>
                    <td><?php echo h($application['admin_message'] ?? 'No message yet.'); ?></td>
                </tr>
            </table>

            <?php if ($status === 'rejected'): ?>

                <div class="form-card">
                    <h2>Submit New Room Request</h2>

                    <form method="POST">
                        <input type="hidden" name="action" value="submit_application">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Department *</label>
                                <input type="text" name="department" value="<?php echo h($application['department']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Batch *</label>
                                <input type="text" name="batch" value="<?php echo h($application['batch']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Semester *</label>
                                <input type="text" name="semester" value="<?php echo h($application['semester']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone *</label>
                                <input type="text" name="phone" value="<?php echo h($application['phone']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Guardian Name *</label>
                                <input type="text" name="guardian_name" value="<?php echo h($application['guardian_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Guardian Phone *</label>
                                <input type="text" name="guardian_phone" value="<?php echo h($application['guardian_phone']); ?>" required>
                            </div>

                            <div class="form-group full">
                                <label>Address *</label>
                                <textarea name="address" required><?php echo h($application['address']); ?></textarea>
                            </div>

                            <div class="form-group full">
                                <label>Reason for Hostel Seat *</label>
                                <textarea name="reason_for_hostel" required><?php echo h($application['reason_for_hostel']); ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-submit">Submit New Room Request</button>
                    </form>
                </div>

            <?php endif; ?>

            <?php if (!$is_resident): ?>

                <h2>Limited Student Portal</h2>

                <div class="portal-grid">
                    <div class="portal-card">
                        <h3>My Profile</h3>
                        <p>View account, room request, guardian, and compatibility information.</p>
                        <a href="profile.php" class="btn btn-blue">View Profile</a>
                    </div>

                    <div class="portal-card">
                        <h3>Room Request Status</h3>
                        <p>Track your room request status and admin message.</p>
                        <a href="dashboard.php" class="btn btn-blue">View Status</a>
                    </div>

                    <div class="portal-card">
                        <h3>Compatibility Form</h3>
                        <p>View or update roommate compatibility information.</p>
                        <a href="compatibility-form.php" class="btn btn-blue">Open Form</a>
                    </div>

                    <div class="portal-card">
                        <h3>Contact Support</h3>
                        <p>Contact admin or hostel authority for help.</p>
                        <a href="../support.php" class="btn btn-blue">Contact Support</a>
                    </div>

                    <div class="portal-card">
                        <h3>General Notices</h3>
                        <p>View general hostel announcements and university notices.</p>
                        <a href="notices.php" class="btn btn-blue">View Notices</a>
                    </div>

                    <div class="portal-card locked">
                        <h3>Resident Services Locked</h3>
                        <p>
                            Room details, payments, maintenance and leave applications
                            will unlock after room assignment.
                        </p>
                        <span class="btn btn-disabled">Locked</span>
                    </div>
                </div>

            <?php else: ?>

                <h2>Full Resident Portal</h2>

                <div class="portal-grid">
                    <div class="portal-card">
                        <h3>Room Information</h3>
                        <p>
                            Room No:
                            <strong><?php echo h($application['room_no']); ?></strong><br>
                            Seat No:
                            <strong><?php echo h($application['seat_no']); ?></strong>
                        </p>
                        <div class="room-action-buttons">
                            <a href="room-details.php" class="btn btn-green">View Room Details</a>
                        </div>
                    </div>

                    <div class="portal-card">
                        <h3>Maintenance Request</h3>
                        <p>Submit room, water, electricity, cleaning, or service problems.</p>
                        <a href="maintenance.php" class="btn btn-green">Submit Request</a>
                    </div>

                    <div class="portal-card">
                        <h3>Hostel Payment</h3>
                        <p>View hostel fee and payment information.</p>
                        <a href="payment.php" class="btn btn-green">View Payment</a>
                    </div>

                    <div class="portal-card">
                        <h3>Late Entry Application</h3>
                        <p>Submit a late entry request if you need to enter the hall after the regular entry time.</p>
                        <a href="late-entry-application.php" class="btn btn-green">Apply Late Entry</a>
                    </div>

                    <div class="portal-card">
                        <h3>Leave Application</h3>
                        <p>Submit hostel leave or absence application.</p>
                        <a href="leave-application.php" class="btn btn-green">Apply Leave</a>
                    </div>

                    <div class="portal-card">
                        <h3>Resident Notices</h3>
                        <p>View notices for hall residents.</p>
                        <a href="notices.php" class="btn btn-green">View Notices</a>
                    </div>

                    <div class="portal-card">
                        <h3>My Profile</h3>
                        <p>View your profile, guardian, compatibility, room, and contact details.</p>
                        <a href="profile.php" class="btn btn-green">View Profile</a>
                    </div>

                    <div class="portal-card">
                        <h3>Support</h3>
                        <p>Contact hostel authority or admin support.</p>
                        <a href="../support.php" class="btn btn-green">Contact Support</a>
                    </div>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>