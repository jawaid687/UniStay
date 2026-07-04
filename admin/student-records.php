<?php
session_start();
require_once '../includes/db.php';

// Admin + Super Admin can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// -----------------------------
// ADD STUDENT RECORD
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $full_name = trim($_POST['full_name']);
    $institutional_id = trim($_POST['institutional_id']);
    $department = trim($_POST['department']);
    $batch = trim($_POST['batch']);
    $semester = trim($_POST['semester']);
    $phone = trim($_POST['phone']);
    $guardian_name = trim($_POST['guardian_name']);
    $guardian_phone = trim($_POST['guardian_phone']);
    $room_no = trim($_POST['room_no']);

    if (empty($full_name) || empty($institutional_id)) {
        $error_msg = "Full name and institutional ID are required.";
    } else {
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM student_records WHERE institutional_id = ? AND is_deleted = 0 LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, "s", $institutional_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        mysqli_stmt_close($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "A student record with this institutional ID already exists.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO student_records 
                (full_name, institutional_id, department, batch, semester, phone, guardian_name, guardian_phone, room_no, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssi",
                $full_name,
                $institutional_id,
                $department,
                $batch,
                $semester,
                $phone,
                $guardian_name,
                $guardian_phone,
                $room_no,
                $current_user_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Student record added successfully.";
            } else {
                $error_msg = "Failed to add student record.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// -----------------------------
// MOVE STUDENT RECORD TO ADMIN RECYCLE BIN
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    $student_id = intval($_POST['student_id']);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE student_records
         SET is_deleted = 1,
             deleted_at = NOW(),
             deleted_by = ?,
             deleted_from = 'admin_panel'
         WHERE id = ? AND is_deleted = 0"
    );

    mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $student_id);

    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
        $success_msg = "Student record moved to Admin Recycle Bin.";
    } else {
        $error_msg = "Failed to move student record to recycle bin.";
    }

    mysqli_stmt_close($stmt);
}

// Fetch active student records
$query = "SELECT * FROM student_records 
          WHERE is_deleted = 0 
          ORDER BY id DESC";

$result = mysqli_query($conn, $query);

$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Records - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/UniStay/assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 1300px;
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

        .btn-add {
            background-color: #00897b;
            color: white;
            width: 100%;
            margin-top: 10px;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            color: #1f2937;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background-color: #f1f8f7;
            color: #004d40;
        }

        tr:hover {
            background-color: #f9fdfc;
        }

        .badge-active {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        form {
            margin: 0;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="container">

    <div class="header">
        <h1>Student Records</h1>

        <div class="header-actions">
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
        Student records deleted from this page will move to <strong>Admin Recycle Bin</strong>.
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="form-card">
        <h2>Add Student Record</h2>

        <form method="POST">
            <input type="hidden" name="action" value="add_student">

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-group">
                    <label>Institutional ID *</label>
                    <input type="text" name="institutional_id" required>
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department">
                </div>

                <div class="form-group">
                    <label>Batch</label>
                    <input type="text" name="batch">
                </div>

                <div class="form-group">
                    <label>Semester</label>
                    <input type="text" name="semester">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>

                <div class="form-group">
                    <label>Guardian Name</label>
                    <input type="text" name="guardian_name">
                </div>

                <div class="form-group">
                    <label>Guardian Phone</label>
                    <input type="text" name="guardian_phone">
                </div>

                <div class="form-group">
                    <label>Room No</label>
                    <input type="text" name="room_no">
                </div>
            </div>

            <button type="submit" class="btn btn-add">Add Student Record</button>
        </form>
    </div>

    <h2>All Active Student Records</h2>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Institutional ID</th>
                <th>Department</th>
                <th>Batch</th>
                <th>Semester</th>
                <th>Phone</th>
                <th>Guardian</th>
                <th>Room</th>
                <th>Status</th>
                <th>Delete</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['institutional_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['batch'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['semester'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>

                        <td>
                            <?php echo htmlspecialchars($row['guardian_name'] ?? 'N/A'); ?>
                            <br>
                            <small><?php echo htmlspecialchars($row['guardian_phone'] ?? ''); ?></small>
                        </td>

                        <td><?php echo htmlspecialchars($row['room_no'] ?? 'N/A'); ?></td>

                        <td>
                            <span class="badge-active">ACTIVE</span>
                        </td>

                        <td>
                            <form method="POST" onsubmit="return confirm('Move this student record to Admin Recycle Bin? You can restore it later.');">
                                <input type="hidden" name="action" value="delete_student">
                                <input type="hidden" name="student_id" value="<?php echo intval($row['id']); ?>">
                                <button type="submit" class="btn btn-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align:center;">No student records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<script src="/UniStay/assets/js/theme.js"></script>
</body>
</html>