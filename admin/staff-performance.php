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



/*
==============================
GET STAFF PERFORMANCE DATA
==============================
*/


$sql = "

SELECT

sr.id AS staff_record_id,

sr.user_id,

sr.staff_id,

sr.full_name,

sr.department,

sr.designation,


COALESCE(u.availability,'Available') AS availability,


COALESCE(sp.task_completed,0) AS task_completed,

COALESCE(sp.task_pending,0) AS task_pending,

COALESCE(sp.total_tasks,0) AS total_tasks,

COALESCE(sp.rating,5) AS rating,

COALESCE(sp.performance_status,'Good') AS performance_status,

COALESCE(sp.feedback,'') AS feedback,


COALESCE(sp.complaints_resolved,0) AS solved_complaints



FROM staff_records sr



LEFT JOIN users u

ON sr.user_id=u.id



LEFT JOIN staff_performance sp

ON sr.id=sp.staff_id



ORDER BY sr.id DESC


";



$result=mysqli_query($conn,$sql);



?>



<!DOCTYPE html>

<html>

<head>


<title>
Staff Performance - UniStay
</title>



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





.theme-btn{


background:#1e293b;

color:white;

border:1px solid #475569;

padding:10px 15px;

border-radius:8px;

cursor:pointer;

margin-right:10px;


}




.staff-box{


background:#0f172a;

border:1px solid #334155;


border-left:5px solid #38bdf8;


padding:25px;


border-radius:15px;


margin-bottom:25px;


}



.staff-box h2{


color:#7dd3fc;


}



.staff-box p{


color:#cbd5e1;

margin-top:8px;


}




.badge{


background:#bbf7d0;

color:#166534;

padding:7px 15px;

border-radius:20px;

font-weight:bold;

display:inline-block;

margin-top:10px;


}




.cards{


display:grid;

grid-template-columns:repeat(4,1fr);

gap:15px;

margin-top:25px;


}




.card{


background:#111827;

border:1px solid #334155;

padding:20px;

border-radius:12px;

text-align:center;


}



.card h3{


color:#38bdf8;

font-size:15px;


}




.card h2{


color:#14b8a6;

font-size:30px;

margin-top:10px;


}




.progress{


height:15px;

background:#334155;

border-radius:20px;

overflow:hidden;

margin-top:10px;


}




.bar{


height:100%;

background:#0ea5e9;


}




.feedback{


margin-top:25px;


background:#111827;


border:1px solid #334155;


padding:25px;


border-radius:15px;


}


.feedback h3{

color:#38bdf8;

margin-bottom:15px;

}




label{


display:block;

margin-top:15px;

color:#cbd5e1;

font-weight:bold;


}




select,
textarea{


width:100%;

padding:12px;

margin-top:8px;


border-radius:10px;


border:1px solid #475569;


background:#020617;


color:white;


}



textarea{


height:100px;

resize:none;


}




button{


background:#14b8a6;


color:white;


border:none;


padding:12px 25px;


border-radius:10px;


cursor:pointer;


font-weight:bold;


}






/* LIGHT MODE */


.light{


background:#f1f5f9;

color:#111827;


}



.light .header,
.light .staff-box{


background:white;

}



.light .card,
.light .feedback{


background:#f8fafc;


}



.light select,
.light textarea{


background:white;

color:#111827;


}






@media(max-width:900px){


.cards{


grid-template-columns:repeat(2,1fr);


}


}





@media(max-width:600px){


.cards{


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

📊 Staff Performance Management

</h1>


<p>

Admin can monitor staff activities

</p>


</div>



<div>


<button class="theme-btn" onclick="theme()">

☀️ Light Mode

</button>



<a href="dashboard.php" class="btn">

🏠 Dashboard

</a>



</div>


</div>






<?php while($staff=mysqli_fetch_assoc($result)){



/*
=========================
AUTO COMPLAINT COUNT
=========================
*/



$cid=$staff['user_id'];



$csql="

SELECT

COUNT(*) total,

SUM(status='Solved') solved


FROM complaints


WHERE assigned_staff_id=?


";



$stmt=mysqli_prepare($conn,$csql);


mysqli_stmt_bind_param(
$stmt,
"i",
$cid
);


mysqli_stmt_execute($stmt);



$cresult=mysqli_stmt_get_result($stmt);



$complaint=mysqli_fetch_assoc($cresult);



$total_complaint=$complaint['total'] ?? 0;


$solved_complaint=$complaint['solved'] ?? 0;




$task_percent=0;


if($staff['total_tasks']>0){


$task_percent=round(

($staff['task_completed']/$staff['total_tasks'])*100

);


}




$complaint_percent=0;


if($total_complaint>0){


$complaint_percent=round(

($solved_complaint/$total_complaint)*100

);


}



?>






<div class="staff-box">



<h2>

👤 <?=h($staff['full_name'])?>

</h2>




<p>

Staff ID :

<?=h($staff['staff_id'])?>

</p>




<p>

Department :

<?=h($staff['department'])?>

</p>




<p>

Designation :

<?=h($staff['designation'])?>

</p>




<span class="badge">

<?=h($staff['availability'])?>

</span>







<div class="cards">





<div class="card">


<h3>

📋 Completed Task

</h3>


<h2>

<?=$staff['task_completed']?>

</h2>


</div>







<div class="card">


<h3>

⚠️ Solved Complaint

</h3>


<h2>

<?=$solved_complaint?>

</h2>


</div>







<div class="card">


<h3>

📈 Task Completion

</h3>


<h2>

<?=$task_percent?>%

</h2>


</div>







<div class="card">


<h3>

⭐ Rating

</h3>


<h2>

<?=$staff['rating']?>/5

</h2>


</div>





</div>






<h3 style="margin-top:25px;color:#38bdf8;">

📋 Task Progress

</h3>



<div class="progress">


<div class="bar"

style="width:<?=$task_percent?>%">

</div>


</div>




<p>

<?=$staff['task_completed']?> /

<?=$staff['total_tasks']?>

Completed

</p>






<h3 style="margin-top:25px;color:#38bdf8;">

⚠️ Complaint Progress

</h3>




<div class="progress">


<div class="bar"

style="width:<?=$complaint_percent?>%">

</div>


</div>





<p>

<?=$solved_complaint?> /

<?=$total_complaint?>

Solved

</p>




<div class="feedback">


<h3>

💬 Admin Feedback

</h3>




<form method="POST" action="save_feedback.php">





<input type="hidden"

name="staff_id"

value="<?=$staff['staff_record_id']?>">





<label>

Performance Status

</label>



<select name="performance_status">



<option value="Excellent">

Excellent

</option>



<option value="Good" selected>

Good

</option>



<option value="Average">

Average

</option>



<option value="Poor">

Poor

</option>



</select>







<label>

Rating

</label>



<select name="rating">



<?php


for($i=5;$i>=1;$i--){


?>



<option value="<?=$i?>">


<?=$i?> ⭐


</option>



<?php

}


?>



</select>







<label>

Feedback

</label>




<textarea 

name="feedback"

placeholder="Write feedback..."><?=h($staff['feedback'])?></textarea>







<button type="submit">


💾 Save Feedback


</button>





</form>



</div>





</div>





<?php } ?>





</div>








<script>



function theme(){



document.body.classList.toggle("light");




if(document.body.classList.contains("light")){



localStorage.setItem(

"performanceTheme",

"light"

);



}

else{



localStorage.setItem(

"performanceTheme",

"dark"

);



}



}





if(localStorage.getItem("performanceTheme")=="light"){



document.body.classList.add("light");



}



</script>







</body>


</html>



