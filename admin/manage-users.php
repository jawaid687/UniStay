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

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------
   BACKEND SECURITY:
   Admin can manage ONLY staff and student.
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_user_id = intval($_POST['target_user_id'] ?? 0);

    if ($target_user_id <= 0) {
        $error_msg = "Invalid user selected.";
    } else {
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, email, role, is_verified, is_approved
             FROM users
             WHERE id = ?
             AND role IN ('staff', 'student')
             AND is_deleted = 0
             LIMIT 1"
        );

        mysqli_stmt_bind_param($check_stmt, "i", $target_user_id);
        mysqli_stmt_execute($check_stmt);
        $target_user = mysqli_stmt_get_result($check_stmt)->fetch_assoc();
        mysqli_stmt_close($check_stmt);

        if (!$target_user) {
            $error_msg = "You are not allowed to manage this user.";
        } else {

            /* APPROVE LOGIN */
            if ($action === 'approve_user') {
                if ((int) $target_user['is_verified'] !== 1) {
                    $error_msg = "This user has not verified email yet. You cannot approve login.";
                } elseif ((int) $target_user['is_approved'] === 1) {
                    $info_msg = "This user is already approved.";
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET is_approved = 1
                         WHERE id = ?
                         AND role IN ('staff', 'student')
                         AND is_deleted = 0"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $target_user_id);

                    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                        $success_msg = "User login approved successfully.";
                    } else {
                        $error_msg = "Failed to approve user.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }

            /* DISABLE LOGIN */ elseif ($action === 'disable_user') {
                if ((int) $target_user['is_approved'] === 0) {
                    $info_msg = "This user is already pending or disabled.";
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET is_approved = 0
                         WHERE id = ?
                         AND role IN ('staff', 'student')
                         AND is_deleted = 0"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $target_user_id);

                    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                        $success_msg = "User login disabled successfully.";
                    } else {
                        $error_msg = "Failed to disable user.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }

            /* CHANGE ROLE BETWEEN STAFF AND STUDENT ONLY */ elseif ($action === 'change_role') {
                $new_role = $_POST['new_role'] ?? '';

                if (!in_array($new_role, ['staff', 'student'])) {
                    $error_msg = "Admin can only assign Staff or Student role.";
                } elseif ($new_role === $target_user['role']) {
                    $info_msg = "No role change was made.";
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET role = ?
                         WHERE id = ?
                         AND role IN ('staff', 'student')
                         AND is_deleted = 0"
                    );

                    mysqli_stmt_bind_param($stmt, "si", $new_role, $target_user_id);

                    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                        $success_msg = "User role updated successfully.";
                    } else {
                        $error_msg = "Failed to update user role.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }

            /* SOFT DELETE USER */ elseif ($action === 'delete_user') {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET is_deleted = 1,
                         deleted_at = NOW(),
                         deleted_by = ?,
                         deleted_from = 'admin_manage_users'
                     WHERE id = ?
                     AND role IN ('staff', 'student')
                     AND is_deleted = 0"
                );

                mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $target_user_id);

                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                    $success_msg = "User moved to recycle bin successfully.";
                } else {
                    $error_msg = "Failed to delete user.";
                }

                mysqli_stmt_close($stmt);
            } else {
                $error_msg = "Invalid action selected.";
            }
        }
    }
}

/* -------------------------------------------------
   FETCH ONLY STAFF AND STUDENT
------------------------------------------------- */

