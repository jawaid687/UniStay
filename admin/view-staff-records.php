<?php

session_start();

require_once "../includes/db.php";


if(!isset($_SESSION['user_id']) || $_SESSION['role']!='admin'){
    header("Location: ../auth/login.php");
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Staff View | Admin</title>


<style>

*{
    box-sizing:border-box;
}


body{

    margin:0;
    padding:25px;
    background:#f4f8f7;
    font-family:'Segoe UI',sans-serif;
    transition:.3s;

}


.container{

    max-width:1200px;
    margin:auto;

}


/* Header */

.header{

    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
    flex-wrap:wrap;

}


.header h1{

    color:#004d40;
    font-size:32px;

}



.header-buttons{

    display:flex;
    gap:10px;

}



button,
.back{

    padding:10px 18px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    text-decoration:none;
    color:white;
    font-weight:bold;

}



.back{

    background:#64748b;

}


.theme{

    background:#00897b;

}



/* Card Grid */


.grid{

    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(270px,1fr));
    gap:25px;

}



/* Card */


.card{

    background:white;
    padding:25px;
    border-radius:18px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
    border-top:6px solid #00897b;
    display:flex;
    flex-direction:column;
    transition:.3s;

}



.card:hover{

    transform:translateY(-8px);

}



.card h3{

    color:#004d40;
    font-size:22px;

}



.card p{

    color:#64748b;
    line-height:1.6;
    flex:1;

}



/* Badge */


.badge{

    background:#fde68a;
    color:#78350f;
    padding:6px 12px;
    border-radius:20px;
    width:max-content;
    font-size:13px;
    font-weight:bold;

}



/* Button */


.card a{

    background:#00897b;
    color:white;
    padding:12px;
    border-radius:8px;
    text-align:center;
    text-decoration:none;
    font-weight:bold;

}



/* Different colors */


.blue{

    border-top-color:#2563eb;

}


.blue a{

    background:#2563eb;

}




.purple{

    border-top-color:#7c3aed;

}


.purple a{

    background:#7c3aed;

}




.orange{

    border-top-color:#ea580c;

}


.orange a{

    background:#ea580c;

}





.green{

    border-top-color:#16a34a;

}


.green a{

    background:#16a34a;

}




/* Dark Mode */


.dark-mode{

    background:#020617;
    color:white;

}



.dark-mode .card{

    background:#0f172a;

}



.dark-mode h1{

    color:#7dd3fc;

}



.dark-mode .card h3{

    color:#7dd3fc;

}



.dark-mode .card p{

    color:#cbd5e1;

}




</style>


</head>



<body>



<div class="container">



<div class="header">


<h1>
👥 Staff Management
</h1>



<div class="header-buttons">


<a href="dashboard.php" class="back">
← Dashboard
</a>


<button class="theme" id="themeBtn">
🌙 Dark Mode
</button>


</div>


</div>





<div class="grid">



<!-- Profile -->

<div class="card">

<h3>
👤 Staff Profile
</h3>


<span class="badge">
Information
</span>


<p>
View staff personal details,
contact information and job profile.
</p>


<a href="staff-profile.php">
View Profile
</a>

</div>





<!-- Availability -->

<div class="card blue">

<h3>
🟢 Availability
</h3>


<span class="badge">
Duty Status
</span>


<p>
Check current availability,
working time and duty status.
</p>


<a href="staff-availability.php">
View Availability
</a>

</div>





<!-- Performance -->


<div class="card purple">

<h3>
📊 Performance
</h3>


<span class="badge">
Statistics
</span>


<p>
View completed tasks,
rating, attendance and performance.
</p>


<a href="staff-performance.php">
View Performance
</a>


</div>





<!-- Floor Schedule -->


<div class="card orange">


<h3>
🏢 Floor Schedule
</h3>


<span class="badge">
Assigned Duty
</span>


<p>
Check assigned floor,
room range, shift and supervisor.
</p>


<a href="staff-floor.php">
View Schedule
</a>


</div>







<!-- Messages -->


<div class="card blue">


<h3>
💬 Messages
</h3>


<span class="badge">
Communication
</span>


<p>
View messages sent by staff
and send replies.
</p>


<a href="staff-messages.php">
View Messages
</a>


</div>








<!-- Leave -->


<div class="card purple">


<h3>
📝 Leave Application
</h3>


<span class="badge">
Requests
</span>


<p>
Check staff leave requests,
approve or reject applications.
</p>


<a href="staff-leave.php">
View Leave
</a>


</div>





</div>


</div>





<script>


const btn=document.getElementById("themeBtn");

const body=document.body;



if(localStorage.getItem("theme")=="dark"){

body.classList.add("dark-mode");

btn.innerHTML="☀️ Light Mode";

}



btn.onclick=function(){


body.classList.toggle("dark-mode");


if(body.classList.contains("dark-mode")){


localStorage.setItem("theme","dark");

btn.innerHTML="☀️ Light Mode";


}

else{


localStorage.setItem("theme","light");

btn.innerHTML="🌙 Dark Mode";


}


}



</script>



</body>

</html>