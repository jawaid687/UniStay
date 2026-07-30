<?php

session_start();

require_once '../includes/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {

    header("Location: ../auth/login.php");
    exit();

}



// Fetch notices

$sql = "

SELECT * FROM admin_notices

ORDER BY

CASE

WHEN priority='Emergency' THEN 1

WHEN priority='High' THEN 2

WHEN priority='Medium' THEN 3

WHEN priority='Low' THEN 4

END,

created_at DESC

";


$result = $conn->query($sql);


?>


<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Admin Notices | UniStay</title>


<link rel="stylesheet" href="../assets/css/theme.css">


<style>


*{

box-sizing:border-box;

}



body{

margin:0;

padding:25px;

font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;

background:#f1f5f9;

}



.container{

max-width:1100px;

margin:40px auto;

}



.card{

background:white;

padding:35px;

border-radius:18px;

box-shadow:0 10px 25px rgba(0,0,0,.08);

border-top:6px solid #00897b;

}



.header{

display:flex;

justify-content:space-between;

align-items:center;

border-bottom:1px solid #ddd;

padding-bottom:15px;

margin-bottom:25px;

}



.header h1{

margin:0;

color:#00897b;

}



.theme-btn{

padding:10px 15px;

border-radius:8px;

border:1px solid #ccc;

background:white;

cursor:pointer;

}



.notice{

background:#f8fafc;

border:1px solid #d9eeee;

border-radius:14px;

padding:22px;

margin-bottom:20px;

}



.notice-head{

display:flex;

justify-content:space-between;

align-items:center;

}



.notice h3{

color:#004d40;

margin:0;

}



.message{

margin-top:15px;

line-height:1.7;

color:#334155;

}



.date{

font-size:14px;

color:#64748b;

}



.priority{

padding:7px 15px;

border-radius:20px;

font-size:13px;

font-weight:bold;

}



.Emergency{

background:#fee2e2;

color:#991b1b;

}



.High{

background:#fef3c7;

color:#92400e;

}



.Medium{

background:#dbeafe;

color:#1d4ed8;

}



.Low{

background:#dcfce7;

color:#166534;

}



.btn{

display:inline-block;

padding:12px 22px;

background:#64748b;

color:white;

text-decoration:none;

border-radius:8px;

font-weight:bold;

}




/* DARK MODE */


body.dark-mode{

background:#020617;

}



body.dark-mode .card{

background:#0f172a;

color:white;

}



body.dark-mode .notice{

background:#1e293b;

border-color:#334155;

}



body.dark-mode .header{

border-color:#334155;

}



body.dark-mode .header h1{

color:#7dd3fc;

}



body.dark-mode .notice h3{

color:#7dd3fc;

}



body.dark-mode .message{

color:#e5e7eb;

}



body.dark-mode .date{

color:#cbd5e1;

}



body.dark-mode .theme-btn{

background:#1e293b;

color:white;

}



@media(max-width:700px){


.header{

flex-direction:column;

gap:15px;

}


.notice-head{

flex-direction:column;

align-items:flex-start;

gap:10px;

}


}


</style>


</head>



<body>



<div class="container">


<div class="card">


<div class="header">


<h1>
📢 Admin Notices
</h1>



<button id="themeToggle" class="theme-btn">

🌙 Dark Mode

</button>



</div>





<?php


if($result && $result->num_rows > 0){


while($row=$result->fetch_assoc()){


?>



<div class="notice">



<div class="notice-head">


<h3>

<?= htmlspecialchars($row['title']); ?>

</h3>



<span class="priority <?= $row['priority']; ?>">

<?= htmlspecialchars($row['priority']); ?>

</span>



</div>




<p class="message">

<?= nl2br(htmlspecialchars($row['message'])); ?>

</p>




<hr>



<div class="date">

📅 Posted:

<?= date("d M Y, h:i A",strtotime($row['created_at'])); ?>

</div>



</div>



<?php


}


}

else{


?>


<div class="notice">

<h3 style="text-align:center">

No notices available

</h3>

</div>


<?php

}


?>





<a href="dashboard.php" class="btn">

← Back to Dashboard

</a>



</div>


</div>





<script>


const btn=document.getElementById("themeToggle");



if(localStorage.getItem("theme")=="dark"){

document.body.classList.add("dark-mode");

}



function updateTheme(){

if(document.body.classList.contains("dark-mode")){

btn.innerHTML="☀️ Light Mode";

}

else{

btn.innerHTML="🌙 Dark Mode";

}

}



updateTheme();



btn.onclick=function(){


document.body.classList.toggle("dark-mode");


localStorage.setItem(

"theme",

document.body.classList.contains("dark-mode")

?"dark"

:"light"

);


updateTheme();


};



</script>


</body>

</html>