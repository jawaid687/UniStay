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
   RESTORE USER
   Admin can restore only staff/student deleted by admin_manage_users
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_user') {
    $target_user_id = intval($_POST['target_user_id'] ?? 0);

    if ($target_user_id <= 0) {
        $error_msg = "Invalid user selected.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET is_deleted = 0,
                 deleted_at = NULL,
                 deleted_by = NULL,
                 deleted_from = NULL
             WHERE id = ?
             AND role IN ('staff', 'student')
             AND is_deleted = 1
             AND deleted_from = 'admin_manage_users'"
        );

        mysqli_stmt_bind_param($stmt, "i", $target_user_id);

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
            $success_msg = "User restored successfully.";
        } else {
            $error_msg = "Failed to restore user.";
        }

        mysqli_stmt_close($stmt);
    }
}

/* -------------------------------------------------
   PERMANENT DELETE USER
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'permanent_delete_user') {
    $target_user_id = intval($_POST['target_user_id'] ?? 0);

    if ($target_user_id <= 0) {
        $error_msg = "Invalid user selected.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE id = ?
             AND role IN ('staff', 'student')
             AND is_deleted = 1
             AND deleted_from = 'admin_manage_users'"
        );

        mysqli_stmt_bind_param($stmt, "i", $target_user_id);

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
            $success_msg = "User permanently deleted.";
        } else {
            $error_msg = "Failed to permanently delete user.";
        }

        mysqli_stmt_close($stmt);
    }
}

/* -------------------------------------------------
   FETCH DELETED STAFF/STUDENT USERS
------------------------------------------------- */
$query = "SELECT u.*, d.name AS deleted_by_name
          FROM users u
          LEFT JOIN users d ON u.deleted_by = d.id
          WHERE u.role IN ('staff', 'student')
          AND u.is_deleted = 1
          AND u.deleted_from = 'admin_manage_users'
          ORDER BY u.deleted_at DESC, u.id DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin User Recycle Bin - UniStay</title>
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
            max-width: 1400px;
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

        .btn-dashboard,
        .btn-users {
            background: var(--primary-color);
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

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1100px;
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

        .users-table tbody tr:hover {
            background: transparent !important;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            text-transform: uppercase;
        }

        .badge-staff {
            background: #17a2b8;
            color: white;
        }

        .badge-student {
            background: #6c757d;
            color: white;
        }

        .badge-verified {
            background: #28a745;
            color: white;
        }

        .badge-unverified {
            background: #dc3545;
            color: white;
        }

        .badge-approved {
            background: #28a745;
            color: white;
        }

        .badge-pending {
            background: #f59e0b;
            color: #000;
        }

        .action-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 160px;
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
        <h1>Admin User Recycle Bin</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
            <a href="manage-users.php" class="btn btn-users">Manage Users</a>
        </div>
    </div>

    <div class="info-box">
        Logged in as: <strong><?php echo h($name); ?></strong>
        |
        Role: <strong><?php echo h($role); ?></strong>
        <br>
        Deleted <strong>Staff</strong> and <strong>Student</strong> users from Admin Manage Users appear here.
    </div>

    <?php if ($success_msg !== ''): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg !== ''): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="users-table">
            <thead>
                <tr>
                    <th>User Info</th>
                    <th>Role</th>
                    <th>Email Status</th>
                    <th>Approval Status</th>
                    <th>Deleted Info</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>

                        <?php
                        $user_role = $row['role'] ?? 'student';

                        $role_badge = $user_role === 'staff'
                            ? 'badge-staff'
                            : 'badge-student';

                        $is_verified = (int)($row['is_verified'] ?? 0);
                        $is_approved = (int)($row['is_approved'] ?? 0);
                        ?>

                        <tr>
                            <td>
                                <strong><?php echo h($row['name'] ?? 'N/A'); ?></strong><br>
                                ID: <?php echo h($row['institutional_id'] ?? 'N/A'); ?><br>
                                Email: <?php echo h($row['email'] ?? 'N/A'); ?><br>
                                Phone: <?php echo h($row['phone'] ?? 'N/A'); ?>
                            </td>

                            <td>
                                <span class="badge <?php echo h($role_badge); ?>">
                                    <?php echo h($user_role); ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($is_verified === 1): ?>
                                    <span class="badge badge-verified">Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-unverified">Not Verified</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($is_approved === 1): ?>
                                    <span class="badge badge-approved">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending / Disabled</span>
                                <?php endif; ?>
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
                                        <input type="hidden" name="action" value="restore_user">
                                        <input type="hidden" name="target_user_id" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="btn btn-restore">Restore</button>
                                    </form>

                                    <form method="POST" onsubmit="return confirm('Are you sure? This user will be permanently deleted and cannot be restored.');">
                                        <input type="hidden" name="action" value="permanent_delete_user">
                                        <input type="hidden" name="target_user_id" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="btn btn-delete">Permanent Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">
                            Admin User Recycle Bin is empty.
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