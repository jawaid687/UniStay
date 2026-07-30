<?php

session_start();

require_once '../includes/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {

    header("Location: ../auth/login.php");
    exit();

}


$user_id = intval($_SESSION['user_id']);



function h($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}





/* Get Staff Data */

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM staff_records
     WHERE user_id=?
     LIMIT 1"
);


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


$staff = mysqli_fetch_assoc($result);



if(!$staff){

    $insert = mysqli_prepare($conn,

    "INSERT INTO staff_records
    (user_id, full_name, status, availability)
    VALUES (?, 'New Staff', 'Active', 'Available')"

    );


    mysqli_stmt_bind_param(
        $insert,
        "i",
        $user_id
    );


    mysqli_stmt_execute($insert);



    // আবার data load

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM staff_records
         WHERE user_id=?
         LIMIT 1"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );


    mysqli_stmt_execute($stmt);


    $result = mysqli_stmt_get_result($stmt);


    $staff = mysqli_fetch_assoc($result);

}




/* Update Profile */


if(isset($_POST['update'])){


    $full_name = $_POST['full_name'];
    $staff_id = $_POST['staff_id'];
    $employee_id = $_POST['employee_id'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $designation = $_POST['designation'];
    $assigned_floor = $_POST['assigned_floor'];
    $availability = $_POST['availability'];



    $update = mysqli_prepare(

        $conn,

        "UPDATE staff_records SET

        full_name=?,
        staff_id=?,
        employee_id=?,
        phone=?,
        department=?,
        designation=?,
        assigned_floor=?,
        availability=?

        WHERE user_id=?
        "

    );



    mysqli_stmt_bind_param(

        $update,

        "ssssssssi",

        $full_name,
        $staff_id,
        $employee_id,
        $phone,
        $department,
        $designation,
        $assigned_floor,
        $availability,
        $user_id

    );



    mysqli_stmt_execute($update);



    header("Location: profile.php");
    exit();

}



?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Staff Profile - UniStay</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}


body{

    background:#f1f5f9;
    padding:30px;
    transition:.3s;

}


.container{

    max-width:850px;
    margin:auto;

}


.header{

    background:white;
    padding:25px;
    border-radius:15px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 5px 20px rgba(0,0,0,.1);

}


.header h1{

    color:#1e293b;

}


.header p{

    color:#64748b;
    margin-top:5px;

}



.btn{

    text-decoration:none;
    background:#2563eb;
    color:white;
    padding:12px 18px;
    border-radius:8px;
    font-weight:600;

}




.card{

    margin-top:25px;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);

}



.card h2{

    color:#0f172a;
    margin-bottom:20px;
    border-bottom:2px solid #e2e8f0;
    padding-bottom:10px;

}




.form-grid{

    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;

}



.form-group{

    display:flex;
    flex-direction:column;

}



label{

    font-weight:600;
    color:#475569;
    margin-bottom:8px;

}



input,select{

    padding:12px;
    border-radius:8px;
    border:1px solid #cbd5e1;
    outline:none;
    font-size:15px;

}



input:focus,
select:focus{

    border-color:#2563eb;

}



.full{

    grid-column:1/-1;

}




.save-btn{

    margin-top:25px;
    background:#2563eb;
    color:white;
    border:none;
    padding:13px 25px;
    border-radius:10px;
    cursor:pointer;
    font-size:16px;
    font-weight:bold;

}




.back{

    display:inline-block;
    margin-top:20px;
    text-decoration:none;
    color:#2563eb;
    font-weight:600;

}




.theme-btn{

    cursor:pointer;
    background:#0f766e;
    color:white;
    border:none;
    padding:10px 15px;
    border-radius:8px;

}



/* DARK MODE */


body.dark{

    background:#020617;
}



body.dark .header,
body.dark .card{

    background:#0f172a;

}



body.dark h1,
body.dark h2{

    color:#e2e8f0;

}



body.dark label{

    color:#cbd5e1;

}



body.dark input,
body.dark select{

    background:#1e293b;
    color:white;
    border-color:#475569;

}



@media(max-width:700px){


.form-grid{

    grid-template-columns:1fr;

}


.header{

    flex-direction:column;
    gap:15px;

}


}



</style>

</head>



<body>


<div class="container">



<div class="header">


<div>

<h1>
✏ Edit Staff Profile
</h1>

<p>
Update your staff information
</p>

</div>


<div>

<button class="theme-btn" onclick="toggleTheme()">
🌙 Theme
</button>


<a href="dashboard.php" class="btn">
⬅ Dashboard
</a>


</div>


</div>





<div class="card">


<h2>
Staff Information
</h2>



<form method="POST">



<div class="form-grid">



<div class="form-group">

<label>
Full Name
</label>

<input 
type="text"
name="full_name"
value="<?=h($staff['full_name'])?>"
required>

</div>





<div class="form-group">

<label>
Staff ID
</label>

<input 
type="text"
name="staff_id"
value="<?=h($staff['staff_id'])?>">

</div>





<div class="form-group">

<label>
Employee ID
</label>

<input 
type="text"
name="employee_id"
value="<?=h($staff['employee_id'])?>">

</div>





<div class="form-group">

<label>
Phone
</label>

<input 
type="text"
name="phone"
value="<?=h($staff['phone'])?>">

</div>






<div class="form-group">

<label>
Department
</label>

<input 
type="text"
name="department"
value="<?=h($staff['department'])?>">

</div>







<div class="form-group">

<label>
Designation
</label>

<input 
type="text"
name="designation"
value="<?=h($staff['designation'])?>">

</div>







<div class="form-group">

<label>
Assigned Floor
</label>

<input 
type="text"
name="assigned_floor"
value="<?=h($staff['assigned_floor'])?>">

</div>







<div class="form-group">

<label>
Availability
</label>


<select name="availability">


<option value="Available"
<?=($staff['availability']=="Available")?'selected':''?>>
Available
</option>



<option value="Busy"
<?=($staff['availability']=="Busy")?'selected':''?>>
Busy
</option>




<option value="Leave"
<?=($staff['availability']=="Leave")?'selected':''?>>
Leave
</option>


</select>


</div>




</div>





<button name="update" class="save-btn">

💾 Save Changes

</button>



</form>



<a href="profile.php" class="back">

⬅ Back to Profile

</a>



</div>





</div>





<script>


function toggleTheme(){

document.body.classList.toggle("dark");


if(document.body.classList.contains("dark")){

localStorage.setItem("staffTheme","dark");

}else{

localStorage.setItem("staffTheme","light");

}

}



if(localStorage.getItem("staffTheme")=="dark"){

document.body.classList.add("dark");

}



</script>



</body>

</html>