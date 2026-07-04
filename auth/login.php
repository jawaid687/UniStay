<?php
session_start();
require_once '../includes/db.php';

// Detect failed attempt column name safely.
// Your old code used failed_attempts, but your SQL file may use login_attempts.
$attempt_column = 'failed_attempts';
$check_failed_col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'failed_attempts'");

if (!$check_failed_col || mysqli_num_rows($check_failed_col) == 0) {
    $attempt_column = 'login_attempts';
}

// If user is already logged in, redirect them based on role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'super_admin') {
        header("Location: ../super-admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'staff') {
        header("Location: ../staff/dashboard.php");
    } else {
        header("Location: ../student/dashboard.php");
    }
    exit();
}

$error = '';
$success = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';
unset($_SESSION['success_msg']);

$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $query = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            // Step 1: Brute-force lock check
            $is_locked = false;

            if (!empty($user['locked_until'])) {
                $locked_time = strtotime($user['locked_until']);
                $current_time = time();

                if ($current_time < $locked_time) {
                    $is_locked = true;
                    $minutes_left = ceil(($locked_time - $current_time) / 60);
                    $error = "Account locked due to multiple failed attempts. Please try again in $minutes_left minute(s).";
                }
            }

            if (!$is_locked) {

                // Step 2: Email verification check
                if ($user['is_verified'] == 0) {
                    $error = "Please verify your email first.";
                } else {

                    // Step 3: Password verification
                    if (password_verify($password, $user['password'])) {

                        // Password correct: reset attempts
                        mysqli_query($conn, "UPDATE users SET $attempt_column = 0, locked_until = NULL WHERE id = '{$user['id']}'");

                        // Step 4: Account approval check
                        if ($user['is_approved'] == 0 && $user['role'] !== 'super_admin') {
                            $error = "Email verified! However, your account is still pending approval from management.";
                        } else {

                            // Login success
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['name'] = $user['name'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['institutional_id'] = $user['institutional_id'];

                            // Remember Me
                            if ($remember_me) {
                                $token = bin2hex(random_bytes(32));
                                $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                                $user_id = $user['id'];

                                mysqli_query($conn, "UPDATE users SET remember_token = '$hashed_token' WHERE id = '$user_id'");

                                $cookie_value = $user['id'] . ':' . $token;

                                setcookie('remember_me', $cookie_value, [
                                    'expires' => time() + (86400 * 30),
                                    'path' => '/',
                                    'httponly' => true,
                                    'samesite' => 'Lax'
                                ]);
                            }

                            // Role-based redirect
                            if ($user['role'] === 'super_admin') {
                                header("Location: ../super-admin/dashboard.php");
                            } elseif ($user['role'] === 'admin') {
                                header("Location: ../admin/dashboard.php");
                            } elseif ($user['role'] === 'staff') {
                                header("Location: ../staff/dashboard.php");
                            } else {
                                header("Location: ../student/dashboard.php");
                            }
                            exit();
                        }

                    } else {
                        // Password incorrect
                        $current_attempts = isset($user[$attempt_column]) ? (int)$user[$attempt_column] : 0;
                        $attempts = $current_attempts + 1;
                        $max_attempts = 5;

                        if ($attempts >= $max_attempts) {
                            $locked_until = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                            mysqli_query(
                                $conn,
                                "UPDATE users 
                                 SET $attempt_column = $attempts, locked_until = '$locked_until' 
                                 WHERE id = '{$user['id']}'"
                            );

                            $error = "Too many failed attempts. Your account is locked for 15 minutes.";
                        } else {
                            mysqli_query(
                                $conn,
                                "UPDATE users 
                                 SET $attempt_column = $attempts 
                                 WHERE id = '{$user['id']}'"
                            );

                            $attempts_left = $max_attempts - $attempts;
                            $error = "Invalid email or password. You have $attempts_left attempt(s) remaining.";
                        }
                    }
                }
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - DIU Hostel Management System</title>
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
            max-width: 430px;
            background: var(--card-color, #ffffff);
            color: var(--text-color, #1f2937);
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.10);
            border: 1px solid var(--border-color, #d9eeee);
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .auth-logo h1 {
            margin: 0;
            color: var(--heading-color, #004d40);
            font-size: 26px;
            font-weight: 800;
        }

        .auth-logo p {
            margin: 8px 0 0;
            color: var(--muted-text, #555);
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-container h2 {
            margin: 0 0 20px;
            color: var(--heading-color, #004d40);
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
            color: var(--text-color, #1f2937);
            font-size: 14px;
        }

        .form-group input {
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

        .form-group input:focus {
            border-color: #00897b;
            box-shadow: 0 0 0 3px rgba(0, 137, 123, 0.15);
        }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .remember-box {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-box input {
            width: auto;
            margin: 0;
        }

        .remember-box label {
            margin: 0;
            font-weight: normal;
            font-size: 14px;
            cursor: pointer;
            color: var(--text-color, #1f2937);
        }

        .forgot-link {
            font-size: 14px;
            text-decoration: none;
            color: #00897b;
            font-weight: bold;
        }

        .forgot-link:hover {
            text-decoration: underline;
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
            color: var(--muted-text, #555);
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
            color: var(--muted-text, #555);
            font-size: 14px;
        }

        body.dark-mode .auth-container {
            background: #111827 !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.45) !important;
        }

        body.dark-mode .form-group input {
            background: #1e293b !important;
            color: #e5e7eb !important;
            border-color: #334155 !important;
        }

        body.dark-mode .form-group input::placeholder {
            color: #94a3b8 !important;
        }

        body.dark-mode .remember-box label {
            color: #e5e7eb !important;
        }

        @media (max-width: 520px) {
            .auth-container {
                padding: 25px 20px;
            }

            .auth-logo h1 {
                font-size: 22px;
            }

            .login-options {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <link rel="stylesheet" href="/auth-system/assets/css/theme.css">
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="auth-page">
    <div class="auth-container">

        <div class="auth-logo">
            <h1>DIU Hostel Management System</h1>
            <p>Login to access your hostel portal dashboard and services.</p>
        </div>

        <h2>Login to Your Account</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">

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
                <label for="password">Password</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    placeholder="Enter your password"
                    required
                >
            </div>

            <div class="login-options">
                <div class="remember-box">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <label for="remember_me">Remember Me</label>
                </div>

                <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>

    </div>
</div>

<div class="site-footer">
    Project by <strong>Jawaid</strong> | Daffodil International University
</div>

<script src="/auth-system/assets/js/theme.js"></script>
</body>
</html>