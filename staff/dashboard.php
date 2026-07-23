<?php
session_start();
require_once '../includes/db.php';

// Access control: Only allow logged-in staff members
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$staff_name = $_SESSION['name'] ?? 'Staff';
$staff_id = $_SESSION['user_id'];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pending_complaints = 0;
$pending_tasks = 0;

/* 
  1. DYNAMIC COLUMN DETECTION FOR COMPLAINTS TABLE
  We query the database structure to find the actual column name used 
  for assigning staff (usually 'assigned_to', 'assigned_staff_id', or 'staff_id').
*/
$detected_complaints_column = null;
try {
    $structure_query = "SHOW COLUMNS FROM complaints";
    if ($result = mysqli_query($conn, $structure_query)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $field = strtolower($row['Field']);
            // Look for common staff/assignment column names
            if (in_array($field, ['assigned_to', 'assigned_staff_id', 'staff_id', 'assigned_staff', 'user_id'])) {
                $detected_complaints_column = $row['Field'];
                break;
            }
        }
        mysqli_free_result($result);
    }
} catch (mysqli_sql_exception $e) {
    // If the check itself fails, we fail gracefully
}

// 2. SAFE QUERY FOR COMPLAINTS
if ($detected_complaints_column !== null) {
    try {
        $complaints_query = "SELECT COUNT(*) AS total FROM complaints WHERE {$detected_complaints_column} = ? AND status IN ('pending', 'in_progress')";
        if ($stmt = mysqli_prepare($conn, $complaints_query)) {
            mysqli_stmt_bind_param($stmt, "i", $staff_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($result)) {
                    $pending_complaints = intval($row['total'] ?? 0);
                }
            }
            mysqli_stmt_close($stmt);
        }
    } catch (mysqli_sql_exception $e) {
        $pending_complaints = 0; 
    }
} else {
    // Fallback default: If no column is found, default to 0 and do not crash
    $pending_complaints = 0;
}

