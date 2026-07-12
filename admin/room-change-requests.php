<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id']);
$admin_name = $_SESSION['name'] ?? 'Admin';

$success_msg = '';
$error_msg = '';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function compatValue($arr, $keys, $default = '')
{
    foreach ((array)$keys as $key) {
        if (isset($arr[$key]) && $arr[$key] !== null && $arr[$key] !== '') {
            return trim((string)$arr[$key]);
        }
    }

    return $default;
}

function normalizeCompat($value)
{
    return strtolower(trim((string)$value));
}

function compatLevel($score)
{
    if ($score >= 80) return 'High Match';
    if ($score >= 60) return 'Medium Match';
    return 'Low Match';
}

function compatClass($score)
{
    if ($score >= 80) return 'level-high';
    if ($score >= 60) return 'level-medium';
    return 'level-low';
}

function loadStudentCompatibilityProfile($conn, $student_record_id)
{
    $sql = "
        SELECT 
            sr.id AS student_record_id,
            sr.full_name,
            sr.institutional_id,
            rp.gender,
            rp.preferred_hall,
            rp.sleep_time,
            rp.wake_time,
            rp.study_habit,
            rp.cleanliness,
            rp.noise_tolerance,
            rp.guest_preference,
            rp.personality,
            rp.religion_sensitive
        FROM student_records sr
        LEFT JOIN roommate_preferences rp
            ON sr.id = rp.student_record_id
        WHERE sr.id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $student_record_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $profile ?: [];
}

function calculateCompatibilityScore($studentA, $studentB, $roomHallCode = '')
{
    $genderA = normalizeCompat(compatValue($studentA, 'gender'));
    $genderB = normalizeCompat(compatValue($studentB, 'gender'));

    if ($genderA !== '' && $genderB !== '' && $genderA !== $genderB) {
        return [
            'eligible' => false,
            'score' => 0,
            'level' => 'Not Eligible',
            'reasons' => ['Gender does not match']
        ];
    }

    $score = 0;
    $reasons = [];

    $preferredHallA = normalizeCompat(compatValue($studentA, 'preferred_hall'));
    $preferredHallB = normalizeCompat(compatValue($studentB, 'preferred_hall'));
    $roomHallCode = normalizeCompat($roomHallCode);

    if ($roomHallCode !== '' && $preferredHallA !== '' && $roomHallCode === $preferredHallA) {
        $score += 15;
        $reasons[] = 'Target room matches preferred hall';
    } elseif ($preferredHallA !== '' && $preferredHallB !== '' && $preferredHallA === $preferredHallB) {
        $score += 10;
        $reasons[] = 'Same preferred hall';
    }

    $checks = [
        [
            'a' => ['study_habit'],
            'b' => ['study_habit'],
            'points' => 20,
            'reason' => 'Similar study habit'
        ],
        [
            'a' => ['sleep_time', 'sleep_habit'],
            'b' => ['sleep_time', 'sleep_habit'],
            'points' => 15,
            'reason' => 'Similar sleep time'
        ],
        [
            'a' => ['wake_time'],
            'b' => ['wake_time'],
            'points' => 10,
            'reason' => 'Similar wake time'
        ],
        [
            'a' => ['cleanliness', 'cleanliness_level'],
            'b' => ['cleanliness', 'cleanliness_level'],
            'points' => 15,
            'reason' => 'Similar cleanliness level'
        ],
        [
            'a' => ['noise_tolerance'],
            'b' => ['noise_tolerance'],
            'points' => 10,
            'reason' => 'Similar noise tolerance'
        ],
        [
            'a' => ['guest_preference'],
            'b' => ['guest_preference'],
            'points' => 5,
            'reason' => 'Similar guest preference'
        ],
        [
            'a' => ['personality'],
            'b' => ['personality'],
            'points' => 5,
            'reason' => 'Similar personality'
        ],
        [
            'a' => ['religion_sensitive'],
            'b' => ['religion_sensitive'],
            'points' => 5,
            'reason' => 'Religion sensitivity preference matches'
        ]
    ];

    foreach ($checks as $check) {
        $valueA = normalizeCompat(compatValue($studentA, $check['a']));
        $valueB = normalizeCompat(compatValue($studentB, $check['b']));

        if ($valueA !== '' && $valueB !== '' && $valueA === $valueB) {
            $score += $check['points'];
            $reasons[] = $check['reason'];
        }
    }

    if (empty($reasons)) {
        $reasons[] = 'Basic eligibility matched, but lifestyle similarity is low';
    }

    $score = min(100, $score);

    return [
        'eligible' => true,
        'score' => $score,
        'level' => compatLevel($score),
        'reasons' => array_unique($reasons)
    ];
}

