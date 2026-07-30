<?php

session_start();

require_once '../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../auth/login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$message = "";

if(isset($_GET['success'])){
    $message = "Leave application submitted successfully.";
}

// Submit Leave Application
if($_SERVER['REQUEST_METHOD'] == "POST"){

    $leave_type = $_POST['leave_type'];
    $reason = $_POST['reason'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $stmt = $conn->prepare("
        INSERT INTO staff_leave_applications
        (
            staff_id,
            leave_type,
            reason,
            start_date,
            end_date,
            status,
            applied_at
        )
        VALUES (?,?,?,?,?,'Pending',NOW())
    ");

    $stmt->bind_param(
        "issss",
        $staff_id,
        $leave_type,
        $reason,
        $start_date,
        $end_date
    );

    if($stmt->execute()){
        header("Location: leave-application.php?success=1");
        exit();
    }
}

// Get Leave History
$stmt = $conn->prepare("
    SELECT *
    FROM staff_leave_applications
    WHERE staff_id=?
    ORDER BY applied_at DESC
");

$stmt->bind_param("i", $staff_id);
$stmt->execute();
$leaves = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>
<title>Staff Leave Application | UniStay</title>
<link rel="stylesheet" href="../assets/css/theme.css">

<style>

body {
    background: #f1f5f9;
    font-family: 'Segoe UI', sans-serif;
    padding: 30px;
}

.container {
    max-width: 1000px;
    margin: auto;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 18px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border-top: 6px solid #00897b;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 15px;
}

h2 {
    color: #00897b;
}

button,
.btn {
    padding: 12px 20px;
    border-radius: 8px;
    border: none;
    text-decoration: none;
    font-weight: bold;
}

.submit {
    background: #00897b;
    color: white;
    cursor: pointer;
}

.back {
    background: #64748b;
    color: white;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

table {
    width: 100%;
    margin-top: 30px;
    border-collapse: collapse;
}

th {
    background: #00897b;
    color: white;
    padding: 12px;
    text-align: left;
}

td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

/* STATUS BADGES (LIGHT MODE) */
.badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.88rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
}

.pending {
    background-color: #fef3c7;
    color: #92400e;
}

.approved {
    background-color: #d1fae5;
    color: #065f46;
}

.rejected {
    background-color: #fee2e2;
    color: #991b1b;
}

@media(max-width: 700px){
    .row {
        grid-template-columns: 1fr;
    }
}

/* DARK MODE STYLES */
body.dark-mode {
    background: #020617;
    color: white;
}

body.dark-mode .card {
    background: #0f172a;
    color: #e5e7eb;
}

body.dark-mode h2,
body.dark-mode h3 {
    color: #7dd3fc;
}

body.dark-mode label {
    color: #7dd3fc;
}

body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea {
    background: #1e293b;
    color: white;
    border-color: #475569;
}

body.dark-mode textarea::placeholder {
    color: #cbd5e1;
}

body.dark-mode table {
    color: white;
}

body.dark-mode td {
    border-color: #475569;
}

body.dark-mode .header {
    border-color: #334155;
}

.theme-btn {
    background: white;
    border: 1px solid #ccc;
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
}

body.dark-mode .theme-btn {
    background: #1e293b;
    color: white;
    border-color: #475569;
}

/* STATUS BADGES (DARK MODE) */
body.dark-mode .pending {
    background-color: #451a03;
    color: #fcd34d;
    border: 1px solid #78350f;
}

body.dark-mode .approved {
    background-color: #064e3b;
    color: #6ee7b7;
    border: 1px solid #047857;
}

body.dark-mode .rejected {
    background-color: #7f1d1d;
    color: #fca5a5;
    border: 1px solid #b91c1c;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="header">

<h2>
📝 Staff Leave Application
</h2>

<div>

<button id="themeToggle" class="theme-btn">
🌙 Dark Mode
</button>

<a href="dashboard.php" class="btn back">
← Dashboard
</a>

</div>

</div>

<?php if($message != ""){ ?>

<div style="background:#62b880; color:white; padding:15px; border-radius:10px;">
<?= htmlspecialchars($message) ?>
</div>

<br>

<?php } ?>

<form method="POST">

<div class="row">

<div>
<label>Leave Type</label>
<select name="leave_type" required>
<option value="">Select Leave Type</option>
<option>Casual Leave</option>
<option>Sick Leave</option>
<option>Emergency Leave</option>
<option>Other</option>
</select>
</div>

<div>
<label>Start Date</label>
<input type="date" name="start_date" required>
</div>

<div>
<label>End Date</label>
<input type="date" name="end_date" required>
</div>

</div>

<br>

<label>Reason</label>
<textarea 
name="reason"
rows="4"
placeholder="Write your reason..."
required></textarea>

<br><br>

<button type="submit" class="submit">
Submit Application
</button>

</form>

<hr style="margin-top: 30px; border-color: #334155;">

<h3>
📋 My Leave Applications
</h3>

<table>

<tr>
<th>Type</th>
<th>Reason</th>
<th>Date</th>
<th>Status</th>
</tr>

<?php while($row = $leaves->fetch_assoc()){ ?>

<tr>

<td>
<?= htmlspecialchars($row['leave_type']); ?>
</td>

<td>
<?= htmlspecialchars($row['reason']); ?>
</td>

<td>
<?= htmlspecialchars($row['start_date']); ?>
<br>
to
<br>
<?= htmlspecialchars($row['end_date']); ?>
</td>

<td>

<?php
$class = "pending";

if($row['status'] == "Approved"){
    $class = "approved";
}
elseif($row['status'] == "Rejected"){
    $class = "rejected";
}
?>

<span class="badge <?= $class ?>">
<?= !empty($row['status']) ? htmlspecialchars($row['status']) : 'Pending'; ?>
</span>

</td>

</tr>

<?php } ?>

</table>

</div>

</div>

<script>

const themeBtn = document.getElementById("themeToggle");

// Load saved theme
if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark-mode");
}

function updateThemeButton(){
    if(document.body.classList.contains("dark-mode")){
        themeBtn.innerHTML = "☀️ Light Mode";
    }
    else{
        themeBtn.innerHTML = "🌙 Dark Mode";
    }
}

updateThemeButton();

themeBtn.onclick = function(){
    document.body.classList.toggle("dark-mode");

    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme", "dark");
    }
    else{
        localStorage.setItem("theme", "light");
    }

    updateThemeButton();
}

</script>

</body>
</html>