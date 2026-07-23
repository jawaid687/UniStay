<?php
include 'db_connect.php';

/*
---------------------------------------------------
Duplicate Complaint Detection
Checks:
1. Same category
2. Same floor
3. Submitted within last 48 hours
---------------------------------------------------
*/

function findDuplicateComplaint($student_id, $category)
{
    global $conn;

    // Find student's floor
    $floorQuery = "
    SELECT r.floor
    FROM room_assignments ra
    JOIN rooms r
    ON ra.room_id = r.room_id
    WHERE ra.student_id = '$student_id'
    LIMIT 1";

    $floorResult = mysqli_query($conn, $floorQuery);

    if(mysqli_num_rows($floorResult)==0)
    {
        return NULL;
    }

    $floor = mysqli_fetch_assoc($floorResult)['floor'];

    // Search duplicate complaint
    $duplicateQuery = "

    SELECT
    c.complaint_id,
    c.cluster_id

    FROM complaints c

    JOIN room_assignments ra
    ON c.student_id=ra.student_id

    JOIN rooms r
    ON ra.room_id=r.room_id

    WHERE

    c.category='$category'

    AND

    r.floor='$floor'

    AND

    c.status!='Solved'

    AND

    c.created_at>=NOW()-INTERVAL 2 DAY

    LIMIT 1";

    $duplicateResult = mysqli_query($conn,$duplicateQuery);

    if(mysqli_num_rows($duplicateResult)>0)
    {
        return mysqli_fetch_assoc($duplicateResult);
    }

    return NULL;
}
?>