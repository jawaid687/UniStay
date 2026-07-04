<?php
session_start();
require_once '../includes/db.php';

// Only Super Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

$current_super_admin_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// -----------------------------
// RESTORE USER
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_user') {
    $user_id = intval($_POST['user_id']);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users 
         SET is_deleted = 0, 
             deleted_at = NULL, 
             deleted_by = NULL, 
             deleted_from = NULL
         WHERE id = ? 
         AND is_deleted = 1 
         AND deleted_from = 'super_admin_panel'"
    );

    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
        $success_msg = "User restored successfully.";
    } else {
        $error_msg = "Failed to restore user.";
    }

    mysqli_stmt_close($stmt);
}

// -----------------------------
// PERMANENT DELETE USER
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'permanent_delete') {
    $user_id = intval($_POST['user_id']);

    if ($user_id === $current_super_admin_id) {
        $error_msg = "You cannot permanently delete your own account.";
    } else {
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id, role 
             FROM users 
             WHERE id = ? 
             AND is_deleted = 1 
             AND deleted_from = 'super_admin_panel'
             LIMIT 1"
        );

        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);

        if (!$user) {
            $error_msg = "User not found in recycle bin.";
        } elseif ($user['role'] === 'super_admin') {
            $error_msg = "Super Admin account cannot be permanently deleted.";
        } else {
            $delete_stmt = mysqli_prepare(
                $conn,
                "DELETE FROM users 
                 WHERE id = ? 
                 AND is_deleted = 1 
                 AND deleted_from = 'super_admin_panel' 
                 AND role != 'super_admin'"
            );

            mysqli_stmt_bind_param($delete_stmt, "i", $user_id);

            if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) === 1) {
                $success_msg = "User permanently deleted.";
            } else {
                $error_msg = "Failed to permanently delete user.";
            }

            mysqli_stmt_close($delete_stmt);
        }
    }
}

// Fetch deleted users
$query = "SELECT u.*, d.name AS deleted_by_name
          FROM users u
          LEFT JOIN users d ON u.deleted_by = d.id
          WHERE u.is_deleted = 1 
          AND u.deleted_from = 'super_admin_panel'
          ORDER BY u.deleted_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Recycle Bin - UniStay</title>
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
            max-width: 1200px;
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

        .btn-back {
            background-color: #00897b;
            color: white;
        }

        .btn-back:hover {
            background-color: #00695c;
        }

        .btn-home {
            background-color: #64748b;
            color: white;
        }

        .btn-home:hover {
            background-color: #475569;
        }

        .btn-restore {
            background-color: #28a745;
            color: white;
        }

        .btn-restore:hover {
            background-color: #218838;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background-color: #b02a37;
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

        .info-box {
            background: #e0f2f1;
            border-left: 5px solid #00897b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #004d40;
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

        .badge-admin {
            background-color: #007bff;
        }

        .badge-staff {
            background-color: #17a2b8;
        }

        .badge-student {
            background-color: #6c757d;
        }

        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        form {
            margin: 0;
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
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="container">

    <div class="header">
        <h1>User Recycle Bin</h1>

        <div class="header-actions">
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="manage-users.php" class="btn btn-back">Back to Manage Users</a>
        </div>
    </div>

    <div class="info-box">
        Deleted users from <strong>Super Admin Manage Users</strong> appear here.
        Only Super Admin can restore or permanently delete these users.
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
                <th>Role</th>
                <th>Deleted At</th>
                <th>Deleted By</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <?php
                    $role_class = 'badge-student';

                    if ($row['role'] === 'admin') {
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

                        <td><?php echo htmlspecialchars($row['deleted_at'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['deleted_by_name'] ?? 'Unknown'); ?></td>

                        <td>
                            <div class="action-group">
                                <form method="POST">
                                    <input type="hidden" name="action" value="restore_user">
                                    <input type="hidden" name="user_id" value="<?php echo intval($row['id']); ?>">
                                    <button type="submit" class="btn btn-restore">Restore</button>
                                </form>

                                <form method="POST" onsubmit="return confirm('Are you sure? This user will be permanently deleted and cannot be restored.');">
                                    <input type="hidden" name="action" value="permanent_delete">
                                    <input type="hidden" name="user_id" value="<?php echo intval($row['id']); ?>">
                                    <button type="submit" class="btn btn-delete">Permanent Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Recycle bin is empty.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<script src="/UniStay/assets/js/theme.js"></script>
</body>
</html>