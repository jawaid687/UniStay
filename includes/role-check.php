<?php
function allowRoles($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: ../auth/login.php");
        exit();
    }
}
?>