function refreshRoomStatus($conn, $room_id)
{
    $room_id = intval($room_id);

    if ($room_id <= 0) {
        return;
    }

    $sql = "
        UPDATE rooms r
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        SET r.room_status =
            CASE
                WHEN (
                    SELECT COUNT(*)
                    FROM room_assignments ra
                    WHERE ra.room_id = r.room_id
                    AND ra.assignment_status = 'active'
                ) >= rt.capacity
                THEN 'occupied'
                ELSE 'available'
            END
        WHERE r.room_id = ?
        AND r.room_status NOT IN ('maintenance', 'inactive')
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function getTargetRoomCompatibility($conn, $student_record_id, $room_id)
{
    $student_record_id = intval($student_record_id);
    $room_id = intval($room_id);

    $student = loadStudentCompatibilityProfile($conn, $student_record_id);

    $room_sql = "
        SELECT 
            r.room_id,
            r.room_number,
            r.floor_number,
            r.room_status,
            rt.type_name,
            rt.capacity,
            h.hall_code,
            h.hall_name,
            h.gender_type
        FROM rooms r
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN halls h ON r.hall_id = h.hall_id
        WHERE r.room_id = ?
        LIMIT 1
    ";

    $room_stmt = mysqli_prepare($conn, $room_sql);
    mysqli_stmt_bind_param($room_stmt, "i", $room_id);
    mysqli_stmt_execute($room_stmt);
    $room_result = mysqli_stmt_get_result($room_stmt);
    $room = mysqli_fetch_assoc($room_result);
    mysqli_stmt_close($room_stmt);

    if (!$room) {
        return [
            'score' => 'Room not found',
            'type' => 'Invalid Room',
            'roommates' => 'N/A',
            'reason' => 'The selected room could not be found.'
        ];
    }

    $studentGender = normalizeCompat(compatValue($student, 'gender'));
    $roomGender = normalizeCompat($room['gender_type'] ?? '');

    if ($studentGender !== '' && $roomGender !== '' && $studentGender !== $roomGender) {
        return [
            'score' => 'Not Eligible',
            'type' => 'Gender Hall Mismatch',
            'roommates' => 'N/A',
            'reason' => 'The selected room belongs to a different gender hall.'
        ];
    }

    if (intval($room['capacity']) === 1) {
        return [
            'score' => 'Single Room',
            'type' => 'No Roommate Needed',
            'roommates' => 'No roommate',
            'reason' => 'This is an individual room, so roommate compatibility is not required.'
        ];
    }

    $mate_sql = "
        SELECT 
            sr.id AS roommate_record_id,
            sr.full_name,
            sr.institutional_id,
            rp.gender,
            rp.preferred_hall,
            rp.sleep_time,
            rp.wake_time,
            rp.study_habit,
            rp.cleanliness,
            rp.noise_tolerance,
            rp.guest_preference,
            rp.personality,
            rp.religion_sensitive
        FROM room_assignments ra
        JOIN student_records sr ON ra.student_record_id = sr.id
        LEFT JOIN roommate_preferences rp ON sr.id = rp.student_record_id
        WHERE ra.room_id = ?
        AND ra.assignment_status = 'active'
        AND sr.id <> ?
    ";

    $mate_stmt = mysqli_prepare($conn, $mate_sql);
    mysqli_stmt_bind_param($mate_stmt, "ii", $room_id, $student_record_id);
    mysqli_stmt_execute($mate_stmt);
    $mate_result = mysqli_stmt_get_result($mate_stmt);

    $totalScore = 0;
    $count = 0;
    $roommates = [];
    $reasons = [];

    while ($mate = mysqli_fetch_assoc($mate_result)) {
        $match = calculateCompatibilityScore($student, $mate, $room['hall_code'] ?? '');

        if (!$match['eligible']) {
            continue;
        }

        $totalScore += intval($match['score']);
        $count++;

        $roommates[] = $mate['full_name'] . ' - ' . $match['score'] . '%';
        $reasons = array_merge($reasons, $match['reasons']);
    }

    mysqli_stmt_close($mate_stmt);

    if ($count === 0) {
        return [
            'score' => 'No roommate score yet',
            'type' => 'Empty Room / No Current Roommate',
            'roommates' => 'No active roommate in this target room.',
            'reason' => 'This room is empty or has no other active roommate to compare with.'
        ];
    }

    $avgScore = round($totalScore / $count);

    return [
        'score' => $avgScore . '% - ' . compatLevel($avgScore),
        'type' => 'Target Room Compatibility',
        'roommates' => implode(', ', $roommates),
        'reason' => implode(', ', array_slice(array_unique($reasons), 0, 5))
    ];
}

function statusClass($status)
{
    if ($status === 'approved') return 'status-approved';
    if ($status === 'rejected') return 'status-rejected';
    return 'status-pending';
}

function statusLabel($status)
{
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];

    return $labels[$status] ?? $status;
}

