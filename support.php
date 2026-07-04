<?php
session_start();

/* ================= DATABASE CONNECTION ================= */

$db_loaded = false;

$possible_db_files = [
    __DIR__ . '/includes/db.php',
    __DIR__ . '/includes/config.php',
    __DIR__ . '/includes/connection.php',
    __DIR__ . '/includes/db_connect.php'
];

foreach ($possible_db_files as $db_file) {
    if (file_exists($db_file)) {
        require_once $db_file;
        $db_loaded = true;
        break;
    }
}

if (!isset($conn) && isset($mysqli)) {
    $conn = $mysqli;
}

if (!isset($conn) && isset($connection)) {
    $conn = $connection;
}

if (!$db_loaded || !isset($conn)) {
    die("Database connection not found. Please check your includes/db.php file.");
}

/* ================= SESSION / DASHBOARD ================= */

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$name_session = $_SESSION['name'] ?? '';
$email_session = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? '';

$dashboard_link = 'auth/login.php';
$dashboard_text = 'Dashboard';

if ($is_logged_in) {
    if ($role === 'super_admin') {
        $dashboard_link = 'super-admin/dashboard.php';
        $dashboard_text = 'Super Admin Dashboard';
    } elseif ($role === 'admin') {
        $dashboard_link = 'admin/dashboard.php';
        $dashboard_text = 'Admin Dashboard';
    } elseif ($role === 'student') {
        $dashboard_link = 'student/dashboard.php';
        $dashboard_text = 'Student Dashboard';
    } elseif ($role === 'staff') {
        $dashboard_link = 'stuff/dashboard.php';
        $dashboard_text = 'Staff Dashboard';
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ================= FORM PROCESS ================= */

$errors = [];
$success = false;

$form_name = $name_session;
$form_email = $email_session;
$form_subject = '';
$form_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_name = trim($_POST['name'] ?? '');
    $form_email = trim($_POST['email'] ?? '');
    $form_subject = trim($_POST['subject'] ?? '');
    $form_message = trim($_POST['message'] ?? '');

    if ($form_name === '') {
        $errors[] = "Name is required.";
    }

    if ($form_email === '') {
        $errors[] = "Email is required.";
    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($form_subject === '') {
        $errors[] = "Subject is required.";
    }

    if ($form_message === '') {
        $errors[] = "Message is required.";
    }

    if (strlen($form_message) > 3000) {
        $errors[] = "Message must be within 3000 characters.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO support_messages 
            (user_id, name, email, subject, message, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'new', NOW())
        ");

        if ($stmt) {
            $stmt->bind_param(
                "issss",
                $user_id,
                $form_name,
                $form_email,
                $form_subject,
                $form_message
            );

            if ($stmt->execute()) {
                header("Location: support.php?sent=1");
                exit;
            } else {
                $errors[] = "Something went wrong. Message could not be sent.";
            }

            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

if (isset($_GET['sent']) && $_GET['sent'] == 1) {
    $success = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/theme.css">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --sp-bg: #f7fbff;
            --sp-soft: #edf5ff;
            --sp-card: #ffffff;
            --sp-card-2: #f9fbff;
            --sp-text: #0f172a;
            --sp-muted: #5f6f86;
            --sp-heading: #0f172a;
            --sp-border: #d7e5f5;
            --sp-primary: #2f63d8;
            --sp-primary-dark: #234fb4;
            --sp-accent: #f59e0b;
            --sp-success: #16a34a;
            --sp-danger: #dc2626;
            --sp-shadow: rgba(15, 23, 42, 0.08);
        }

        body.dark-mode {
            --sp-bg: #020617;
            --sp-soft: #081122;
            --sp-card: #0f172a;
            --sp-card-2: #111827;
            --sp-text: #e5e7eb;
            --sp-muted: #9fb0c8;
            --sp-heading: #f8fafc;
            --sp-border: #24344d;
            --sp-primary: #4a86ff;
            --sp-primary-dark: #2f63d8;
            --sp-accent: #fbbf24;
            --sp-success: #34d399;
            --sp-danger: #f87171;
            --sp-shadow: rgba(0, 0, 0, 0.35);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--sp-bg) !important;
            color: var(--sp-text) !important;
        }

        .support-page {
            min-height: 100vh;
            background: var(--sp-bg);
        }

        /* NAVBAR */

        .support-navbar {
            width: 100%;
            padding: 16px 7%;
            background: var(--sp-card);
            border-bottom: 1px solid var(--sp-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 4px 18px var(--sp-shadow);
        }

        .support-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .support-logo {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: var(--sp-primary);
            color: white !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(47, 99, 216, 0.22);
        }

        .support-brand-text strong {
            display: block;
            color: var(--sp-heading) !important;
            font-size: 27px;
            line-height: 1.05;
        }

        .support-brand-text span {
            color: var(--sp-muted) !important;
            font-size: 13px;
            font-weight: 600;
        }

        .support-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .support-nav-link {
            text-decoration: none;
            color: var(--sp-text) !important;
            font-weight: 700;
            font-size: 14px;
            padding: 9px 10px;
            border-radius: 8px;
        }

        .support-nav-link:hover,
        .support-nav-link.active {
            background: var(--sp-soft);
            color: var(--sp-primary) !important;
        }

        .support-btn {
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            display: inline-block;
            transition: all 0.25s ease;
        }

        .support-btn:hover {
            transform: translateY(-2px);
        }

        .support-btn-primary {
            background: var(--sp-primary);
            color: white !important;
            box-shadow: 0 10px 22px rgba(47, 99, 216, 0.24);
        }

        .support-btn-primary:hover {
            background: var(--sp-primary-dark);
        }

        .support-btn-outline {
            background: transparent;
            color: var(--sp-primary) !important;
            border: 1px solid var(--sp-primary);
        }

        .support-btn-logout {
            background: #dc2626;
            color: white !important;
        }

        /* HERO */

        .support-hero {
            padding: 62px 7% 40px;
            text-align: center;
            background:
                radial-gradient(circle at 12% 12%, rgba(111, 168, 255, 0.20), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(188, 216, 255, 0.24), transparent 24%),
                linear-gradient(180deg, #eef5ff 0%, #f7fbff 100%);
        }

        body.dark-mode .support-hero {
            background:
                radial-gradient(circle at 12% 12%, rgba(74, 134, 255, 0.16), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(96, 165, 250, 0.10), transparent 24%),
                linear-gradient(180deg, #07101f 0%, #020617 100%);
        }

        .support-badge {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 999px;
            background: var(--sp-card);
            color: var(--sp-primary) !important;
            border: 1px solid var(--sp-border);
            font-weight: 900;
            font-size: 13px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px var(--sp-shadow);
        }

        .support-hero h1 {
            margin: 0;
            color: var(--sp-heading) !important;
            font-size: 44px;
            line-height: 1.2;
        }

        .support-hero p {
            max-width: 720px;
            margin: 14px auto 0;
            color: var(--sp-muted) !important;
            font-size: 17px;
            line-height: 1.8;
        }

        /* CONTENT */

        .support-section {
            padding: 48px 7% 75px;
            background: var(--sp-bg);
        }

        .support-inner {
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
            gap: 24px;
            align-items: start;
        }

        .info-card,
        .form-card {
            background: var(--sp-card);
            border: 1px solid var(--sp-border);
            border-radius: 22px;
            padding: 30px;
            box-shadow: 0 12px 30px var(--sp-shadow);
        }

        .info-card h2,
        .form-card h2 {
            margin: 0 0 12px;
            color: var(--sp-heading) !important;
            font-size: 26px;
        }

        .info-card > p,
        .form-card > p {
            color: var(--sp-muted) !important;
            line-height: 1.8;
            margin: 0 0 24px;
        }

        .contact-box {
            display: flex;
            gap: 15px;
            padding: 18px;
            border: 1px solid var(--sp-border);
            border-radius: 18px;
            background: var(--sp-card-2);
            margin-bottom: 16px;
        }

        .contact-icon {
            width: 48px;
            height: 48px;
            border-radius: 15px;
            background: var(--sp-soft);
            color: var(--sp-primary) !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
            flex-shrink: 0;
        }

        .contact-box strong {
            display: block;
            color: var(--sp-heading) !important;
            margin-bottom: 5px;
        }

        .contact-box span,
        .contact-box a {
            color: var(--sp-muted) !important;
            text-decoration: none;
            line-height: 1.65;
            word-break: break-word;
        }

        .contact-box a {
            color: var(--sp-primary) !important;
            font-weight: 800;
        }

        .divider {
            height: 1px;
            background: var(--sp-border);
            margin: 24px 0;
        }

        .quick-note {
            background: var(--sp-soft);
            border: 1px solid var(--sp-border);
            border-radius: 18px;
            padding: 18px;
            color: var(--sp-muted) !important;
            line-height: 1.75;
        }

        .quick-note strong {
            color: var(--sp-heading) !important;
        }

        /* FORM */

        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 18px;
            font-weight: 700;
            line-height: 1.6;
        }

        .alert-success {
            background: rgba(22, 163, 74, 0.12);
            color: var(--sp-success) !important;
            border: 1px solid rgba(22, 163, 74, 0.25);
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.10);
            color: var(--sp-danger) !important;
            border: 1px solid rgba(220, 38, 38, 0.25);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            color: var(--sp-heading) !important;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 15px;
            border-radius: 13px;
            border: 1px solid var(--sp-border);
            background: var(--sp-card-2) !important;
            color: var(--sp-text) !important;
            outline: none;
            font-size: 15px;
            font-family: inherit;
        }

        .form-control::placeholder {
            color: var(--sp-muted) !important;
            opacity: 0.75;
        }

        .form-control:focus {
            border-color: var(--sp-primary);
            box-shadow: 0 0 0 4px rgba(47, 99, 216, 0.12);
        }

        textarea.form-control {
            min-height: 170px;
            resize: vertical;
        }

        .char-count {
            text-align: right;
            color: var(--sp-muted) !important;
            font-size: 13px;
            margin-top: 6px;
        }

        .send-btn {
            width: 100%;
            padding: 14px 18px;
            border: none;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--sp-primary), #2563eb);
            color: white !important;
            font-weight: 900;
            cursor: pointer;
            font-size: 15px;
            box-shadow: 0 12px 25px rgba(47, 99, 216, 0.24);
        }

        .send-btn:hover {
            background: linear-gradient(135deg, var(--sp-primary-dark), #1d4ed8);
        }

        @media (max-width: 950px) {
            .support-inner {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .support-navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .support-hero h1 {
                font-size: 34px;
            }
        }
    </style>
</head>

<body>

<div class="support-page">

    <nav class="support-navbar">
        <a href="index.php" class="support-brand">
            <div class="support-logo">U</div>
            <div class="support-brand-text">
                <strong>UniStay</strong>
                <span>Hostel Management Portal</span>
            </div>
        </a>

        <div class="support-nav-actions">
            <a href="index.php" class="support-nav-link">Home</a>
            <a href="index.php#facilities" class="support-nav-link">Facilities</a>
            <a href="index.php#process" class="support-nav-link">Application</a>
            <a href="support.php" class="support-nav-link active">Support</a>
            <a href="about.php" class="support-nav-link">Team</a>

            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

            <?php if ($is_logged_in): ?>
                <a href="<?php echo h($dashboard_link); ?>" class="support-btn support-btn-primary">
                    <?php echo h($dashboard_text); ?>
                </a>
                <a href="auth/logout.php" class="support-btn support-btn-logout">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="support-btn support-btn-outline">Login</a>
                <a href="auth/register.php" class="support-btn support-btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="support-hero">
        <div class="support-badge">UniStay Support</div>
        <h1>Contact Hostel Support</h1>
        <p>
            Have a hostel problem, question, or suggestion? Send your message to UniStay support.
            The admin authority will review your message from the admin panel.
        </p>
    </section>

    <section class="support-section">
        <div class="support-inner">

            <div class="info-card">
                <h2>Contact Information</h2>
                <p>
                    Reach out for hostel support, complaints, application issues,
                    room updates, notices, or other UniStay-related help.
                </p>

                <div class="contact-box">
                    <div class="contact-icon">📍</div>
                    <div>
                        <strong>Address</strong>
                        <span>
                            Daffodil International University<br>
                            Daffodil Smart City (DSC)<br>
                            Birulia, Savar, Dhaka-1216<br>
                            Bangladesh
                        </span>
                    </div>
                </div>

                <div class="contact-box">
                    <div class="contact-icon">✉️</div>
                    <div>
                        <strong>Email</strong>
                        <a href="mailto:jawaid242-15-687@diu.edu.bd">
                            jawaid242-15-687@diu.edu.bd
                        </a>
                    </div>
                </div>

                <div class="contact-box">
                    <div class="contact-icon">⏰</div>
                    <div>
                        <strong>Support Hours</strong>
                        <span>
                            Sunday - Thursday<br>
                            9:00 AM - 5:00 PM
                        </span>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="quick-note">
                    <strong>Note:</strong> After submitting the form, your message will be stored
                    in the admin support section. Admin can review your name, email, subject, and message.
                </div>
            </div>

            <div class="form-card">
                <h2>Send a Message</h2>
                <p>
                    Fill out the form and the message will be sent to the admin support panel.
                </p>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Your message has been sent successfully. Admin will review it soon.
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo h($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="support.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-control"
                                placeholder="Enter your full name"
                                value="<?php echo h($form_name); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Account Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                placeholder="Enter your email"
                                value="<?php echo h($form_email); ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            class="form-control"
                            placeholder="What is this about?"
                            value="<?php echo h($form_subject); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea
                            id="message"
                            name="message"
                            class="form-control"
                            maxlength="3000"
                            placeholder="Write your message here..."
                            required
                        ><?php echo h($form_message); ?></textarea>
                        <div class="char-count">
                            <span id="charCount">0</span> / 3000
                        </div>
                    </div>

                    <button type="submit" class="send-btn">📨 Send Message</button>
                </form>
            </div>

        </div>
    </section>

</div>

<script src="assets/js/theme.js"></script>

<script>
    const messageBox = document.getElementById("message");
    const charCount = document.getElementById("charCount");

    function updateCount() {
        if (messageBox && charCount) {
            charCount.textContent = messageBox.value.length;
        }
    }

    if (messageBox) {
        messageBox.addEventListener("input", updateCount);
        updateCount();
    }
</script>

</body>
</html>