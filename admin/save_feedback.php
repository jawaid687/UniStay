<?php

session_start();

require_once "../includes/db.php";


/* ONLY ADMIN */

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){

    header("Location: ../auth/login.php");
    exit();

}


/* ONLY POST REQUEST */

if($_SERVER['REQUEST_METHOD'] != 'POST'){

    die("Invalid Request");

}



/*
=========================
GET FORM DATA
=========================
*/

$staff_id = intval($_POST['staff_id'] ?? 0);

$performance_status = mysqli_real_escape_string(
    $conn,
    $_POST['performance_status'] ?? 'Good'
);


$rating = floatval($_POST['rating'] ?? 5);


$feedback = mysqli_real_escape_string(
    $conn,
    $_POST['feedback'] ?? ''
);



/*
=========================
CHECK STAFF EXISTS
(staff_records table)
=========================
*/


$checkStaff = mysqli_query(
    $conn,
    "SELECT id 
     FROM staff_records 
     WHERE id='$staff_id'"
);



if(mysqli_num_rows($checkStaff)==0){

    die("Invalid Staff ID.");

}



/*
=========================
CHECK PERFORMANCE EXISTS
=========================
*/


$checkPerformance = mysqli_query(
    $conn,
    "SELECT id 
     FROM staff_performance 
     WHERE staff_id='$staff_id'"
);



if(mysqli_num_rows($checkPerformance)>0){


    /*
    =====================
    UPDATE PERFORMANCE
    =====================
    */


    $sql = "

    UPDATE staff_performance

    SET

    performance_status='$performance_status',

    rating='$rating',

    feedback='$feedback',

    updated_at=NOW()


    WHERE staff_id='$staff_id'

    ";



}else{


    /*
    =====================
    INSERT PERFORMANCE
    =====================
    */


    $sql = "

    INSERT INTO staff_performance

    (
        staff_id,
        task_completed,
        task_pending,
        total_tasks,
        attendance_percentage,
        rating,
        complaints_resolved,
        average_resolution_time,
        overtime_hours,
        late_attendance,
        absent_days,
        performance_status,
        feedback,
        updated_at
    )


    VALUES

    (

        '$staff_id',
        0,
        0,
        0,
        0,
        '$rating',
        0,
        0,
        0,
        0,
        0,
        '$performance_status',
        '$feedback',
        NOW()

    )

    ";

}




/*
=========================
EXECUTE QUERY
=========================
*/


if(mysqli_query($conn,$sql)){


    echo "

    <script>

    alert('Feedback saved successfully.');

    window.location='staff-performance.php';

    </script>

    ";



}else{


    die(
        "Database Error : "
        .mysqli_error($conn)
    );


}


?>