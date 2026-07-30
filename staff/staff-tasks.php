<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$message = "";

// Update task status
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['task_id'])) {

    $task_id = intval($_POST['task_id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("
        UPDATE staff_tasks
        SET status=?
        WHERE id=? AND staff_id=?
    ");

    $stmt->bind_param("sii", $status, $task_id, $staff_id);

    if($stmt->execute()){
        $message="Task updated successfully.";
    }
}

// Load tasks
$stmt = $conn->prepare("
SELECT *
FROM staff_tasks
WHERE staff_id=?
ORDER BY due_date ASC
");

$stmt->bind_param("i",$staff_id);
$stmt->execute();
$tasks = $stmt->get_result();
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>My Tasks</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card{
border-radius:15px;
}

.table th{
background:#0d6efd;
color:white;
}

.priority-high{
color:#dc3545;
font-weight:bold;
}

.priority-medium{
color:#fd7e14;
font-weight:bold;
}

.priority-low{
color:#198754;
font-weight:bold;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3 class="mb-0">

My Tasks

</h3>

</div>

<div class="card-body">

<?php if($message!=""){ ?>

<div class="alert alert-success">

<?= $message ?>

</div>

<?php } ?>

<table class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>ID</th>

<th>Title</th>

<th>Description</th>

<th>Priority</th>

<th>Due Date</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php

if($tasks->num_rows>0){

while($row=$tasks->fetch_assoc()){

$priorityClass="priority-low";

if($row['priority']=="Medium")
$priorityClass="priority-medium";

if($row['priority']=="High")
$priorityClass="priority-high";

echo "<tr>";

echo "<td>".$row['id']."</td>";

echo "<td>".htmlspecialchars($row['task_title'])."</td>";

echo "<td>".htmlspecialchars($row['task_description'])."</td>";

echo "<td class='$priorityClass'>".$row['priority']."</td>";

echo "<td>".$row['due_date']."</td>";

echo "<td>";
$status = $row['status'];

if($status=="Pending"){
    echo "<span class='badge bg-danger'>Pending</span>";
}
elseif($status=="In Progress"){
    echo "<span class='badge bg-warning text-dark'>In Progress</span>";
}
else{
    echo "<span class='badge bg-success'>Completed</span>";
}

echo "</td>";

?>

<td>

<form method="POST">

<input type="hidden"
name="task_id"
value="<?= $row['id']; ?>">

<select
name="status"
class="form-select form-select-sm mb-2">

<option value="Pending"
<?= $row['status']=="Pending"?"selected":"" ?>>
Pending
</option>

<option value="In Progress"
<?= $row['status']=="In Progress"?"selected":"" ?>>
In Progress
</option>

<option value="Completed"
<?= $row['status']=="Completed"?"selected":"" ?>>
Completed
</option>

</select>

<button
class="btn btn-primary btn-sm w-100">

Update

</button>

</form>

</td>

<?php

echo "</tr>";

}

}else{

?>

<tr>

<td colspan="7" class="text-center text-muted">

No tasks assigned.

</td>

</tr>

<?php

}

?>

</tbody>

</table>

<div class="mt-3">

<a href="dashboard.php"
class="btn btn-secondary">

← Back to Dashboard

</a>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>