<?php
session_start();

$is_logged_in = isset($_SESSION['user_id']);
$name = $_SESSION['name'] ?? '';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniStay - Hostel Management Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/theme.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        :root {
            --home-bg: #f7fbff;
            --home-bg-soft: #edf5ff;
            --home-bg-section: #f2f7ff;
            --home-card: #ffffff;
            --home-card-2: #f8fbff;
            --home-text: #0f172a;
            --home-muted: #5f6f86;
            --home-heading: #0f172a;
            --home-border: #d7e5f5;
            --home-primary: #2f63d8;
            --home-primary-dark: #234fb4;
            --home-accent: #f59e0b;
            --home-shadow: rgba(15, 23, 42, 0.08);
        }

        body.dark-mode {
            --home-bg: #020617;
            --home-bg-soft: #081122;
            --home-bg-section: #0b1324;
            --home-card: #0f172a;
            --home-card-2: #131d33;
            --home-text: #e5e7eb;
            --home-muted: #9fb0c8;
            --home-heading: #f8fafc;
            --home-border: #24344d;
            --home-primary: #4a86ff;
            --home-primary-dark: #2f63d8;
            --home-accent: #fbbf24;
            --home-shadow: rgba(0, 0, 0, 0.35);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--home-bg) !important;
            color: var(--home-text) !important;
        }

        .home-page {
            min-height: 100vh;
            background: var(--home-bg);
            color: var(--home-text);
        }

        /* NAVBAR */
        .navbar {
            width: 100%;
            padding: 16px 7%;
            background: var(--home-card);
            border-bottom: 1px solid var(--home-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 4px 18px var(--home-shadow);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-logo {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: var(--home-primary);
            color: white !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(47, 99, 216, 0.22);
        }

        .brand-text strong {
            display: block;
            color: var(--home-heading) !important;
            font-size: 27px;
            line-height: 1.05;
            margin-bottom: 2px;
        }

        .brand-text span {
            color: var(--home-muted) !important;
            font-size: 13px;
            font-weight: 600;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-link {
            text-decoration: none;
            color: var(--home-text) !important;
            font-weight: 700;
            font-size: 14px;
            padding: 9px 10px;
            border-radius: 8px;
        }

        .nav-link:hover {
            background: var(--home-bg-soft);
        }

        .btn {
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

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-login {
            background: transparent;
            color: var(--home-primary) !important;
            border: 1px solid var(--home-primary);
        }

        .btn-register,
        .btn-primary {
            background: var(--home-primary);
            color: white !important;
            box-shadow: 0 10px 22px rgba(47, 99, 216, 0.24);
        }

        .btn-register:hover,
        .btn-primary:hover {
            background: var(--home-primary-dark);
            color: white !important;
        }

        .btn-secondary {
            background: var(--home-card);
            color: var(--home-text) !important;
            border: 1px solid var(--home-border);
        }

        .btn-logout {
            background: #dc2626;
            color: white !important;
        }

        /* HERO */
        .hero {
            padding: 88px 7% 72px;
            background:
                radial-gradient(circle at 12% 12%, rgba(111, 168, 255, 0.20), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(188, 216, 255, 0.26), transparent 24%),
                linear-gradient(180deg, #eef5ff 0%, #f8fbff 100%);
        }

        body.dark-mode .hero {
            background:
                radial-gradient(circle at 12% 12%, rgba(74, 134, 255, 0.16), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(96, 165, 250, 0.10), transparent 24%),
                linear-gradient(180deg, #07101f 0%, #020617 100%);
        }

        .hero-inner {
            max-width: 1120px;
            margin: 0 auto;
            text-align: center;
        }

        .hero-badge {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.78);
            color: var(--home-primary) !important;
            border: 1px solid var(--home-border);
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px var(--home-shadow);
        }

        body.dark-mode .hero-badge {
            background: rgba(15, 23, 42, 0.92);
        }

        .hero-title {
            margin: 0;
            font-size: 62px;
            line-height: 1.08;
            letter-spacing: -1.4px;
            color: #10203f !important;
            font-weight: 800;
        }

        body.dark-mode .hero-title {
            color: #f8fafc !important;
        }

        .hero-title span {
            color: var(--home-primary) !important;
        }

        .hero-text {
            max-width: 760px;
            margin: 22px auto 0;
            color: #4e617f !important;
            font-size: 18px;
            line-height: 1.85;
            font-weight: 500;
        }

        body.dark-mode .hero-text {
            color: #9fb0c8 !important;
        }

        .hero-actions {
            margin-top: 34px;
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .hero-mini {
            margin-top: 28px;
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .mini-item {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid var(--home-border);
            color: #5a6d88 !important;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 6px 18px var(--home-shadow);
        }

        body.dark-mode .mini-item {
            background: #0f172a;
            color: #a8b8d0 !important;
        }

        /* STATS */
        .stats {
            padding: 0 7% 56px;
            background: var(--home-bg);
        }

        .stats-grid {
            max-width: 920px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .stat-card {
            background: var(--home-card);
            border: 1px solid var(--home-border);
            border-radius: 18px;
            padding: 26px 24px;
            text-align: center;
            box-shadow: 0 12px 30px var(--home-shadow);
        }

        .stat-card h3 {
            margin: 0;
            color: var(--home-heading) !important;
            font-size: 30px;
        }

        .stat-card p {
            margin: 8px 0 0;
            color: var(--home-muted) !important;
            font-weight: 700;
        }

        /* SECTIONS */
        .section {
            padding: 72px 7%;
            background: var(--home-bg-section);
        }

        .section.white {
            background: var(--home-bg);
        }

        .section-inner {
            max-width: 1120px;
            margin: 0 auto;
        }

        .section-heading {
            max-width: 760px;
            margin: 0 auto 40px;
            text-align: center;
        }

        .kicker {
            color: var(--home-accent) !important;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 1.4px;
            margin-bottom: 10px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--home-heading) !important;
            font-size: 38px;
            line-height: 1.22;
        }

        .section-heading p {
            margin: 14px 0 0;
            color: var(--home-muted) !important;
            line-height: 1.8;
            font-size: 16px;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .feature-card {
            background: var(--home-card);
            border: 1px solid var(--home-border);
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 12px 30px var(--home-shadow);
            transition: 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--home-primary);
        }

        .feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--home-bg-soft);
            color: var(--home-primary) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 18px;
        }

        .feature-card h3 {
            margin: 0 0 11px;
            color: var(--home-heading) !important;
            font-size: 20px;
        }

        .feature-card p {
            margin: 0;
            color: var(--home-muted) !important;
            line-height: 1.75;
        }

        /* PROCESS */
        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .process-card {
            background: var(--home-card);
            border: 1px solid var(--home-border);
            border-radius: 18px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 10px 26px var(--home-shadow);
        }

        .process-number {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: var(--home-primary);
            color: white !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 900;
        }

        .process-card h3 {
            margin: 0 0 10px;
            color: var(--home-heading) !important;
        }

        .process-card p {
            margin: 0;
            color: var(--home-muted) !important;
            line-height: 1.6;
            font-size: 14px;
        }

        /* CTA */
        .cta {
            background: var(--home-card);
            padding: 72px 7%;
            text-align: center;
            border-top: 1px solid var(--home-border);
        }

        .cta h2 {
            margin: 0;
            color: var(--home-heading) !important;
            font-size: 38px;
        }

        .cta p {
            max-width: 680px;
            margin: 16px auto 28px;
            color: var(--home-muted) !important;
            line-height: 1.8;
        }

        /* FOOTER */
        .footer {
            background: var(--home-bg-soft);
            color: var(--home-muted) !important;
            padding: 26px 7%;
            text-align: center;
            border-top: 1px solid var(--home-border);
        }

        .footer p {
            margin: 6px 0;
            color: var(--home-muted) !important;
        }

        .footer strong {
            color: var(--home-primary) !important;
        }

        /* RESPONSIVE */
        @media (max-width: 950px) {
            .hero-title {
                font-size: 46px;
            }

            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .process-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .nav-actions {
                width: 100%;
            }

            .hero {
                padding-top: 60px;
            }

            .hero-title {
                font-size: 36px;
            }

            .card-grid,
            .process-grid {
                grid-template-columns: 1fr;
            }

            .section-heading h2,
            .cta h2 {
                font-size: 29px;
            }
        }
        /* =================================================
   HOMEPAGE HERO TEXT VISIBILITY FIX
   This overrides old theme.css .hero rules
================================================= */

body .home-page .hero .hero-title {
    color: #10203f !important;
}

body .home-page .hero .hero-title span {
    color: var(--home-primary) !important;
}

body .home-page .hero .hero-text {
    color: #4e617f !important;
}

body .home-page .hero .btn-secondary {
    background: #ffffff !important;
    color: #10203f !important;
    border: 1px solid var(--home-border) !important;
}

body .home-page .hero .btn-secondary:hover {
    background: var(--home-bg-soft) !important;
    color: var(--home-primary) !important;
}

body.dark-mode .home-page .hero .hero-title {
    color: #f8fafc !important;
}

body.dark-mode .home-page .hero .hero-title span {
    color: var(--home-primary) !important;
}

body.dark-mode .home-page .hero .hero-text {
    color: #9fb0c8 !important;
}

body.dark-mode .home-page .hero .btn-secondary {
    background: #0f172a !important;
    color: #e5e7eb !important;
    border: 1px solid #24344d !important;
}

body.dark-mode .home-page .hero .btn-secondary:hover {
    background: #111827 !important;
    color: #ffffff !important;
}
    </style>

</head>

<body>
<div class="home-page">

    <nav class="navbar">
        <a href="index.php" class="brand">
            <div class="brand-logo">U</div>
            <div class="brand-text">
                <strong>UniStay</strong>
                <span>Hostel Management Portal</span>
            </div>
        </a>

        <div class="nav-actions">
            <a href="#facilities" class="nav-link">Facilities</a>
            <a href="#process" class="nav-link">Application</a>
            <a href="support.php" class="nav-link">Support</a>
            <a href="about.php" class="nav-link">Team</a>

            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

            <?php if ($is_logged_in): ?>
                <a href="<?php echo h($dashboard_link); ?>" class="btn btn-primary">
                    <?php echo h($dashboard_text); ?>
                </a>
                <a href="auth/logout.php" class="btn btn-logout">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="btn btn-login">Login</a>
                <a href="auth/register.php" class="btn btn-register">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-inner">
            <div class="hero-badge">Official University Hostel Portal</div>

            <h1 class="hero-title">
                Welcome to <span>UniStay</span>
            </h1>

            <p class="hero-text">
                A clean and secure hostel management portal for students to apply for hostel seats,
                track application status, receive room updates, and communicate with hostel authority.
            </p>

            <div class="hero-actions">
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo h($dashboard_link); ?>" class="btn btn-primary">Open Dashboard</a>
                <?php else: ?>
                    <a href="auth/register.php" class="btn btn-primary">Register for Hostel</a>
                    <a href="auth/login.php" class="btn btn-secondary">Login to Portal</a>
                <?php endif; ?>
            </div>

            <div class="hero-mini">
                <span class="mini-item">Online Application</span>
                <span class="mini-item">Admin Review</span>
                <span class="mini-item">Room Assignment</span>
                <span class="mini-item">Secure Login</span>
            </div>
        </div>
    </section>

    <section class="stats">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>24/7</h3>
                <p>Portal Access</p>
            </div>

            <div class="stat-card">
                <h3>Easy</h3>
                <p>Seat Request Tracking</p>
            </div>

            <div class="stat-card">
                <h3>Safe</h3>
                <p>Record Management</p>
            </div>
        </div>
    </section>

    <section class="section" id="facilities">
        <div class="section-inner">
            <div class="section-heading">
                <div class="kicker">Hostel Facilities</div>
                <h2>Services for students and hostel authority</h2>
                <p>
                    UniStay focuses on real hostel needs such as applications, room records,
                    guardian information, maintenance support, and secure access.
                </p>
            </div>

            <div class="card-grid">
                <div class="feature-card">
                    <div class="feature-icon">🏠</div>
                    <h3>Room & Seat Records</h3>
                    <p>Keep hostel room allocation and seat information organized and accessible.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Hostel Application</h3>
                    <p>Students can apply for hostel seats directly using the student portal.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🛠️</div>
                    <h3>Maintenance Support</h3>
                    <p>Students can report hostel service or maintenance issues in one place.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">👨‍👩‍👦</div>
                    <h3>Guardian Information</h3>
                    <p>Guardian and emergency contact information remains securely stored.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>Secure Access</h3>
                    <p>Only verified and approved users can enter their dashboards and records.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📢</div>
                    <h3>Authority Updates</h3>
                    <p>Students can easily see waiting, rejected, or assigned seat status.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section white" id="process">
        <div class="section-inner">
            <div class="section-heading">
                <div class="kicker">Application Process</div>
                <h2>Simple hostel seat application process</h2>
                <p>
                    From registration to final decision, the whole process is clean, simple, and easy to track.
                </p>
            </div>

            <div class="process-grid">
                <div class="process-card">
                    <div class="process-number">1</div>
                    <h3>Register</h3>
                    <p>Create an account and verify your email.</p>
                </div>

                <div class="process-card">
                    <div class="process-number">2</div>
                    <h3>Get Approved</h3>
                    <p>Authority checks and approves the account.</p>
                </div>

                <div class="process-card">
                    <div class="process-number">3</div>
                    <h3>Apply</h3>
                    <p>Submit hostel information and seat request.</p>
                </div>

                <div class="process-card">
                    <div class="process-number">4</div>
                    <h3>Track Status</h3>
                    <p>Check assigned, waiting, or rejected decision.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="support">
        <div class="section-inner">
            <div class="section-heading">
                <div class="kicker">Student Support</div>
                <h2>Better communication with hostel authority</h2>
                <p>
                    Students can manage hostel-related information from one portal, while authority
                    can review applications and maintain records safely.
                </p>
            </div>

            <div class="card-grid">
                <div class="feature-card">
                    <div class="feature-icon">✅</div>
                    <h3>Clear Decisions</h3>
                    <p>Authority can assign, waitlist, or reject applications with proper messages.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">♻️</div>
                    <h3>Recycle Bin Safety</h3>
                    <p>Deleted records can be restored when needed instead of being lost instantly.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Organized Management</h3>
                    <p>Student, staff, admin, and super admin actions remain separated and organized.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Start using UniStay today</h2>
        <p>
            Register, login, apply for hostel, and stay updated through one clean hostel portal.
        </p>

        <?php if ($is_logged_in): ?>
            <a href="<?php echo h($dashboard_link); ?>" class="btn btn-primary">Open Dashboard</a>
        <?php else: ?>
            <a href="auth/register.php" class="btn btn-primary">Register Now</a>
            <a href="auth/login.php" class="btn btn-secondary">Login</a>
        <?php endif; ?>
    </section>

    <footer class="footer">
        <p><strong>UniStay</strong> - Hostel Management Portal</p>
        <p>Developed for DBMS Project | Daffodil International University</p>
    </footer>

</div>

<script src="assets/js/theme.js"></script>
</body>
</html>