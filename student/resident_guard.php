<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

$guard_stmt = mysqli_prepare(
    $conn,
    "SELECT application_status, room_no, seat_no
     FROM student_records
     WHERE user_id = ?
     AND is_deleted = 0
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($guard_stmt, "i", $user_id);
mysqli_stmt_execute($guard_stmt);
$guard_result = mysqli_stmt_get_result($guard_stmt);
$guard_application = mysqli_fetch_assoc($guard_result);
mysqli_stmt_close($guard_stmt);

$guard_is_resident = (
    $guard_application &&
    $guard_application['application_status'] === 'assigned' &&
    !empty($guard_application['room_no']) &&
    !empty($guard_application['seat_no'])
);

if (!$guard_is_resident) {
    header("Location: dashboard.php?limited=1");
    exit();
}
?>