<?php
session_start();

require_once 'resident_guard.php';
require_once '../includes/db.php';

/*----------------------------------------------------
 Auto Priority Function
----------------------------------------------------*/
function getPriority($category)
{
    switch($category)
    {
        case "Electricity":
        case "Water":
            return "High";

        case "Internet":
        case "Furniture":
            return "Medium";

        default:
            return "Low";
    }
}

/*----------------------------------------------------
 Timeline Function
----------------------------------------------------*/
function addTimeline($conn,$complaint_id,$status,$note="")
{
    $sql="INSERT INTO complaint_timeline
    (complaint_id,status_change,note,changed_at)
    VALUES
    (?,?,?,NOW())";

    $stmt=mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iss",
        $complaint_id,
        $status,
        $note
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/*----------------------------------------------------
 Check Request
----------------------------------------------------*/

if($_SERVER["REQUEST_METHOD"]!="POST")
{
    die("Invalid Request");
}

/*----------------------------------------------------
 Student Information
----------------------------------------------------*/

$student_id=$_SESSION['user_id'];

$category=$_POST['category'];

$description=trim($_POST['description']);

$priority=getPriority($category);

$status="Pending";

$assigned_staff=NULL;

$cluster_id=NULL;

$escalated=0;

$photo_path="";

/*----------------------------------------------------
 Upload Photo
----------------------------------------------------*/

if(isset($_FILES['complaint_photo']) &&
$_FILES['complaint_photo']['error']==0)
{

    $allowed=array("jpg","jpeg","png");

    $extension=strtolower(
        pathinfo(
            $_FILES['complaint_photo']['name'],
            PATHINFO_EXTENSION
        )
    );

    if(!in_array($extension,$allowed))
    {
        die("Only JPG, JPEG and PNG allowed.");
    }

    if($_FILES['complaint_photo']['size']>2097152)
    {
        die("Maximum photo size is 2MB.");
    }

    $folder="../uploads/complaints/";

    if(!is_dir($folder))
    {
        mkdir($folder,0777,true);
    }

    $filename=time()."_".uniqid().".".$extension;

    $destination=$folder.$filename;

    if(move_uploaded_file(
        $_FILES['complaint_photo']['tmp_name'],
        $destination
    ))
    {
        $photo_path="uploads/complaints/".$filename;
    }
}

/*----------------------------------------------------
 Find Existing Cluster
----------------------------------------------------*/

$sql="SELECT complaint_id,
             cluster_id,
             assigned_staff_id
      FROM complaints
      WHERE category=?
      AND status<>'Solved'
      ORDER BY complaint_id ASC
      LIMIT 1";

$stmt=mysqli_prepare($conn,$sql);

mysqli_stmt_bind_param($stmt,"s",$category);

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)>0)
{
    $old=mysqli_fetch_assoc($result);

    /* Existing Cluster */

    if(!empty($old['cluster_id']))
    {
        $cluster_id=$old['cluster_id'];
    }
    else
    {
        /* First complaint becomes leader */

        $cluster_id="C".$old['complaint_id'];

        mysqli_query(
            $conn,
            "UPDATE complaints
             SET cluster_id='$cluster_id'
             WHERE complaint_id='".$old['complaint_id']."'"
        );
    }

    /* Copy Assigned Staff */

    if(!empty($old['assigned_staff_id']))
    {
        $assigned_staff=$old['assigned_staff_id'];
    }
}

mysqli_stmt_close($stmt);

/*----------------------------------------------------
 Insert Complaint
----------------------------------------------------*/

$sql="INSERT INTO complaints
(
student_id,
category,
description,
photo_path,
priority,
status,
assigned_staff_id,
cluster_id,
escalated,
created_at
)

VALUES
(
?,
?,
?,
?,
?,
?,
?,
?,
?,
NOW()
)";

$stmt=mysqli_prepare($conn,$sql);

mysqli_stmt_bind_param(
$stmt,
"isssssssi",
$student_id,
$category,
$description,
$photo_path,
$priority,
$status,
$assigned_staff,
$cluster_id,
$escalated
);

if(mysqli_stmt_execute($stmt))
{

    $complaint_id=mysqli_insert_id($conn);

    /*-----------------------------------------
      First Complaint Creates Cluster
    ------------------------------------------*/

    if(empty($cluster_id))
    {
        $cluster_id="C".$complaint_id;

        mysqli_query(
            $conn,
            "UPDATE complaints
             SET cluster_id='$cluster_id'
             WHERE complaint_id='$complaint_id'"
        );
    }

    /*-----------------------------------------
      Timeline
    ------------------------------------------*/

    addTimeline(
        $conn,
        $complaint_id,
        "Pending",
        "Complaint Submitted"
    );

    echo "
    <script>
    alert('Complaint Submitted Successfully!');
    window.location='complaint_status.php';
    </script>";

}
else
{
    echo "Database Error : ".mysqli_error($conn);
}

mysqli_stmt_close($stmt);

mysqli_close($conn);

?>