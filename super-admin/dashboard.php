<?php
session_start();
require_once '../includes/db.php';

// Security: Only Super Admin can access this dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle the "Approve" button click
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve') {
    $user_id_to_approve = mysqli_real_escape_string($conn, $_POST['user_id']);

    $update_query = "UPDATE users SET is_approved = 1 WHERE id = '$user_id_to_approve'";
    if (mysqli_query($conn, $update_query)) {
        $success_msg = "User has been approved successfully!";
    } else {
        $error_msg = "Error approving user. Please try again.";
    }
}

// Fetch all users except the currently logged-in Super Admin
$current_admin_id = $_SESSION['user_id'];
$users_query = "SELECT * FROM users WHERE id != '$current_admin_id' ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_query);

// Safe display name
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Super Admin';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard - DIU Hostel Management System</title>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f8f7;
            margin: 0;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #00897b;
            padding-bottom: 15px;
            margin-bottom: 20px;
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

        .btn-logout {
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 5px;
            font-weight: bold;
        }

        .btn-logout:hover {
            background-color: #b02a37;
        }

        .quick-actions {
            display: flex;
            gap: 15px;
            margin: 25px 0;
            flex-wrap: wrap;
        }

        .btn-admin-panel {
            background-color: #00897b;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }

        .btn-admin-panel:hover {
            background-color: #00695c;
        }

        .btn-home {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }

        .btn-home:hover {
            background-color: #5a6268;
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

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
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

        .badge-verified {
            background-color: #28a745;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-approved {
            background-color: #007bff;
        }

        .badge-role {
            background-color: #6c757d;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 7px 13px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-approve:hover {
            background-color: #218838;
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

        .section-title {
            color: #004d40;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            table {
                font-size: 13px;
            }

            th,
            td {
                padding: 8px;
            }

            .dashboard-container {
                padding: 20px;
            }
        }
    </style>

    <link rel="stylesheet" href="/UniStay/assets/css/theme.css">
</head>

<body>

    <button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

    <div class="dashboard-container">

        <div class="header">
            <h1>Super Admin Portal</h1>

            <div class="header-actions">
                <a href="../index.php" class="btn-home">Home</a>
                <a href="../auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <h3>Welcome, <?php echo htmlspecialchars($admin_name); ?></h3>

        <div class="info-box">
            <strong>DIU Hostel Management System</strong><br>
            From this Super Admin Portal, you can approve users, monitor accounts, and access the Admin Panel.
        </div>

        <div class="quick-actions">
            <a href="../admin/dashboard.php" class="btn-admin-panel">Open Admin Panel</a>
            <a href="manage-users.php" class="btn-admin-panel">Manage Users</a>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <h3 class="section-title">User Approval & Account Management</h3>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>ID Number</th>
                    <th>Email Status</th>
                    <th>Account Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($users_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>

                            <td>
                                <span class="badge badge-role">
                                    <?php echo strtoupper(htmlspecialchars($row['role'])); ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                if (isset($row['institutional_id'])) {
                                    echo htmlspecialchars($row['institutional_id']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>

                            <td>
                                <?php if ($row['is_verified'] == 1): ?>
                                    <span class="badge badge-verified">Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Unverified</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($row['is_approved'] == 1): ?>
                                    <span class="badge badge-approved">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($row['is_approved'] == 0 && $row['is_verified'] == 1): ?>
                                    <form method="POST" action="dashboard.php" style="margin:0;">
                                        <input type="hidden" name="user_id" value="<?php echo intval($row['id']); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">Approve</button>
                                    </form>
                                <?php elseif ($row['is_approved'] == 1): ?>
                                    <span style="color: #666; font-size: 13px;">No Action Needed</span>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 13px;">Waiting for Email</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No other users found in the system.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <script src="/UniStay/assets/js/theme.js"></script>
</body>

</html>