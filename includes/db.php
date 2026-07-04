<?php
// Database configuration for local XAMPP environment
$host = "localhost";
$db_user = "root";     // Default XAMPP username
$db_pass = "";         // Default XAMPP password (empty)
$db_name = "auth_system";

// Establish the connection
$conn = mysqli_connect($host, $db_user, $db_pass, $db_name);

// Check the connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>