/* Handle approve / reject */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    $admin_message = trim($_POST['admin_message'] ?? '');

    if ($request_id <= 0) {
        $error_msg = "Invalid room change request selected.";
    } else {
        $request_stmt = mysqli_prepare(
            $conn,
            "SELECT 
                scr.*,
                rp.gender
             FROM student_room_change_requests scr
             LEFT JOIN roommate_preferences rp 
                ON scr.student_record_id = rp.student_record_id
             WHERE scr.id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($request_stmt, "i", $request_id);
        mysqli_stmt_execute($request_stmt);
        $request_result = mysqli_stmt_get_result($request_stmt);
        $request = mysqli_fetch_assoc($request_result);
        mysqli_stmt_close($request_stmt);

        if (!$request) {
            $error_msg = "Room change request not found.";
        } elseif ($request['status'] !== 'pending') {
            $error_msg = "This room change request has already been reviewed.";
        } else {
            if ($action === 'reject') {
                if ($admin_message === '') {
                    $admin_message = "Your room change request has been rejected. Please contact the hostel authority for details.";
                }

                $reject_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE student_room_change_requests
                     SET status = 'rejected',
                         admin_message = ?,
                         reviewed_by = ?,
                         reviewed_at = NOW()
                     WHERE id = ?"
                );

                mysqli_stmt_bind_param($reject_stmt, "sii", $admin_message, $admin_id, $request_id);

                if (mysqli_stmt_execute($reject_stmt)) {
                    $success_msg = "Room change request rejected successfully.";
                } else {
                    $error_msg = "Failed to reject room change request.";
                }

                mysqli_stmt_close($reject_stmt);
            } elseif ($action === 'approve') {
                $approved_room_id = intval($_POST['approved_room_id'] ?? 0);
                $approved_seat_no = trim($_POST['approved_seat_no'] ?? '');

                if ($approved_room_id <= 0) {
                    $error_msg = "Please select a target room.";
                } elseif ($approved_seat_no === '') {
                    $error_msg = "Please enter the approved seat number.";
                } else {
                    $room_stmt = mysqli_prepare(
                        $conn,
                        "SELECT 
                            r.room_id,
                            r.room_number,
                            r.room_status,
                            rt.capacity,
                            rt.type_name,
                            h.hall_code,
                            h.hall_name,
                            h.gender_type
                         FROM rooms r
                         JOIN room_types rt ON r.room_type_id = rt.room_type_id
                         LEFT JOIN halls h ON r.hall_id = h.hall_id
                         WHERE r.room_id = ?
                         LIMIT 1"
                    );

                    mysqli_stmt_bind_param($room_stmt, "i", $approved_room_id);
                    mysqli_stmt_execute($room_stmt);
                    $room_result = mysqli_stmt_get_result($room_stmt);
                    $room = mysqli_fetch_assoc($room_result);
                    mysqli_stmt_close($room_stmt);

                    if (!$room) {
                        $error_msg = "Selected target room not found.";
                    } elseif (in_array($room['room_status'], ['maintenance', 'inactive'])) {
                        $error_msg = "Selected target room is not available.";
                    } elseif (
                        !empty($request['gender']) &&
                        !empty($room['gender_type']) &&
                        normalizeCompat($request['gender']) !== normalizeCompat($room['gender_type'])
                    ) {
                        $error_msg = "Selected target room belongs to a different gender hall.";
                    } else {
                        $active_stmt = mysqli_prepare(
                            $conn,
                            "SELECT 
                                ra.assignment_id,
                                ra.room_id AS old_room_id,
                                ra.seat_no AS old_seat_no
                             FROM room_assignments ra
                             WHERE ra.student_record_id = ?
                             AND ra.assignment_status = 'active'
                             LIMIT 1"
                        );

                        mysqli_stmt_bind_param($active_stmt, "i", $request['student_record_id']);
                        mysqli_stmt_execute($active_stmt);
                        $active_result = mysqli_stmt_get_result($active_stmt);
                        $active_assignment = mysqli_fetch_assoc($active_result);
                        mysqli_stmt_close($active_stmt);

                        if (!$active_assignment) {
                            $error_msg = "No active room assignment found for this student.";
                        } else {
                            $old_room_id = intval($active_assignment['old_room_id']);

                            $count_stmt = mysqli_prepare(
                                $conn,
                                "SELECT COUNT(*) AS total
                                 FROM room_assignments
                                 WHERE room_id = ?
                                 AND assignment_status = 'active'
                                 AND student_record_id <> ?"
                            );

                            mysqli_stmt_bind_param($count_stmt, "ii", $approved_room_id, $request['student_record_id']);
                            mysqli_stmt_execute($count_stmt);
                            $count_result = mysqli_stmt_get_result($count_stmt);
                            $count_row = mysqli_fetch_assoc($count_result);
                            mysqli_stmt_close($count_stmt);

                            $occupied_count = intval($count_row['total'] ?? 0);
                            $capacity = intval($room['capacity']);

                            $seat_stmt = mysqli_prepare(
                                $conn,
                                "SELECT COUNT(*) AS total
                                 FROM room_assignments
                                 WHERE room_id = ?
                                 AND seat_no = ?
                                 AND assignment_status = 'active'
                                 AND student_record_id <> ?"
                            );

                            mysqli_stmt_bind_param($seat_stmt, "isi", $approved_room_id, $approved_seat_no, $request['student_record_id']);
                            mysqli_stmt_execute($seat_stmt);
                            $seat_result = mysqli_stmt_get_result($seat_stmt);
                            $seat_row = mysqli_fetch_assoc($seat_result);
                            mysqli_stmt_close($seat_stmt);

                            $seat_taken = intval($seat_row['total'] ?? 0);

                            if ($occupied_count >= $capacity) {
                                $error_msg = "Selected target room is already full.";
                            } elseif ($seat_taken > 0) {
                                $error_msg = "This seat is already taken in the target room.";
                            } else {
                                if ($admin_message === '') {
                                    $admin_message = "Your room change request has been approved. New room: " . $room['room_number'] . ", Seat: " . $approved_seat_no . ".";
                                }

                                $update_assign_stmt = mysqli_prepare(
                                    $conn,
                                    "UPDATE room_assignments
                                     SET room_id = ?,
                                         seat_no = ?,
                                         assigned_by = ?,
                                         assigned_date = NOW()
                                     WHERE assignment_id = ?"
                                );

                                mysqli_stmt_bind_param(
                                    $update_assign_stmt,
                                    "isii",
                                    $approved_room_id,
                                    $approved_seat_no,
                                    $admin_id,
                                    $active_assignment['assignment_id']
                                );

                                if (mysqli_stmt_execute($update_assign_stmt)) {
                                    mysqli_stmt_close($update_assign_stmt);

                                    $update_request_stmt = mysqli_prepare(
                                        $conn,
                                        "UPDATE student_room_change_requests
                                         SET status = 'approved',
                                             approved_room_id = ?,
                                             approved_room_no = ?,
                                             approved_seat_no = ?,
                                             admin_message = ?,
                                             reviewed_by = ?,
                                             reviewed_at = NOW()
                                         WHERE id = ?"
                                    );

                                    mysqli_stmt_bind_param(
                                        $update_request_stmt,
                                        "isssii",
                                        $approved_room_id,
                                        $room['room_number'],
                                        $approved_seat_no,
                                        $admin_message,
                                        $admin_id,
                                        $request_id
                                    );

                                    mysqli_stmt_execute($update_request_stmt);
                                    mysqli_stmt_close($update_request_stmt);

                                    $update_record_stmt = mysqli_prepare(
                                        $conn,
                                        "UPDATE student_records
                                         SET room_no = ?,
                                             seat_no = ?,
                                             admin_message = ?,
                                             reviewed_by = ?,
                                             reviewed_at = NOW()
                                         WHERE id = ?"
                                    );

                                    mysqli_stmt_bind_param(
                                        $update_record_stmt,
                                        "sssii",
                                        $room['room_number'],
                                        $approved_seat_no,
                                        $admin_message,
                                        $admin_id,
                                        $request['student_record_id']
                                    );

                                    mysqli_stmt_execute($update_record_stmt);
                                    mysqli_stmt_close($update_record_stmt);

                                    refreshRoomStatus($conn, $old_room_id);
                                    refreshRoomStatus($conn, $approved_room_id);

                                    $success_msg = "Room change request approved successfully.";
                                } else {
                                    $error_msg = "Failed to update room assignment.";
                                    mysqli_stmt_close($update_assign_stmt);
                                }
                            }
                        }
                    }
                }
            } else {
                $error_msg = "Invalid admin action.";
            }
        }
    }
}

