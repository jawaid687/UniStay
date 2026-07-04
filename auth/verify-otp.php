<?php
session_start();
require_once '../includes/db.php';

// Catch messages passed from resend-otp.php
$error = isset($_SESSION['error_msg']) ? $_SESSION['error_msg'] : '';
$success = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';

unset($_SESSION['error_msg']);
unset($_SESSION['success_msg']);

// Get email from URL
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    header("Location: register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp_array = isset($_POST['otp']) ? $_POST['otp'] : [];
    $submitted_otp = mysqli_real_escape_string($conn, trim(implode('', $otp_array)));
    $email_to_verify = mysqli_real_escape_string($conn, $_POST['email']);

    if (strlen($submitted_otp) < 6) {
        $error = "Please enter the complete 6-digit OTP.";
    } else {
        $query = "SELECT * FROM users WHERE email = '$email_to_verify' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $current_time = date("Y-m-d H:i:s");

            if ($user['otp_code'] !== $submitted_otp) {
                $error = "Invalid OTP code. Please try again.";
            } elseif ($user['otp_expiry'] < $current_time) {
                $error = "This OTP has expired. Please request a new OTP.";
            } else {
                $update_query = "UPDATE users 
                                 SET is_verified = 1, otp_code = NULL, otp_expiry = NULL 
                                 WHERE email = '$email_to_verify'";

                if (mysqli_query($conn, $update_query)) {
                    $success = "Account verified successfully! You can now login.";
                } else {
                    $error = "Failed to update account status.";
                }
            }
        } else {
            $error = "User not found.";
        }
    }
}

$is_verified_success = ($success === "Account verified successfully! You can now login.");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - DIU Hostel Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
            max-width: 470px;
            background: #ffffff;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.10);
            border: 1px solid #d9eeee;
            text-align: center;
        }

        .otp-header-icon {
            width: 85px;
            height: 85px;
            background: #e0f2f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            color: #00897b;
            font-weight: bold;
            font-size: 22px;
            border: 4px solid #b2dfdb;
        }

        .auth-container h1 {
            margin: 0;
            color: #004d40;
            font-size: 25px;
            font-weight: 800;
        }

        .auth-container h2 {
            margin: 10px 0 12px;
            color: #004d40;
            font-size: 22px;
        }

        .subtitle {
            font-size: 14px;
            margin-bottom: 18px;
            color: #555;
            line-height: 1.6;
        }

        .subtitle strong {
            color: #004d40;
        }

        .otp-input-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 25px 0;
        }

        .otp-box {
            width: 52px;
            height: 60px;
            font-size: 24px;
            text-align: center;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            background-color: #f8fafc;
            font-weight: bold;
            color: #1f2937;
            transition: all 0.2s ease-in-out;
        }

        .otp-box:focus {
            border-color: #00897b;
            background-color: #ffffff;
            outline: none;
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
            text-decoration: none;
            display: block;
            text-align: center;
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
            text-align: left;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
            border-left: 5px solid #28a745;
            text-align: left;
        }

        .resend-text {
            margin-top: 25px;
            font-size: 14px;
            color: #555;
            line-height: 1.6;
        }

        #timerText {
            color: #777;
        }

        #resendLink {
            display: none;
            color: #00897b;
            text-decoration: none;
            font-weight: bold;
        }

        #resendLink:hover {
            text-decoration: underline;
        }

        .auth-links {
            margin-top: 20px;
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
            padding: 18px 10px;
            color: #555;
            font-size: 14px;
        }

        @media (max-width: 520px) {
            .auth-container {
                padding: 25px 20px;
            }

            .otp-input-group {
                gap: 7px;
            }

            .otp-box {
                width: 43px;
                height: 54px;
                font-size: 21px;
            }

            .auth-container h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<div class="auth-page">
    <div class="auth-container">

        <div class="otp-header-icon">OTP</div>

        <h1>DIU Hostel Management System</h1>
        <h2>Verify Your Email</h2>

        <p class="subtitle">
            Enter the 6-digit verification code sent to<br>
            <strong><?php echo htmlspecialchars($email); ?></strong>
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>

            <?php if ($is_verified_success): ?>
                <a href="login.php" class="btn">Go to Login</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$is_verified_success): ?>
            <form action="verify-otp.php?email=<?php echo urlencode($email); ?>" method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                <div class="otp-input-group">
                    <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required autofocus>
                    <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                    <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                    <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                    <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                    <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                </div>

                <button type="submit" class="btn">Verify OTP</button>

                <p class="resend-text">
                    Didn't receive the code?<br>
                    <span id="timerText">Wait <span id="countdown">60</span>s to resend</span>
                    <a href="resend-otp.php?email=<?php echo urlencode($email); ?>" id="resendLink">Resend OTP</a>
                </p>
            </form>
        <?php endif; ?>

        <div class="auth-links">
            <a href="login.php">Back to Login</a>
        </div>

    </div>
</div>

<div class="site-footer">
    Project by <strong>Jawaid</strong> | Daffodil International University
</div>

<script>
    const otpBoxes = document.querySelectorAll('.otp-box');

    otpBoxes.forEach((box, index) => {
        box.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');

            if (e.target.value.length > 1) {
                e.target.value = e.target.value.slice(0, 1);
            }

            if (e.target.value.length === 1 && index < otpBoxes.length - 1) {
                otpBoxes[index + 1].focus();
            }
        });

        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                otpBoxes[index - 1].focus();
            }
        });

        box.addEventListener('paste', (e) => {
            e.preventDefault();

            const pastedData = e.clipboardData
                .getData('text')
                .replace(/[^0-9]/g, '')
                .slice(0, 6)
                .split('');

            otpBoxes.forEach((b, i) => {
                b.value = pastedData[i] || '';
            });

            if (pastedData.length > 0) {
                const nextIndex = Math.min(pastedData.length, otpBoxes.length) - 1;
                otpBoxes[nextIndex].focus();
            }
        });
    });

    let timeLeft = 60;
    const countdownEl = document.getElementById('countdown');
    const timerText = document.getElementById('timerText');
    const resendLink = document.getElementById('resendLink');

    if (countdownEl && timerText && resendLink) {
        const timerId = setInterval(() => {
            timeLeft--;
            countdownEl.textContent = timeLeft;

            if (timeLeft <= 0) {
                clearInterval(timerId);
                timerText.style.display = 'none';
                resendLink.style.display = 'inline';
            }
        }, 1000);
    }
</script>

</body>
</html>