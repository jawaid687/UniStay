<?php
session_start();
require_once "../includes/db.php";


if(!isset($_SESSION['user_id']) || $_SESSION['role']!='staff'){
    header("Location: ../auth/login.php");
    exit();
}


$staff_id = $_SESSION['user_id'];



$stmt = $conn->prepare("
    SELECT *
    FROM floor_schedule
    WHERE staff_id = ?
");


$stmt->bind_param("i",$staff_id);

$stmt->execute();


$result = $stmt->get_result();

?>



<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Staff Floor Schedule</title>



<style>


*{

margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;

}



body{

background:#f4f8f7;
padding:25px;
transition:.3s;

}



.container{

max-width:1300px;
margin:auto;

}



.card{

background:white;
padding:30px;
border-radius:18px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
border-top:6px solid #00897b;
transition:.3s;

}



.card:hover{

transform:translateY(-4px);

}



.header{

display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:25px;
border-bottom:1px solid #ddd;
padding-bottom:15px;

}



.header h2{

color:#004d40;

}



.header-btn{

display:flex;
gap:10px;

}



.back-btn,
.theme-btn{

padding:10px 18px;
border:none;
border-radius:8px;
color:white;
font-weight:bold;
text-decoration:none;
cursor:pointer;

}



.back-btn{

background:#64748b;

}



.theme-btn{

background:#00897b;

}




table{

width:100%;
border-collapse:collapse;
margin-top:20px;

}



th{

background:#00897b;
color:white;
padding:14px;

}



td{

padding:12px;
text-align:center;
border-bottom:1px solid #ddd;

}



tr:hover{

background:#f1f5f9;

}



/* DARK MODE */


.dark-mode{

background:#020617;

}



.dark-mode .card{

background:#0f172a;
color:white;

}



.dark-mode .header{

border-color:#334155;

}



.dark-mode h2{

color:#38bdf8;

}



.dark-mode td{

border-color:#334155;

}



.dark-mode tr:hover{

background:#1e293b;

}



.dark-mode table{

color:white;

}





@media(max-width:700px){


.header{

flex-direction:column;
gap:15px;
align-items:flex-start;

}


table{

font-size:13px;

}


}


</style>


</head>



<body>



<div class="container">


<div class="card">



<div class="header">


<h2>
🏢 Staff Floor Schedule
</h2>



<div class="header-btn">


<a href="dashboard.php" class="back-btn">
← Back
</a>


<button onclick="darkMode()" class="theme-btn" id="themeBtn">

🌙 Dark Mode

</button>



</div>



</div>





<table>


<tr>

<th>ID</th>
<th>Floor Name</th>
<th>Room Range</th>
<th>Shift Time</th>
<th>Working Days</th>
<th>Weekly Off</th>
<th>Duty Type</th>
<th>Supervisor</th>

</tr>




<?php


if($result && $result->num_rows > 0){


while($row=$result->fetch_assoc()){


?>


<tr>


<td>
<?= htmlspecialchars($row['schedule_id']); ?>
</td>


<td>
<?= htmlspecialchars($row['floor_name']); ?>
</td>


<td>
<?= htmlspecialchars($row['room_range']); ?>
</td>


<td>
<?= htmlspecialchars($row['shift_time']); ?>
</td>


<td>
<?= htmlspecialchars($row['working_days']); ?>
</td>


<td>
<?= htmlspecialchars($row['weekly_off']); ?>
</td>


<td>
<?= htmlspecialchars($row['duty_type']); ?>
</td>


<td>
<?= htmlspecialchars($row['supervisor_name']); ?>
</td>


</tr>


<?php

}

}

else{


?>

<tr>

<td colspan="8">

No Floor Schedule Found

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


let body=document.body;

let btn=document.getElementById("themeBtn");


body.classList.toggle("dark-mode");


if(body.classList.contains("dark-mode")){


localStorage.setItem("floorTheme","dark");

btn.innerHTML="☀️ Light Mode";


}

else{


localStorage.setItem("floorTheme","light");

btn.innerHTML="🌙 Dark Mode";


}


}




window.onload=function(){


let btn=document.getElementById("themeBtn");


if(localStorage.getItem("floorTheme")=="dark"){


document.body.classList.add("dark-mode");

btn.innerHTML="☀️ Light Mode";


}


}



</script>



</body>

</html>