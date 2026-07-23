<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'Student';

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
    background:#f4f8f7;
    font-family:'Segoe UI',Tahoma,sans-serif;
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
    gap:10px;
}

h2{
    margin:0;
    color:#004d40;
}

.student{
    background:#e0f2f1;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
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
    padding:12px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

.status{
    padding:6px 14px;
    border-radius:20px;
    color:white;
    font-size:13px;
}

.pending{
    background:#ffc107;
    color:black;
}

.progress{
    background:#17a2b8;
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

.back-btn,
.theme-toggle{

    padding:10px 18px;
    border:none;
    border-radius:8px;
    background:#00897b;
    color:white;
    text-decoration:none;
    cursor:pointer;
    font-weight:bold;
}

.back-btn:hover,
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

.dark-mode td{

    border-color:#334155;

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

<h2>Complaint Timeline</h2>

<div class="header-buttons">

<a href="complaint_status.php" class="back-btn">
← Back
</a>

<button id="themeToggle" class="theme-toggle">
🌙 Dark Mode
</button>

</div>

</div>

<div class="student">

👤 <strong><?php echo htmlspecialchars($user_name); ?></strong>

&nbsp;&nbsp;|&nbsp;&nbsp;

Complaint ID :
<strong><?php echo $complaint_id; ?></strong>

</div>

<table>

<tr>

<th>Timeline ID</th>

<th>Status</th>

<th>Note</th>

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

case "In Progress":
echo "<span class='status progress'>In Progress</span>";
break;

case "Solved":
echo "<span class='status solved'>Solved</span>";
break;

case "Reopened":
echo "<span class='status reopened'>Reopened</span>";
break;

case "Escalated":
echo "<span class='status escalated'>Escalated</span>";
break;

default:
echo htmlspecialchars($row['status_change']);

}

echo "</td>";

echo "<td>".htmlspecialchars($row['note'])."</td>";

echo "<td>".date("d M Y h:i A",strtotime($row['changed_at']))."</td>";

echo "</tr>";

}

}else{

echo "<tr><td colspan='4'>No Timeline Found.</td></tr>";

}

?>

</table>

</div>

</div>

<script>

const btn=document.getElementById("themeToggle");
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

    }else{

        localStorage.setItem("theme","light");
        btn.innerHTML="🌙 Dark Mode";

    }

};

</script>

</body>
</html>