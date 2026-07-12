<?php
session_start();
require_once '../includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        // Check user exists
        $stmt = mysqli_prepare($conn, "SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = "No account found with this email address.";
        } else {
            $reset_code = (string) rand(100000, 999999);
            $reset_expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            $mail = new PHPMailer(true);

            try {
                // SMTP settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;

                // Gmail account
                $mail->Username   = 'unistayhallportal@gmail.com';

                // Use NEW 16-character Gmail App Password here, no spaces
                $mail->Password   = 'dtahbiwbifvmtugk';

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Prevent long loading
                $mail->Timeout = 15;
                $mail->SMTPKeepAlive = false;

                // Localhost/XAMPP SSL helper
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                $mail->CharSet = 'UTF-8';

                $mail->setFrom('unistayhallportal@gmail.com', 'UniStay');
                $mail->addAddress($email, $user['name']);

                $mail->isHTML(true);
                $mail->Subject = 'UniStay Password Reset Code';

                $safe_name = htmlspecialchars($user['name']);

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px;'>
                        <h2 style='color:#004d40;'>UniStay Password Reset</h2>
                        <p>Hello <strong>{$safe_name}</strong>,</p>
                        <p>You requested to reset your password.</p>
                        <p>Your reset code is:</p>
                        <h1 style='color:#00897b; letter-spacing:4px;'>{$reset_code}</h1>
                        <p>This code is valid for <strong>10 minutes</strong>.</p>
                        <br>
                        <p>If you did not request this, please ignore this email.</p>
                    </div>
                ";

                $mail->AltBody = "Hello {$user['name']}, your UniStay password reset code is {$reset_code}. This code is valid for 10 minutes.";

                // Send email first
                $mail->send();

                // If email sent successfully, save reset code in database
                $update_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?"
                );

                mysqli_stmt_bind_param($update_stmt, "sss", $reset_code, $reset_expiry, $email);

                if (mysqli_stmt_execute($update_stmt)) {
                    mysqli_stmt_close($update_stmt);

                    header("Location: verify-reset-otp.php?email=" . urlencode($email));
                    exit();
                } else {
                    mysqli_stmt_close($update_stmt);
                    $error = "Reset code was sent, but database update failed.";
                }

            } catch (Exception $e) {
                // Development error message. This shows the real PHPMailer problem.
                $error = "Mailer Error: " . $mail->ErrorInfo;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/UniStay/assets/css/theme.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
            color: #1f2937;
        }

        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.10);
            border: 1px solid #d9eeee;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .auth-logo h1 {
            margin: 0;
            color: #004d40;
            font-size: 30px;
            font-weight: 800;
        }

        .auth-logo p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 10px;
        }

        .auth-container h2 {
            text-align: center;
            color: #004d40;
            margin-bottom: 22px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #1f2937;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #00897b;
            box-shadow: 0 0 0 3px rgba(0, 137, 123, 0.15);
        }

        .btn {
            width: 100%;
            background: #00897b;
            color: white;
            border: none;
            padding: 13px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #00695c;
            transform: translateY(-2px);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            border-left: 5px solid #dc3545;
            line-height: 1.5;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            border-left: 5px solid #28a745;
        }

        .auth-links {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
        }

        .auth-links a {
            color: #00897b;
            font-weight: bold;
            text-decoration: none;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }

        .site-footer {
            text-align: center;
            color: #555;
            font-size: 14px;
            margin-top: 18px;
        }

        @media (max-width: 520px) {
            .auth-container {
                padding: 25px 20px;
            }

            .auth-logo h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="auth-page">
    <div>
        <div class="auth-container">

            <div class="auth-logo">
                <h1>UniStay</h1>
                <p>
                    Enter your registered email address.
                    We will send a 6-digit reset code to your email.
                </p>
            </div>

            <h2>Reset Password</h2>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="forgot-password.php">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email address" required>
                </div>

                <button type="submit" class="btn">Send Reset Code</button>
            </form>

            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>

        </div>

        <div class="site-footer">
            Project by <strong>Jawaid</strong> | Daffodil International University
        </div>
    </div>
</div>

<script src="/UniStay/assets/js/theme.js"></script>
</body>
</html>