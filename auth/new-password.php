<?php
session_start();
require_once '../includes/db.php';

$error = '';
$success = '';

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    header("Location: forgot-password.php");
    exit();
}

$reset_email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in both password fields.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users 
             SET password = ?, reset_token = NULL, reset_expiry = NULL 
             WHERE email = ?"
        );

        mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $reset_email);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);

            $_SESSION['success_msg'] = "Password updated successfully. Please login with your new password.";

            header("Location: login.php");
            exit();
        } else {
            $error = "Failed to update password. Please try again.";
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Password - UniStay</title>
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
                    Create a new password for your account.<br>
                    Email: <strong><?php echo htmlspecialchars($reset_email); ?></strong>
                </p>
            </div>

            <h2>Create New Password</h2>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="new-password.php">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                </div>

                <button type="submit" class="btn">Save New Password</button>
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