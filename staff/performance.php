<?php
session_start();
require_once '../includes/db.php';

/* Only Staff Access */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

function h($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/*
====================================
GET STAFF INFORMATION
====================================
*/

$sql = "
SELECT
    sr.*,
    u.name,
    u.email,
    u.phone,
    u.availability
FROM staff_records sr
JOIN users u
ON sr.user_id = u.id
WHERE sr.user_id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$staff = mysqli_fetch_assoc($result);

if (!$staff) {
    die("Staff record not found");
}

$staff_record_id = $staff['id'];

/*
====================================
PERFORMANCE
====================================
*/

$sql = "
SELECT *
FROM staff_performance
WHERE staff_id = ?
ORDER BY id DESC
LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $staff_record_id);
mysqli_stmt_execute($stmt);

$performance = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

/*
====================================
COMPLAINTS
====================================
*/

$sql = "
SELECT
COUNT(*) AS total_complaints,
SUM(status='Solved') AS solved,
SUM(status='Pending') AS pending
FROM complaints
WHERE assigned_staff_id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$complaints = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

/*
====================================
CALCULATION
====================================
*/

/* Completed Task = Solved Complaint */

$completed_tasks = $complaints['solved'] ?? 0;

$total_tasks = $performance['total_tasks'] ?? $completed_tasks;

if($total_tasks < $completed_tasks){
    $total_tasks = $completed_tasks;
}

$total_complaints = $complaints['total_complaints'] ?? 0;
$solved_complaints = $complaints['solved'] ?? 0;

$task_rate = ($total_tasks > 0)
    ? round(($completed_tasks/$total_tasks)*100)
    : 0;

$complaint_rate = ($total_complaints > 0)
    ? round(($solved_complaints/$total_complaints)*100)
    : 0;

$score = $performance['performance_score'] ?? 0;


?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Staff Performance Dashboard</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;
}

body{
background:#eef2f7;
color:#1f2937;
transition:.3s;
}

.container{
max-width:1200px;
margin:auto;
padding:30px;
}

/* ================= HEADER ================= */

.header{
background:#fff;
padding:22px 30px;
border-radius:18px;
display:flex;
justify-content:space-between;
align-items:center;
box-shadow:0 10px 25px rgba(0,0,0,.08);
margin-bottom:25px;
}

.header h1{
font-size:28px;
color:#0f172a;
}

.header p{
margin-top:8px;
color:#64748b;
}

.header-right{
    display:flex;
    align-items:center;
    gap:15px;
    flex-wrap:wrap;
}

.badge{
background:#22c55e;
color:#fff;
padding:8px 18px;
border-radius:25px;
font-weight:bold;
}

.theme-switch{
display:flex;
align-items:center;
gap:10px;
}

#themeText{
font-weight:600;
}

.theme-btn{
width:45px;
height:45px;
border:none;
border-radius:50%;
background:#2563eb;
color:#fff;
cursor:pointer;
font-size:18px;
transition:.3s;
}

.theme-btn:hover{
transform:rotate(180deg);
}

/* ================= HERO ================= */

