<?php
require_once 'resident_guard.php';

$user_id = intval($_SESSION['user_id']);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM student_records
     WHERE user_id = ?
     AND is_deleted = 0
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$application = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Room Details - UniStay</title>
    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
        }

        .container {
            max-width: 850px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #004d40;
        }

        .box {
            background: #e0f2f1;
            border-left: 5px solid #00897b;
            padding: 18px;
            border-radius: 8px;
            line-height: 1.8;
            color: #004d40;
        }

        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #00897b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        body.dark-mode .container {
            background: #0f172a;
            color: #e5e7eb;
            border: 1px solid #334155;
        }

        body.dark-mode h1 {
            color: #7dd3fc;
        }

        .room-details-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn-room-change {
            background: transparent !important;
            color: #7dd3fc !important;
            border: 1px solid #00897b;
            text-decoration: none;
        }

        .btn-room-change:hover {
            background: #00897b !important;
            color: white !important;
        }
    </style>
</head>

<body>

    <div class="container">
        <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

        <h1>Room Details</h1>

        <div class="box">
            <strong>Room No:</strong> <?php echo h($application['room_no']); ?><br>
            <strong>Seat No:</strong> <?php echo h($application['seat_no']); ?><br>
            <strong>Status:</strong> Resident
        </div>

        <div class="room-details-actions">
            <a href="dashboard.php" class="btn btn-green">
                Back to Dashboard
            </a>

            <a href="room-change-request.php" class="btn btn-green">
                Room Change Request
            </a>
        </div>
    </div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>