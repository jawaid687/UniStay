<?php
session_start();
require_once '../includes/db.php';

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$error = '';
$success = '';

$name = '';
$email = '';
$institutional_id = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $institutional_id = trim($_POST['institutional_id'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($institutional_id) || empty($role) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!in_array($role, ['student', 'staff', 'admin'])) {
        $error = "Invalid role selected.";
    } else {
        $check_stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ? OR institutional_id = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($check_stmt, "ss", $email, $institutional_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "Email or Institutional ID is already registered.";
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $otp = rand(100000, 999999);
            $otp_expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            mysqli_begin_transaction($conn);

            try {
                $insert_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO users 
                    (name, email, institutional_id, role, password, otp_code, otp_expiry)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "sssssss",
                    $name,
                    $email,
                    $institutional_id,
                    $role,
                    $hashed_password,
                    $otp,
                    $otp_expiry
                );

                if (!mysqli_stmt_execute($insert_stmt)) {
                    throw new Exception("Database insert failed.");
                }

                mysqli_stmt_close($insert_stmt);

                $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;

                $mail->Username   = 'unistayhallportal@gmail.com';

                /*
                    Paste your Gmail App Password below.
                    Do not use your normal Gmail password.
                */
                $mail->Password   = 'sovuhuibsfnklsip';

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('unistayhallportal@gmail.com', 'UniStay Portal');
                $mail->addAddress($email, $name);

                $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

                $mail->isHTML(true);
                $mail->Subject = 'UniStay Portal - Verification OTP';
                $mail->Body    = "
                    Hello <b>{$safe_name}</b>,<br><br>
                    Welcome to <b>UniStay Hall Portal</b>.<br>
                    Your registration OTP is: <b style='font-size:18px;'>{$otp}</b><br><br>
                    This OTP is valid for 10 minutes.<br><br>
                    Thank you.
                ";

                $mail->send();

                mysqli_commit($conn);

                header("Location: verify-otp.php?email=" . urlencode($email));
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);

                $error = "OTP could not be sent. Registration was not completed. Please check your email settings and try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - UniStay Hall Portal</title>
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
            font-size: 26px;
            font-weight: 800;
        }

        .auth-logo p {
            margin: 8px 0 0;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-container h2 {
            margin: 0 0 20px;
            color: #004d40;
            text-align: center;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            background: white;
            color: #1f2937;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            margin-top: 5px;
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
            margin-top: 20px;
        }

        .auth-links p {
            margin: 0;
            color: #555;
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

        body.dark-mode .auth-container {
            background: #111827 !important;
            color: #e5e7eb !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.45) !important;
        }

        body.dark-mode .auth-logo h1,
        body.dark-mode .auth-container h2 {
            color: #7dd3fc !important;
        }

        body.dark-mode .auth-logo p,
        body.dark-mode .auth-links p,
        body.dark-mode .form-group label {
            color: #cbd5e1 !important;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group select {
            background: #1e293b !important;
            color: #e5e7eb !important;
            border-color: #334155 !important;
        }

        body.dark-mode .form-group input::placeholder {
            color: #94a3b8 !important;
        }

        body.dark-mode .site-footer {
            color: #cbd5e1 !important;
        }

        @media (max-width: 520px) {
            .auth-container {
                padding: 25px 20px;
            }

            .auth-logo h1 {
                font-size: 22px;
            }
        }
    </style>

    <link rel="stylesheet" href="/UniStay/assets/css/theme.css">
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="auth-page">
    <div class="auth-container">

        <div class="auth-logo">
            <h1>UniStay Hall Portal</h1>
            <p>Create your hostel portal account to access student, staff, or admin services.</p>
        </div>

        <h2>Create an Account</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input 
                    type="text" 
                    name="name" 
                    id="name" 
                    value="<?php echo htmlspecialchars($name); ?>" 
                    placeholder="Enter your full name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    value="<?php echo htmlspecialchars($email); ?>" 
                    placeholder="Enter your email address"
                    required
                >
            </div>

            <div class="form-group">
                <label for="institutional_id">Student / Employee ID</label>
                <input 
                    type="text" 
                    name="institutional_id" 
                    id="institutional_id" 
                    value="<?php echo htmlspecialchars($institutional_id); ?>" 
                    placeholder="Enter your DIU ID"
                    required
                >
            </div>

            <div class="form-group">
                <label for="role">Register As</label>
                <select name="role" id="role" required>
                    <option value="" disabled <?php if ($role == '') echo 'selected'; ?>>Select your role...</option>
                    <option value="student" <?php if ($role == 'student') echo 'selected'; ?>>Student</option>
                    <option value="staff" <?php if ($role == 'staff') echo 'selected'; ?>>Staff</option>
                    <option value="admin" <?php if ($role == 'admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    placeholder="Minimum 6 characters"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input 
                    type="password" 
                    name="confirm_password" 
                    id="confirm_password" 
                    placeholder="Re-enter your password"
                    required
                >
            </div>

            <button type="submit" class="btn">Register</button>
        </form>

        <div class="auth-links">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>

    </div>
</div>

<div class="site-footer">
    Developed by <strong>Jawaid Hossain</strong> | Daffodil International University
</div>

<script src="/UniStay/assets/js/theme.js"></script>
</body>
</html>