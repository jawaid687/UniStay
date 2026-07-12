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
        $reasons[] = 'Room matches preferred hall';
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

function getSmartCompatibilityRecommendations($conn, $app)
{
    $student_record_id = intval($app['student_record_id']);
    $preferred_room_type_id = intval($app['preferred_room_type_id']);

    $currentStudent = loadStudentCompatibilityProfile($conn, $student_record_id);

    if (empty($currentStudent)) {
        return [
            'room_matches' => [],
            'empty_rooms' => [],
            'pending_matches' => []
        ];
    }

    $studentGender = normalizeCompat(compatValue($currentStudent, 'gender'));
    $preferredHall = normalizeCompat(compatValue($currentStudent, 'preferred_hall'));

    $data = [
        'room_matches' => [],
        'empty_rooms' => [],
        'pending_matches' => []
    ];

    $sql = "
        SELECT
            v.room_id,
            v.room_number,
            v.floor_number,
            v.hall_code,
            v.hall_name,
            v.gender_type,
            v.type_name,
            v.available_seats,
            sr.id AS roommate_record_id,
            sr.full_name,
            sr.institutional_id
        FROM view_available_rooms v
        JOIN room_assignments ra
            ON v.room_id = ra.room_id
            AND ra.assignment_status = 'active'
        JOIN student_records sr
            ON ra.student_record_id = sr.id
        WHERE v.room_type_id = ?
        AND v.available_seats > 0
        AND LOWER(v.gender_type) = ?
        ORDER BY 
            CASE WHEN LOWER(v.hall_code) = ? THEN 0 ELSE 1 END,
            v.floor_number ASC,
            v.room_number ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $preferred_room_type_id, $studentGender, $preferredHall);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $roomGroups = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $roommateProfile = loadStudentCompatibilityProfile($conn, intval($row['roommate_record_id']));
        $match = calculateCompatibilityScore($currentStudent, $roommateProfile, $row['hall_code']);

        if (!$match['eligible']) {
            continue;
        }

        $roomId = intval($row['room_id']);

        if (!isset($roomGroups[$roomId])) {
            $roomGroups[$roomId] = [
                'room_id' => $roomId,
                'room_number' => $row['room_number'],
                'hall_code' => $row['hall_code'],
                'hall_name' => $row['hall_name'],
                'type_name' => $row['type_name'],
                'available_seats' => $row['available_seats'],
                'total_score' => 0,
                'count' => 0,
                'roommates' => [],
                'reasons' => []
            ];
        }

        $roomGroups[$roomId]['total_score'] += $match['score'];
        $roomGroups[$roomId]['count']++;

        $roomGroups[$roomId]['roommates'][] =
            $row['full_name'] . ' - ' . $match['score'] . '%';

        $roomGroups[$roomId]['reasons'] = array_merge(
            $roomGroups[$roomId]['reasons'],
            $match['reasons']
        );
    }

    mysqli_stmt_close($stmt);

    foreach ($roomGroups as $room) {
        $avgScore = round($room['total_score'] / max(1, $room['count']));

        $data['room_matches'][] = [
            'room_id' => $room['room_id'],
            'room_number' => $room['room_number'],
            'hall_code' => $room['hall_code'],
            'hall_name' => $room['hall_name'],
            'type_name' => $room['type_name'],
            'available_seats' => $room['available_seats'],
            'score' => $avgScore,
            'level' => compatLevel($avgScore),
            'roommates' => $room['roommates'],
            'reasons' => array_unique($room['reasons'])
        ];
    }

    usort($data['room_matches'], function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $emptySql = "
        SELECT *
        FROM view_available_rooms v
        WHERE v.room_type_id = ?
        AND LOWER(v.gender_type) = ?
        AND NOT EXISTS (
            SELECT 1
            FROM room_assignments ra
            WHERE ra.room_id = v.room_id
            AND ra.assignment_status = 'active'
        )
        ORDER BY
            CASE WHEN LOWER(v.hall_code) = ? THEN 0 ELSE 1 END,
            v.floor_number ASC,
            v.room_number ASC
        LIMIT 3
    ";

    $emptyStmt = mysqli_prepare($conn, $emptySql);
    mysqli_stmt_bind_param($emptyStmt, "iss", $preferred_room_type_id, $studentGender, $preferredHall);
    mysqli_stmt_execute($emptyStmt);
    $emptyResult = mysqli_stmt_get_result($emptyStmt);

    while ($emptyRoom = mysqli_fetch_assoc($emptyResult)) {
        $data['empty_rooms'][] = $emptyRoom;
    }

    mysqli_stmt_close($emptyStmt);

    $pendingSql = "
        SELECT
            other.application_id,
            other.student_record_id,
            sr.full_name,
            sr.institutional_id
        FROM room_applications other
        JOIN student_records sr
            ON other.student_record_id = sr.id
        WHERE other.application_status = 'pending'
        AND other.application_id <> ?
        AND other.preferred_room_type_id = ?
        ORDER BY other.created_at ASC
    ";

    $pendingStmt = mysqli_prepare($conn, $pendingSql);
    mysqli_stmt_bind_param($pendingStmt, "ii", $app['application_id'], $preferred_room_type_id);
    mysqli_stmt_execute($pendingStmt);
    $pendingResult = mysqli_stmt_get_result($pendingStmt);

    while ($pending = mysqli_fetch_assoc($pendingResult)) {
        $pendingProfile = loadStudentCompatibilityProfile($conn, intval($pending['student_record_id']));
        $match = calculateCompatibilityScore($currentStudent, $pendingProfile);

        if (!$match['eligible']) {
            continue;
        }

        $data['pending_matches'][] = [
            'name' => $pending['full_name'],
            'institutional_id' => $pending['institutional_id'],
            'score' => $match['score'],
            'level' => $match['level'],
            'reasons' => $match['reasons']
        ];
    }

    mysqli_stmt_close($pendingStmt);

    usort($data['pending_matches'], function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $data['pending_matches'] = array_slice($data['pending_matches'], 0, 3);

    return $data;
}

function statusClass($status)
{
    if ($status === 'assigned') return 'status-assigned';
    if ($status === 'approved') return 'status-approved';
    if ($status === 'rejected') return 'status-rejected';
    return 'status-pending';
}

function statusLabel($status)
{
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'assigned' => 'Assigned'
    ];

    return $labels[$status] ?? $status;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $application_id = intval($_POST['application_id'] ?? 0);
    $admin_message = trim($_POST['admin_message'] ?? '');

    if ($application_id <= 0) {
        $error_msg = "Invalid room application selected.";
    } else {
        $app_stmt = mysqli_prepare(
            $conn,
            "SELECT 
                ra.*, 
                sr.full_name, 
                sr.institutional_id,
                rp.gender,
                rp.preferred_hall
             FROM room_applications ra
             LEFT JOIN student_records sr ON ra.student_record_id = sr.id
             LEFT JOIN roommate_preferences rp ON ra.student_record_id = rp.student_record_id
             WHERE ra.application_id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($app_stmt, "i", $application_id);
        mysqli_stmt_execute($app_stmt);
        $app_result = mysqli_stmt_get_result($app_stmt);
        $application = mysqli_fetch_assoc($app_result);
        mysqli_stmt_close($app_stmt);

        if (!$application) {
            $error_msg = "Room application not found.";
        } elseif ($application['application_status'] === 'assigned') {
            $error_msg = "This room application is already assigned.";
        } else {
            if ($action === 'reject') {
                if ($admin_message === '') {
                    $admin_message = "Your room request has been rejected. Please contact the hostel authority for details.";
                }

                $reject_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE room_applications
                     SET application_status = 'rejected',
                         admin_message = ?,
                         reviewed_at = NOW()
                     WHERE application_id = ?"
                );

                mysqli_stmt_bind_param($reject_stmt, "si", $admin_message, $application_id);

                if (mysqli_stmt_execute($reject_stmt)) {
                    mysqli_stmt_close($reject_stmt);

                    if (!empty($application['student_record_id'])) {
                        $record_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE student_records
                             SET application_status = 'rejected',
                                 admin_message = ?,
                                 reviewed_by = ?,
                                 reviewed_at = NOW()
                             WHERE id = ?"
                        );

                        mysqli_stmt_bind_param(
                            $record_stmt,
                            "sii",
                            $admin_message,
                            $admin_id,
                            $application['student_record_id']
                        );

                        mysqli_stmt_execute($record_stmt);
                        mysqli_stmt_close($record_stmt);
                    }

                    $success_msg = "Room application rejected successfully.";
                } else {
                    $error_msg = "Failed to reject room application.";
                    mysqli_stmt_close($reject_stmt);
                }
            } elseif ($action === 'assign') {
                $room_id = intval($_POST['room_id'] ?? 0);
                $seat_no = trim($_POST['seat_no'] ?? '');

                if ($room_id <= 0) {
                    $error_msg = "Please select an available room.";
                } elseif ($seat_no === '') {
                    $error_msg = "Please enter a seat number.";
                } else {
                    $room_stmt = mysqli_prepare(
                        $conn,
                        "SELECT 
                            r.room_id, 
                            r.room_number, 
                            r.room_status,
                            r.room_type_id,
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

                    mysqli_stmt_bind_param($room_stmt, "i", $room_id);
                    mysqli_stmt_execute($room_stmt);
                    $room_result = mysqli_stmt_get_result($room_stmt);
                    $room = mysqli_fetch_assoc($room_result);
                    mysqli_stmt_close($room_stmt);

                    if (!$room) {
                        $error_msg = "Selected room not found.";
                    } elseif (in_array($room['room_status'], ['maintenance', 'inactive'])) {
                        $error_msg = "Selected room is not available for assignment.";
                    } elseif (intval($room['room_type_id']) !== intval($application['preferred_room_type_id'])) {
                        $error_msg = "Selected room type does not match the student's room preference.";
                    } elseif (
                        !empty($application['gender']) &&
                        !empty($room['gender_type']) &&
                        normalizeCompat($application['gender']) !== normalizeCompat($room['gender_type'])
                    ) {
                        $error_msg = "Selected room belongs to a different gender hall.";
                    } else {
                        $count_stmt = mysqli_prepare(
                            $conn,
                            "SELECT COUNT(*) AS total
                             FROM room_assignments
                             WHERE room_id = ?
                             AND assignment_status = 'active'"
                        );

                        mysqli_stmt_bind_param($count_stmt, "i", $room_id);
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
                             AND assignment_status = 'active'"
                        );

                        mysqli_stmt_bind_param($seat_stmt, "is", $room_id, $seat_no);
                        mysqli_stmt_execute($seat_stmt);
                        $seat_result = mysqli_stmt_get_result($seat_stmt);
                        $seat_row = mysqli_fetch_assoc($seat_result);
                        mysqli_stmt_close($seat_stmt);

                        $seat_taken = intval($seat_row['total'] ?? 0);

                        if ($occupied_count >= $capacity) {
                            $error_msg = "Selected room is already full.";
                        } elseif ($seat_taken > 0) {
                            $error_msg = "This seat is already assigned in the selected room.";
                        } else {
                            if ($admin_message === '') {
                                $admin_message = "Your room request has been approved. Room " . $room['room_number'] . ", Seat " . $seat_no . " has been assigned.";
                            }

                            $assign_stmt = mysqli_prepare(
                                $conn,
                                "INSERT INTO room_assignments
                                (
                                    application_id,
                                    student_id,
                                    student_record_id,
                                    room_id,
                                    seat_no,
                                    assigned_by
                                )
                                VALUES (?, ?, ?, ?, ?, ?)"
                            );

                            mysqli_stmt_bind_param(
                                $assign_stmt,
                                "iiiisi",
                                $application_id,
                                $application['student_id'],
                                $application['student_record_id'],
                                $room_id,
                                $seat_no,
                                $admin_id
                            );

                            if (mysqli_stmt_execute($assign_stmt)) {
                                mysqli_stmt_close($assign_stmt);

                                $update_app_stmt = mysqli_prepare(
                                    $conn,
                                    "UPDATE room_applications
                                     SET application_status = 'assigned',
                                         admin_message = ?,
                                         reviewed_at = NOW()
                                     WHERE application_id = ?"
                                );

                                mysqli_stmt_bind_param($update_app_stmt, "si", $admin_message, $application_id);
                                mysqli_stmt_execute($update_app_stmt);
                                mysqli_stmt_close($update_app_stmt);

                                $update_record_stmt = mysqli_prepare(
                                    $conn,
                                    "UPDATE student_records
                                     SET application_status = 'assigned',
                                         room_no = ?,
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
                                    $seat_no,
                                    $admin_message,
                                    $admin_id,
                                    $application['student_record_id']
                                );

                                mysqli_stmt_execute($update_record_stmt);
                                mysqli_stmt_close($update_record_stmt);

                                $success_msg = "Room assigned successfully.";
                            } else {
                                $error_msg = "Failed to assign room. This application may already have an assignment.";
                                mysqli_stmt_close($assign_stmt);
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

$filter = $_GET['filter'] ?? 'all';

$where = "";
if ($filter === 'pending') {
    $where = "WHERE ra.application_status = 'pending'";
} elseif ($filter === 'assigned') {
    $where = "WHERE ra.application_status = 'assigned'";
} elseif ($filter === 'rejected') {
    $where = "WHERE ra.application_status = 'rejected'";
}

$applications = [];

$query = "
    SELECT
        ra.*,
        rt.type_name,
        rt.capacity,
        rt.monthly_fee,
        u.name AS account_name,
        u.institutional_id AS account_institutional_id,
        sr.full_name,
        sr.institutional_id,
        sr.department,
        sr.batch,
        sr.semester,
        rp.gender,
        rp.preferred_hall,
        rp.sleep_time,
        rp.wake_time,
        rp.study_habit AS compatibility_study_habit,
        rp.cleanliness AS compatibility_cleanliness,
        rp.noise_tolerance,
        rp.guest_preference,
        rp.personality,
        (
            SELECT CONCAT(r.room_number, ' / Seat ', ras.seat_no)
            FROM room_assignments ras
            JOIN rooms r ON ras.room_id = r.room_id
            WHERE ras.application_id = ra.application_id
            AND ras.assignment_status = 'active'
            LIMIT 1
        ) AS assigned_room_seat
    FROM room_applications ra
    JOIN room_types rt ON ra.preferred_room_type_id = rt.room_type_id
    JOIN users u ON ra.student_id = u.id
    LEFT JOIN student_records sr ON ra.student_record_id = sr.id
    LEFT JOIN roommate_preferences rp ON ra.student_record_id = rp.student_record_id
    $where
    ORDER BY ra.created_at DESC
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $applications[] = $row;
    }
}

$available_rooms = [];

$room_result = mysqli_query(
    $conn,
    "SELECT *
     FROM view_available_rooms
     ORDER BY floor_number ASC, room_number ASC"
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
    <title>Room Applications - UniStay</title>
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

        .btn-assign {
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

        .application-card {
            background: #f8fafc;
            border: 1px solid #d9eeee;
            border-left: 5px solid #00897b;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .application-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .application-top h2 {
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

        .status-assigned {
            background: #dbeafe;
            color: #1e40af;
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
            grid-template-columns: 1.2fr 0.6fr 1.4fr auto auto;
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
        body.dark-mode .application-card,
        body.dark-mode .detail-box,
        body.dark-mode .admin-form {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .header h1,
        body.dark-mode .application-top h2,
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

        body.dark-mode .status-badge.status-pending {
            background: #fed7aa !important;
            color: #7c2d12 !important;
        }

        body.dark-mode .status-badge.status-assigned {
            background: #bfdbfe !important;
            color: #1e3a8a !important;
        }

        body.dark-mode .status-badge.status-approved {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        body.dark-mode .status-badge.status-rejected {
            background: #fecaca !important;
            color: #7f1d1d !important;
        }

        @media (max-width: 1000px) {

            .details-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .smart-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .smart-card {
            background: #ffffff;
            border: 1px solid #d9eeee;
            border-radius: 12px;
            padding: 16px;
            line-height: 1.7;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .smart-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #004d40;
        }

        .best-smart-card {
            border-left: 6px solid #00897b;
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

        .smart-card hr {
            border: none;
            border-top: 1px solid #d9eeee;
            margin: 12px 0;
        }

        body.dark-mode .smart-card {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode .smart-card h4 {
            color: #7dd3fc;
        }

        body.dark-mode .smart-card hr {
            border-top-color: #334155;
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

        @media (max-width: 900px) {
            .smart-box {
                grid-template-columns: 1fr;
            }
        }

        .selected-compat-box {
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

        body.dark-mode .selected-compat-box {
            background: #042f2e;
            color: #ccfbf1;
            border-color: #14b8a6;
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
            <h1>Room Applications</h1>

            <div class="header-actions">
                <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
                <a href="../index.php" class="btn btn-home">Home</a>
                <a href="student-requests.php" class="btn btn-dashboard">Student Requests</a>
                <a href="dashboard.php" class="btn btn-dashboard">Admin Dashboard</a>
            </div>
        </div>

        <div class="info-box">
            Logged in as <strong><?php echo h($admin_name); ?></strong>.
            This page is used to review final student room preferences and assign available rooms.
        </div>

        <?php if ($success_msg): ?>
            <div class="alert-success"><?php echo h($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert-error"><?php echo h($error_msg); ?></div>
        <?php endif; ?>

        <div class="filter-box">
            <a href="room-applications.php?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="room-applications.php?filter=pending" class="filter-link <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="room-applications.php?filter=assigned" class="filter-link <?php echo $filter === 'assigned' ? 'active' : ''; ?>">Assigned</a>
            <a href="room-applications.php?filter=rejected" class="filter-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
        </div>

        <?php if (empty($applications)): ?>
            <div class="empty-box">
                No room applications found.
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <div class="application-card">
                    <div class="application-top">
                        <h2>
                            <?php echo h($app['full_name'] ?: $app['account_name']); ?>
                            - Room Preference
                        </h2>

                        <span class="status-badge <?php echo h(statusClass($app['application_status'])); ?>">
                            <?php echo h(statusLabel($app['application_status'])); ?>
                        </span>
                    </div>

                    <h3 class="section-title">Student Information</h3>

                    <div class="details-grid">
                        <div class="detail-box">
                            <strong>Student ID</strong>
                            <?php echo h($app['institutional_id'] ?: $app['account_institutional_id']); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Department</strong>
                            <?php echo h($app['department'] ?? 'N/A'); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Batch / Semester</strong>
                            <?php echo h(($app['batch'] ?? 'N/A') . ' / ' . ($app['semester'] ?? 'N/A')); ?>
                        </div>
                    </div>

                    <h3 class="section-title">Room Preference</h3>

                    <div class="details-grid">
                        <div class="detail-box">
                            <strong>Preferred Room Type</strong>
                            <?php echo h($app['type_name']); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Capacity / Fee</strong>
                            <?php echo h($app['capacity']); ?> Student(s),
                            <?php echo number_format((float)$app['monthly_fee']); ?> TK
                        </div>

                        <div class="detail-box">
                            <strong>Student Budget</strong>
                            <?php echo number_format((float)$app['budget']); ?> TK
                        </div>

                        <div class="detail-box">
                            <strong>Study Habit</strong>
                            <?php echo h($app['study_habit']); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Sleep Habit</strong>
                            <?php echo h($app['sleep_habit']); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Cleanliness</strong>
                            <?php echo h($app['cleanliness_level']); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Additional Note</strong>
                            <?php echo nl2br(h($app['reason'] ?? 'No note added.')); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Submitted At</strong>
                            <?php echo h($app['created_at']); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Assigned Room</strong>
                            <?php echo h($app['assigned_room_seat'] ?? 'Not assigned yet'); ?>
                        </div>
                    </div>

                    <h3 class="section-title">Compatibility Summary</h3>

                    <div class="details-grid">
                        <div class="detail-box">
                            <strong>Gender</strong>
                            <?php echo h($app['gender'] ?? 'N/A'); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Preferred Hall</strong>
                            <?php echo h($app['preferred_hall'] ?? 'N/A'); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Wake Time</strong>
                            <?php echo h($app['wake_time'] ?? 'N/A'); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Noise Tolerance</strong>
                            <?php echo h($app['noise_tolerance'] ?? 'N/A'); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Guest Preference</strong>
                            <?php echo h($app['guest_preference'] ?? 'N/A'); ?>
                        </div>

                        <div class="detail-box">
                            <strong>Personality</strong>
                            <?php echo h($app['personality'] ?? 'N/A'); ?>
                        </div>
                    </div>

                    <?php
                    $smartRecommendations = getSmartCompatibilityRecommendations($conn, $app);
                    ?>

                    <h3 class="section-title">Smart Compatibility Recommendation</h3>

                    <div class="smart-box">

                        <?php if (!empty($smartRecommendations['room_matches'])): ?>
                            <?php $bestRoom = $smartRecommendations['room_matches'][0]; ?>

                            <div class="smart-card best-smart-card">
                                <h4>Best Existing Room Match</h4>

                                <p>
                                    <strong>Recommended Room:</strong>
                                    <?php echo h($bestRoom['room_number']); ?>
                                </p>

                                <p>
                                    <strong>Hall:</strong>
                                    <?php echo h($bestRoom['hall_name']); ?>
                                </p>

                                <p>
                                    <strong>Room Type:</strong>
                                    <?php echo h($bestRoom['type_name']); ?>
                                </p>

                                <p>
                                    <strong>Available Seat:</strong>
                                    <?php echo h($bestRoom['available_seats']); ?>
                                </p>

                                <p>
                                    <strong>Compatibility Score:</strong>
                                    <span class="score-pill <?php echo h(compatClass($bestRoom['score'])); ?>">
                                        <?php echo h($bestRoom['score']); ?>% - <?php echo h($bestRoom['level']); ?>
                                    </span>
                                </p>

                                <p>
                                    <strong>Existing Roommate(s):</strong><br>
                                    <?php echo h(implode(', ', $bestRoom['roommates'])); ?>
                                </p>

                                <p>
                                    <strong>Reason:</strong><br>
                                    <?php echo h(implode(', ', array_slice($bestRoom['reasons'], 0, 5))); ?>
                                </p>
                            </div>

                        <?php else: ?>
                            <div class="smart-card">
                                <h4>No Existing Roommate Match Found</h4>

                                <p>
                                    No suitable partially-filled room was found for this student.
                                    The admin can consider an empty room or group this student with compatible pending students.
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($smartRecommendations['empty_rooms'])): ?>
                            <div class="smart-card">
                                <h4>Suitable Empty Room Option</h4>

                                <?php foreach ($smartRecommendations['empty_rooms'] as $emptyRoom): ?>
                                    <p>
                                        <strong>Room:</strong>
                                        <?php echo h($emptyRoom['room_number']); ?>
                                        <br>

                                        <strong>Hall:</strong>
                                        <?php echo h($emptyRoom['hall_name']); ?>
                                        <br>

                                        <strong>Type:</strong>
                                        <?php echo h($emptyRoom['type_name']); ?>
                                        <br>

                                        <strong>Available Seats:</strong>
                                        <?php echo h($emptyRoom['available_seats']); ?>
                                    </p>
                                    <hr>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($smartRecommendations['pending_matches'])): ?>
                            <div class="smart-card">
                                <h4>Compatible Pending Student(s)</h4>

                                <?php foreach ($smartRecommendations['pending_matches'] as $pendingMatch): ?>
                                    <p>
                                        <strong><?php echo h($pendingMatch['name']); ?></strong>
                                        <br>

                                        Student ID:
                                        <?php echo h($pendingMatch['institutional_id']); ?>
                                        <br>

                                        <span class="score-pill <?php echo h(compatClass($pendingMatch['score'])); ?>">
                                            <?php echo h($pendingMatch['score']); ?>% - <?php echo h($pendingMatch['level']); ?>
                                        </span>
                                        <br>

                                        <small>
                                            Reason:
                                            <?php echo h(implode(', ', array_slice($pendingMatch['reasons'], 0, 4))); ?>
                                        </small>
                                    </p>
                                    <hr>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="smart-card">
                                <h4>No Compatible Pending Student Found</h4>

                                <p>
                                    No other pending student with the same room type preference was found right now.
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php if ($app['application_status'] === 'pending' || $app['application_status'] === 'approved'): ?>
                        <form method="POST" class="admin-form">
                            <input type="hidden" name="application_id" value="<?php echo intval($app['application_id']); ?>">

                            <div class="form-grid">
                                <div>
                                    <label>Available Room</label>

                                    <?php
                                    $roomScoreMap = [];
                                    $recommendedRoomId = 0;

                                    if (!empty($smartRecommendations['room_matches'])) {
                                        $recommendedRoomId = intval($smartRecommendations['room_matches'][0]['room_id']);

                                        foreach ($smartRecommendations['room_matches'] as $matchRoom) {
                                            $roomScoreMap[intval($matchRoom['room_id'])] = [
                                                'score' => $matchRoom['score'] . '% - ' . $matchRoom['level'],
                                                'type' => 'Existing Roommate Match',
                                                'reason' => implode(', ', array_slice($matchRoom['reasons'], 0, 5)),
                                                'roommates' => implode(', ', $matchRoom['roommates'])
                                            ];
                                        }
                                    } elseif (!empty($smartRecommendations['empty_rooms'])) {
                                        $recommendedRoomId = intval($smartRecommendations['empty_rooms'][0]['room_id']);
                                    }

                                    $bestPendingText = 'No compatible pending student found yet.';
                                    $bestPendingScore = 'No group score yet';

                                    if (!empty($smartRecommendations['pending_matches'])) {
                                        $bestPending = $smartRecommendations['pending_matches'][0];

                                        $bestPendingScore = $bestPending['score'] . '% - ' . $bestPending['level'];
                                        $bestPendingText = $bestPending['name'] . ' (' . $bestPending['institutional_id'] . ')';
                                    }

                                    foreach ($smartRecommendations['empty_rooms'] as $emptyRoom) {
                                        $emptyRoomId = intval($emptyRoom['room_id']);

                                        if ($recommendedRoomId === 0) {
                                            $recommendedRoomId = $emptyRoomId;
                                        }

                                        $roomScoreMap[$emptyRoomId] = [
                                            'score' => $bestPendingScore,
                                            'type' => 'Empty Room / Pending Student Grouping',
                                            'reason' => 'This room is empty. Compatibility depends on which pending student is grouped with this student.',
                                            'roommates' => 'Possible pending match: ' . $bestPendingText
                                        ];
                                    }

                                    $appGender = normalizeCompat($app['gender'] ?? '');
                                    $appRoomTypeId = intval($app['preferred_room_type_id']);
                                    $appPreferredHall = normalizeCompat($app['preferred_hall'] ?? '');
                                    $roomOptionCount = 0;
                                    ?>

                                    <select
                                        name="room_id"
                                        class="smart-room-select"
                                        data-preview-id="compat-preview-<?php echo intval($app['application_id']); ?>"
                                    >
                                        <option
                                            value=""
                                            data-score="No room selected"
                                            data-type="N/A"
                                            data-roommates="N/A"
                                            data-reason="Please select a room first."
                                        >
                                            Select Available Room
                                        </option>

                                        <?php foreach ($available_rooms as $room): ?>
                                            <?php
                                            $roomId = intval($room['room_id']);
                                            $roomTypeId = intval($room['room_type_id']);
                                            $roomGender = normalizeCompat($room['gender_type'] ?? '');
                                            $roomHall = normalizeCompat($room['hall_code'] ?? '');
                                            $occupiedSeats = intval($room['occupied_seats'] ?? 0);
                                            $capacity = intval($room['capacity'] ?? 0);

                                            if ($roomTypeId !== $appRoomTypeId) {
                                                continue;
                                            }

                                            if ($appGender !== '' && $roomGender !== '' && $roomGender !== $appGender) {
                                                continue;
                                            }

                                            $isRecommended = ($roomId === $recommendedRoomId);
                                            $isPreferredHall = ($appPreferredHall !== '' && $roomHall === $appPreferredHall);

                                            $scoreInfo = $roomScoreMap[$roomId] ?? null;

                                            if (!$scoreInfo && $capacity === 1) {
                                                $scoreInfo = [
                                                    'score' => 'Single Room',
                                                    'type' => 'No Roommate Needed',
                                                    'reason' => 'This is an individual room, so roommate compatibility is not required.',
                                                    'roommates' => 'No roommate'
                                                ];
                                            } elseif (!$scoreInfo && $occupiedSeats === 0) {
                                                $scoreInfo = [
                                                    'score' => $bestPendingScore,
                                                    'type' => 'Empty Room / Pending Student Grouping',
                                                    'reason' => 'This room is empty. Compatibility depends on which pending student is grouped with this student.',
                                                    'roommates' => 'Possible pending match: ' . $bestPendingText
                                                ];
                                            } elseif (!$scoreInfo) {
                                                $scoreInfo = [
                                                    'score' => 'Not calculated',
                                                    'type' => 'Basic Suitable Room',
                                                    'reason' => 'This room matches room type and gender rules, but roommate compatibility score is not available.',
                                                    'roommates' => 'N/A'
                                                ];
                                            }

                                            $roomOptionCount++;
                                            ?>

                                            <option
                                                value="<?php echo $roomId; ?>"
                                                <?php echo $isRecommended ? 'selected' : ''; ?>
                                                data-score="<?php echo h($scoreInfo['score']); ?>"
                                                data-type="<?php echo h($scoreInfo['type']); ?>"
                                                data-roommates="<?php echo h($scoreInfo['roommates']); ?>"
                                                data-reason="<?php echo h($scoreInfo['reason']); ?>"
                                            >
                                                <?php echo $isRecommended ? '⭐ Recommended - ' : ''; ?>
                                                Room <?php echo h($room['room_number']); ?>
                                                - <?php echo h($room['hall_name'] ?? 'Hall N/A'); ?>
                                                - <?php echo h($room['type_name']); ?>
                                                - Available Seats: <?php echo h($room['available_seats']); ?>
                                                <?php echo $isPreferredHall ? ' - Preferred Hall' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>

                                        <?php if ($roomOptionCount === 0): ?>
                                            <option value="" disabled>No suitable room found for this student</option>
                                        <?php endif; ?>
                                    </select>

                                    <div
                                        id="compat-preview-<?php echo intval($app['application_id']); ?>"
                                        class="selected-compat-box"
                                    >
                                        Select a room to see compatibility score.
                                    </div>

                                </div>

                                <div>
                                    <label>Seat No</label>
                                    <input type="text" name="seat_no" placeholder="Example: 1">
                                </div>

                                <div>
                                    <label>Admin Message</label>
                                    <textarea name="admin_message" placeholder="Write message for student..."></textarea>
                                </div>

                                <div>
                                    <button type="submit" name="action" value="assign" class="btn btn-assign">
                                        Assign Room
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
                            <strong>Admin Message</strong>
                            <?php echo h($app['admin_message'] ?? 'No admin message.'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <script>
        document.querySelectorAll('.smart-room-select').forEach(function(selectBox) {
            function updateCompatibilityPreview() {
                const selectedOption = selectBox.options[selectBox.selectedIndex];
                const previewId = selectBox.getAttribute('data-preview-id');
                const previewBox = document.getElementById(previewId);

                if (!previewBox || !selectedOption) {
                    return;
                }

                const score = selectedOption.getAttribute('data-score') || 'Not calculated';
                const type = selectedOption.getAttribute('data-type') || 'N/A';
                const reason = selectedOption.getAttribute('data-reason') || 'N/A';
                const roommates = selectedOption.getAttribute('data-roommates') || 'N/A';

                previewBox.innerHTML = `
                    <strong>Selected Room Compatibility</strong><br>
                    <strong>Score:</strong> ${score}<br>
                    <strong>Type:</strong> ${type}<br>
                    <strong>Roommate / Group Info:</strong> ${roommates}<br>
                    <strong>Reason:</strong> ${reason}
                `;
            }

            selectBox.addEventListener('change', updateCompatibilityPreview);
            updateCompatibilityPreview();
        });
    </script>

    <script src="../assets/js/theme.js"></script>
</body>

</html>