/* Filter */
$filter = $_GET['filter'] ?? 'all';

$where = "";
if ($filter === 'pending') {
    $where = "WHERE scr.status = 'pending'";
} elseif ($filter === 'approved') {
    $where = "WHERE scr.status = 'approved'";
} elseif ($filter === 'rejected') {
    $where = "WHERE scr.status = 'rejected'";
}

/* Load requests */
$requests = [];

$query = "
    SELECT 
        scr.*,
        sr.department,
        sr.batch,
        sr.semester,
        rp.gender,
        rp.preferred_hall,
        rp.sleep_time,
        rp.wake_time,
        rp.study_habit,
        rp.cleanliness,
        rp.noise_tolerance,
        rp.guest_preference,
        rp.personality,
        current_room.room_number AS current_real_room_no,
        requested_room.room_number AS requested_real_room_no,
        approved_room.room_number AS approved_real_room_no
    FROM student_room_change_requests scr
    LEFT JOIN student_records sr ON scr.student_record_id = sr.id
    LEFT JOIN roommate_preferences rp ON scr.student_record_id = rp.student_record_id
    LEFT JOIN rooms current_room ON scr.current_room_id = current_room.room_id
    LEFT JOIN rooms requested_room ON scr.requested_room_id = requested_room.room_id
    LEFT JOIN rooms approved_room ON scr.approved_room_id = approved_room.room_id
    $where
    ORDER BY scr.created_at DESC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
}

