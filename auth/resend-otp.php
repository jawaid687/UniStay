<?php
session_start();
require_once '../includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if (!isset($_GET['email']) || empty($_GET['email'])) {
    header("Location: register.php");
    exit();
}

$email = trim($_GET['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_msg'] = "Invalid email address.";
    header("Location: register.php");
    exit();
}

// Check if user exists and is not verified
$stmt = mysqli_prepare($conn, "SELECT id, name, email, is_verified FROM users WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['error_msg'] = "User not found.";
    header("Location: register.php");
    exit();
}

if ($user['is_verified'] == 1) {
    $_SESSION['error_msg'] = "This account is already verified. Please login.";
    header("Location: login.php");
    exit();
}

$name = $user['name'];
$new_otp = rand(100000, 999999);
$new_expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

// Send email first
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    // Your Gmail address
    $mail->Username   = 'jawaidhossain5@gmail.com';

    // Put your NEW Gmail App Password here
    $mail->Password   = 'dtahbiwbifvmtugk';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Important: prevents very long loading
    $mail->Timeout = 10;
    $mail->SMTPKeepAlive = false;

    $mail->setFrom('jawaidhossain5@gmail.com', 'DIU Hostel Management System');
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Your New Verification OTP - DIU Hostel Management System';

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2 style='color:#004d40;'>DIU Hostel Management System</h2>
            <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>You requested a new verification code.</p>
            <p>Your new OTP is:</p>
            <h1 style='color:#00897b; letter-spacing:4px;'>$new_otp</h1>
            <p>This OTP is valid for <strong>10 minutes</strong>.</p>
            <br>
            <p>If you did not request this, please ignore this email.</p>
        </div>
    ";

    $mail->AltBody = "Hello $name, your new OTP is $new_otp. This OTP is valid for 10 minutes.";

    $mail->send();

    // Update database only after email is successfully sent
    $update_stmt = mysqli_prepare($conn, "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
    mysqli_stmt_bind_param($update_stmt, "sss", $new_otp, $new_expiry, $email);

    if (mysqli_stmt_execute($update_stmt)) {
        $_SESSION['success_msg'] = "A new 6-digit OTP has been sent to your email.";
    } else {
        $_SESSION['error_msg'] = "OTP email was sent, but database update failed.";
    }

    mysqli_stmt_close($update_stmt);

} catch (Exception $e) {
    $_SESSION['error_msg'] = "Failed to send new OTP. Please check your internet, Gmail App Password, or SMTP settings.";
}

header("Location: verify-otp.php?email=" . urlencode($email));
exit();
?>