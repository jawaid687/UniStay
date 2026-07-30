<?php
session_start();

require_once '../includes/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {

    header("Location: ../auth/login.php");
    exit();

}


$user_id = intval($_SESSION['user_id']);



function h($value)
{
    return htmlspecialchars($value ?? 'N/A', ENT_QUOTES, 'UTF-8');
}



function getValue($array,$key,$default="N/A")
{
    return (!empty($array[$key])) ? $array[$key] : $default;
}





/*
==========================
 USER INFORMATION
==========================
*/


$user_stmt = mysqli_prepare(
$conn,

"SELECT 
id,
name,
email,
role
FROM users
WHERE id=?"

);



mysqli_stmt_bind_param(
$user_stmt,
"i",
$user_id
);



mysqli_stmt_execute($user_stmt);



$user_result=mysqli_stmt_get_result($user_stmt);



$user=mysqli_fetch_assoc($user_result);



if(!$user){

    die("User not found");

}






/*
==========================
 STAFF INFORMATION
==========================
*/


$staff_stmt=mysqli_prepare(
$conn,

"SELECT *
FROM staff_records
WHERE user_id=?
LIMIT 1"

);



mysqli_stmt_bind_param(
$staff_stmt,
"i",
$user_id
);



mysqli_stmt_execute($staff_stmt);



$staff_result=mysqli_stmt_get_result($staff_stmt);



$staff=mysqli_fetch_assoc($staff_result);



if(!$staff){

    $staff=[];

}



?>


<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Staff Profile - UniStay</title>


<style>


*{

margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;

}



body{

background:#f1f5f9;

padding:30px;

color:#1e293b;

}



.container{

max-width:1100px;

margin:auto;

}




.header{


background:white;

padding:25px;

border-radius:18px;

display:flex;

justify-content:space-between;

align-items:center;

box-shadow:0 8px 20px rgba(0,0,0,.08);


}



.header h1{

color:#0f172a;

}



.header p{

color:#64748b;

margin-top:8px;

}



.header-actions{

display:flex;

gap:10px;

}

/* BUTTON */

.btn{

text-decoration:none;

padding:12px 20px;

border-radius:10px;

color:white;

font-weight:600;

border:none;

cursor:pointer;

transition:.3s;

}



.btn:hover{

transform:translateY(-2px);

opacity:.85;

}



.dashboard{

background:#0f766e;

}



.edit{

background:#2563eb;

}



.theme{

background:#64748b;

}






/* HERO */

.hero{


margin-top:25px;


background:linear-gradient(
135deg,
#2563eb,
#0f766e
);


color:white;


padding:35px;


border-radius:18px;


box-shadow:0 10px 25px rgba(0,0,0,.15);


}



.hero h2{


font-size:32px;


margin-bottom:10px;


}



.hero p{


margin-top:8px;


font-size:16px;


}







/* GRID */


.grid{


margin-top:25px;


display:grid;


grid-template-columns:repeat(2,1fr);


gap:22px;


}







/* CARD */


.card{


background:white;


padding:25px;


border-radius:18px;


box-shadow:
0 8px 20px rgba(0,0,0,.08);


transition:.3s;


}



.card:hover{


transform:translateY(-5px);


}




.card h2{


color:#1e293b;


margin-bottom:20px;


border-bottom:2px solid #e2e8f0;


padding-bottom:10px;


font-size:20px;


}







/* INFORMATION ROW */


.row{


display:flex;


justify-content:space-between;


padding:12px 0;


border-bottom:1px dashed #cbd5e1;


}



.row:last-child{


border:none;


}




.label{


font-weight:600;


color:#475569;


}



.value{


color:#0f172a;


text-align:right;


}







/* STATUS */


.badge{


background:#dcfce7;


color:#166534;


padding:7px 15px;


border-radius:20px;


font-weight:bold;


}






/* PERFORMANCE FULL WIDTH */


.performance{


grid-column:1/-1;


}






/* RESPONSIVE */