// 3. SAFE QUERY FOR TASKS
try {
    $tasks_query = "SELECT COUNT(*) AS total FROM staff_tasks WHERE assigned_staff_id = ? AND status = 'pending'";
    if ($stmt = mysqli_prepare($conn, $tasks_query)) {
        mysqli_stmt_bind_param($stmt, "i", $staff_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $pending_tasks = intval($row['total'] ?? 0);
            }
        }
        mysqli_stmt_close($stmt);
    }
} catch (mysqli_sql_exception $e) {
    $pending_tasks = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 25px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: 25px auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
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

        .btn-logout {
            background: #dc2626;
            color: white;
        }

        .info-box {
            background: #e0f2f1;
            color: #004d40;
            border-left: 5px solid #00897b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.7;
        }

        .request-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .request-card {
            background: #ffffff;
            border: 1px solid #d9eeee;
            border-top: 5px solid #00897b;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            min-height: 230px;
        }

        .request-card h3 {
            margin-top: 0;
            color: #004d40;
            font-size: 22px;
        }

        .request-card p {
            line-height: 1.7;
            color: #475569;
            margin-bottom: 18px;
        }

        .request-card a {
            background: #00897b;
            color: white;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }

        .badge {
            display: inline-block;
            background: #f59e0b;
            color: #111827;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .card-purple {
            border-top-color: #7c3aed;
        }

        .card-purple h3 {
            color: #5b21b6;
        }

        .card-purple a {
            background: #7c3aed;
        }

        .card-blue {
            border-top-color: #0ea5e9;
        }

        .card-blue h3 {
            color: #075985;
        }

        .card-blue a {
            background: #0ea5e9;
        }

        .card-gray {
            border-top-color: #64748b;
        }

        .card-gray h3 {
            color: #334155;
        }

        .card-gray a {
            background: #64748b;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .request-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1 {
            color: #7dd3fc;
        }

        body.dark-mode .info-box {
            background: #134e4a;
            color: #e5e7eb;
            border-left-color: #14b8a6;
        }

        body.dark-mode .request-card h3 {
            color: #7dd3fc;
        }

        body.dark-mode .request-card p {
            color: #cbd5e1;
        }

        body.dark-mode .badge {
            background: #fde68a;
            color: #78350f;
        }

        /* Fix yellow badge text visibility */
        .container .request-grid .request-card .badge {
            background: #fde68a !important;
            color: #78350f !important;
            font-weight: 900 !important;
        }

        body.dark-mode .container .request-grid .request-card .badge {
            background: #fde68a !important;
            color: #78350f !important;
            font-weight: 900 !important;
        }

        /* Flex alignment */
        .request-card {
            display: flex !important;
            flex-direction: column !important;
        }

        .request-card .badge {
            display: inline-block !important;
            width: auto !important;
            max-width: fit-content !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
            background: #fde68a !important;
            color: #78350f !important;
            font-weight: 900 !important;
            font-size: 13px !important;
            margin-bottom: 12px !important;
        }

        .request-card p {
            margin-bottom: 18px !important;
        }

        .request-card a {
            margin-top: auto !important;
            align-self: center !important;
            color: #ffffff !important;
            font-weight: 800 !important;
        }

        .card-blue {
            border-top: 5px solid #2563eb !important;
        }

        .clickable-card {
            cursor: pointer;
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="header">
            <h1>Staff Portal</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="../index.php" class="btn btn-home">Home</a>
                <a href="../auth/logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>

        <div class="info-box">
            Welcome back, <strong><?php echo h($staff_name); ?></strong>. 
            Here is an overview of your active maintenance tasks, cleaning duties, and system shifts.
        </div>

        <div class="request-grid">
            
            <!-- Assigned Complaints & Repairs -->
            <div class="request-card card-green clickable-card" onclick="window.location.href='assigned-complaints.php'">
                <h3>Assigned Complaints</h3>

                <?php if ($pending_complaints > 0): ?>
                    <span class="badge"><?php echo $pending_complaints; ?> Active Tickets</span>
                <?php else: ?>
                    <span class="badge">0 Active Tickets</span>
                <?php endif; ?>

                <p>
                    Resolve maintenance requests and repairs assigned to you by the hostel administration.
                </p>

                <a href="assigned-complaints.php" onclick="event.stopPropagation();">
                    Open Tasks & Repairs
                </a>
            </div>

            <!-- Cleaning & Floor Schedules -->
            <div class="request-card card-blue clickable-card" onclick="window.location.href='staff-tasks.php'">
                <h3>My Daily Tasks</h3>

                <?php if ($pending_tasks > 0): ?>
                    <span class="badge"><?php echo $pending_tasks; ?> Pending Tasks</span>
                <?php else: ?>
                    <span class="badge">All Completed</span>
                <?php endif; ?>

                <p>
                    Track your daily assigned cleaning areas, routines, and floor sanitation schedules.
                </p>

                <a href="staff-tasks.php" onclick="event.stopPropagation();">
                    Open Shift Tasks
                </a>
            </div>

            <!-- Supply & Inventory Requests -->
            <div class="request-card card-purple clickable-card" onclick="window.location.href='supply-requests.php'">
                <h3>Inventory Requests</h3>

                <span class="badge">Supplies & Tools</span>

                <p>
                    Request replacement components, cleaning chemicals, or utility assets from management.
                </p>

                <a href="supply-requests.php" onclick="event.stopPropagation();">
                    Request Supplies
                </a>
            </div>

            <!-- Shift Details & Roster -->
            <div class="request-card card-gray clickable-card" onclick="window.location.href='duty-roster.php'">
                <h3>Shift & Duty Roster</h3>

                <span class="badge">Schedule</span>

                <p>
                    View your weekly shift timing, assigned wings, holiday calendars, and shift records.
                </p>

                <a href="duty-roster.php" onclick="event.stopPropagation();">
                    View Roster
                </a>
            </div>

        </div>

    </div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>