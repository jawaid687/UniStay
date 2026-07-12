<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['name'] ?? 'Admin';

$success_msg = '';
$error_msg = '';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusClass($status)
{
    if ($status === 'available') return 'status-available';
    if ($status === 'occupied') return 'status-occupied';
    if ($status === 'maintenance') return 'status-maintenance';
    if ($status === 'inactive') return 'status-inactive';
    return 'status-neutral';
}

function statusLabel($status)
{
    return ucwords(str_replace('_', ' ', (string)$status));
}

/*
    Update room status
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $room_id = intval($_POST['room_id'] ?? 0);
    $new_status = trim($_POST['room_status'] ?? '');

    $allowed_statuses = ['available', 'maintenance', 'inactive'];

    if ($room_id <= 0) {
        $error_msg = "Invalid room selected.";
    } elseif (!in_array($new_status, $allowed_statuses)) {
        $error_msg = "Invalid room status selected.";
    } else {
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT 
                r.room_id,
                r.room_number,
                r.room_status,
                rt.capacity,
                COUNT(ra.assignment_id) AS occupied_seats
             FROM rooms r
             JOIN room_types rt ON r.room_type_id = rt.room_type_id
             LEFT JOIN room_assignments ra
                ON r.room_id = ra.room_id
                AND ra.assignment_status = 'active'
             WHERE r.room_id = ?
             GROUP BY r.room_id, r.room_number, r.room_status, rt.capacity
             LIMIT 1"
        );

        mysqli_stmt_bind_param($check_stmt, "i", $room_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $room_check = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);

        if (!$room_check) {
            $error_msg = "Room not found.";
        } else {
            $occupied_seats = intval($room_check['occupied_seats']);
            $capacity = intval($room_check['capacity']);

            if ($occupied_seats > 0 && in_array($new_status, ['maintenance', 'inactive'])) {
                $error_msg = "This room has active residents. You cannot mark it as maintenance or inactive.";
            } else {
                if ($new_status === 'available' && $occupied_seats >= $capacity) {
                    $new_status = 'occupied';
                }

                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE rooms
                     SET room_status = ?
                     WHERE room_id = ?"
                );

                mysqli_stmt_bind_param($update_stmt, "si", $new_status, $room_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    $success_msg = "Room status updated successfully.";
                } else {
                    $error_msg = "Failed to update room status.";
                }

                mysqli_stmt_close($update_stmt);
            }
        }
    }
}

/*
    Load filter options
*/
$halls = [];
$hall_result = mysqli_query($conn, "SELECT * FROM halls ORDER BY hall_code ASC");
if ($hall_result) {
    while ($row = mysqli_fetch_assoc($hall_result)) {
        $halls[] = $row;
    }
}

$room_types = [];
$type_result = mysqli_query($conn, "SELECT * FROM room_types ORDER BY capacity ASC");
if ($type_result) {
    while ($row = mysqli_fetch_assoc($type_result)) {
        $room_types[] = $row;
    }
}

/*
    Filters
*/
$filter_hall = intval($_GET['hall_id'] ?? 0);
$filter_floor = intval($_GET['floor_number'] ?? 0);
$filter_type = intval($_GET['room_type_id'] ?? 0);
$filter_status = trim($_GET['room_status'] ?? '');
$search_room = trim($_GET['search_room'] ?? '');

$where_parts = [];

if ($filter_hall > 0) {
    $where_parts[] = "r.hall_id = " . $filter_hall;
}

if ($filter_floor > 0) {
    $where_parts[] = "r.floor_number = " . $filter_floor;
}

if ($filter_type > 0) {
    $where_parts[] = "r.room_type_id = " . $filter_type;
}

if ($filter_status !== '') {
    $safe_status = mysqli_real_escape_string($conn, $filter_status);
    $where_parts[] = "r.room_status = '$safe_status'";
}

if ($search_room !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search_room);
    $where_parts[] = "r.room_number LIKE '%$safe_search%'";
}

$where_sql = '';
if (!empty($where_parts)) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

/*
    Load rooms
*/
$rooms = [];

$rooms_sql = "
    SELECT 
        r.room_id,
        r.room_number,
        r.floor_number,
        r.room_status,
        h.hall_id,
        h.hall_code,
        h.hall_name,
        h.gender_type,
        rt.room_type_id,
        rt.type_name,
        rt.capacity,
        rt.monthly_fee,
        COUNT(ra.assignment_id) AS occupied_seats,
        (rt.capacity - COUNT(ra.assignment_id)) AS available_seats
    FROM rooms r
    LEFT JOIN halls h ON r.hall_id = h.hall_id
    LEFT JOIN room_types rt ON r.room_type_id = rt.room_type_id
    LEFT JOIN room_assignments ra
        ON r.room_id = ra.room_id
        AND ra.assignment_status = 'active'
    $where_sql
    GROUP BY 
        r.room_id,
        r.room_number,
        r.floor_number,
        r.room_status,
        h.hall_id,
        h.hall_code,
        h.hall_name,
        h.gender_type,
        rt.room_type_id,
        rt.type_name,
        rt.capacity,
        rt.monthly_fee
    ORDER BY 
        h.hall_code ASC,
        r.floor_number ASC,
        r.room_number ASC
