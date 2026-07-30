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
            
           <div class="request-grid">


          <!-- My Profile -->
    <div class="request-card card-purple clickable-card" onclick="window.location.href='profile.php'">
        <h3>My Profile</h3>

        <span class="badge">Account</span>

        <p>
            View and manage your personal information and staff details.
        </p>

        <a href="profile.php" onclick="event.stopPropagation();">
            Open Profile
        </a>
    </div>





    <!-- Assigned Complaints -->
    <div class="request-card card-green clickable-card" onclick="window.location.href='assigned-complaints.php'">
        <h3>Assigned Complaints</h3>
        <span class="badge"><?php echo $pending_complaints; ?> Active Tickets</span>

        <p>
            View and solve complaints assigned by hostel administration.
        </p>

        <a href="assigned-complaints.php" onclick="event.stopPropagation();">
            Open Complaints
        </a>
    </div>


    <!-- Availability -->
    <div class="request-card card-blue clickable-card" onclick="window.location.href='availability.php'">
        <h3>Availability</h3>

        <span class="badge">Status</span>

        <p>
            Update your available time and working status.
        </p>

        <a href="availability.php" onclick="event.stopPropagation();">
            Update Availability
        </a>
    </div>




    <!-- Leave Application -->
    <div class="request-card card-purple clickable-card" onclick="window.location.href='leave-application.php'">
        <h3>Leave Application</h3>

        <span class="badge">Request</span>

        <p>
            Apply for leave and check your leave approval status.
        </p>

        <a href="leave-application.php" onclick="event.stopPropagation();">
            Apply Leave
        </a>
    </div>


    <!-- Performance -->
    <div class="request-card card-green clickable-card" onclick="window.location.href='performance.php'">
        <h3>Performance</h3>

        <span class="badge">Report</span>

        <p>
            Check your duty performance and completed work records.
        </p>

        <a href="performance.php" onclick="event.stopPropagation();">
            View Performance
        </a>
    </div>


    <!-- Messages -->
    <div class="request-card card-blue clickable-card" onclick="window.location.href='messages.php'">
        <h3>Messages</h3>

        <span class="badge">Inbox</span>

        <p>
            Communicate with administration and receive updates.
        </p>

        <a href="messages.php" onclick="event.stopPropagation();">
            Open Messages
        </a>
    </div>


    <!-- Floor Schedule NEW -->
    <div class="request-card card-gray clickable-card" onclick="window.location.href='floor-schedule.php'">
        <h3>Floor Schedule</h3>

        <span class="badge">Floor Duty</span>

        <p>
            View assigned floors, cleaning schedules, inspection time,
            and daily floor maintenance duties.
        </p>

        <a href="floor-schedule.php" onclick="event.stopPropagation();">
            View Schedule
        </a>
    </div>


</div>

    <script src="../assets/js/theme.js"></script>
</body>

</html>