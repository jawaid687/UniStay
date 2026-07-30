<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

require_once "../includes/db.php";


if(!isset($_SESSION['user_id']) || $_SESSION['role']!='admin'){

    header("Location: ../auth/login.php");
    exit();

}



// Accept / Reject

if(isset($_GET['id']) && isset($_GET['status'])){


    $id = intval($_GET['id']);

    $status = $_GET['status'];



    if(in_array($status,['Approved','Rejected'])){


        $update = $conn->prepare("

            UPDATE staff_leave_applications

            SET status=?

            WHERE id=?

        ");



        $update->bind_param(
            "si",
            $status,
            $id
        );


        if($update->execute()){

            header("Location: staff-leave.php");
            exit();

        }


    }

}




// Fetch leave data


$sql = "

SELECT

sla.id,

sla.staff_id,

sla.leave_type,

sla.reason,

sla.start_date,

sla.end_date,

sla.status,

sla.applied_at,

u.name,

u.email


FROM staff_leave_applications sla


INNER JOIN users u

ON sla.staff_id=u.id


ORDER BY sla.applied_at DESC

";



$result=$conn->query($sql);



if(!$result){

    die("Database Error : ".$conn->error);

}


?>



<!DOCTYPE html>

<html>

<head>


<title>Staff Leave Applications</title>


<style>


body{

margin:0;

padding:25px;

background:#f4f8f7;

font-family:'Segoe UI',sans-serif;

transition:.3s;

}



.container{

max-width:1250px;

margin:auto;

}



.card{

background:white;

padding:30px;

border-radius:15px;

box-shadow:0 8px 20px rgba(0,0,0,.1);

border-top:6px solid #00897b;

}



.header{

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:25px;

}



h2{

color:#004d40;

}



.btn{

padding:10px 15px;

border-radius:7px;

text-decoration:none;

color:white;

font-weight:bold;

border:none;

cursor:pointer;

}



.back{

background:#64748b;

}


.theme{

background:#00897b;

}



.accept{

background:#16a34a;

}



.reject{

background:#dc2626;

}



table{

width:100%;

border-collapse:collapse;

}



th{

background:#00897b;

color:white;

padding:14px;

}



td{

padding:14px;

text-align:center;

border-bottom:1px solid #ddd;

}



tr:hover{

background:#f1f5f9;

}



.badge{

padding:7px 15px;

border-radius:20px;

font-weight:bold;

}



.pending{

background:#fde68a;

color:#78350f;

}



.approved{

background:#dcfce7;

color:#166534;

}



.rejected{

background:#fee2e2;

color:#991b1b;

}



.empty{

text-align:center;

padding:20px;

}





/* DARK MODE */


.dark-mode{

background:#020617;

color:white;

}



.dark-mode .card{

background:#0f172a;

color:white;

}



.dark-mode td{

border-color:#334155;

}



.dark-mode table{

color:white;

}



.dark-mode h2{

color:#7dd3fc;

}



</style>


</head>



<body>



<div class="container">


<div class="card">



<div class="header">


<h2>
📝 Staff Leave Applications
</h2>



<div>


<button onclick="darkMode()" class="btn theme">

🌙 Dark Mode

</button>



<a href="dashboard.php" class="btn back">

← Dashboard

</a>



</div>


</div>




<table>


<tr>

<th>ID</th>

<th>Staff Name</th>

<th>Email</th>

<th>Leave Type</th>

<th>Reason</th>

<th>Date</th>

<th>Status</th>

<th>Action</th>


</tr>



<?php


if($result->num_rows>0){


while($row=$result->fetch_assoc()){


$status=$row['status'];


$class="pending";


if($status=="Approved"){

$class="approved";

}

elseif($status=="Rejected"){

$class="rejected";

}


?>



<tr>


<td>

<?= $row['id']; ?>

</td>



<td>

<?= htmlspecialchars($row['name']); ?>

</td>



<td>

<?= htmlspecialchars($row['email']); ?>

</td>



<td>

<?= htmlspecialchars($row['leave_type']); ?>

</td>



<td>

<?= htmlspecialchars($row['reason']); ?>

</td>



<td>

<?= $row['start_date']; ?>

<br>

To

<br>

<?= $row['end_date']; ?>

</td>



<td>

<span class="badge <?= $class ?>">

<?= $status; ?>

</span>

</td>



<td>



<?php if($status=="Pending"){ ?>


<a class="btn accept"

href="staff-leave.php?id=<?= $row['id']; ?>&status=Approved">

Accept

</a>



<a class="btn reject"

href="staff-leave.php?id=<?= $row['id']; ?>&status=Rejected">

Reject

</a>



<?php }else{ ?>


Completed


<?php } ?>


</td>



</tr>



<?php


}


}else{


?>


<tr>

<td colspan="8" class="empty">

📭 No Staff Leave Application Found

</td>

</tr>



<?php

}


?>


</table>



</div>


</div>




<script>


function darkMode(){


document.body.classList.toggle("dark-mode");


}



</script>



</body>


</html>