<?php

session_start();

require_once "../includes/db.php";

// Add Complaint Timeline Function

function addTimeline($complaint_id, $status_change, $note)
{
    global $conn;


    $sql = "INSERT INTO complaint_timeline
            (
                complaint_id,
                status_change,
                note,
                changed_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                NOW()
            )";


    $stmt = mysqli_prepare($conn, $sql);


    if(!$stmt)
    {
        die("Timeline Insert Error: ".mysqli_error($conn));
    }


    mysqli_stmt_bind_param(
        $stmt,
        "iss",
        $complaint_id,
        $status_change,
        $note
    );


    mysqli_stmt_execute($stmt);


    mysqli_stmt_close($stmt);
}


if(!isset($_GET['id']))
{
    die("Complaint ID Missing!");
}


$complaint_id = intval($_GET['id']);



$query = mysqli_query(
    $conn,
    "SELECT * FROM complaints 
     WHERE complaint_id='$complaint_id'"
);



if(mysqli_num_rows($query)==0)
{
    die("Complaint Not Found!");
}


$complaint=mysqli_fetch_assoc($query);



if(isset($_POST['submit']))
{

    $student_id=$complaint['student_id'];

    $resolved=$_POST['resolved'];

    $comment=mysqli_real_escape_string(
        $conn,
        $_POST['comment']
    );


    if($resolved=="Yes")
    {

        $rating=intval($_POST['rating']);


        mysqli_query(
            $conn,

            "INSERT INTO complaint_rating
            (
            complaint_id,
            student_id,
            resolved_confirmed,
            rating,
            comment,
            submitted_at
            )

            VALUES
            (
            '$complaint_id',
            '$student_id',
            1,
            '$rating',
            '$comment',
            NOW()
            )"
        );


        addTimeline(
            $complaint_id,
            "Feedback",
            "Student rated ".$rating."/5"
        );


        echo "
        <script>
        alert('Thank you for your feedback.');
        window.location='complaint_status.php?student_id=$student_id';
        </script>";

    }

    else
    {


        mysqli_query(
            $conn,

            "UPDATE complaints
             SET status='Reopened'
             WHERE complaint_id='$complaint_id'"
        );


        mysqli_query(
            $conn,

            "INSERT INTO complaint_rating
            (
            complaint_id,
            student_id,
            resolved_confirmed,
            rating,
            comment,
            submitted_at
            )

            VALUES
            (
            '$complaint_id',
            '$student_id',
            0,
            NULL,
            '$comment',
            NOW()
            )"
        );


        addTimeline(
            $complaint_id,
            "Reopened",
            "Student reported issue not fixed."
        );


        echo "
        <script>
        alert('Complaint Reopened.');
        window.location='complaint_status.php?student_id=$student_id';
        </script>";

    }

}

?>

<!DOCTYPE html>

<html>

<head>

<title>Complaint Feedback | UniStay</title>


<style>


body{

margin:0;
padding:30px;
background:#f4f8f7;
font-family:'Segoe UI',sans-serif;
transition:.3s;

}



.container{

max-width:700px;
margin:auto;

}



.card{

background:white;
padding:30px;
border-radius:15px;
box-shadow:0 8px 20px rgba(0,0,0,.1);
border-top:6px solid #00897b;

}



.header{

display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:25px;

}



h2{

color:#004d40;

}



button,
.back-btn{

padding:10px 18px;
border:none;
border-radius:8px;
cursor:pointer;
color:white;
font-weight:bold;
text-decoration:none;

}



.back-btn{

background:#6c757d;

}



.theme-btn{

background:#00897b;

}



.info{

background:#e0f2f1;
padding:15px;
border-left:5px solid #00897b;
border-radius:8px;
margin-bottom:20px;

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
border:1px solid #ccc;
border-radius:8px;
font-size:15px;

}



textarea{

resize:none;

}



.submit-btn{

margin-top:20px;
width:100%;
padding:14px;
background:#00897b;
color:white;
border:none;
border-radius:8px;
font-size:16px;
font-weight:bold;
cursor:pointer;

}



.submit-btn:hover{

background:#004d40;

}



/* Dark Mode */


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



.dark-mode .info{

background:#1e293b;
color:white;

}



.dark-mode select,
.dark-mode textarea{

background:#1e293b;
color:white;
border-color:#475569;

}



</style>


</head>


<body>


<div class="container">


<div class="card">


<div class="header">


<h2>
Complaint Feedback
</h2>


<div>

<a href="complaint_status.php?student_id=<?php echo $complaint['student_id']; ?>" 
class="back-btn">
← Back
</a>


<button class="theme-btn" id="themeToggle">
🌙 Dark
</button>

</div>


</div>



<div class="info">

<strong>
Complaint ID:
</strong>

<?php echo $complaint_id; ?>


<br>


<strong>
Category:
</strong>

<?php echo htmlspecialchars($complaint['category']); ?>


<br>


<strong>
Current Status:
</strong>

<?php echo htmlspecialchars($complaint['status']); ?>


</div>




<form method="POST">



<label>
Is your complaint resolved?
</label>


<select name="resolved" id="resolved">

<option value="Yes">
Yes
</option>

<option value="No">
No
</option>


</select>





<div id="ratingBox">


<label>
Rate our service (1-5)
</label>


<select name="rating">


<option value="5">
⭐⭐⭐⭐⭐ Excellent
</option>


<option value="4">
⭐⭐⭐⭐ Good
</option>


<option value="3">
⭐⭐⭐ Average
</option>


<option value="2">
⭐⭐ Poor
</option>


<option value="1">
⭐ Very Bad
</option>


</select>


</div>




<label>
Comment
</label>


<textarea
name="comment"
rows="5"
placeholder="Write your feedback..."
></textarea>



<input 
class="submit-btn"
type="submit"
name="submit"
value="Submit Feedback">


</form>


</div>


</div>



<script>


const btn=document.getElementById("themeToggle");

const body=document.body;



if(localStorage.getItem("theme")=="dark")
{
body.classList.add("dark-mode");
btn.innerHTML="☀️ Light";
}



btn.onclick=function(){

body.classList.toggle("dark-mode");


if(body.classList.contains("dark-mode"))
{
localStorage.setItem("theme","dark");
btn.innerHTML="☀️ Light";
}

else
{
localStorage.setItem("theme","light");
btn.innerHTML="🌙 Dark";
}

}




// Hide rating if complaint not solved


const resolved=document.getElementById("resolved");

const ratingBox=document.getElementById("ratingBox");


resolved.onchange=function(){


if(this.value=="No")
{

ratingBox.style.display="none";

}

else
{

ratingBox.style.display="block";

}


}


</script>



</body>

</html>