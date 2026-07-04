<?php
// 1. Start the session so PHP knows WHICH session to destroy
session_start();

// 2. Unset all session variables
session_unset();

// 3. Destroy the session entirely
session_destroy();

// 4. Redirect the user back to the login page
header("Location: login.php");
exit();
?>