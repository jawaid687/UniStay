<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$success = "";
$error = "";

if (!isset($_GET['id'])) {
    die("Complaint ID Missing!");
}

$complaint_id = intval($_GET['id']);

if (isset($_POST['assign'])) {

    $staff_id = intval($_POST['staff_id']);

    /* Get Cluster ID */

$clusterQuery = mysqli_query($conn,
"SELECT cluster_id
FROM complaints
WHERE complaint_id='$complaint_id'");

$cluster = mysqli_fetch_assoc($clusterQuery);

/* If complaint belongs to a cluster */

if(!empty($cluster['cluster_id']))
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE complaints
         SET assigned_staff_id=?
         WHERE cluster_id=?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $staff_id,
        $cluster['cluster_id']
    );
}
else
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE complaints
         SET assigned_staff_id=?
         WHERE complaint_id=?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $staff_id,
        $complaint_id
    );
}

    if(mysqli_stmt_execute($stmt))
    {
        mysqli_query($conn,"
        INSERT INTO complaint_timeline
        (complaint_id,status_change,note,changed_at)

        VALUES

        (
        '$complaint_id',
        'Assigned',
        'Complaint assigned to staff',
        NOW()
        )");

        $success="Staff assigned successfully.";
    }
    else
    {
        $error="Assignment failed.";
    }

    mysqli_stmt_close($stmt);
}

/* Load Staff */

$staff = mysqli_query($conn,"
SELECT id, name
FROM users
WHERE role='staff'
");
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Assign Staff | UniStay</title>

<link rel="stylesheet" href="../assets/css/theme.css">

<style>

body{
    margin:0;
    padding:25px;
    background:#f4f8f7;
    font-family:'Segoe UI',sans-serif;
}

.container{
    max-width:800px;
    margin:auto;
}

.card{
    background:#fff;
    padding:30px;
    border-radius:15px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
    border-top:6px solid #00897b;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.header-buttons{
    display:flex;
    gap:10px;
}

h2{
    margin:0;
    color:#004d40;
}

.success{
    background:#d1fae5;
    color:#065f46;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
}

.error{
    background:#fee2e2;
    color:#991b1b;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
}

label{
    display:block;
    margin-bottom:8px;
    font-weight:bold;
    color:#004d40;
}

select{
    width:100%;
    padding:12px;
    border:1px solid #ccc;
    border-radius:8px;
    margin-bottom:20px;
    font-size:15px;
}

.btn{
    padding:10px 20px;
    border:none;
    border-radius:6px;
    background:#00897b;
    color:white;
    cursor:pointer;
    font-size:15px;
}

.btn:hover{
    background:#004d40;
}

.back-btn{
    text-decoration:none;
    background:#00897b;
    color:white;
    padding:10px 18px;
    border-radius:6px;
    font-weight:bold;
}

.back-btn:hover{
    background:#004d40;
}

.theme-toggle{
    padding:10px 18px;
    border:none;
    border-radius:6px;
    background:#00897b;
    color:white;
    cursor:pointer;
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

.dark-mode h2{
    color:#7dd3fc;
}

.dark-mode label{
    color:white;
}

.dark-mode select{
    background:#1e293b;
    color:white;
    border:1px solid #334155;
}

.dark-mode .success{
    background:#14532d;
    color:#d1fae5;
}

.dark-mode .error{
    background:#7f1d1d;
    color:#fecaca;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="header">

<h2>Assign Complaint To Staff</h2>

<div class="header-buttons">

<a href="javascript:history.back()" class="back-btn">
← Back
</a>

<button class="theme-toggle" id="themeToggle">
🌙 Dark Mode
</button>

</div>

</div>

<?php
if($success!=""){
    echo "<div class='success'>$success</div>";
}

if($error!=""){
    echo "<div class='error'>$error</div>";
}
?>

<form method="POST">

<label>Select Staff</label>

<select name="staff_id" required>

<option value="">Choose Staff</option>

<?php
while($row=mysqli_fetch_assoc($staff)){
?>

<option value="<?php echo $row['id']; ?>">

<?php echo htmlspecialchars($row['name']); ?>

</option>

<?php
}
?>

</select>

<button type="submit" name="assign" class="btn">
Assign Staff
</button>

</form>

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

}

</script>

</body>

</html>