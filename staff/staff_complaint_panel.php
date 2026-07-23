<?php

session_start();

require_once '../includes/db.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff')
{
    header("Location: ../auth/login.php");
    exit();
}


$staff_id = intval($_SESSION['user_id']);


function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}



/* Fetch complaints */

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM complaints
     WHERE assigned_staff_id = ?
     ORDER BY created_at DESC"
);


mysqli_stmt_bind_param($stmt,"i",$staff_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Staff Complaint Panel | UniStay</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<style>


body{

    margin:0;

    padding:25px;

    font-family:'Segoe UI',Arial,sans-serif;

    background:linear-gradient(
        135deg,
        #e0f7fa,
        #f1f8e9
    );

    color:#1f2937;

    transition:.3s;

}


/* Main Container */

.container{

    max-width:1200px;

    margin:auto;

    background:white;

    padding:30px;

    border-radius:15px;

    box-shadow:0 10px 25px rgba(0,0,0,.15);

}



/* Header */

.header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    border-bottom:3px solid #00897b;

    padding-bottom:15px;

    margin-bottom:25px;

}



.header h1{

    color:#004d40;

}



/* Buttons */


.btn,
.theme-btn{

    text-decoration:none;

    border:none;

    cursor:pointer;

    padding:10px 15px;

    border-radius:7px;

    background:#00897b;

    color:white;

    font-weight:bold;

}



.theme-btn{

    background:#334155;

}



/* Table */


table{

    width:100%;

    border-collapse:collapse;

    background:white;

    overflow:hidden;

    border-radius:10px;

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

    background:#e0f2f1;

}



/* Image */


img{

    width:80px;

    height:80px;

    object-fit:cover;

    border-radius:8px;

    border:2px solid #00897b;

}



/* Links */


.action{

    text-decoration:none;

    color:#00897b;

    font-weight:bold;

}



.action:hover{

    color:#004d40;

}



/* No Data */

.no-data{

    padding:20px;

    text-align:center;

    color:#64748b;

}




/* =====================
      DARK MODE
===================== */


body.dark-mode{

    background:#020617;

    color:#e5e7eb;

}



body.dark-mode .container{

    background:#0f172a;

    color:#e5e7eb;

}



body.dark-mode .header{

    border-color:#38bdf8;

}



body.dark-mode .header h1{

    color:#7dd3fc;

}



body.dark-mode table{

    background:#1e293b;

}



body.dark-mode th{

    background:#0369a1;

}



body.dark-mode td{

    color:#e5e7eb;

    border-color:#475569;

}



body.dark-mode tr:hover{

    background:#334155;

}



body.dark-mode .action{

    color:#38bdf8;

}



body.dark-mode .btn{

    background:#0369a1;

}





@media(max-width:900px){


table{

    font-size:13px;

}


.container{

    padding:20px;

}


}



</style>


</head>



<body>


<div class="container">



<div class="header">


<h1>
📋 Staff Complaint Panel
</h1>



<div>


<button id="themeToggle" class="theme-btn">
🌙 Dark Mode
</button>


<a href="dashboard.php" class="btn">
Dashboard
</a>


</div>


</div>




<table>


<tr>

<th>ID</th>

<th>Student</th>

<th>Category</th>

<th>Description</th>

<th>Priority</th>

<th>Status</th>

<th>Cluster</th>

<th>Photo</th>

<th>Timeline</th>

<th>Update</th>


</tr>




<?php


if(mysqli_num_rows($result)>0)

{


while($row=mysqli_fetch_assoc($result))

{


?>

<tr>


<td>

<?=h($row['complaint_id'])?>

</td>



<td>

<?=h($row['student_id'])?>

</td>



<td>

<?=h($row['category'])?>

</td>



<td>

<?=h($row['description'])?>

</td>



<td>

<?=h($row['priority'])?>

</td>



<td>

<?=h($row['status'])?>

</td>




<td>


<?=empty($row['cluster_id']) ? "-" : h($row['cluster_id']);?>


</td>




<td>


<?php

if(!empty($row['photo_path']))
{
    $photo = "../" . $row['photo_path'];

    echo "DB Path: " . $row['photo_path'] . "<br>";
    echo "Full Path: " . $photo . "<br>";

    if(file_exists($photo))
    {
        echo "✅ File Found<br>";
    }
    else
    {
        echo "❌ File Not Found<br>";
    }

    echo "<img src='$photo' width='80' height='80'>";
}
else
{
    echo "No Photo";
}



?>


</td>



<td>


<a class="action"
href="complaint_timeline.php?id=<?=$row['complaint_id']?>">

View

</a>


</td>



<td>


<a class="action"
href="update_status.php?id=<?=$row['complaint_id']?>">

Update

</a>


</td>



</tr>


<?php


}

}

else

{


?>


<tr>

<td colspan="10" class="no-data">

No Assigned Complaints Found

</td>

</tr>


<?php

}


?>


</table>



</div>




<script>


const themeBtn=document.getElementById("themeToggle");



if(localStorage.getItem("theme")=="dark")
{

document.body.classList.add("dark-mode");

themeBtn.innerHTML="☀️ Light Mode";

}




themeBtn.onclick=function()

{


document.body.classList.toggle("dark-mode");



if(document.body.classList.contains("dark-mode"))

{

localStorage.setItem("theme","dark");

themeBtn.innerHTML="☀️ Light Mode";

}

else

{

localStorage.setItem("theme","light");

themeBtn.innerHTML="🌙 Dark Mode";

}


};



</script>



</body>

</html>