$query = "SELECT id, name, email, institutional_id, phone, role, is_verified, is_approved, created_at
          FROM users
          WHERE role IN ('staff', 'student')
          AND is_deleted = 0
          ORDER BY
          CASE
              WHEN is_verified = 1 AND is_approved = 0 THEN 1
              WHEN is_verified = 0 THEN 2
              WHEN is_verified = 1 AND is_approved = 1 THEN 3
              ELSE 4
          END,
          created_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users - UniStay</title>
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

        .btn-update {
            background: var(--primary-color);
            color: white;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-disable,
        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .btn-disabled {
            background: #94a3b8;
            color: white;
            cursor: not-allowed;
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

        .rule-box {
            background: #fff8e1;
            color: #664d03;
            border-left: 5px solid #f59e0b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .rule-box strong {
            color: #664d03;
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

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            max-width: 450px;
            padding: 11px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: #1f2937;
            font-size: 14px;
        }

        body.dark-mode .search-box input {
            background: #1e293b;
            color: #e5e7eb;
            border: 1px solid #334155;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse;
            margin-top: 20px;
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

        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: #1f2937;
        }

        body.dark-mode select {
            background: #1e293b;
            color: #e5e7eb;
            border: 1px solid #334155;
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
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .small-text {
            color: var(--muted-text);
            font-size: 13px;
            line-height: 1.5;
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
            <h1>Manage Users</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="../index.php" class="btn btn-home">Home</a>
                <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
                <a href="user-recycle-bin.php" class="btn btn-recycle">User Recycle Bin</a>
                <a href="../auth/logout.php" class="btn btn-delete">Logout</a>
            </div>
        </div>

        <div class="info-box">
            <strong>UniStay Admin Panel</strong><br>
            Logged in as: <strong><?php echo h($name); ?></strong>
            |
            Role: <strong><?php echo h($role); ?></strong>
        </div>

        <div class="rule-box">
            <strong>Admin Rule:</strong>
            Admin can manage only <strong>Staff</strong> and <strong>Student</strong> users.
            Admin cannot manage another Admin or Super Admin because they are same/higher level.
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

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by name, email, ID, phone, or role...">
        </div>

        <div class="table-wrapper">
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Institutional ID</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Current Role</th>
                        <th>Email Status</th>
                        <th>Approval Status</th>
                        <th>Change Role</th>
                        <th>Login Control</th>
                        <th>Delete User</th>
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

                            $is_verified = (int) ($row['is_verified'] ?? 0);
                            $is_approved = (int) ($row['is_approved'] ?? 0);
                            ?>

                            <tr>
                                <td><?php echo h($row['name'] ?? 'N/A'); ?></td>
                                <td><?php echo h($row['institutional_id'] ?? 'N/A'); ?></td>
                                <td><?php echo h($row['email'] ?? 'N/A'); ?></td>
                                <td><?php echo h($row['phone'] ?? 'N/A'); ?></td>

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
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form method="POST" class="action-group">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="target_user_id" value="<?php echo intval($row['id']); ?>">

                                        <select name="new_role" required>
                                            <option value="student" <?php if ($user_role === 'student')
                                                echo 'selected'; ?>>
                                                Student</option>
                                            <option value="staff" <?php if ($user_role === 'staff')
                                                echo 'selected'; ?>>Staff
                                            </option>
                                        </select>

                                        <button type="submit" class="btn btn-update">Update</button>
                                    </form>
                                </td>

                                <td>
                                    <?php if ($is_verified !== 1): ?>
                                        <button class="btn btn-disabled" disabled>Waiting Verification</button>

                                    <?php elseif ($is_approved !== 1): ?>
                                        <form method="POST" onsubmit="return confirm('Approve login for this user?');">
                                            <input type="hidden" name="action" value="approve_user">
                                            <input type="hidden" name="target_user_id" value="<?php echo intval($row['id']); ?>">
                                            <button type="submit" class="btn btn-approve">Approve</button>
                                        </form>

                                    <?php else: ?>
                                        <form method="POST" onsubmit="return confirm('Disable login for this user?');">
                                            <input type="hidden" name="action" value="disable_user">
                                            <input type="hidden" name="target_user_id" value="<?php echo intval($row['id']); ?>">
                                            <button type="submit" class="btn btn-disable">Disable</button>
                                        </form>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form method="POST"
                                        onsubmit="return confirm('Delete this user? Admin can delete only Staff and Student users.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_user_id" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center;">
                                No staff or student users found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('usersTable');

        if (searchInput && table) {
            searchInput.addEventListener('keyup', function () {
                const filter = searchInput.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(function (row) {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }
    </script>

    <script src="../assets/js/theme.js"></script>
</body>

</html>