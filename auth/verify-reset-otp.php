<?php
session_start();
require_once '../includes/db.php';

$error = '';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot-password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_array = isset($_POST['otp']) ? $_POST['otp'] : [];
    $submitted_otp = trim(implode('', $otp_array));
    $submitted_otp = preg_replace('/[^0-9]/', '', $submitted_otp);

    $email_to_verify = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email_to_verify) || !filter_var($email_to_verify, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($submitted_otp) !== 6) {
        $error = "Please enter the complete 6-digit code.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, email, reset_token, reset_expiry 
             FROM users 
             WHERE email = ? 
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email_to_verify);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = "User not found.";
        } elseif (empty($user['reset_token']) || $user['reset_token'] !== $submitted_otp) {
            $error = "Invalid reset code. Please try again.";
        } elseif (empty($user['reset_expiry']) || strtotime($user['reset_expiry']) < time()) {
            $error = "This code has expired. Please request a new one.";
        } else {
            $_SESSION['reset_email'] = $email_to_verify;
            $_SESSION['reset_verified'] = true;

            header("Location: new-password.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Reset Code - UniStay</title>
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
            font-size: 34px;
            border: 4px solid #b2dfdb;
        }

        .auth-container h2 {
            margin: 10px 0 12px;
            color: #004d40;
            font-size: 24px;
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
        }
    </style>
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="auth-page">
    <div class="auth-container">

        <div class="otp-header-icon">🔒</div>

        <h2>Verify Reset Code</h2>

        <p class="subtitle">
            Enter the 6-digit reset code sent to<br>
            <strong><?php echo htmlspecialchars($email); ?></strong>
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="verify-reset-otp.php?email=<?php echo urlencode($email); ?>" method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

            <div class="otp-input-group">
                <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required autofocus>
                <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
                <input type="text" name="otp[]" class="otp-box" inputmode="numeric" pattern="[0-9]*" maxlength="1" required>
            </div>

            <button type="submit" class="btn">Verify Code</button>
        </form>

        <div class="auth-links">
            <a href="forgot-password.php">Request New Code</a>
            |
            <a href="login.php">Back to Login</a>
        </div>

    </div>
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
</script>

<script src="/UniStay/assets/js/theme.js"></script>
</body>
</html>