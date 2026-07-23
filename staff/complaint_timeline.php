<?php

session_start();

require_once '../includes/db.php';


if(!isset($_SESSION['user_id']))
{
    header("Location: ../auth/login.php");
    exit();
}


function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}



if(!isset($_GET['id']))
{
    die("Complaint ID not found");
}


$complaint_id = intval($_GET['id']);



$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM complaint_timeline
     WHERE complaint_id = ?
     ORDER BY timeline_id ASC"
);


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $complaint_id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Complaint Timeline | UniStay</title>


<style>


body{

    margin:0;
    padding:25px;
    font-family:'Segoe UI',Arial;
    background:linear-gradient(135deg,#e0f7fa,#f1f8e9);

}



.container{

    max-width:900px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,.15);

}



.header{

    display:flex;
    justify-content:space-between;
    align-items:center;
    border-bottom:3px solid #00897b;
    margin-bottom:25px;

}



h1{

    color:#004d40;

}



.theme-btn{

    padding:10px 15px;
    background:#334155;
    color:white;
    border:none;
    border-radius:7px;
    cursor:pointer;

}



.card{

    background:#e0f2f1;
    border-left:6px solid #00897b;
    padding:18px;
    margin-bottom:20px;
    border-radius:10px;

}



.status{

    font-size:20px;
    font-weight:bold;
    color:#00695c;

}



.note{

    margin-top:10px;
    font-size:16px;

}



.date{

    margin-top:10px;
    color:#64748b;

}



.back{

    display:inline-block;
    margin-top:20px;
    background:#00897b;
    color:white;
    padding:10px 15px;
    border-radius:7px;
    text-decoration:none;

}



/* Dark Mode */

body.dark-mode{

    background:#020617;
    color:white;

}



body.dark-mode .container{

    background:#0f172a;

}



body.dark-mode h1{

    color:#7dd3fc;

}



body.dark-mode .card{

    background:#1e293b;
    border-color:#38bdf8;

}



body.dark-mode .status{

    color:#38bdf8;

}



</style>


</head>



<body>


<div class="container">


<div class="header">

<h1>
Complaint Timeline
</h1>


<button id="themeToggle" class="theme-btn">
🌙 Dark Mode
</button>


</div>



<?php


if(mysqli_num_rows($result)>0)

{


while($row=mysqli_fetch_assoc($result))

{


?>


<div class="card">


<div class="status">

<?=h($row['status_change'])?>

</div>



<div class="note">

<?=h($row['note'])?>

</div>



<div class="date">

Updated: <?=h($row['changed_at'])?>

</div>



</div>



<?php


}


}

else

{


echo "

<div class='card'>
No timeline found.
</div>

";


}


?>


<a href="staff_complaint_view.php" class="back">

← Back to Complaints

</a>


</div>




<script>


const btn=document.getElementById("themeToggle");


if(localStorage.getItem("theme")=="dark")
{
    document.body.classList.add("dark-mode");
    btn.innerHTML="☀️ Light Mode";
}



btn.onclick=function(){

document.body.classList.toggle("dark-mode");


if(document.body.classList.contains("dark-mode"))
{
    localStorage.setItem("theme","dark");
    btn.innerHTML="☀️ Light Mode";
}

else
{
    localStorage.setItem("theme","light");
    btn.innerHTML="🌙 Dark Mode";
}


};


</script>



</body>

</html>