/* Load available rooms */
$available_rooms = [];

$room_result = mysqli_query(
    $conn,
    "SELECT *
     FROM view_available_rooms
     ORDER BY hall_code ASC, floor_number ASC, room_number ASC"
);

if ($room_result) {
    while ($row = mysqli_fetch_assoc($room_result)) {
        $available_rooms[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Room Change Requests - UniStay</title>
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

        .btn-dashboard {
            background: #00897b;
            color: white;
        }

        .btn-home {
            background: #64748b;
            color: white;
        }

        .btn-approve {
            background: #00897b;
            color: white;
        }

        .btn-reject {
            background: #dc2626;
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

        .filter-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .filter-link {
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 999px;
            font-weight: bold;
            background: #e0f2f1;
            color: #004d40;
        }

        .filter-link.active {
            background: #00897b;
            color: white;
        }

        .request-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-left: 5px solid #00897b;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .request-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .request-top h2 {
            margin: 0;
            color: #004d40;
        }

        .status-badge {
            padding: 7px 12px;
            border-radius: 999px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }

        .status-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        .section-title {
            color: #004d40;
            margin: 18px 0 10px;
        }

        .admin-form {
            background: white;
            border: 1px solid #d9eeee;
            border-radius: 10px;
            padding: 16px;
            margin-top: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1.3fr 0.6fr 1.4fr auto auto;
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
        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            color: #1f2937;
        }

        textarea {
            min-height: 70px;
            resize: vertical;
        }

        .compat-box {
            margin-top: 10px;
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            border-left: 5px solid #00897b;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            line-height: 1.6;
            color: #134e4a;
        }

        .score-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 13px;
        }

        .level-high {
            background: #dcfce7;
            color: #166534;
        }

        .level-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .level-low {
            background: #fee2e2;
            color: #991b1b;
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
        body.dark-mode .request-card,
        body.dark-mode .detail-box,
        body.dark-mode .admin-form {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .request-top h2,
        body.dark-mode .detail-box strong,
        body.dark-mode .section-title,
        body.dark-mode label {
            color: #7dd3fc;
        }

        body.dark-mode .info-box {
            background: #134e4a;
            color: #e5e7eb;
            border-left-color: #14b8a6;
        }

        body.dark-mode select,
        body.dark-mode input,
        body.dark-mode textarea {
            background: #1e293b;
            color: white;
            border-color: #475569;
        }

        body.dark-mode select option {
            background: #1e293b;
            color: white;
        }

        body.dark-mode .compat-box {
            background: #042f2e;
            color: #ccfbf1;
            border-color: #14b8a6;
        }

        body.dark-mode .status-badge.status-pending {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-badge.status-approved {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-badge.status-rejected {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        body.dark-mode .level-high {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .level-medium {
            background: #fde68a !important;
            color: #78350f !important;
        }

        body.dark-mode .level-low {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        @media (max-width: 1000px) {
            .details-grid,
            .form-grid {
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
        <h1>Room Change Requests</h1>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="../index.php" class="btn btn-home">Home</a>
            <a href="student-requests.php" class="btn btn-dashboard">Student Requests</a>
            <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
        </div>
    </div>

    <div class="info-box">
        Logged in as <strong><?php echo h($admin_name); ?></strong>.
        This page reviews resident room change requests and checks compatibility with the selected target room.
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-success"><?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert-error"><?php echo h($error_msg); ?></div>
    <?php endif; ?>

    <div class="filter-box">
        <a href="room-change-requests.php?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="room-change-requests.php?filter=pending" class="filter-link <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="room-change-requests.php?filter=approved" class="filter-link <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="room-change-requests.php?filter=rejected" class="filter-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
    </div>

    <?php if (empty($requests)): ?>
        <div class="empty-box">
            No room change requests found.
        </div>
    <?php else: ?>
        <?php foreach ($requests as $request): ?>
            <div class="request-card">
                <div class="request-top">
                    <h2>
                        <?php echo h($request['student_name']); ?>
                        - Room Change Request
                    </h2>

                    <span class="status-badge <?php echo h(statusClass($request['status'])); ?>">
                        <?php echo h(statusLabel($request['status'])); ?>
                    </span>
                </div>

                <h3 class="section-title">Student Information</h3>

                <div class="details-grid">
                    <div class="detail-box">
                        <strong>Student ID</strong>
                        <?php echo h($request['institutional_id']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Department</strong>
                        <?php echo h($request['department'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Batch / Semester</strong>
                        <?php echo h(($request['batch'] ?? 'N/A') . ' / ' . ($request['semester'] ?? 'N/A')); ?>
                    </div>
                </div>

                <h3 class="section-title">Room Change Details</h3>

                <div class="details-grid">
                    <div class="detail-box">
                        <strong>Current Room / Seat</strong>
                        Room <?php echo h($request['current_real_room_no'] ?: $request['current_room_no']); ?>
                        |
                        Seat <?php echo h($request['current_seat_no']); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Preferred Room / Seat</strong>
                        Room <?php echo h($request['requested_real_room_no'] ?: ($request['preferred_room_no'] ?? 'Any suitable room')); ?>
                        |
                        Seat <?php echo h($request['preferred_seat_no'] ?? 'Any'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Reason</strong>
                        <?php echo nl2br(h($request['reason'])); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Gender</strong>
                        <?php echo h($request['gender'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Preferred Hall</strong>
                        <?php echo h($request['preferred_hall'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Submitted At</strong>
                        <?php echo h($request['created_at']); ?>
                    </div>
                </div>

                <h3 class="section-title">Compatibility Summary</h3>

                <div class="details-grid">
                    <div class="detail-box">
                        <strong>Study Habit</strong>
                        <?php echo h($request['study_habit'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Sleep Time</strong>
                        <?php echo h($request['sleep_time'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Wake Time</strong>
                        <?php echo h($request['wake_time'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Cleanliness</strong>
                        <?php echo h($request['cleanliness'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Noise Tolerance</strong>
                        <?php echo h($request['noise_tolerance'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box">
                        <strong>Personality</strong>
                        <?php echo h($request['personality'] ?? 'N/A'); ?>
                    </div>
                </div>

                <?php if ($request['status'] === 'pending'): ?>
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="request_id" value="<?php echo intval($request['id']); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Target Room</label>

                                <select 
                                    name="approved_room_id"
                                    class="target-room-select"
                                    data-preview-id="target-preview-<?php echo intval($request['id']); ?>"
                                >
                                    <option
                                        value=""
                                        data-score="No room selected"
                                        data-type="N/A"
                                        data-roommates="N/A"
                                        data-reason="Please select a target room first."
                                    >
                                        Select Target Room
                                    </option>

                                    <?php foreach ($available_rooms as $room): ?>
                                        <?php
                                        $requestGender = normalizeCompat($request['gender'] ?? '');
                                        $roomGender = normalizeCompat($room['gender_type'] ?? '');

                                        if ($requestGender !== '' && $roomGender !== '' && $requestGender !== $roomGender) {
                                            continue;
                                        }

                                        $compat = getTargetRoomCompatibility(
                                            $conn,
                                            intval($request['student_record_id']),
                                            intval($room['room_id'])
                                        );

                                        $isPreferredHall = (
                                            !empty($request['preferred_hall']) &&
                                            normalizeCompat($request['preferred_hall']) === normalizeCompat($room['hall_code'] ?? '')
                                        );

                                        $isRequestedRoom = (
                                            !empty($request['requested_room_id']) &&
                                            intval($request['requested_room_id']) === intval($room['room_id'])
                                        );
                                        ?>

                                        <option
                                            value="<?php echo intval($room['room_id']); ?>"
                                            <?php echo $isRequestedRoom ? 'selected' : ''; ?>
                                            data-score="<?php echo h($compat['score']); ?>"
                                            data-type="<?php echo h($compat['type']); ?>"
                                            data-roommates="<?php echo h($compat['roommates']); ?>"
                                            data-reason="<?php echo h($compat['reason']); ?>"
                                        >
                                            <?php echo $isRequestedRoom ? '⭐ Requested - ' : ''; ?>
                                            Room <?php echo h($room['room_number']); ?>
                                            - <?php echo h($room['hall_name'] ?? 'Hall N/A'); ?>
                                            - <?php echo h($room['type_name']); ?>
                                            - Available Seats: <?php echo h($room['available_seats']); ?>
                                            <?php echo $isPreferredHall ? ' - Preferred Hall' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div 
                                    id="target-preview-<?php echo intval($request['id']); ?>" 
                                    class="compat-box"
                                >
                                    Select a target room to see compatibility score.
                                </div>
                            </div>

                            <div>
                                <label>Approved Seat No</label>
                                <input 
                                    type="text" 
                                    name="approved_seat_no" 
                                    placeholder="Example: 1"
                                    value="<?php echo h($request['preferred_seat_no'] ?? ''); ?>"
                                >
                            </div>

                            <div>
                                <label>Admin Message</label>
                                <textarea name="admin_message" placeholder="Write message for student..."></textarea>
                            </div>

                            <div>
                                <button type="submit" name="action" value="approve" class="btn btn-approve">
                                    Approve
                                </button>
                            </div>

                            <div>
                                <button type="submit" name="action" value="reject" class="btn btn-reject">
                                    Reject
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="detail-box">
                        <strong>Approved Room / Seat</strong>
                        Room <?php echo h($request['approved_real_room_no'] ?: ($request['approved_room_no'] ?? 'N/A')); ?>
                        |
                        Seat <?php echo h($request['approved_seat_no'] ?? 'N/A'); ?>
                    </div>

                    <div class="detail-box" style="margin-top:12px;">
                        <strong>Admin Message</strong>
                        <?php echo h($request['admin_message'] ?? 'No admin message.'); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.target-room-select').forEach(function(selectBox) {
    function updateTargetPreview() {
        const selectedOption = selectBox.options[selectBox.selectedIndex];
        const previewId = selectBox.getAttribute('data-preview-id');
        const previewBox = document.getElementById(previewId);

        if (!previewBox || !selectedOption) {
            return;
        }

        const score = selectedOption.getAttribute('data-score') || 'Not calculated';
        const type = selectedOption.getAttribute('data-type') || 'N/A';
        const roommates = selectedOption.getAttribute('data-roommates') || 'N/A';
        const reason = selectedOption.getAttribute('data-reason') || 'N/A';

        previewBox.innerHTML = `
            <strong>Selected Target Room Compatibility</strong><br>
            <strong>Score:</strong> ${score}<br>
            <strong>Type:</strong> ${type}<br>
            <strong>Roommate / Group Info:</strong> ${roommates}<br>
            <strong>Reason:</strong> ${reason}
        `;
    }

    selectBox.addEventListener('change', updateTargetPreview);
    updateTargetPreview();
});
</script>

<script src="../assets/js/theme.js"></script>
</body>

</html>