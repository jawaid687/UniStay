<?php
session_start();
require_once '../includes/db.php';

// Security: Only Super Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

$current_super_admin_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// -----------------------------
// APPROVE USER
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $user_id = intval($_POST['user_id']);

    $stmt = mysqli_prepare($conn, "UPDATE users SET is_approved = 1 WHERE id = ? AND id != ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $current_super_admin_id);

    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "User approved successfully.";
    } else {
        $error_msg = "Failed to approve user.";
    }

    mysqli_stmt_close($stmt);
}

// -----------------------------
// CHANGE NORMAL USER ROLE
// student / staff / admin only
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_role') {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];

    $allowed_roles = ['student', 'staff', 'admin'];

    if (!in_array($new_role, $allowed_roles)) {
        $error_msg = "Invalid role selected.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ? AND role != 'super_admin'");
        mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "User role updated successfully.";
        } else {
            $error_msg = "Failed to update user role.";
        }

        mysqli_stmt_close($stmt);
    }
}

// -----------------------------
// DELETE USER
// Super Admin cannot delete himself
// Super Admin cannot delete Super Admin account
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = intval($_POST['user_id']);

    if ($user_id === $current_super_admin_id) {
        $error_msg = "You cannot delete your own Super Admin account.";
    } else {
        $check_stmt = mysqli_prepare($conn, "SELECT id, name, role FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $user_to_delete = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);

        if (!$user_to_delete) {
            $error_msg = "User not found.";
        } elseif ($user_to_delete['role'] === 'super_admin') {
            $error_msg = "Super Admin account cannot be deleted.";
        } else {
            $delete_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND id != ? AND role != 'super_admin'");
            mysqli_stmt_bind_param($delete_stmt, "ii", $user_id, $current_super_admin_id);

            if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) === 1) {
                $success_msg = "User deleted successfully.";
            } else {
                $error_msg = "Failed to delete user.";
            }

            mysqli_stmt_close($delete_stmt);
        }
    }
}

// -----------------------------
// TRANSFER SUPER ADMIN POWER
// Only one Super Admin rule:
// Current Super Admin -> Admin
// Selected User -> Super Admin
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_power') {
    $new_super_admin_id = intval($_POST['new_super_admin_id']);

    if ($new_super_admin_id === $current_super_admin_id) {
        $error_msg = "You are already the Super Admin.";
    } else {
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, role, is_verified, is_approved 
             FROM users 
             WHERE id = ? 
             LIMIT 1"
        );

        mysqli_stmt_bind_param($check_stmt, "i", $new_super_admin_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $selected_user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);

        if (!$selected_user) {
            $error_msg = "Selected user not found.";
        } elseif ($selected_user['is_verified'] != 1 || $selected_user['is_approved'] != 1) {
            $error_msg = "Power can only be transferred to a verified and approved user.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                $demote_stmt = mysqli_prepare($conn, "UPDATE users SET role = 'admin' WHERE id = ? AND role = 'super_admin'");
                mysqli_stmt_bind_param($demote_stmt, "i", $current_super_admin_id);
                mysqli_stmt_execute($demote_stmt);

                if (mysqli_stmt_affected_rows($demote_stmt) !== 1) {
                    throw new Exception("Failed to demote current Super Admin.");
                }

                mysqli_stmt_close($demote_stmt);

                $promote_stmt = mysqli_prepare($conn, "UPDATE users SET role = 'super_admin' WHERE id = ?");
                mysqli_stmt_bind_param($promote_stmt, "i", $new_super_admin_id);
                mysqli_stmt_execute($promote_stmt);

                if (mysqli_stmt_affected_rows($promote_stmt) !== 1) {
                    throw new Exception("Failed to promote selected user.");
                }

                mysqli_stmt_close($promote_stmt);

                mysqli_commit($conn);

                session_unset();
                session_destroy();

                header("Location: ../auth/login.php");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_msg = "Power transfer failed. Please try again.";
            }
        }
    }
}

// Fetch all users
$users_query = "SELECT * FROM users ORDER BY 
                CASE 
                    WHEN role = 'super_admin' THEN 1
                    WHEN role = 'admin' THEN 2
                    WHEN role = 'staff' THEN 3
                    WHEN role = 'student' THEN 4
                    ELSE 5
                END,
                id DESC";

$users_result = mysqli_query($conn, $users_query);

