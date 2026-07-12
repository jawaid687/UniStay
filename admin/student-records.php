<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$students = [];

$query = "
    SELECT 
        sr.*,
        u.email,
        u.name AS account_name
    FROM student_records sr
    LEFT JOIN users u ON sr.user_id = u.id
    WHERE sr.is_deleted = 0
    AND sr.application_status = 'assigned'
    ORDER BY sr.updated_at DESC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assigned Student Records - UniStay</title>
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

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-dashboard {
            background: #00897b;
            color: white;
        }

        .btn-view {
            background: #0ea5e9;
            color: white;
        }

        .info-box {
            background: #e0f2f1;
            color: #004d40;
            border-left: 5px solid #00897b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .record-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-left: 5px solid #00897b;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 18px;
        }

        .record-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 15px;
        }

        .record-top h2 {
            margin: 0;
            color: #004d40;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }

        .detail-box {
            background: white;
            border: 1px solid #d9eeee;
            padding: 12px;
            border-radius: 8px;
            line-height: 1.6;
        }

        .detail-box strong {
            display: block;
            color: #004d40;
            margin-bottom: 4px;
        }

        .empty-box {
            background: #fff8e1;
            border-left: 5px solid #f59e0b;
            color: #664d03;
            padding: 18px;
            border-radius: 8px;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .record-card,
        body.dark-mode .detail-box {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .record-top h2,
        body.dark-mode .detail-box strong {
            color: #7dd3fc;
        }

        body.dark-mode .info-box {
            background: #134e4a;
            color: #e5e7eb;
            border-left-color: #14b8a6;
        }

        body.dark-mode .status-badge {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Assigned Student Records</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
        </div>
    </div>

    <div class="info-box">
        Welcome, <strong><?php echo h($admin_name); ?></strong>.
        This page shows assigned residents only. Click <strong>View Full Record</strong> to see everything the student submitted.
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-box">
            No assigned student records found yet.
        </div>
    <?php else: ?>
        <?php foreach ($students as $student): ?>
            <div class="record-card">
                <div class="record-top">
                    <h2><?php echo h($student['full_name']); ?></h2>

                    <span class="status-badge">
                        <?php echo h($student['application_status']); ?>
                    </span>
                </div>

                <div class="details-grid">
                    <div class="detail-box">
                        <strong>Student ID</strong>
                        <?php echo h($student['institutional_id']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Email</strong>
                        <?php echo h($student['email'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Department</strong>
                        <?php echo h($student['department'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Batch / Semester</strong>
                        <?php echo h(($student['batch'] ?? 'N/A') . ' / ' . ($student['semester'] ?? 'N/A')); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Phone</strong>
                        <?php echo h($student['phone'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Guardian Phone</strong>
                        <?php echo h($student['guardian_phone'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Room No</strong>
                        <?php echo h($student['room_no'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Seat No</strong>
                        <?php echo h($student['seat_no'] ?? 'N/A'); ?>
                    </div>
                </div>

                <a href="student-record-details.php?id=<?php echo intval($student['id']); ?>" class="btn btn-view">
                    View Full Record
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>