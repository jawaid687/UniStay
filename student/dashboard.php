<?php
session_start();
require_once '../includes/db.php';

// Only student can access student dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Student';
$institutional_id = $_SESSION['institutional_id'] ?? 'N/A';

$success_msg = '';
$error_msg = '';

// -----------------------------
// CHECK EXISTING APPLICATION
// -----------------------------
$application = null;

$check_stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM student_records 
     WHERE is_deleted = 0 
     AND (user_id = ? OR institutional_id = ?)
     LIMIT 1"
);

mysqli_stmt_bind_param($check_stmt, "is", $user_id, $institutional_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$application = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

// -----------------------------
// SUBMIT / UPDATE HOSTEL APPLICATION
// -----------------------------
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
            // If already assigned, student cannot edit application
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
                    $success_msg = "Your hostel application has been updated and submitted for review.";
                } else {
                    $error_msg = "Failed to update application.";
                }

                mysqli_stmt_close($update_stmt);
            }
        } else {
            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO student_records
                 (user_id, full_name, institutional_id, department, batch, semester, phone, guardian_name, guardian_phone, address, reason_for_hostel, application_status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
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
                $success_msg = "Your hostel application has been submitted successfully. Please wait for authority review.";
            } else {
                $error_msg = "Failed to submit hostel application.";
            }

            mysqli_stmt_close($insert_stmt);
        }

        // Refresh application data after submit/update
        $refresh_stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM student_records 
             WHERE is_deleted = 0 
             AND (user_id = ? OR institutional_id = ?)
             LIMIT 1"
        );

        mysqli_stmt_bind_param($refresh_stmt, "is", $user_id, $institutional_id);
        mysqli_stmt_execute($refresh_stmt);
        $refresh_result = mysqli_stmt_get_result($refresh_stmt);
        $application = mysqli_fetch_assoc($refresh_result);
        mysqli_stmt_close($refresh_stmt);
    }
}

$status = $application['application_status'] ?? 'not_submitted';
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
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
                gap: 15px;
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
            <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <div class="info-box">
        Welcome, <strong><?php echo htmlspecialchars($name); ?></strong>
        |
        ID: <strong><?php echo htmlspecialchars($institutional_id); ?></strong>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <?php if (!$application): ?>

        <div class="status-box status-pending">
            <strong>No hostel application found.</strong><br>
            Please complete your hostel application below. After submission, wait for authority review.
        </div>

        <div class="form-card">
            <h2>Complete Hostel Application</h2>

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

                <button type="submit" class="btn btn-submit">Submit Hostel Application</button>
            </form>
        </div>

    <?php else: ?>

        <?php if ($status === 'pending'): ?>
            <div class="status-box status-pending">
                <strong>Your hostel application is under review.</strong><br>
                Please wait for authority approval.
            </div>
        <?php elseif ($status === 'waiting'): ?>
            <div class="status-box status-waiting">
                <strong>Your application is valid, but no seat is available right now.</strong><br>
                Message from authority:
                <strong><?php echo htmlspecialchars($application['admin_message'] ?? 'Please wait for further notice.'); ?></strong>
            </div>
        <?php elseif ($status === 'rejected'): ?>
            <div class="status-box status-rejected">
                <strong>Your hostel application was rejected.</strong><br>
                Message from authority:
                <strong><?php echo htmlspecialchars($application['admin_message'] ?? 'Please contact the hostel office.'); ?></strong>
                <br><br>
                You may update your information and resubmit.
            </div>
        <?php elseif ($status === 'assigned'): ?>
            <div class="status-box status-assigned">
                <strong>Congratulations! Your hostel seat has been assigned.</strong><br>
                Room No:
                <strong><?php echo htmlspecialchars($application['room_no'] ?? 'N/A'); ?></strong>
                |
                Seat No:
                <strong><?php echo htmlspecialchars($application['seat_no'] ?? 'N/A'); ?></strong>
            </div>
        <?php endif; ?>

        <h2>Your Hostel Application Details</h2>

        <table class="detail-table">
            <tr>
                <th>Full Name</th>
                <td><?php echo htmlspecialchars($application['full_name']); ?></td>
            </tr>
            <tr>
                <th>Institutional ID</th>
                <td><?php echo htmlspecialchars($application['institutional_id']); ?></td>
            </tr>
            <tr>
                <th>Department</th>
                <td><?php echo htmlspecialchars($application['department']); ?></td>
            </tr>
            <tr>
                <th>Batch</th>
                <td><?php echo htmlspecialchars($application['batch']); ?></td>
            </tr>
            <tr>
                <th>Semester</th>
                <td><?php echo htmlspecialchars($application['semester']); ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?php echo htmlspecialchars($application['phone']); ?></td>
            </tr>
            <tr>
                <th>Guardian Name</th>
                <td><?php echo htmlspecialchars($application['guardian_name']); ?></td>
            </tr>
            <tr>
                <th>Guardian Phone</th>
                <td><?php echo htmlspecialchars($application['guardian_phone']); ?></td>
            </tr>
            <tr>
                <th>Application Status</th>
                <td><?php echo strtoupper(htmlspecialchars($application['application_status'])); ?></td>
            </tr>
            <tr>
                <th>Admin Message</th>
                <td><?php echo htmlspecialchars($application['admin_message'] ?? 'No message yet.'); ?></td>
            </tr>
        </table>

        <?php if ($status !== 'assigned'): ?>

            <div class="form-card" style="margin-top: 30px;">
                <h2>Update Application Information</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="submit_application">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Department *</label>
                            <input type="text" name="department" value="<?php echo htmlspecialchars($application['department']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Batch *</label>
                            <input type="text" name="batch" value="<?php echo htmlspecialchars($application['batch']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Semester *</label>
                            <input type="text" name="semester" value="<?php echo htmlspecialchars($application['semester']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($application['phone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Guardian Name *</label>
                            <input type="text" name="guardian_name" value="<?php echo htmlspecialchars($application['guardian_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Guardian Phone *</label>
                            <input type="text" name="guardian_phone" value="<?php echo htmlspecialchars($application['guardian_phone']); ?>" required>
                        </div>

                        <div class="form-group full">
                            <label>Address *</label>
                            <textarea name="address" required><?php echo htmlspecialchars($application['address']); ?></textarea>
                        </div>

                        <div class="form-group full">
                            <label>Reason for Hostel Seat *</label>
                            <textarea name="reason_for_hostel" required><?php echo htmlspecialchars($application['reason_for_hostel']); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-submit">Update & Resubmit Application</button>
                </form>
            </div>

        <?php else: ?>

            <h2>Full Hostel Student Portal</h2>

            <div class="portal-grid">
                <div class="portal-card">
                    <h3>Room Information</h3>
                    <p>
                        Room No:
                        <strong><?php echo htmlspecialchars($application['room_no'] ?? 'N/A'); ?></strong><br>
                        Seat No:
                        <strong><?php echo htmlspecialchars($application['seat_no'] ?? 'N/A'); ?></strong>
                    </p>
                </div>

                <div class="portal-card">
                    <h3>Maintenance Request</h3>
                    <p>
                        Submit hostel maintenance or service requests.
                        This module will be added next.
                    </p>
                </div>

                <div class="portal-card">
                    <h3>Notices</h3>
                    <p>
                        View hostel notices, announcements, and important instructions.
                    </p>
                </div>

                <div class="portal-card">
                    <h3>Profile</h3>
                    <p>
                        View your hostel profile, guardian information, and contact details.
                    </p>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>