$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Super Admin';
$institutional_id = isset($_SESSION['institutional_id']) ? $_SESSION['institutional_id'] : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - DIU Hostel Management System</title>

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
            background-color: #6c757d;
            color: white;
        }

        .btn-home:hover {
            background-color: #5a6268;
        }

        .btn-dashboard {
            background-color: #00897b;
            color: white;
        }

        .btn-dashboard:hover {
            background-color: #00695c;
        }

        .btn-logout {
            background-color: #dc3545;
            color: white;
        }

        .btn-logout:hover {
            background-color: #b02a37;
        }

        .info-box {
            background: #e0f2f1;
            border-left: 5px solid #00897b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #004d40;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #664d03;
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

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: inline-block;
        }

        .badge-super {
            background-color: #6f42c1;
        }

        .badge-admin {
            background-color: #007bff;
        }

        .badge-staff {
            background-color: #17a2b8;
        }

        .badge-student {
            background-color: #6c757d;
        }

        .badge-approved {
            background-color: #28a745;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-unverified {
            background-color: #dc3545;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-update {
            background-color: #007bff;
            color: white;
        }

        .btn-update:hover {
            background-color: #0056b3;
        }

        .btn-transfer {
            background-color: #6f42c1;
            color: white;
        }

        .btn-transfer:hover {
            background-color: #5a32a3;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background-color: #b02a37;
        }

        select {
            padding: 7px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        form {
            margin: 0;
        }

        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .disabled-text {
            color: #777;
            font-size: 13px;
        }

        @media (max-width: 900px) {
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

    <link rel="stylesheet" href="/UniStay/assets/css/theme.css">
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="container">

    <div class="header">
        <h1>Manage Users</h1>

        <div class="header-actions">
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="dashboard.php" class="btn btn-dashboard">Super Admin Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <div class="info-box">
        <strong>DIU Hostel Management System</strong><br>
        Logged in as: <strong><?php echo htmlspecialchars($name); ?></strong>
        |
        ID: <strong><?php echo htmlspecialchars($institutional_id); ?></strong>
    </div>

    <div class="warning-box">
        <strong>Super Admin Rule:</strong>
        The system must have only one Super Admin. If you transfer power,
        your role will become Admin and the selected user will become the new Super Admin.
        After transfer, you will be logged out automatically.
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Institutional ID</th>
                <th>Email</th>
                <th>Current Role</th>
                <th>Email Status</th>
                <th>Approval Status</th>
                <th>Change Role</th>
                <th>Transfer Power</th>
                <th>Delete User</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($users_result)): ?>

                    <?php
                    $role_class = 'badge-student';

                    if ($row['role'] === 'super_admin') {
                        $role_class = 'badge-super';
                    } elseif ($row['role'] === 'admin') {
                        $role_class = 'badge-admin';
                    } elseif ($row['role'] === 'staff') {
                        $role_class = 'badge-staff';
                    }
                    ?>

                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>

                        <td><?php echo htmlspecialchars($row['institutional_id'] ?? 'N/A'); ?></td>

                        <td><?php echo htmlspecialchars($row['email']); ?></td>

                        <td>
                            <span class="badge <?php echo $role_class; ?>">
                                <?php echo strtoupper(htmlspecialchars($row['role'])); ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($row['is_verified'] == 1): ?>
                                <span class="badge badge-approved">Verified</span>
                            <?php else: ?>
                                <span class="badge badge-unverified">Unverified</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($row['is_approved'] == 1): ?>
                                <span class="badge badge-approved">Approved</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Pending</span>

                                <?php if ($row['is_verified'] == 1): ?>
                                    <form method="POST" class="action-group" style="margin-top: 8px;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="user_id" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="btn btn-approve">Approve</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($row['role'] !== 'super_admin'): ?>
                                <form method="POST" class="action-group">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?php echo intval($row['id']); ?>">

                                    <select name="new_role" required>
                                        <option value="student" <?php if ($row['role'] === 'student') echo 'selected'; ?>>Student</option>
                                        <option value="staff" <?php if ($row['role'] === 'staff') echo 'selected'; ?>>Staff</option>
                                        <option value="admin" <?php if ($row['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                                    </select>

                                    <button type="submit" class="btn btn-update">Update</button>
                                </form>
                            <?php else: ?>
                                <span class="disabled-text">Root authority</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($row['id'] != $current_super_admin_id && $row['is_verified'] == 1 && $row['is_approved'] == 1): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure? You will lose Super Admin authority and be logged out.');">
                                    <input type="hidden" name="action" value="transfer_power">
                                    <input type="hidden" name="new_super_admin_id" value="<?php echo intval($row['id']); ?>">
                                    <button type="submit" class="btn btn-transfer">Transfer Power</button>
                                </form>
                            <?php elseif ($row['id'] == $current_super_admin_id): ?>
                                <span class="disabled-text">Current Super Admin</span>
                            <?php else: ?>
                                <span class="disabled-text">Must be verified & approved</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($row['id'] != $current_super_admin_id && $row['role'] !== 'super_admin'): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this user? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo intval($row['id']); ?>">
                                    <button type="submit" class="btn btn-delete">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="disabled-text">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align:center;">No users found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<script src="/UniStay/assets/js/theme.js"></script>
</body>
</html>