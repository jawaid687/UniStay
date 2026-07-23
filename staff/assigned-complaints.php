<?php
session_start();

require_once '../includes/db.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff')
{
    header("Location: ../auth/login.php");
    exit();
}


$staff_id = $_SESSION['user_id'];


// Status Update
// Status Update

if(isset($_POST['update_status']))
{

    $complaint_id = intval($_POST['complaint_id']);

    $status = $_POST['status'];

    $note = !empty($_POST['note']) 
        ? $_POST['note'] 
        : "Status updated by staff";



    // Get cluster id

    $cluster_query = mysqli_prepare(
        $conn,
        "SELECT cluster_id 
         FROM complaints 
         WHERE complaint_id=?"
    );


    mysqli_stmt_bind_param(
        $cluster_query,
        "i",
        $complaint_id
    );


    mysqli_stmt_execute($cluster_query);


    $cluster_result = mysqli_stmt_get_result($cluster_query);


    $cluster = mysqli_fetch_assoc($cluster_result);


    $cluster_id = $cluster['cluster_id'];



    // Update all complaints of same cluster

    $update = mysqli_prepare(
        $conn,
        "UPDATE complaints
         SET status=?, 
             assigned_staff_id=?
         WHERE cluster_id=?"
    );



    mysqli_stmt_bind_param(
        $update,
        "sis",
        $status,
        $staff_id,
        $cluster_id
    );



    mysqli_stmt_execute($update);



    // Add timeline for every complaint in cluster

    $list = mysqli_prepare(
        $conn,
        "SELECT complaint_id
         FROM complaints
         WHERE cluster_id=?"
    );


    mysqli_stmt_bind_param(
        $list,
        "s",
        $cluster_id
    );


    mysqli_stmt_execute($list);


    $complaints = mysqli_stmt_get_result($list);



    while($c=mysqli_fetch_assoc($complaints))
    {


        $timeline = mysqli_prepare(
            $conn,
            "INSERT INTO complaint_timeline
            (complaint_id,status_change,note,changed_at)
            VALUES(?,?,?,NOW())"
        );


        mysqli_stmt_bind_param(
            $timeline,
            "iss",
            $c['complaint_id'],
            $status,
            $note
        );


        mysqli_stmt_execute($timeline);


    }



    echo "
    <script>
    alert('Status Updated Successfully');
    window.location='assigned-complaints.php';
    </script>";

}



// Fetch complaints


$sql = mysqli_prepare(
    $conn,
    "SELECT *
     FROM complaints
     WHERE assigned_staff_id=?
     ORDER BY created_at DESC"
);


mysqli_stmt_bind_param(
    $sql,
    "i",
    $staff_id
);


mysqli_stmt_execute($sql);


$result=mysqli_stmt_get_result($sql);

?>



<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Assigned Complaints | Staff</title>


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

background:white;
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

color:#004d40;

}



button,
.back-btn{

padding:8px 15px;
border:none;
border-radius:6px;
cursor:pointer;
color:white;
text-decoration:none;

}


.theme-btn{

background:#00897b;

}


.back-btn{

background:#6c757d;

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



.status{

font-weight:bold;

}



img{

width:70px;
height:70px;
object-fit:cover;
border-radius:8px;

}



.update-box{

background:#f8fafc;
padding:10px;
border-radius:8px;

}



select,
textarea{

width:100%;
padding:8px;
margin-bottom:8px;
border-radius:5px;
border:1px solid #ccc;

}



textarea{

height:60px;

}



.update-btn{

background:#00897b;

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


<h2>
Staff Assigned Complaints
</h2>



<div class="header-right">


<a href="dashboard.php" class="back-btn">
← Back
</a>


<button id="themeToggle" class="theme-btn">
🌙 Dark Mode
</button>


</div>


</div>




<table>


<tr>

<th>ID</th>
<th>Category</th>
<th>Description</th>
<th>Priority</th>
<th>Status</th>
<th>Photo</th>
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
<?php echo $row['complaint_id']; ?>
</td>


<td>
<?php echo $row['category']; ?>
</td>



<td>
<?php echo $row['description']; ?>
</td>



<td>


<?php

if($row['priority']=="High")
echo "<span class='high'>High</span>";

elseif($row['priority']=="Medium")
echo "<span class='medium'>Medium</span>";

else
echo "<span class='low'>Low</span>";

?>


</td>




<td class="status">

<?php echo $row['status']; ?>

</td>



<td>


<?php

if(!empty($row['photo_path']))
{

?>

<img src="<?='/UniStay/'.$row['photo_path']?>" width="80" height="80">


<?php

}

else

{

echo "No Photo";

}


?>


</td>




<td>


<div class="update-box">


<form method="POST">


<input type="hidden"
name="complaint_id"
value="<?php echo $row['complaint_id']; ?>">


<select name="status">

<option value="<?php echo $row['status']; ?>">
Current: <?php echo $row['status']; ?>
</option>

<option value="Pending">
Pending
</option>

<option value="In Progress">
In Progress
</option>

<option value="Solved">
Solved
</option>

</select>



<textarea name="note"
placeholder="Write note (optional)..."></textarea>


<button class="update-btn"
name="update_status">

Update

</button>


</form>


</div>


</td>



</tr>



<?php


}

}


else

{

echo "<tr>
<td colspan='7'>No Assigned Complaints Found</td>
</tr>";

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