@media(max-width:800px){


.grid{


grid-template-columns:1fr;


}



.header{


flex-direction:column;


gap:20px;


}



.header-actions{


width:100%;


}



.btn{


flex:1;


text-align:center;


}



.row{


flex-direction:column;


gap:5px;


}



.value{


text-align:left;


}



}
</style>

</head>


<body>



<div class="container">



<!-- HEADER -->


<div class="header">


<div>


<h1>
👤 Staff Profile
</h1>


<p>
<?= h($user['email']); ?>
</p>


</div>




<div class="header-actions">



<button onclick="toggleTheme()" class="btn theme">

🌙 / ☀️ Mode

</button>




<a href="dashboard.php" class="btn dashboard">

⬅ Dashboard

</a>




<a href="edit_profile.php" class="btn edit">

✏ Edit Profile

</a>



</div>



</div>






<!-- HERO SECTION -->


<div class="hero">



<h2>

<?= h(getValue($staff,'full_name',$user['name'])); ?>

</h2>



<p>

Staff ID :

<?= h(getValue($staff,'staff_id')); ?>

</p>



<p>

Role :

<?= h($user['role']); ?>

</p>



</div>







<div class="grid">







<!-- PERSONAL INFORMATION -->


<div class="card">


<h2>
Personal Information
</h2>




<div class="row">

<div class="label">
Full Name
</div>


<div class="value">

<?= h(getValue($staff,'full_name',$user['name'])); ?>

</div>


</div>






<div class="row">

<div class="label">
Email
</div>


<div class="value">

<?= h($user['email']); ?>

</div>


</div>







<div class="row">

<div class="label">
Phone
</div>


<div class="value">

<?= h(getValue($staff,'phone')); ?>

</div>


</div>







<div class="row">

<div class="label">
Employee ID
</div>


<div class="value">

<?= h(getValue($staff,'employee_id')); ?>

</div>


</div>




</div>









<!-- EMPLOYMENT INFORMATION -->


<div class="card">


<h2>
Employment Information
</h2>





<div class="row">

<div class="label">
Staff ID
</div>


<div class="value">

<?= h(getValue($staff,'staff_id')); ?>

</div>


</div>






<div class="row">

<div class="label">
Designation
</div>


<div class="value">

<?= h(getValue($staff,'designation')); ?>

</div>


</div>






<div class="row">

<div class="label">
Assigned Floor
</div>


<div class="value">

<?= h(getValue($staff,'assigned_floor')); ?>

</div>


</div>







<div class="row">

<div class="label">
Joining Date
</div>


<div class="value">

<?= h(getValue($staff,'joining_date')); ?>

</div>


</div>




</div>









<!-- WORK STATUS -->


<div class="card">


<h2>
Work Status
</h2>





<div class="row">

<div class="label">
Status
</div>


<div class="value">


<span class="badge">

<?= h(getValue($staff,'status','Active')); ?>

</span>


</div>


</div>






<div class="row">

<div class="label">
Availability
</div>


<div class="value">

<?= h(getValue($staff,'availability')); ?>

</div>


</div>






</div>


</div>






</div>





</div>


</div>







<style>


/* DARK MODE */


body.dark-mode{

background:#020617;

color:#e5e7eb;

}



body.dark-mode .header,
body.dark-mode .card{

background:#0f172a;

}



body.dark-mode .header h1,
body.dark-mode .card h2{

color:white;

}



body.dark-mode .label{

color:#94a3b8;

}



body.dark-mode .value{

color:#e5e7eb;

}



body.dark-mode .card h2{

border-color:#334155;

}




</style>








<script>


function toggleTheme(){


document.body.classList.toggle("dark-mode");



if(document.body.classList.contains("dark-mode")){


localStorage.setItem("staffTheme","dark");


}

else{


localStorage.setItem("staffTheme","light");


}



}







// Load saved theme


window.onload=function(){


let theme = localStorage.getItem("staffTheme");



if(theme==="dark"){


document.body.classList.add("dark-mode");


}



}



</script>





</body>

</html>