<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ডাটাবেজ কানেকশন
$conn = new mysqli("localhost", "root", "", "auth_system");

if($conn->connect_error){
    die("Database Connection Failed: " . $conn->connect_error);
}

// 🟢 ১. আপডেট লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {

    $id = intval($_POST['id']);
    $status = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE staff_messages SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if($stmt->execute()){
        header("Location: staff-messages.php");
        exit();
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
    $stmt->close();
}

// 🔴 ২. ডিলিট লজিক
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_report'])) {
    $id = intval($_POST['id']);
    
    $stmt_del = $conn->prepare("DELETE FROM staff_messages WHERE id = ?");
    $stmt_del->bind_param("i", $id);
    
    if($stmt_del->execute()){
        header("Location: staff-messages.php");
        exit();
    }
    $stmt_del->close();
}

// ডাটা ফেচ করা
$sql = "
SELECT 
    staff_messages.*,
    users.name AS staff_name
FROM staff_messages
LEFT JOIN users ON staff_messages.staff_id = users.id
ORDER BY staff_messages.id DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Messages</title>

<style>
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body {
    background:#f1f5f9;
    padding:30px;
    transition:.3s;
}

.container {
    max-width:1100px;
    margin:auto;
}

.card {
    background:white;
    padding:30px;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding-bottom:20px;
    border-bottom:1px solid #ddd;
    margin-bottom:25px;
}

.header h2 {
    color:#00897b;
}

.header-btn {
    display:flex;
    gap:10px;
    align-items:center;
}

.btn {
    padding:10px 18px;
    border:none;
    border-radius:8px;
    color:white;
    cursor:pointer;
    text-decoration:none;
    font-weight:bold;
}

.dashboard {
    background:#64748b;
}

.theme {
    background:#004d40;
}

.search {
    width:100%;
    padding:13px;
    border-radius:10px;
    border:1px solid #ccc;
    margin-bottom:20px;
    font-size:15px;
}

.report {
    background:#f8fafc;
    padding:25px;
    border-radius:15px;
    border-left:6px solid #00897b;
    margin-bottom:20px;
}

.report h3 {
    color:#00897b;
    margin-bottom:15px;
}

.info p {
    line-height:1.8;
}

.badge {
    padding:7px 18px;
    border-radius:20px;
    color:white;
    font-weight:bold;
    display:inline-block;
}

.pending { background:#f59e0b; }
.approved { background:#10b981; }
.solved { background:#0284c7; }
.rejected { background:#ef4444; }

.action-area {
    display:flex;
    gap:10px;
    margin-top:20px;
    align-items:center;
}

select {
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}

.update-btn {
    background:#00897b;
}

.delete-btn {
    background:#ef4444;
}

button {
    padding:10px 18px;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

/* DARK MODE */
body.dark {
    background:#020617;
}

body.dark .card {
    background:#0f172a;
    color:white;
}

body.dark .report {
    background:#1e293b;
    color:white;
}

body.dark select,
body.dark .search {
    background:#1e293b;
    color:white;
    border:1px solid #475569;
}

body.dark h2 {
    color:#7dd3fc;
}

body.dark .info p {
    color:white;
}

@media(max-width:700px){
    .header {
        flex-direction:column;
        gap:15px;
    }
    .action-area {
        flex-direction:column;
        align-items: stretch;
    }
}
</style>
</head>

<body>

<div class="container">
    <div class="card">

        <div class="header">
            <h2>📩 Staff Messages</h2>
            <div class="header-btn">
                <a href="dashboard.php" class="btn dashboard">← Dashboard</a>
                <button onclick="darkMode()" class="btn theme">🌙 Dark Mode</button>
            </div>
        </div>

        <input type="text" id="search" class="search" placeholder="🔍 Search complaint..." onkeyup="searchReport()">

        <!-- 🔄 DYNAMIC REPORT CARDS FROM DATABASE -->
        <?php
        if($result && $result->num_rows > 0){
            while($row = $result->fetch_assoc()){
                
                // স্ট্যাটাস প্রসেসিং
                $status_from_db = !empty($row['status']) ? trim($row['status']) : 'Pending';
                $status_lower = strtolower($status_from_db);
                
                // ডাইনামিক ব্যাজ ক্লাস সেট করা
                $badge_class = "pending";
                if ($status_lower === 'approved' || $status_lower === 'approve') {
                    $badge_class = "approved";
                } elseif ($status_lower === 'solved') {
                    $badge_class = "solved";
                } elseif ($status_lower === 'rejected') {
                    $badge_class = "rejected";
                }
        ?>

        <div class="report">
            <h3>👤 <?php echo htmlspecialchars($row['staff_name'] ?? 'MD Roy'); ?></h3>

            <div class="info">
                <p><b>Floor:</b> <?php echo htmlspecialchars($row['floor']); ?></p>
                <p><b>Room:</b> <?php echo htmlspecialchars($row['room_no']); ?></p>
                <p><b>Problem:</b> <?php echo htmlspecialchars($row['report_type']); ?></p>
                <p><b>Impact:</b> <?php echo htmlspecialchars($row['impact_level']); ?></p>
                <p><b>Description:</b> <?php echo htmlspecialchars($row['description']); ?></p>
            </div>

            <br>

            <!-- ডাইনামিক স্ট্যাটাস ব্যাজ -->
            <span class="badge <?php echo $badge_class; ?>">
                <?php echo htmlspecialchars(ucfirst($status_from_db)); ?>
            </span>

            <div class="action-area">
                <!-- আপডেট ফর্ম -->
                <form method="POST" action="" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

                    <select name="status">
                        <option value="Pending" <?php if($status_lower == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="Approved" <?php if($status_lower == 'approved' || $status_lower == 'approve') echo 'selected'; ?>>Approved</option>
                        <option value="Solved" <?php if($status_lower == 'solved') echo 'selected'; ?>>Solved</option>
                        <option value="Rejected" <?php if($status_lower == 'rejected') echo 'selected'; ?>>Rejected</option>
                    </select>

                    <button type="submit" name="update_status" class="update-btn">Update</button>
                </form>

                <!-- ডিলিট ফর্ম -->
                <form method="POST" action="" onsubmit="return confirm('Delete this report?');">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="delete_report" class="delete-btn">Delete</button>
                </form>
            </div>

            <br>
            <small>📅 <?php echo date('d F Y - h:i A', strtotime($row['created_at'])); ?></small>
        </div>

        <?php
            }
        } else {
            echo "<h3>No Staff Reports Found</h3>";
        }
        $conn->close();
        ?>

    </div>
</div>

<script>
// DARK MODE
function darkMode(){
    document.body.classList.toggle("dark");
    if(document.body.classList.contains("dark")){
        localStorage.setItem("theme","dark");
    } else {
        localStorage.setItem("theme","light");
    }
}

// KEEP DARK MODE AFTER RELOAD
window.onload=function(){
    if(localStorage.getItem("theme")=="dark"){
        document.body.classList.add("dark");
    }
}

// SEARCH FUNCTION
function searchReport(){
    let input = document.getElementById("search").value.toLowerCase();
    let reports = document.getElementsByClassName("report");

    for(let i=0; i<reports.length; i++){
        let text = reports[i].innerText.toLowerCase();
        if(text.includes(input)){
            reports[i].style.display = "block";
        } else {
            reports[i].style.display = "none";
        }
    }
}
</script>

</body>
</html>