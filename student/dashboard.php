<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head><title>Student Dashboard</title></head>
<body style="padding: 50px; font-family: sans-serif;">
    <button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>
    <h1>Student Portal</h1>
    <h2>Welcome, <?php echo $_SESSION['name']; ?> (Student ID: <?php echo $_SESSION['institutional_id']; ?>)</h2>
    <p>This is the private area for enrolled students.</p>
    <a href="../auth/logout.php" style="color: red;">Logout</a>
    <style>
    /* your page css */
</style>

<link rel="stylesheet" href="/auth-system/assets/css/theme.css">

</head>
</body>
</html>
