<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username   = "root"; 
$password   = "";      
$dbname     = "auth_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("<h2 style='color:red; text-align:center;'>Database Connection Failed: " . $conn->connect_error . "</h2>");
}

if (isset($_SESSION['user_id'])) {
    $staff_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $staff_id = $_SESSION['id'];
} else {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $floor        = $_POST['floor'] ?? '';
    $room_no      = $_POST['room_no'] ?? '';
    $report_type  = $_POST['report_type'] ?? '';
    $impact_level = $_POST['impact_level'] ?? '';
    $description  = $_POST['description'] ?? '';
    $status       = "Pending";

    $stmt = $conn->prepare("INSERT INTO staff_messages (staff_id, floor, room_no, report_type, impact_level, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $staff_id, $floor, $room_no, $report_type, $impact_level, $description, $status);

    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Report</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

body {
    background: #f1f5f9;
    padding: 30px;
    transition: .3s;
}

.container {
    max-width: 1100px;
    margin: auto;
}

.card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, .08);
    border-top: 6px solid #00897b;
    margin-bottom: 25px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 15px;
}

.header h2 {
    color: #00897b;
}

.buttons {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    color: white;
    cursor: pointer;
    font-size: 15px;
}

.back {
    background: #64748b;
}

.theme {
    background: #00897b;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #004d40;
    font-weight: bold;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 15px;
}

textarea {
    height: 120px;
    resize: none;
}

.submit {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 8px;
    background: #00897b;
    color: white;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.submit:hover {
    background: #00695c;
}

.report {
    background: #f8fafc;
    border-left: 5px solid #00897b;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}

.report h3 {
    color: #00897b;
    margin-bottom: 10px;
}


.badge {
    padding: 6px 16px;
    border-radius: 20px;
    color: #ffffff !important;
    font-size: 13px;
    display: inline-block;
    font-weight: bold;
    min-width: 90px;
    text-align: center;
}

.bg-pending { background: #f59e0b !important; }
.bg-approved { background: #10b981 !important; }
.bg-solved { background: #0284c7 !important; }
.bg-rejected { background: #ef4444 !important; } 
/* Dark Mode */
.dark-mode {
    background: #020617;
    color: white;
}

.dark-mode .card {
    background: #0f172a;
}

.dark-mode label, .dark-mode h2, .dark-mode h3 {
    color: #7dd3fc;
}

.dark-mode input, .dark-mode select, .dark-mode textarea {
    background: #1e293b;
    color: white;
    border: 1px solid #475569;
}

.dark-mode .report {
    background: #1e293b;
    border-left: 5px solid #06b6d4;
}

.dark-mode .theme {
    background: #334155;
}

.dark-mode .back {
    background: #475569;
}

@media(max-width:768px) {
    .row {
        grid-template-columns: 1fr;
    }
    .header {
        flex-direction: column;
        gap: 15px;
    }
}
</style>
</head>
<body>

<div class="container">

<div class="card">
    <div class="header">
        <h2>📝 Staff Report</h2>
        <div class="buttons">
            <button onclick="window.history.back()" class="btn back">← Dashboard</button>
            <button id="themeBtn" class="btn theme">🌙 Dark Mode</button>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <p style="color: green; background: #d1fae5; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold;">✅ Report submitted successfully!</p>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="row">
            <div>
                <label>Floor</label>
                <input type="text" name="floor" placeholder="Enter Floor" required>
            </div>

            <div>
                <label>Room Number</label>
                <input type="text" name="room_no" placeholder="Enter Room Number" required>
            </div>
        </div>

        <label>Report Type</label>
        <select name="report_type" required>
            <option value="">Select Report Type</option>
            <option value="Furniture Damage">Furniture Damage</option>
            <option value="Room Damage">Room Damage</option>
            <option value="Student Misbehavior">Student Misbehavior</option>
            <option value="Other">Other</option>
        </select>

        <label>Impact Level</label>
        <select name="impact_level" required>
            <option value="Minor">Minor</option>
            <option value="Major">Major</option>
            <option value="Critical">Critical</option>
            <option value="Suggestion">Suggestion</option>
        </select>

        <label>Description</label>
        <textarea name="description" placeholder="Write your report..." required></textarea>

        <button type="submit" class="submit">📤 Submit Report</button>
    </form>
</div>

<div class="card">
    <h2>📋 My Reports</h2>

    <?php
    $stmt_fetch = $conn->prepare("SELECT * FROM staff_messages WHERE staff_id = ? ORDER BY id DESC");
    $stmt_fetch->bind_param("i", $staff_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();

    if ($result) {
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                
                // 🔍 Status processing to handle all cases
                $status = !empty($row['status']) ? trim($row['status']) : 'Pending';
                $status_lower = strtolower($status);
                
                // 🎨 Class assignment based on Database Status
                $badge_class = "bg-pending";
                if ($status_lower === 'approved' || $status_lower === 'approve') {
                    $badge_class = "bg-approved";
                } elseif ($status_lower === 'solved') {
                    $badge_class = "bg-solved";
                } elseif ($status_lower === 'rejected' || $status_lower === 'reject') {
                    $badge_class = "bg-rejected";
                }
                ?>
                <div class="report">
                    <h3><?php echo htmlspecialchars($row['report_type']); ?></h3>
                    <p><b>Floor:</b> <?php echo htmlspecialchars($row['floor']); ?></p>
                    <p><b>Room:</b> <?php echo htmlspecialchars($row['room_no']); ?></p>
                    <p><b>Impact:</b> <?php echo htmlspecialchars($row['impact_level']); ?></p>
                    <p><b>Description:</b> <?php echo htmlspecialchars($row['description']); ?></p>
                    <br>
                    
                    <!-- 🟢 DYNAMIC BADGE WITH TEXT & COLOR -->
                    <span class="badge <?php echo $badge_class; ?>">
                        <?php echo htmlspecialchars(ucfirst($status)); ?>
                    </span>
                    
                    <br><br>
                    <small>📅 <?php echo date('d F Y - h:i A', strtotime($row['created_at'])); ?></small>
                </div>
                <?php
            }
        } else {
            echo "<p style='margin-top: 15px;'>No reports found.</p>";
        }
    } else {
        echo "<p style='color:red;'>Query Failed: " . $conn->error . "</p>";
    }

    $conn->close();
    ?>

</div>

</div>

<script>
const btn = document.getElementById("themeBtn");

if(localStorage.getItem("theme")=="dark"){
    document.body.classList.add("dark-mode");
}

changeText();

btn.onclick=function(){
    document.body.classList.toggle("dark-mode");

    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme","dark");
    }else{
        localStorage.setItem("theme","light");
    }

    changeText();
};

function changeText(){
    if(document.body.classList.contains("dark-mode")){
        btn.innerHTML="☀️ Light Mode";
    }else{
        btn.innerHTML="🌙 Dark Mode";
    }
}
</script>

</body>
</html>