.hero{
background:linear-gradient(135deg,#2563eb,#0f766e);
color:#fff;
padding:35px;
border-radius:20px;
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:25px;
}

.hero h2{
font-size:34px;
}

.hero p{
margin-top:10px;
}



/* ================= CARDS ================= */

.cards{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:20px;
margin-bottom:25px;
}

.card{
background:#fff;
padding:25px;
border-radius:18px;
text-align:center;
box-shadow:0 8px 20px rgba(0,0,0,.08);
transition:.3s;
}

.card:hover{
transform:translateY(-5px);
}

.icon{
font-size:42px;
}

.card h3{
margin:15px 0;
color:#64748b;
}

.card h2{
font-size:36px;
color:#2563eb;
}

.section{
background:#fff;
padding:25px;
border-radius:18px;
margin-bottom:25px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
}

.section h2{
margin-bottom:15px;
}

.progress{
height:18px;
background:#dbe4ee;
border-radius:50px;
overflow:hidden;
}

.bar{
height:100%;
background:linear-gradient(90deg,#2563eb,#38bdf8);
width:0%;
transition:1.5s;
}

.progress-text{
margin-top:10px;
font-weight:bold;
color:#2563eb;
}

.feedback{
background:#fff;
padding:25px;
border-radius:18px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
margin-bottom:25px;
}

.feedback-box{
margin-top:15px;
padding:18px;
background:#f8fafc;
border-left:5px solid #2563eb;
border-radius:10px;
line-height:1.8;
}

.actions{
text-align:center;
margin-top:30px;
}

.btn{
display:inline-block;
padding:12px 28px;
background:#2563eb;
color:#fff;
text-decoration:none;
border-radius:10px;
font-weight:bold;
transition:.3s;
}

.btn:hover{
transform:translateY(-3px);
}

.footer{
margin-top:30px;
text-align:center;
color:#64748b;
}

body.dark{
background:#0f172a;
color:#fff;
}

body.dark .header,
body.dark .card,
body.dark .section,
body.dark .feedback{
background:#1e293b;
}

body.dark .feedback-box{
background:#334155;
color:#fff;
}

body.dark #themeText{
color:#fff;
}

@media(max-width:900px){

.hero{
flex-direction:column;
text-align:center;
gap:25px;
}

.header{
flex-direction:column;
gap:20px;
}

}

</style>

</head>

<body>

<div class="container">

<div class="header">

<div>

<h1>📊 Staff Performance Dashboard</h1>

<p>
Welcome Back,
<strong><?= h($staff['full_name']) ?></strong>
</p>

</div>

<div class="header-right">

    <span class="badge">
        <?= h($staff['availability']) ?>
    </span>

    <a href="dashboard.php" class="btn dashboard">
        🏠 Dashboard
    </a>

    <div class="theme-switch">
        <span id="themeText">Dark Mode</span>

        <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">
            🌙
        </button>
    </div>

</div>

</div>

<div class="hero">

<div>

<h2><?= h($staff['full_name']) ?></h2>

<p>Staff ID : <?= h($staff['staff_id']) ?></p>

<p>Department : <?= h($staff['department']) ?></p>

<p>Designation : <?= h($staff['designation']) ?></p>

</div>


</div>

<div class="cards">

<div class="card">
<div class="icon">📋</div>
<h3>Completed Tasks</h3>
<h2><?= $completed_tasks ?></h2>
</div>

<div class="card">
<div class="icon">⚠️</div>
<h3>Solved Complaints</h3>
<h2><?= $solved_complaints ?></h2>
</div>
<div class="section">

<h2>📋 Task Completion</h2>

<div class="progress">
    <div class="bar" style="width:<?= $task_rate ?>%"></div>
</div>

<div class="progress-text">
    <?= $completed_tasks ?> / <?= $total_tasks ?>
    (<?= $task_rate ?>%)
</div>

</div>


<div class="section">

<h2>⚠️ Complaint Resolution</h2>

<div class="progress">
    <div class="bar" style="width:<?= $complaint_rate ?>%"></div>
</div>

<div class="progress-text">
    <?= $solved_complaints ?> / <?= $total_complaints ?>
    (<?= $complaint_rate ?>%)
</div>

</div>


<div class="feedback">

<h2>💬 Admin Feedback</h2>

<div class="feedback-box">

<?= h($performance['feedback'] ?? 'No feedback available.') ?>

</div>

</div>


<div class="footer">

© <?= date("Y") ?> UniStay Hostel Management System

</div>

</div>

<script>

function toggleTheme(){

    document.body.classList.toggle("dark");

    const dark=document.body.classList.contains("dark");

    localStorage.setItem("staff_theme",dark);

    document.getElementById("themeText").innerHTML=
        dark ? "Dark Mode" : "Light Mode";

    document.getElementById("themeBtn").innerHTML=
        dark ? "🌙" : "☀️";

}

window.onload=function(){

    if(localStorage.getItem("staff_theme")=="true"){

        document.body.classList.add("dark");

    }

    const dark=document.body.classList.contains("dark");

    document.getElementById("themeText").innerHTML=
        dark ? "Dark Mode" : "Light Mode";

    document.getElementById("themeBtn").innerHTML=
        dark ? "🌙" : "☀️";

    document.querySelectorAll(".bar").forEach(function(bar){

        let width=bar.style.width;

        bar.style.width="0%";

        setTimeout(function(){

            bar.style.width=width;

        },300);

    });

};

</script>

</body>
</html>