<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

/* Form Data */

$full_name       = trim($_POST['full_name']);
$phone           = trim($_POST['phone']);
$department      = trim($_POST['department']);
$designation     = trim($_POST['designation']);
$assigned_floor  = trim($_POST['assigned_floor']);
$status          = trim($_POST['status']);

$availability    = trim($_POST['availability']);
$note            = trim($_POST['note']);


/* Update staff_records */

$stmt = mysqli_prepare($conn,

"UPDATE staff_records
SET
    full_name=?,
    phone=?,
    department=?,
    designation=?,
    assigned_floor=?,
    status=?
WHERE user_id=?");

mysqli_stmt_bind_param(
$stmt,
"ssssssi",
$full_name,
$phone,
$department,
$designation,
$assigned_floor,
$status,
$user_id
);

$success = mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);


/* Find Staff Record ID */

$staff_record_id = 0;

$stmt = mysqli_prepare($conn,

"SELECT id
FROM staff_records
WHERE user_id=?
LIMIT 1");

mysqli_stmt_bind_param($stmt,"i",$user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if($row = mysqli_fetch_assoc($result)){
    $staff_record_id = $row['id'];
}

mysqli_stmt_close($stmt);


/* Update or Insert Availability */

if($staff_record_id > 0){

    $stmt = mysqli_prepare($conn,

    "SELECT id
    FROM staff_availability
    WHERE staff_record_id=?
    LIMIT 1");

    mysqli_stmt_bind_param($stmt,"i",$staff_record_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_fetch_assoc($result)){

        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn,

        "UPDATE staff_availability
        SET
            availability=?,
            note=?
        WHERE staff_record_id=?");

        mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $availability,
        $note,
        $staff_record_id
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

    }else{

        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn,

        "INSERT INTO staff_availability
        (staff_record_id,availability,note)
        VALUES (?,?,?)");

        mysqli_stmt_bind_param(
        $stmt,
        "iss",
        $staff_record_id,
        $availability,
        $note
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

    }

}


/* Redirect */

if($success){

    $_SESSION['success'] = "Profile updated successfully.";

}else{

    $_SESSION['error'] = "Profile update failed.";

}

header("Location: profile.php");
exit();