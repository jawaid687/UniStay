<?php

session_start();

require_once "../includes/db.php";

/* ONLY ADMIN */
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$success = "";
$error = "";

/*
==============================
GET STAFF LIST FROM USERS
==============================
*/
$staff_query = mysqli_query(
    $conn,
    "SELECT id, name, email FROM users WHERE role='staff' ORDER BY name ASC"
);

/*
==============================
INSERT FLOOR SCHEDULE
==============================
*/
if(isset($_POST['assign'])){

    // Prepared statements handle escaping automatically; no need for mysqli_real_escape_string
    $staff_id        = intval($_POST['staff_id']);
    $floor_name      = trim($_POST['floor_name']);
    $room_range      = trim($_POST['room_range']);
    $shift_time      = trim($_POST['shift_time']);
    $working_days    = trim($_POST['working_days']);
    $weekly_off      = trim($_POST['weekly_off']);
    $duty_type       = trim($_POST['duty_type']);
    $emergency_task  = trim($_POST['emergency_task']);
    $supervisor_name = trim($_POST['supervisor_name']);

    /*
    CHECK USER EXISTS
    */
    $check = $conn->prepare("SELECT id FROM users WHERE id=? AND role='staff'");
    $check->bind_param("i", $staff_id);
    $check->execute();
    $check_result = $check->get_result();

    if($check_result->num_rows == 0){
        $error = "Invalid Staff ID";
    }
    else {
        /*
        INSERT DATA
        */
        $sql = "INSERT INTO floor_schedule 
                (staff_id, floor_name, room_range, shift_time, working_days, weekly_off, duty_type, emergency_task, supervisor_name, schedule_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

        $stmt = $conn->prepare($sql);

        // Fixed: Added $emergency_task so all 9 string parameters match "issssssss"
        $stmt->bind_param(
            "issssssss",
            $staff_id,
            $floor_name,
            $room_range,
            $shift_time,
            $working_days,
            $weekly_off,
            $duty_type,
            $emergency_task,
            $supervisor_name
        );

        if($stmt->execute()){
            $success = "Floor Schedule Assigned Successfully";
        }
        else {
            $error = "Database Error : " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Assign Floor Schedule</title>

<style>
* {
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

body {
    margin: 0;
    padding: 30px;
    background: #f1f5f9;
    transition: .3s;
}

.container {
    max-width: 850px;
    margin: auto;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,.08);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

h2 {
    color: #065f46;
}

.back-btn {
    background: #64748b;
    color: white;
    padding: 10px 18px;
    text-decoration: none;
    border-radius: 8px;
}

.message {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: bold;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.form-box {
    background: #f8fafc;
    padding: 25px;
    border-radius: 15px;
}

label {
    display: block;
    font-weight: bold;
    margin-top: 15px;
    color: #064e3b;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    margin-top: 8px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 15px;
}

textarea {
    height: 100px;
    resize: none;
}

button {
    margin-top: 20px;
    background: #059669;
    color: white;
    border: none;
    padding: 13px 30px;
    border-radius: 10px;
    font-size: 16px;
    cursor: pointer;
}

button:hover {
    background: #047857;
}

.theme-btn {
    background: #2563eb;
    margin-left: 10px;
}

.dark {
    background: #020617;
    color: white;
}

.dark .card {
    background: #0f172a;
}

.dark .form-box {
    background: #1e293b;
}

.dark input, .dark select, .dark textarea {
    background: #334155;
    color: white;
    border-color: #475569;
}

.dark label {
    color: #7dd3fc;
}
</style>
</head>

<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h2>🏢 Assign Floor Schedule</h2>
            <div>
                <a href="dashboard.php" class="back-btn">← Back</a>
                <button type="button" class="theme-btn" onclick="toggleTheme()">🌙</button>
            </div>
        </div>

        <?php if($success){ ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php } ?>

        <?php if($error){ ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php } ?>

        <div class="form-box">
            <form method="POST">
                <label>Select Staff</label>
                <select name="staff_id" required>
                    <option value="">-- Select Staff --</option>
                    <?php while($staff = mysqli_fetch_assoc($staff_query)){ ?>
                        <option value="<?= $staff['id']; ?>">
                            <?= htmlspecialchars($staff['name']); ?> (<?= htmlspecialchars($staff['email']); ?>)
                        </option>
                    <?php } ?>
                </select>

                <label>Floor Name</label>
                <input type="text" name="floor_name" placeholder="Example: 4th Floor" required>

                <label>Room Range</label>
                <input type="text" name="room_range" placeholder="Example: 401-420" required>

                <label>Shift Time</label>
                <input type="text" name="shift_time" placeholder="Example: 9:30 AM - 4:00 PM" required>

                <label>Working Days</label>
                <input type="text" name="working_days" placeholder="Example: Monday-Friday" required>

                <label>Weekly Off</label>
                <input type="text" name="weekly_off" placeholder="Example: Sunday" required>

                <label>Duty Type</label>
                <input type="text" name="duty_type" placeholder="Cleaning / Maintenance / Security" required>

                <label>Emergency Task</label>
                <textarea name="emergency_task" placeholder="Write emergency responsibility"></textarea>

                <label>Supervisor Name</label>
                <input type="text" name="supervisor_name" placeholder="Supervisor Name" required>

                <button type="submit" name="assign">✅ Assign Schedule</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTheme(){
    document.body.classList.toggle("dark");
    if(document.body.classList.contains("dark")){
        localStorage.setItem("floor_theme", "dark");
    } else {
        localStorage.setItem("floor_theme", "light");
    }
}

window.onload = function(){
    if(localStorage.getItem("floor_theme") == "dark"){
        document.body.classList.add("dark");
    }
}
</script>

</body>
</html>