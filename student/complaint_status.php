<?php
require_once 'resident_guard.php';
require_once '../includes/db.php';

$user_id = intval($_SESSION['user_id'] ?? 0);
$name = $_SESSION['name'] ?? 'Student';

$sql = "SELECT *
        FROM complaints
        WHERE student_id=?
        ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn,$sql);
mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<title>Complaint Status | UniStay</title>

<link rel="stylesheet" href="../assets/css/theme.css">

<style>

body{
    margin:0;
    padding:25px;
    background:#f4f8f7;
    font-family:'Segoe UI';
}

.container{
    max-width:1100px;
    margin:auto;
}

.card{
    background:#fff;
    border-radius:15px;
    padding:30px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
    border-top:6px solid #00897b;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.header-buttons{
    display:flex;
    align-items:center;
    gap:10px;
}

h2{
    margin:0;
    color:#004d40;
}

.student{
    background:#e0f2f1;
    padding:10px 15px;
    border-radius:8px;
    font-size:14px;
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

.pending{
    background:#ffc107;
    padding:5px 12px;
    border-radius:20px;
    color:#000;
    font-size:13px;
}

.progress{
    background:#17a2b8;
    padding:5px 12px;
    border-radius:20px;
    color:#fff;
    font-size:13px;
}

.solved{
    background:#28a745;
    padding:5px 12px;
    border-radius:20px;
    color:white;
    font-size:13px;
}

.reopened{
    background:#dc3545;
    padding:5px 12px;
    border-radius:20px;
    color:white;
    font-size:13px;
}

.btn{
    padding:8px 15px;
    border-radius:6px;
    text-decoration:none;
    background:#00897b;
    color:white;
    font-size:14px;
}

.btn:hover{
    background:#004d40;
}

.back-btn{
    padding:8px 15px;
    border-radius:6px;
    text-decoration:none;
    background:#00897b;
    color:white;
    font-size:14px;
    font-weight:bold;
}

.back-btn:hover{
    background:#004d40;
}

img{
    width:70px;
    height:70px;
    border-radius:8px;
}

.theme-toggle{
    padding:8px 15px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    background:#00897b;
    color:white;
    font-weight:bold;
}

.theme-toggle:hover{
    background:#004d40;
}

.dark-mode{
    background:#020617;
    color:white;
}

.dark-mode .card{
    background:#0f172a;
}

.dark-mode .student{
    background:#1e293b;
}

.dark-mode table{
    color:white;
}

.dark-mode td{
    border-color:#334155;
}

.dark-mode th{
    background:#00695c;
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

<h2>My Complaint Status</h2>

<div class="header-buttons">

<a href="dashboard.php" class="back-btn">
← Back
</a>

<button id="themeToggle" class="theme-toggle">
🌙 Dark Mode
</button>

</div>

</div>

<div class="student">

👤 <?php echo htmlspecialchars($name); ?>

</div>

<table>

<tr>

<th>ID</th>
<th>Category</th>
<th>Priority</th>
<th>Status</th>
<th>Progress</th>  
<th>Escalated</th>
<th>Photo</th>
<th>Timeline</th>
<th>Feedback</th>
</tr>
<?php

if(mysqli_num_rows($result)>0)
{

while($row=mysqli_fetch_assoc($result))
{

echo "<tr>";

echo "<td>".$row['complaint_id']."</td>";

echo "<td>".$row['category']."</td>";

echo "<td>".$row['priority']."</td>";

echo "<td>";

switch($row['status'])
{

case "Pending":

echo "<span class='pending'>Pending</span>";

break;

case "In Progress":

echo "<span class='progress'>In Progress</span>";

break;

case "Solved":

echo "<span class='solved'>Solved</span>";

break;

default:

echo "<span class='reopened'>Reopened</span>";

}

echo "</td>";

echo "<td>";

if($row['status']=="Pending")
{
    echo "⏳ 0%";
}
elseif($row['status']=="In Progress")
{
    echo "🔧 50%";
}
elseif($row['status']=="Solved")
{
    echo "✅ 100%";
}
elseif($row['status']=="Reopened")
{
    echo "🔄 30%";
}

echo "</td>";

echo "<td>";

echo ($row['escalated']==1) ? "⚠️ Yes" : "No";

echo "</td>";

echo "<td>";

if(!empty($row['photo_path']))
{

$photo = "../" . ltrim($row['photo_path'], '/');

echo "<a href='$photo' target='_blank'>
        <img src='$photo' width='70' height='70'>
      </a>";

}
else
{

echo "-";

}

echo "</td>";

echo "<td>";

echo "<a class='btn' href='complaint_timeline.php?id=".$row['complaint_id']."'>View</a>";

echo "</td>";

echo "<td>";

if($row['status']=="Solved")
{

echo "<a class='btn' href='complaint_feedback.php?id=".$row['complaint_id']."'>Rate</a>";

}
else
{

echo "-";

}

echo "</td>";

echo "</tr>";

}

}
else
{

echo "<tr>";

echo "<td colspan='8' style='padding:25px;font-size:16px;color:#777;'>No Complaint Found.</td>";

echo "</tr>";

}

?>

</table>

</div>

</div>

<script>

const btn=document.getElementById("themeToggle");

const body=document.body;

if(localStorage.getItem("theme")=="dark")
{
    body.classList.add("dark-mode");
    btn.innerHTML="☀️ Light Mode";
}

btn.onclick=function(){

    body.classList.toggle("dark-mode");

    if(body.classList.contains("dark-mode"))
    {
        localStorage.setItem("theme","dark");
        btn.innerHTML="☀️ Light Mode";
    }
    else
    {
        localStorage.setItem("theme","light");
        btn.innerHTML="🌙 Dark Mode";
    }

}

</script>

</body>

</html>