";

$rooms_result = mysqli_query($conn, $rooms_sql);

if ($rooms_result) {
    while ($row = mysqli_fetch_assoc($rooms_result)) {
        $rooms[] = $row;
    }
}

/*
    Dashboard counts
*/
$total_rooms = count($rooms);
$available_count = 0;
$occupied_count = 0;
$maintenance_count = 0;
$inactive_count = 0;

foreach ($rooms as $room) {
    if ($room['room_status'] === 'available') $available_count++;
    if ($room['room_status'] === 'occupied') $occupied_count++;
    if ($room['room_status'] === 'maintenance') $maintenance_count++;
    if ($room['room_status'] === 'inactive') $inactive_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Room Management - UniStay</title>
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
            max-width: 1400px;
            margin: 25px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
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
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 10px 16px;
            border-radius: 7px;
            font-weight: bold;
            display: inline-block;
            font-size: 14px;
        }

        .btn-green {
            background: #00897b;
            color: white;
        }

        .btn-gray {
            background: #64748b;
            color: white;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 13px;
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

        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .summary-card h3 {
            margin: 0;
            color: #004d40;
            font-size: 15px;
        }

        .summary-card p {
            margin: 8px 0 0;
            font-size: 26px;
            font-weight: 800;
            color: #00897b;
        }

        .filter-box {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 25px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.8fr 1fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        label {
            display: block;
            font-weight: bold;
            color: #004d40;
            margin-bottom: 6px;
        }

        select,
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            background: white;
            color: #1f2937;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #d9eeee;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th {
            background: #00897b;
            color: white;
            padding: 13px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 14px;
        }

        tr:hover {
            background: #f1f5f9;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status-available {
            background: #dcfce7;
            color: #166534;
        }

        .status-occupied {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-maintenance {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-neutral {
            background: #e2e8f0;
            color: #334155;
        }

        .seat-pill {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 8px;
            background: #e0f2f1;
            color: #004d40;
            font-weight: bold;
        }

        .status-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .status-form select {
            min-width: 130px;
        }

        .empty-box {
            background: #fff8e1;
            color: #664d03;
            padding: 18px;
            border-radius: 8px;
            border-left: 5px solid #f59e0b;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .summary-card,
        body.dark-mode .filter-box {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode label,
        body.dark-mode .summary-card h3 {
            color: #7dd3fc;
        }

        body.dark-mode .summary-card p {
            color: #14b8a6;
        }

        body.dark-mode .info-box {
            background: #134e4a;
            color: #e5e7eb;
            border-left-color: #14b8a6;
        }

        body.dark-mode select,
        body.dark-mode input {
            background: #1e293b;
            color: white;
            border-color: #475569;
        }

        body.dark-mode select option {
            background: #1e293b;
            color: white;
        }

        body.dark-mode .table-wrap {
            border-color: #334155;
        }

        body.dark-mode table {
            background: #0f172a;
        }

        body.dark-mode td {
            border-bottom-color: #334155;
            color: #e5e7eb;
        }

        body.dark-mode tr:hover {
            background: #1e293b;
        }

        body.dark-mode .seat-pill {
            background: #134e4a;
            color: #ccfbf1;
        }

        body.dark-mode .status-available {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-occupied {
            background: #bfdbfe !important;
            color: #1e3a8a !important;
        }

        body.dark-mode .status-maintenance {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-inactive {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        @media (max-width: 1000px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<script>
(function () {
    const theme =
        localStorage.getItem('theme') ||
        localStorage.getItem('unistayTheme') ||
        localStorage.getItem('themeMode');

    const darkMode =
        theme === 'dark' ||
        localStorage.getItem('darkMode') === 'true' ||
        localStorage.getItem('darkMode') === 'enabled';

    if (darkMode) {
        document.body.classList.add('dark-mode');
    }
})();
</script>

<div class="container">

    <div class="header">
        <h1>Room Management</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-gray">Home</a>
            <a href="dashboard.php" class="btn btn-green">Admin Dashboard</a>
        </div>
    </div>

    <div class="info-box">
        Logged in as <strong><?php echo h($admin_name); ?></strong>.
        This page is used to view hall-wise rooms, capacity, occupied seats, available seats, and update room status.
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            <h3>Total Rooms Showing</h3>
            <p><?php echo intval($total_rooms); ?></p>
        </div>

        <div class="summary-card">
            <h3>Available</h3>
            <p><?php echo intval($available_count); ?></p>
        </div>

        <div class="summary-card">
            <h3>Occupied</h3>
            <p><?php echo intval($occupied_count); ?></p>
        </div>

        <div class="summary-card">
            <h3>Maintenance</h3>
            <p><?php echo intval($maintenance_count); ?></p>
        </div>

        <div class="summary-card">
            <h3>Inactive</h3>
            <p><?php echo intval($inactive_count); ?></p>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div>
                    <label>Search Room</label>
                    <input 
                        type="text" 
                        name="search_room" 
                        placeholder="Example: yksg1-103"
                        value="<?php echo h($search_room); ?>"
                    >
                </div>

                <div>
                    <label>Floor</label>
                    <select name="floor_number">
                        <option value="0">All Floors</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $filter_floor === $i ? 'selected' : ''; ?>>
                                Floor <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label>Hall</label>
                    <select name="hall_id">
                        <option value="0">All Halls</option>
                        <?php foreach ($halls as $hall): ?>
                            <option value="<?php echo intval($hall['hall_id']); ?>" <?php echo $filter_hall === intval($hall['hall_id']) ? 'selected' : ''; ?>>
                                <?php echo h($hall['hall_code']); ?> - <?php echo h($hall['hall_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Room Type</label>
                    <select name="room_type_id">
                        <option value="0">All Types</option>
                        <?php foreach ($room_types as $type): ?>
                            <option value="<?php echo intval($type['room_type_id']); ?>" <?php echo $filter_type === intval($type['room_type_id']) ? 'selected' : ''; ?>>
                                <?php echo h($type['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Status</label>
                    <select name="room_status">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="occupied" <?php echo $filter_status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                        <option value="maintenance" <?php echo $filter_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-green">Filter</button>
                </div>

                <div>
                    <a href="rooms.php" class="btn btn-gray">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($rooms)): ?>
        <div class="empty-box">
            No rooms found based on the selected filters.
        </div>
    <?php else: ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Room No</th>
                        <th>Hall</th>
                        <th>Gender</th>
                        <th>Floor</th>
                        <th>Room Type</th>
                        <th>Monthly Fee</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Update Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <?php
                        $capacity = intval($room['capacity']);
                        $occupied = intval($room['occupied_seats']);
                        $available = max(0, $capacity - $occupied);

                        $display_status = $room['room_status'];

                        if ($occupied >= $capacity && $capacity > 0 && $room['room_status'] === 'available') {
                            $display_status = 'occupied';
                        }
                        ?>

                        <tr>
                            <td><strong><?php echo h($room['room_number']); ?></strong></td>

                            <td>
                                <?php echo h($room['hall_name'] ?? 'N/A'); ?><br>
                                <small><?php echo h($room['hall_code'] ?? 'N/A'); ?></small>
                            </td>

                            <td><?php echo h($room['gender_type'] ?? 'N/A'); ?></td>

                            <td><?php echo h($room['floor_number'] ?? 'N/A'); ?></td>

                            <td><?php echo h($room['type_name'] ?? 'N/A'); ?></td>

                            <td>
                                <?php
                                $fee = $room['monthly_fee'] ?? null;
                                echo $fee !== null ? h(number_format((float)$fee, 2)) . ' TK' : 'N/A';
                                ?>
                            </td>

                            <td>
                                <span class="seat-pill"><?php echo $capacity; ?></span>
                            </td>

                            <td>
                                <span class="seat-pill"><?php echo $occupied; ?></span>
                            </td>

                            <td>
                                <span class="seat-pill"><?php echo $available; ?></span>
                            </td>

                            <td>
                                <span class="status-badge <?php echo h(statusClass($display_status)); ?>">
                                    <?php echo h(statusLabel($display_status)); ?>
                                </span>
                            </td>

                            <td>
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="room_id" value="<?php echo intval($room['room_id']); ?>">

                                    <select name="room_status">
                                        <option value="available" <?php echo $room['room_status'] === 'available' ? 'selected' : ''; ?>>
                                            Available
                                        </option>

                                        <option value="maintenance" <?php echo $room['room_status'] === 'maintenance' ? 'selected' : ''; ?>>
                                            Maintenance
                                        </option>

                                        <option value="inactive" <?php echo $room['room_status'] === 'inactive' ? 'selected' : ''; ?>>
                                            Inactive
                                        </option>
                                    </select>

                                    <button type="submit" name="update_status" class="btn btn-green btn-small">
                                        Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>