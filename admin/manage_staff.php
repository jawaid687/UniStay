<?php

session_start();

require_once "../includes/db.php";


/* ONLY ADMIN */

if(!isset($_SESSION['user_id']) || $_SESSION['role']!='admin'){

    header("Location: ../auth/login.php");
    exit();

}


function h($value){

    return htmlspecialchars($value ?? '',ENT_QUOTES,'UTF-8');

}


/* GET ALL STAFF */


$sql="
SELECT

sr.*,
u.name,
u.email,
u.phone,
u.availability

FROM staff_records sr

JOIN users u

ON sr.user_id=u.id

ORDER BY sr.id DESC
";


$result=mysqli_query($conn,$sql);


?>


<!DOCTYPE html>

<html>

<head>

<title>Manage Staff</title>


<style>

body{

font-family:Segoe UI;

background:#eef2f7;

padding:30px;

}


.container{

max-width:1200px;

margin:auto;

}


.box{

background:white;

padding:25px;

border-radius:15px;

box-shadow:0 8px 20px #ddd;

}


table{

width:100%;

border-collapse:collapse;

}


th{

background:#2563eb;

color:white;

padding:12px;

}


td{

padding:12px;

border-bottom:1px solid #ddd;

}


.btn{

background:#2563eb;

color:white;

padding:8px 15px;

border-radius:8px;

text-decoration:none;

}


</style>


</head>


<body>


<div class="container">


<div class="box">


<h1>👥 Manage Staff</h1>


<br>


<table>


<tr>

<th>Name</th>
<th>Staff ID</th>
<th>Department</th>
<th>Designation</th>
<th>Availability</th>
<th>Action</th>

</tr>



<?php while($row=mysqli_fetch_assoc($result)){ ?>


<tr>


<td>
<?=h($row['full_name'])?>
</td>


<td>
<?=h($row['staff_id'])?>
</td>


<td>
<?=h($row['department'])?>
</td>


<td>
<?=h($row['designation'])?>
</td>


<td>
<?=h($row['availability'])?>
</td>


<td>

<a class="btn"
href="staff_performance.php?id=<?=$row['user_id']?>">

📊 Performance

</a>


</td>


</tr>


<?php } ?>


</table>


</div>


</div>


</body>

</html>