<?php
session_start();
require_once '../includes/db.php';

// Kick them out if they haven't verified an OTP code first
if (!isset($_SESSION['reset_email'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the password, and also reset failed_attempts to unlock the account!
        $update_query = "UPDATE users SET password = '$hashed_password', failed_attempts = 0, locked_until = NULL WHERE email = '$email'";
        
        if (mysqli_query($conn, $update_query)) {
            // Remove the temporary reset session pass
            unset($_SESSION['reset_email']);
            
            // Set a success message for the login page
            $_SESSION['success_msg'] = "Password reset successfully! You can now log in.";
            
            header("Location: login.php");
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

$page_title = 'Create New Password';
$css_file = 'forgot-password.css';
include '../includes/header.php';
?>

<div class="auth-container">
    <h2>Create New Password</h2>
    <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
        Please enter your new password below.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="new-password.php" method="POST">
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" name="password" id="password" required autofocus>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
        </div>
        
        <button type="submit" class="btn">Save New Password</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>