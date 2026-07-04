<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head><title>Staff Dashboard</title></head>
<body style="padding: 50px; font-family: sans-serif;">
    <h1>Staff Portal</h1>
    <h2>Welcome, <?php echo $_SESSION['name']; ?> (Employee ID: <?php echo $_SESSION['institutional_id']; ?>)</h2>
    <p>This is the private area for university/company staff.</p>
    <a href="../auth/logout.php" style="color: red;">Logout</a>
</body>
</html>