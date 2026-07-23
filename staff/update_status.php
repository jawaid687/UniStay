<?php

session_start();
require_once '../includes/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Complaint ID.");
}

$complaint_id = intval($_GET['id']);

$success_msg = "";
$error_msg = "";

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Get Complaint */
$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM complaints WHERE complaint_id=? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $complaint_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $status = trim($_POST['status']);

    if($status=="Solved")
    {
        $sql = "UPDATE complaints
                SET status='Solved',
                    resolved_at=NOW()
                WHERE complaint_id='$complaint_id'";
    }
    else
    {
        $sql = "UPDATE complaints
                SET status='$status'
                WHERE complaint_id='$complaint_id'";
    }

    if(mysqli_query($conn,$sql))
    {
        $_SESSION['success_msg']="Complaint updated successfully.";

        header("Location: staff_complaint_view.php");
        exit();
    }
    else
    {
        die(mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Update Complaint Status | UniStay</title>

<link rel="stylesheet" href="../assets/css/theme.css">

<style>

body{
    margin:0;
    padding:25px;
    background:#f4f8f7;
    font-family:'Segoe UI';
}

.container{

    max-width:500px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,.1);

}

select{

width:100%;
padding:12px;
margin-top:10px;

}

button{

width:100%;
padding:12px;
margin-top:20px;
background:#00897b;
color:white;
border:none;
border-radius:6px;
cursor:pointer;

}

.success{

background:#d4edda;
padding:12px;
margin-bottom:15px;

}

.error{

background:#f8d7da;
padding:12px;
margin-bottom:15px;

}

</style>

</head>

<body>

<div class="container">

<h2>Update Complaint Status</h2>

<?php if($success_msg!=""){ ?>

<div class="success"><?php echo h($success_msg); ?></div>

<?php } ?>

<?php if($error_msg!=""){ ?>

<div class="error"><?php echo h($error_msg); ?></div>

<?php } ?>

<form method="POST">

<label>Status</label>

<select name="status" required>

<option value="Pending" <?=($row['status']=="Pending")?'selected':'';?>>
Pending
</option>

<option value="In Progress" <?=($row['status']=="In Progress")?'selected':'';?>>
In Progress
</option>

<option value="Solved" <?=($row['status']=="Solved")?'selected':'';?>>
Solved
</option>

<option value="Reopened" <?=($row['status']=="Reopened")?'selected':'';?>>
Reopened
</option>

</select>

<button type="submit">
Update Status
</button>

</form>

<br>

<a href="staff_complaint_view.php">
← Back
</a>

</div>

</body>

</html>