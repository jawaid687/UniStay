<?php

session_start();
require_once "../includes/db.php";


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}


$message="";



/* UPDATE AVAILABILITY */

if(isset($_POST['update'])){


    $id = intval($_POST['id']);
    $availability = $_POST['availability'];



    $stmt = $conn->prepare("
        UPDATE users
        SET availability=?
        WHERE id=?
        AND role='staff'
    ");



    $stmt->bind_param(
        "si",
        $availability,
        $id
    );



    if($stmt->execute()){

        $message="Staff availability updated successfully";

    }

}



/* GET STAFF LIST */


$result = $conn->query("

SELECT

id,
name,
email,
institutional_id,
phone,
availability

FROM users

WHERE role='staff'

ORDER BY id DESC

");


?>



<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Staff Availability</title>


<style>


*{
    box-sizing:border-box;
}



body{

    font-family:'Segoe UI',sans-serif;
    background:#f1f5f9;
    padding:30px;
    transition:.3s;

}



.container{

    max-width:1100px;
    margin:auto;

}



.card{

    background:white;
    padding:30px;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);

}



.header{

    display:flex;
    justify-content:space-between;
    align-items:center;

}



h2{

    color:#00897b;

}



.btn{

    padding:10px 18px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
    text-decoration:none;

}



.dashboard{

    background:#334155;
    color:white;

}



.theme{

    background:#00897b;
    color:white;

}



table{

    width:100%;
    border-collapse:collapse;
    margin-top:25px;

}



th{

    background:#00897b;
    color:white;
    padding:12px;

}



td{

    padding:12px;
    text-align:center;
    border-bottom:1px solid #ddd;

}



select{

    padding:8px;
    border-radius:6px;

}



.update{

    background:#2563eb;
    color:white;

}



.available{

    color:green;
    font-weight:bold;

}



.busy{

    color:red;
    font-weight:bold;

}



.leave{

    color:orange;
    font-weight:bold;

}



/* DARK MODE */


body.dark{

    background:#020617;
    color:white;

}



body.dark .card{

    background:#0f172a;
    color:white;

}



body.dark td{

    border-color:#334155;

}



body.dark select{

    background:#0f172a;
    color:white;

}



</style>


</head>



<body>



<div class="container">


<div class="card">



<div class="header">


<h2>
👥 Staff Availability Management
</h2>



<div>


<a href="dashboard.php" class="btn dashboard">
← Dashboard
</a>


<button id="themeToggle" class="btn theme">
🌙 Dark Mode
</button>


</div>


</div>





<?php if($message){ ?>

<p style="color:green;font-weight:bold;">
<?= $message ?>
</p>

<?php } ?>





<table>


<tr>

<th>Name</th>
<th>Email</th>
<th>Institution ID</th>
<th>Phone</th>
<th>Availability</th>
<th>Update</th>

</tr>




<?php while($row=$result->fetch_assoc()){ ?>


<tr>



<td>

<?= htmlspecialchars($row['name']) ?>

</td>




<td>

<?= htmlspecialchars($row['email']) ?>

</td>




<td>

<?= htmlspecialchars($row['institutional_id']) ?>

</td>




<td>

<?= !empty($row['phone']) 
? htmlspecialchars($row['phone'])
: "Not Added"
?>

</td>




<td>


<?php


if($row['availability']=="Available"){

echo "<span class='available'>🟢 Available</span>";

}

elseif($row['availability']=="Busy"){

echo "<span class='busy'>🔴 Busy</span>";

}

else{

echo "<span class='leave'>🟠 On Leave</span>";

}


?>


</td>





<td>



<form method="POST">


<input type="hidden" 
name="id"
value="<?= $row['id'] ?>">



<select name="availability">


<option value="Available"
<?= $row['availability']=="Available"?"selected":"" ?>>
Available
</option>



<option value="Busy"
<?= $row['availability']=="Busy"?"selected":"" ?>>
Busy
</option>



<option value="On Leave"
<?= $row['availability']=="On Leave"?"selected":"" ?>>
On Leave
</option>



</select>



<br><br>



<button class="btn update" name="update">

Update

</button>



</form>


</td>




</tr>



<?php } ?>



</table>



</div>


</div>






<script>


const btn=document.getElementById("themeToggle");



if(localStorage.getItem("theme")=="dark"){

document.body.classList.add("dark");

btn.innerHTML="☀️ Light Mode";

}



btn.onclick=function(){


document.body.classList.toggle("dark");



if(document.body.classList.contains("dark")){


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