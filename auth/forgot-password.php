<?php
session_start();
require_once '../includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $query = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $name = $user['name'];
            
            // Generate a 6-digit OTP
            $reset_otp = rand(100000, 999999);
            $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

            // Save the OTP in the database
            $update_query = "UPDATE users SET reset_token = '$reset_otp', reset_expiry = '$expiry' WHERE email = '$email'";
            
            if (mysqli_query($conn, $update_query)) {
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'jawaidhossain5@gmail.com'; 
                    $mail->Password   = 'dtahbiwbifvmtugk';  
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('jawaidhossain5@gmail.com', 'Auth System');
                    $mail->addAddress($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Code';
                    $mail->Body    = "Hello $name,<br><br>Your 6-digit password reset code is: <b style='font-size:24px; color:#007bff;'>$reset_otp</b><br><br>This code will expire in 15 minutes. Do not share it with anyone.";

                    $mail->send();
                    
                    header("Location: verify-reset-otp.php?email=" . urlencode($email));
                    exit();
                } catch (Exception $e) {
                    $error = "Could not send email. Please try again.";
                }
            } else {
                $error = "Database error. Please try again.";
            }
        } else {
            $error = "If an account with that email exists, a code has been sent.";
        }
    }
}

$page_title = 'Forgot Password';
$css_file = 'forgot-password.css';
include '../includes/header.php';
?>

<div class="auth-container">
    <h2>Reset Password</h2>
    <p style="font-size: 14px; color: #666; margin-bottom: 20px; text-align: center;">
        Enter the email address associated with your account to receive a 6-digit reset code.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="forgot-password.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required autofocus>
        </div>
        
        <button type="submit" class="btn">Send Reset Code</button>
    </form>

    <div class="auth-links" style="margin-top: 15px;">
        <p><a href="login.php">Back to Login</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>