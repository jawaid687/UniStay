<?php 
require_once 'resident_guard.php'; 
require_once '../includes/db.php'; 

$user_id = intval($_SESSION['user_id'] ?? 0);
$name = $_SESSION['name'] ?? 'Student';
$institutional_id = $_SESSION['institutional_id'] ?? 'N/A';

$room_no = $_SESSION['room_no'] ?? 'N/A';
$seat_no = $_SESSION['seat_no'] ?? 'N/A';

if ($user_id > 0 && isset($conn)) {

    $query = "
    SELECT
        r.room_number,
        ra.seat_no

    FROM student_records sr

    INNER JOIN room_assignments ra
        ON sr.id = ra.student_record_id

    INNER JOIN rooms r
        ON ra.room_id = r.room_id

    WHERE sr.user_id = ?

    AND ra.assignment_status='active'

    LIMIT 1";

    $stmt = mysqli_prepare($conn,$query);

    if($stmt){

        mysqli_stmt_bind_param($stmt,"i",$user_id);

        mysqli_stmt_execute($stmt);

        $result=mysqli_stmt_get_result($stmt);

        if($row=mysqli_fetch_assoc($result))
        {
            $room_no=$row['room_number'];
            $seat_no=$row['seat_no'];
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Maintenance Request - UniStay</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link rel="stylesheet" href="../assets/css/theme.css">


<style>

body{

    margin:0;
    padding:25px;
    font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;

}


.container{

    max-width:850px;
    margin:40px auto;

}



.complaint-card{

    background:var(--card-bg, white);
    color:var(--text-color,#1f2937);

    padding:35px;
    border-radius:16px;

    box-shadow:0 10px 25px rgba(0,0,0,0.05);

    border-top:6px solid #00897b;

}



.card-header-section{

    display:flex;
    justify-content:space-between;
    align-items:center;

    margin-bottom:25px;

    border-bottom:1px solid var(--border-color,#e2e8f0);

    padding-bottom:15px;

}



h1{

    margin:0;
    color:#00897b;
    font-size:24px;

}



.student-info-badge{

    background:#e0f2f1;

    color:#004d40;

    padding:10px 16px;

    border-radius:8px;

    font-size:14px;

    font-weight:500;

    line-height:1.6;

}



.form-group{

    margin-bottom:22px;

}



label{

    display:block;

    font-weight:600;

    margin-bottom:8px;

    color:var(--text-color,#374151);

    font-size:15px;

}



select,
textarea,
input[type="file"]{


    width:100%;

    padding:12px;

    border-radius:8px;

    border:1px solid var(--border-color,#cbd5e1);

    background:var(--input-bg,#ffffff);

    color:var(--text-color,#1f2937);

    font-size:15px;

    box-sizing:border-box;

}



textarea{

    min-height:120px;

    resize:vertical;

}



.small-note{

    margin-top:6px;

    font-size:13px;

    color:#64748b;

}



.action-group{

    display:flex;

    gap:12px;

    margin-top:25px;

}



.btn{

    padding:12px 24px;

    text-decoration:none;

    border-radius:8px;

    font-weight:bold;

    cursor:pointer;

    font-size:15px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:none;

}



.btn-primary{

    background:#00897b;

    color:white;

    flex:2;

}



.btn-primary:hover{

    background:#004d40;

}



.btn-secondary{

    background:#64748b;

    color:white;

    flex:1;

}



.theme-toggle{

    background:transparent;

    border:1px solid var(--border-color,#cbd5e1);

    padding:8px 12px;

    border-radius:6px;

    cursor:pointer;

    color:var(--text-color,#111);

}



/* DARK MODE SUPPORT */

body.dark-mode .complaint-card{

    background:#0f172a;

    color:#e5e7eb;

}



body.dark-mode h1{

    color:#7dd3fc;

}



body.dark-mode label{

    color:#cbd5e1;

}



body.dark-mode select,
body.dark-mode textarea,
body.dark-mode input[type="file"]{


    background:#1e293b;

    color:white;

    border-color:#334155;

}



body.dark-mode .student-info-badge{

    background:#1e293b;

    color:#7dd3fc;

    border:1px solid #334155;

}



body.dark-mode .small-note{

    color:#94a3b8;

}



</style>


</head>


<body>


<div class="container">


<div class="complaint-card">


<div class="card-header-section">


<h1>
New Maintenance Request
</h1>


<button id="themeToggle" class="theme-toggle">
🌙 Dark Mode
</button>


</div>



<div class="form-group">


<div class="student-info-badge">


📍 
<strong>Resident:</strong>

<?php echo htmlspecialchars($name); ?>


|

<strong>ID:</strong>

<?php echo htmlspecialchars($institutional_id); ?>


|

<strong>Room No:</strong>

<?php echo htmlspecialchars($room_no); ?>


|

<strong>Seat No:</strong>

<?php echo htmlspecialchars($seat_no); ?>


</div>


</div>




<form method="POST" 
action="process_complaint.php" 
enctype="multipart/form-data">



<div class="form-group">


<label>
Issue Category *
</label>


<select name="category" required>


<option value="">
-- Select Category --
</option>


<option value="Electricity">
Electricity / Power Outage
</option>


<option value="Water">
Water Supply / Plumbing
</option>


<option value="Furniture">
Furniture Repair
</option>


<option value="Internet">
Internet / Wi-Fi Issues
</option>


<option value="Cleanliness">
Room / Corridor Cleaning
</option>


<option value="Other">
Other Problems
</option>


</select>


</div>





<div class="form-group">


<label>
Detailed Description *
</label>


<textarea 
name="description"
placeholder="Please explain the issue clearly..."
required></textarea>


<div class="small-note">

Provide room number or location details.

</div>


</div>





<div class="form-group">


<label>
Attachment / Photo Evidence
</label>


<input 
type="file"
name="complaint_photo"
accept="image/*">



<div class="small-note">

JPG, JPEG, PNG only. Maximum size 2MB.

</div>


</div>





<div class="action-group">


<a href="dashboard.php" 
class="btn btn-secondary">

Cancel

</a>



<button 
type="submit"
name="submit_complaint"
class="btn btn-primary">

Submit Request

</button>

</div>

</form>


</div>

</div>

<script src="../assets/js/theme.js"></script>

</body>

</html>