<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$message = "";
$error = "";


/* Get Staff Information */

$stmt = $conn->prepare("
    SELECT 
        name,
        email,
        institutional_id,
        phone,
        availability,
        availability_note
    FROM users
    WHERE id=?
");

$stmt->bind_param("i",$user_id);
$stmt->execute();

$staff = $stmt->get_result()->fetch_assoc();


if(!$staff){
    die("Staff information not found");
}



/* Update Availability */

if($_SERVER['REQUEST_METHOD']=="POST"){

    $availability = $_POST['availability'];
    $note = trim($_POST['availability_note']);


    $update = $conn->prepare("
        UPDATE users
        SET 
            availability=?,
            availability_note=?
        WHERE id=?
    ");


    $update->bind_param(
        "ssi",
        $availability,
        $note,
        $user_id
    );


    if($update->execute()){

        $message="Availability updated successfully";


        $stmt=$conn->prepare("
            SELECT 
                name,
                email,
                institutional_id,
                phone,
                availability,
                availability_note
            FROM users
            WHERE id=?
        ");

        $stmt->bind_param("i",$user_id);
        $stmt->execute();

        $staff=$stmt->get_result()->fetch_assoc();


    }else{

        $error="Update failed";

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Staff Availability | UniStay</title>


<style>


*{
    box-sizing:border-box;
}


body{

    margin:0;
    padding:30px;
    font-family:'Segoe UI',sans-serif;
    background:#f1f5f9;
    color:#1e293b;
    transition:.3s;

}



.container{

    max-width:650px;
    margin:auto;

}



.card{

    background:white;
    padding:35px;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);

}



.header{

    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;

}



.header h2{

    color:#00897b;
    margin:0;

}



.btn{

    padding:10px 18px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    text-decoration:none;
    font-weight:bold;

}



.theme-btn{

    background:#334155;
    color:white;

}



.dashboard-btn{

    background:#00897b;
    color:white;

    margin-left:8px;

}



.alert-success{

    color:green;
    background:#dcfce7;
    padding:10px;
    border-radius:8px;

}



.alert-danger{

    color:red;
    background:#fee2e2;
    padding:10px;
    border-radius:8px;

}



.profile{

    background:#e0f2f1;
    padding:20px;
    border-radius:12px;
    line-height:32px;
    margin-bottom:25px;

}



label{

    font-weight:bold;
    display:block;
    margin-top:15px;

}



select,
textarea{

    width:100%;
    padding:12px;
    margin-top:8px;
    border-radius:8px;
    border:1px solid #cbd5e1;
    font-size:15px;

}



.save-btn{

    margin-top:20px;
    background:#00897b;
    color:white;

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



body.dark .profile{

    background:#1e293b;

}



body.dark select,
body.dark textarea{

    background:#0f172a;
    color:white;
    border-color:#475569;

}



body.dark .theme-btn{

    background:#f8fafc;
    color:#020617;

}



@media(max-width:600px){

.header{

    flex-direction:column;
    gap:15px;

}

}


</style>


</head>



<body>



<div class="container">


<div class="card">



<div class="header">


<h2>
👤 Staff Availability
</h2>



<div>


<button id="themeToggle" class="btn theme-btn">
🌙 Dark Mode
</button>



<a href="dashboard.php" class="btn dashboard-btn">
← Dashboard
</a>



</div>


</div>





<?php if($message){ ?>

<div class="alert-success">

<?= $message ?>

</div>

<?php } ?>



<?php if($error){ ?>

<div class="alert-danger">

<?= $error ?>

</div>

<?php } ?>






<div class="profile">


<b>Name:</b>

<?= htmlspecialchars($staff['name']) ?>


<br>


<b>Email:</b>

<?= htmlspecialchars($staff['email']) ?>


<br>


<b>Institution ID:</b>

<?= htmlspecialchars($staff['institutional_id']) ?>


<br>


<b>Phone:</b>

<?= !empty($staff['phone']) 
? htmlspecialchars($staff['phone']) 
: "Not Added"
?>


<br>


<b>Current Status:</b>

<?= htmlspecialchars($staff['availability']) ?>


</div>






<form method="POST">


<label>
Availability
</label>


<select name="availability">


<option value="Available"
<?= $staff['availability']=="Available"?"selected":"" ?>>
Available
</option>



<option value="Busy"
<?= $staff['availability']=="Busy"?"selected":"" ?>>
Busy
</option>



<option value="On Leave"
<?= $staff['availability']=="On Leave"?"selected":"" ?>>
On Leave
</option>


</select>





<label>
Availability Note
</label>



<textarea 
name="availability_note"
rows="4"
placeholder="Write your current status..."><?= htmlspecialchars($staff['availability_note']) ?></textarea>





<button class="btn save-btn">

💾 Save

</button>




</form>



</div>


</div>





<script>


const btn=document.getElementById("themeToggle");



if(localStorage.getItem("theme")=="dark"){

document.body.classList.add("dark");

btn.innerHTML="Light Mode";

}



btn.onclick=function(){


document.body.classList.toggle("dark");


if(document.body.classList.contains("dark")){


localStorage.setItem("theme","dark");

btn.innerHTML=" Light Mode";


}else{


localStorage.setItem("theme","light");

btn.innerHTML=" Dark Mode";


}


}



</script>



</body>

</html>