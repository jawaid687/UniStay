<?php

session_start();

require_once "../includes/db.php";


/* Only Admin */

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){

    header("Location: ../auth/login.php");
    exit();

}



function h($value){

    return htmlspecialchars($value ?? 'N/A', ENT_QUOTES,'UTF-8');

}



/* Load All Staff */


$sql = "

SELECT 

sr.*,
u.email

FROM staff_records sr

LEFT JOIN users u

ON sr.user_id = u.id

ORDER BY sr.id DESC

";


$result = mysqli_query($conn,$sql);



?>



<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Staff Records - UniStay</title>



<style>


*{

margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;

}



body{

background:#020617;
padding:30px;
color:white;

}



.container{

max-width:1200px;
margin:auto;

}




.header{

background:#0f172a;
padding:25px;
border-radius:15px;
border:1px solid #334155;

display:flex;
justify-content:space-between;
align-items:center;

margin-bottom:25px;

}



.header h1{

color:#38bdf8;

}



.header p{

color:#94a3b8;
margin-top:5px;

}



.btn{

background:#14b8a6;
color:white;
padding:10px 18px;
border-radius:8px;
text-decoration:none;
font-weight:bold;

}




.card{


background:#0f172a;

border:1px solid #334155;

border-left:5px solid #38bdf8;

padding:25px;

border-radius:15px;

margin-bottom:20px;


}




.card-header{


display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:20px;


}




.card-header h2{

color:#7dd3fc;

}




.status{


background:#bbf7d0;

color:#166534;

padding:6px 15px;

border-radius:20px;

font-weight:bold;

font-size:13px;


}





.grid{


display:grid;

grid-template-columns:repeat(4,1fr);

gap:15px;


}




.box{


background:#111827;

padding:15px;

border-radius:10px;

border:1px solid #334155;


}



.label{


color:#38bdf8;

font-size:14px;

font-weight:bold;

margin-bottom:5px;


}



.value{


color:#e2e8f0;

}




.view-btn{


display:inline-block;

margin-top:20px;

background:#0ea5e9;

color:white;

padding:10px 20px;

border-radius:8px;

text-decoration:none;

font-weight:bold;


}




.theme-btn{


background:#1e293b;

color:white;

border:1px solid #475569;

padding:10px 15px;

border-radius:8px;

cursor:pointer;


}




.light{


background:#f1f5f9;

color:#111827;

}



.light .header,
.light .card{

background:white;

color:#111827;

}



.light .box{

background:#f8fafc;

}



@media(max-width:900px){


.grid{

grid-template-columns:repeat(2,1fr);

}


}



@media(max-width:600px){


.grid{

grid-template-columns:1fr;

}



.header{

flex-direction:column;

gap:15px;

}


}



</style>



</head>


<body>



<div class="container">



<div class="header">


<div>

<h1>
👥 Staff Records
</h1>


<p>
All registered staff members
</p>


</div>



<div>


<button class="theme-btn" onclick="theme()">
☀️ Light Mode
</button>



<a href="dashboard.php" class="btn">

Admin Dashboard

</a>


</div>



</div>







<?php while($staff=mysqli_fetch_assoc($result)){ ?>



<div class="card">



<div class="card-header">


<h2>

<?=h($staff['full_name'])?>

</h2>



<span class="status">

<?=h($staff['status'])?>

</span>



</div>





<div class="grid">





<div class="box">

<div class="label">
Staff ID
</div>

<div class="value">

<?=h($staff['staff_id'])?>

</div>

</div>






<div class="box">

<div class="label">
Email
</div>

<div class="value">

<?=h($staff['email'])?>

</div>

</div>







<div class="box">

<div class="label">
Employee ID
</div>

<div class="value">

<?=h($staff['employee_id'])?>

</div>

</div>








<div class="box">

<div class="label">
Phone
</div>

<div class="value">

<?=h($staff['phone'])?>

</div>

</div>








<div class="box">

<div class="label">
Designation
</div>

<div class="value">

<?=h($staff['designation'])?>

</div>

</div>








<div class="box">

<div class="label">
Assigned Floor
</div>

<div class="value">

<?=h($staff['assigned_floor'])?>

</div>

</div>








<div class="box">

<div class="label">
Availability
</div>

<div class="value">

<?=h($staff['availability'])?>

</div>

</div>








<div class="box">

<div class="label">
Rating
</div>

<div class="value">

⭐ <?=h($staff['rating'])?> / 5

</div>

</div>
</div>
</div>
<?php } ?>
</div>
<script>
function theme(){

document.body.classList.toggle("light");


if(document.body.classList.contains("light")){

localStorage.setItem("adminTheme","light");

}

else{

localStorage.setItem("adminTheme","dark");

}


}



if(localStorage.getItem("adminTheme")=="light"){

document.body.classList.add("light");

}



</script>




</body>

</html>