<?php
session_start();
require_once '../includes/db.php';

/* Admin Login Check */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Complaint ID not found.");
}

$complaint_id = intval($_GET['id']);

$sql = "SELECT *
        FROM complaint_timeline
        WHERE complaint_id=?
        ORDER BY changed_at ASC";

$stmt = mysqli_prepare($conn,$sql);
mysqli_stmt_bind_param($stmt,"i",$complaint_id);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Complaint Timeline | UniStay</title>

<link rel="stylesheet" href="../assets/css/theme.css">

<style>

body{
    margin:0;
    padding:25px;
    background:#f4f8f7;
    font-family:'Segoe UI',Tahoma,sans-serif;
    transition:.3s;
}

.container{
    max-width:1200px;
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
    margin-bottom:25px;
}

.header-right{
    display:flex;
    gap:10px;
}

h2{
    margin:0;
    color:#004d40;
}

.info-box{
    background:#e0f2f1;
    border-left:5px solid #00897b;
    padding:15px;
    border-radius:8px;
    margin-bottom:25px;
    color:#004d40;
    font-size:15px;
}

.back-btn,
.theme-toggle{

    padding:9px 16px;
    border:none;
    border-radius:7px;
    cursor:pointer;
    text-decoration:none;
    color:white;
    font-weight:bold;
    font-size:14px;

}

.back-btn{
    background:#6c757d;
}

.back-btn:hover{
    background:#495057;
}

.theme-toggle{
    background:#00897b;
}

.theme-toggle:hover{
    background:#004d40;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#00897b;
    color:white;
    padding:14px;
}

td{
    padding:13px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f1f5f9;
}

.status{
    display:inline-block;
    padding:7px 15px;
    border-radius:25px;
    color:white;
    font-size:13px;
    font-weight:bold;
}

.pending{
    background:#ffc107;
    color:black;
}

.progress{
    background:#17a2b8;
}

.assigned{
    background:#2563eb;
}

.solved{
    background:#28a745;
}

.reopened{
    background:#dc3545;
}

.escalated{
    background:#ff5722;
}

.empty{
    text-align:center;
    padding:40px;
    color:#777;
    font-size:18px;
}

/* Dark Mode */

.dark-mode{
    background:#020617;
    color:white;
}

.dark-mode .card{
    background:#0f172a;
}

.dark-mode h2{
    color:#7dd3fc;
}

.dark-mode .info-box{
    background:#1e293b;
    color:#e2e8f0;
    border-left-color:#7dd3fc;
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

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="header">

<h2>Complaint Timeline</h2>

<div class="header-right">

<a href="admin_complaint_panel.php" class="back-btn">
← Back
</a>

<button id="themeToggle" class="theme-toggle">
🌙 Dark Mode
</button>

</div>

</div>

<div class="info-box">

<strong>Complaint ID :</strong>
<?php echo $complaint_id; ?>

</div>

<table>

<tr>

<th>Timeline ID</th>
<th>Status</th>
<th>Activity</th>
<th>Date & Time</th>

</tr>

<?php

if(mysqli_num_rows($result)>0){

while($row=mysqli_fetch_assoc($result)){

echo "<tr>";

echo "<td>".$row['timeline_id']."</td>";

echo "<td>";

switch($row['status_change']){

case "Pending":
echo "<span class='status pending'>Pending</span>";
break;

case "Assigned":
echo "<span class='status assigned'>Assigned</span>";
break;

case "In Progress":
echo "<span class='status progress'>In Progress</span>";
break;

case "Solved":
echo "<span class='status solved'>Solved</span>";
break;

case "Escalated":
echo "<span class='status escalated'>Escalated</span>";
break;

case "Reopened":
echo "<span class='status reopened'>Reopened</span>";
break;

default:
echo "<span>".$row['status_change']."</span>";

}

echo "</td>";

echo "<td>".htmlspecialchars($row['note'])."</td>";

echo "<td>".date("d M Y h:i A",strtotime($row['changed_at']))."</td>";

echo "</tr>";

}

}else{

echo "<tr><td colspan='4' class='empty'>No Timeline Found</td></tr>";

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