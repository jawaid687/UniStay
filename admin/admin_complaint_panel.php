<?php

require_once '../includes/db.php';


/* =====================================
   AUTO ESCALATION
===================================== */


$sql = "
SELECT complaint_id, priority, status, created_at
FROM complaints
WHERE status != 'Solved'
AND escalated = 0
";


$result = mysqli_query($conn,$sql);



while($row = mysqli_fetch_assoc($result))
{


    $complaint_id = $row['complaint_id'];
    $priority = $row['priority'];


    $created_time = strtotime($row['created_at']);

    $current_time = time();


    $hours = ($current_time - $created_time) / 3600;

    $days = ($current_time - $created_time) / 86400;



    $newPriority = $priority;

    $escalate = false;



    // SLA RULES

    if($priority == "High" && $hours >= 24)
    {

        $escalate = true;

    }


    elseif($priority == "Medium" && $days >= 3)
    {

        $newPriority = "High";

        $escalate = true;

    }


    elseif($priority == "Low" && $days >= 7)
    {

        $newPriority = "Medium";

        $escalate = true;

    }





    if($escalate)
    {


        // Update complaint

        $update = mysqli_prepare(
            $conn,

            "UPDATE complaints

             SET priority=?,
                 escalated=1

             WHERE complaint_id=?"

        );


        mysqli_stmt_bind_param(
            $update,

            "si",

            $newPriority,

            $complaint_id

        );


        mysqli_stmt_execute($update);





        // Add timeline

        $note = "Complaint exceeded SLA. Priority changed to ".$newPriority;



        $timeline = mysqli_prepare(
            $conn,

            "INSERT INTO complaint_timeline

            (complaint_id,status_change,note,changed_at)

            VALUES(?, 'Escalated', ?, NOW())"

        );



        mysqli_stmt_bind_param(
            $timeline,

            "is",

            $complaint_id,

            $note

        );



        mysqli_stmt_execute($timeline);



    }



}


/* =====================================
   END AUTO ESCALATION
===================================== */


?>


<?php
require_once '../includes/db.php';

$sql = "
SELECT
c.*,

sr.full_name,

u.name AS staff_name,

h.hall_name,

r.floor_number,
r.room_number,

cr.rating,
cr.comment

FROM complaints c

LEFT JOIN users u
ON c.assigned_staff_id = u.id

LEFT JOIN student_records sr
ON c.student_id = sr.user_id

LEFT JOIN room_assignments ra
ON sr.id = ra.student_record_id
AND ra.assignment_status='active'

LEFT JOIN rooms r
ON ra.room_id = r.room_id

LEFT JOIN halls h
ON r.hall_id = h.hall_id

LEFT JOIN complaint_rating cr
ON c.complaint_id = cr.complaint_id

ORDER BY c.created_at DESC
";

$result = mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Admin Complaint Panel | UniStay</title>

<link rel="stylesheet" href="../assets/css/theme.css">

<style>

body{
    margin:0;
    padding:25px;
    background:#f4f8f7;
    font-family:'Segoe UI',sans-serif;
    transition:.3s;
}

.container{
    max-width:1300px;
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

.header-right{
    display:flex;
    gap:10px;
}

h2{
    margin:0;
    color:#004d40;
}

.theme-toggle,
.back-btn{

    padding:8px 15px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    color:#fff;
    text-decoration:none;
    font-size:14px;

}

.theme-toggle{
    background:#00897b;
}

.theme-toggle:hover{
    background:#004d40;
}

.back-btn{
    background:#6c757d;
}

.back-btn:hover{
    background:#495057;
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

.high{
    color:red;
    font-weight:bold;
}

.medium{
    color:#ff9800;
    font-weight:bold;
}

.low{
    color:green;
    font-weight:bold;
}

.flag{
    color:red;
    font-weight:bold;
}

.btn{
    text-decoration:none;
    background:#00897b;
    color:white;
    padding:7px 12px;
    border-radius:6px;
}

.btn:hover{
    background:#004d40;
}

img{
    width:70px;
    height:70px;
    border-radius:8px;
    object-fit:cover;
}

.dark-mode{
    background:#020617;
    color:white;
}

.dark-mode .card{
    background:#0f172a;
}

.dark-mode td{
    border-color:#334155;
}

.dark-mode table{
    color:white;
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

<h2>Admin Complaint Management Panel</h2>

<div class="header-right">

<a href="dashboard.php" class="back-btn">
← Back
</a>

<button id="themeToggle" class="theme-toggle">
🌙 Dark Mode
</button>

</div>

</div>

<table>

<tr>

<th>ID</th>
<th>Hall</th>
<th>Floor</th>
<th>Room</th>
<th>Category</th>
<th>Priority</th>
<th>Status</th>
<th>Solved By</th>
<th>Rating</th>
<th>Feedback</th>
<th>Escalated</th>
<th>Cluster</th>
<th>Photo</th>
<th>Assign Staff</th>
<th>Timeline</th>

</tr>

<?php

if(mysqli_num_rows($result)>0)
{

while($row=mysqli_fetch_assoc($result))
{

echo "<tr>";

echo "<td>".$row['complaint_id']."</td>";

echo "<td>".$row['hall_name']."</td>";

echo "<td>".$row['floor_number']."</td>";

echo "<td>".$row['room_number']."</td>";

echo "<td>".$row['category']."</td>";

/* Priority */
echo "<td>";

if($row['priority']=="High")
    echo "<span class='high'>High</span>";
elseif($row['priority']=="Medium")
    echo "<span class='medium'>Medium</span>";
else
    echo "<span class='low'>Low</span>";

echo "</td>";

/* Status */
echo "<td>".$row['status']."</td>";

/* Solved By */
echo "<td>";

if($row['status']=="Solved")
{
    echo $row['staff_name'];
}
else
{
    echo "-";
}

echo "</td>";

/* Rating */
echo "<td>";

echo empty($row['rating'])
? "-"
: $row['rating']." ⭐";

echo "</td>";

/* Feedback */
echo "<td>";

echo empty($row['comment'])
? "-"
: htmlspecialchars($row['comment']);

echo "</td>";

/* Escalated */
echo "<td>";

echo ($row['escalated']==1)
? "<span class='flag'>⚠ YES</span>"
: "NO";

echo "</td>";

/* Cluster */
echo "<td>";

echo empty($row['cluster_id'])
? "-"
: $row['cluster_id'];

echo "</td>";

/* Photo */
echo "<td>";

if(!empty($row['photo_path']) && file_exists("../".$row['photo_path']))
{
    echo "<a href='../".$row['photo_path']."' target='_blank'>
            <img src='../".$row['photo_path']."' alt='Photo'>
          </a>";
}
else
{
    echo "No Photo";
}

echo "</td>";




echo "<td>";

if(empty($row['assigned_staff_id']))
{
    echo "<a class='btn' href='assign_staff.php?id=".$row['complaint_id']."'>Assign</a>";
}
else
{
    echo "<span style='color:green;font-weight:bold;'>Assigned</span>";
}

echo "</td>";

echo "<td>";

echo "<a class='btn' href='complaint_timeline.php?id=".$row['complaint_id']."'>View</a>";

echo "</td>";

echo "</tr>";

}

}
else
{

echo "<tr>";
echo "<td colspan='11'>No Complaints Found.</td>";
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