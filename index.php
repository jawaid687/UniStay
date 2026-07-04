<?php
session_start();

$is_logged_in = isset($_SESSION['user_id']);
$name = $_SESSION['name'] ?? '';
$role = $_SESSION['role'] ?? '';

$dashboard_link = 'auth/login.php';
$dashboard_text = 'Login';

if ($is_logged_in) {
    if ($role === 'super_admin') {
        $dashboard_link = 'super-admin/dashboard.php';
        $dashboard_text = 'Super Admin Portal';
    } elseif ($role === 'admin') {
        $dashboard_link = 'admin/dashboard.php';
        $dashboard_text = 'Admin Portal';
    } elseif ($role === 'student') {
        $dashboard_link = 'student/dashboard.php';
        $dashboard_text = 'Student Portal';
    } elseif ($role === 'staff') {
        $dashboard_link = 'stuff/dashboard.php';
        $dashboard_text = 'Staff Portal';
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
    <title>UniStay - University Hostel Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/theme.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc !important;
            color: #0f172a !important;
        }

        body.dark-mode {
            background: #020617 !important;
            color: #e5e7eb !important;
        }

        .home-page {
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ===============================
           NAVBAR
        =============================== */

        .top-nav {
            width: 100%;
            padding: 20px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 999;
            background: rgba(2, 6, 23, 0.78);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: linear-gradient(135deg, #00f5d4, #00bbf9, #9b5de5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white !important;
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 15px 40px rgba(0, 245, 212, 0.35);
        }

        .brand-name {
            display: flex;
            flex-direction: column;
        }

        .brand-name strong {
            color: #ffffff !important;
            font-size: 25px;
            letter-spacing: 0.5px;
        }

        .brand-name span {
            color: #cbd5e1 !important;
            font-size: 12px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: #cbd5e1 !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 9px 12px;
            border-radius: 10px;
            transition: 0.3s;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
        }

        .nav-action {
            text-decoration: none;
            padding: 11px 17px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 14px;
            transition: 0.3s;
            display: inline-block;
        }

        .nav-login {
            color: white !important;
            border: 1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.06);
        }

        .nav-primary {
            color: #020617 !important;
            background: linear-gradient(135deg, #5eead4, #38bdf8);
            box-shadow: 0 12px 30px rgba(56,189,248,0.35);
        }

        .nav-danger {
            background: #ef4444;
            color: white !important;
        }

        .nav-action:hover {
            transform: translateY(-2px);
        }

        .top-nav .theme-toggle {
            padding: 9px 14px !important;
            font-size: 13px !important;
        }

        /* ===============================
           HERO
        =============================== */

        .landing-hero {
            min-height: 100vh;
            padding: 140px 7% 90px;
            position: relative;
            background:
                radial-gradient(circle at 12% 20%, rgba(0,245,212,0.24), transparent 28%),
                radial-gradient(circle at 88% 20%, rgba(155,93,229,0.22), transparent 30%),
                radial-gradient(circle at 60% 90%, rgba(56,189,248,0.16), transparent 35%),
                linear-gradient(135deg, #020617 0%, #0f172a 50%, #062c2c 100%);
            color: white !important;
            overflow: hidden;
        }

        .landing-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 55px 55px;
            mask-image: linear-gradient(to bottom, black, transparent 85%);
        }

        .hero-shell {
            position: relative;
            z-index: 2;
            max-width: 1250px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 55px;
            align-items: center;
        }

        .hero-tag {
            display: inline-flex;
            gap: 9px;
            align-items: center;
            padding: 10px 15px;
            border-radius: 999px;
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.14);
            color: #ccfbf1 !important;
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 22px;
        }

        .hero-title {
            margin: 0;
            color: white !important;
            font-size: 68px;
            line-height: 1.02;
            letter-spacing: -2px;
        }

        .hero-title .gradient-text {
            background: linear-gradient(135deg, #5eead4, #38bdf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            color: #dbeafe !important;
            font-size: 18px;
            line-height: 1.8;
            margin: 24px 0 0;
            max-width: 660px;
        }

        .hero-actions {
            margin-top: 34px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .big-btn {
            text-decoration: none;
            padding: 16px 24px;
            border-radius: 14px;
            font-weight: 900;
            display: inline-block;
            transition: 0.3s;
        }

        .big-btn:hover {
            transform: translateY(-3px);
        }

        .big-primary {
            background: linear-gradient(135deg, #5eead4, #38bdf8);
            color: #020617 !important;
            box-shadow: 0 18px 40px rgba(56,189,248,0.34);
        }

        .big-secondary {
            background: rgba(255,255,255,0.08);
            color: white !important;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .hero-mini {
            margin-top: 25px;
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            color: #cbd5e1 !important;
            font-size: 14px;
        }

        .hero-mini span {
            color: #cbd5e1 !important;
        }

        /* ===============================
           RIGHT APP PREVIEW
        =============================== */

        .app-preview {
            position: relative;
        }

        .floating-card {
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 30px;
            padding: 24px;
            box-shadow: 0 35px 100px rgba(0,0,0,0.45);
            backdrop-filter: blur(20px);
        }

        .preview-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .preview-top h3 {
            color: white !important;
            margin: 0;
            font-size: 21px;
        }

        .status-pill {
            background: rgba(34,197,94,0.15);
            color: #86efac !important;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
        }

        .preview-user {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(20,184,166,0.22), rgba(56,189,248,0.12));
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 18px;
        }

        .avatar {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            background: linear-gradient(135deg, #5eead4, #38bdf8);
            color: #020617 !important;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .preview-user strong {
            color: white !important;
            display: block;
        }

        .preview-user span {
            color: #cbd5e1 !important;
            font-size: 13px;
        }

        .preview-list {
            display: grid;
            gap: 13px;
        }

        .preview-item {
            padding: 15px;
            border-radius: 18px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .preview-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .preview-icon {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(94,234,212,0.15);
            color: #5eead4 !important;
            font-size: 18px;
        }

        .preview-item strong {
            color: white !important;
            display: block;
            font-size: 14px;
        }

        .preview-item span {
            color: #cbd5e1 !important;
            font-size: 12px;
        }

        .mini-badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }

        .badge-waiting {
            background: rgba(245,158,11,0.18);
            color: #fde68a !important;
        }

        .badge-secure {
            background: rgba(34,197,94,0.18);
            color: #86efac !important;
        }

        .badge-active {
            background: rgba(56,189,248,0.18);
            color: #7dd3fc !important;
        }

        .small-float {
            position: absolute;
            right: -18px;
            bottom: -25px;
            background: linear-gradient(135deg, #5eead4, #38bdf8);
            color: #020617 !important;
            border-radius: 22px;
            padding: 18px 20px;
            box-shadow: 0 18px 45px rgba(56,189,248,0.35);
            font-weight: 900;
        }

        .small-float span {
            display: block;
            font-size: 12px;
            color: #064e3b !important;
            margin-top: 3px;
        }

        /* ===============================
           SECTION GENERAL
        =============================== */

        .content-section {
            padding: 85px 7%;
            background: #f8fafc;
        }

        body.dark-mode .content-section {
            background: #020617;
        }

        .section-inner {
            max-width: 1180px;
            margin: 0 auto;
        }

        .section-heading {
            max-width: 720px;
            margin-bottom: 40px;
        }

        .center-heading {
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        .section-kicker {
            color: #0d9488 !important;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        body.dark-mode .section-kicker {
            color: #5eead4 !important;
        }

        .section-heading h2 {
            color: #0f172a !important;
            margin: 0;
            font-size: 42px;
            letter-spacing: -1px;
            line-height: 1.15;
        }

        body.dark-mode .section-heading h2 {
            color: #e5e7eb !important;
        }

        .section-heading p {
            color: #64748b !important;
            line-height: 1.8;
            font-size: 16px;
            margin-top: 16px;
        }

        body.dark-mode .section-heading p {
            color: #cbd5e1 !important;
        }

        /* ===============================
           FEATURE GRID
        =============================== */

        .smart-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .smart-card {
            padding: 28px;
            border-radius: 26px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 16px 45px rgba(15, 23, 42, 0.08);
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .smart-card {
            background: #0f172a;
            border-color: #1e293b;
            box-shadow: 0 16px 45px rgba(0,0,0,0.35);
        }

        .smart-card::after {
            content: "";
            position: absolute;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(20,184,166,0.08);
            right: -35px;
            top: -35px;
        }

        .smart-card:hover {
            transform: translateY(-8px);
            border-color: #14b8a6;
        }

        .smart-icon {
            width: 58px;
            height: 58px;
            border-radius: 20px;
            background: linear-gradient(135deg, #0f766e, #38bdf8);
            color: white !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 22px;
        }

        .smart-card h3 {
            color: #0f172a !important;
            margin: 0 0 12px;
            font-size: 21px;
        }

        body.dark-mode .smart-card h3 {
            color: #e5e7eb !important;
        }

        .smart-card p {
            color: #64748b !important;
            line-height: 1.75;
            margin: 0;
        }

        body.dark-mode .smart-card p {
            color: #cbd5e1 !important;
        }

        /* ===============================
           PROCESS
        =============================== */

        .process-section {
            background:
                linear-gradient(135deg, #ecfeff, #f8fafc);
            padding: 85px 7%;
        }

        body.dark-mode .process-section {
            background:
                radial-gradient(circle at top left, rgba(20,184,166,0.12), transparent 30%),
                #020617;
        }

        .process-line {
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .process-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 26px;
            padding: 26px;
            text-align: center;
            box-shadow: 0 16px 40px rgba(15,23,42,0.07);
        }

        body.dark-mode .process-card {
            background: #0f172a;
            border-color: #1e293b;
        }

        .process-number {
            width: 50px;
            height: 50px;
            margin: 0 auto 18px;
            border-radius: 18px;
            background: #0f766e;
            color: white !important;
            font-size: 20px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .process-card h3 {
            color: #0f172a !important;
            margin: 0 0 10px;
        }

        body.dark-mode .process-card h3 {
            color: #e5e7eb !important;
        }

        .process-card p {
            color: #64748b !important;
            line-height: 1.6;
            margin: 0;
            font-size: 14px;
        }

        body.dark-mode .process-card p {
            color: #cbd5e1 !important;
        }

        /* ===============================
           ROLE PORTAL
        =============================== */

        .portal-strip {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 30px;
            align-items: center;
            margin-top: 30px;
        }

        .portal-panel {
            background: #0f172a;
            border-radius: 32px;
            padding: 34px;
            color: white !important;
            position: relative;
            overflow: hidden;
        }

        .portal-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 10%, rgba(94,234,212,0.18), transparent 35%),
                radial-gradient(circle at 90% 90%, rgba(56,189,248,0.16), transparent 35%);
        }

        .portal-panel > * {
            position: relative;
            z-index: 2;
        }

        .portal-panel h2 {
            color: white !important;
            margin: 0;
            font-size: 36px;
        }

        .portal-panel p {
            color: #cbd5e1 !important;
            line-height: 1.8;
        }

        .role-list {
            display: grid;
            gap: 14px;
        }

        .role-row {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        body.dark-mode .role-row {
            background: #0f172a;
            border-color: #1e293b;
        }

        .role-row strong {
            color: #0f172a !important;
        }

        body.dark-mode .role-row strong {
            color: #e5e7eb !important;
        }

        .role-row span {
            color: #64748b !important;
            font-size: 13px;
        }

        body.dark-mode .role-row span {
            color: #cbd5e1 !important;
        }

        .role-mini {
            background: #ccfbf1;
            color: #115e59 !important;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        /* ===============================
           FINAL CTA
        =============================== */

        .final-cta {
            padding: 90px 7%;
            background:
                radial-gradient(circle at top left, rgba(94,234,212,0.2), transparent 30%),
                linear-gradient(135deg, #020617, #0f172a);
            text-align: center;
            color: white !important;
        }

        .final-cta h2 {
            color: white !important;
            font-size: 46px;
            margin: 0;
            letter-spacing: -1px;
        }

        .final-cta p {
            color: #cbd5e1 !important;
            max-width: 680px;
            margin: 18px auto 30px;
            line-height: 1.8;
        }

        /* ===============================
           FOOTER
        =============================== */

        .main-footer {
            padding: 28px 7%;
            background: #020617;
            color: #94a3b8 !important;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .main-footer p {
            color: #94a3b8 !important;
            margin: 6px 0;
        }

        .main-footer strong {
            color: #5eead4 !important;
        }

        /* ===============================
           RESPONSIVE
        =============================== */

        @media (max-width: 1050px) {
            .hero-shell,
            .portal-strip {
                grid-template-columns: 1fr;
            }

            .hero-title {
                font-size: 52px;
            }

            .smart-grid,
            .process-line {
                grid-template-columns: repeat(2, 1fr);
            }

            .small-float {
                position: static;
                margin-top: 18px;
                display: inline-block;
            }
        }

        @media (max-width: 720px) {
            .top-nav {
                position: relative;
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .landing-hero {
                padding-top: 70px;
            }

            .hero-title {
                font-size: 40px;
            }

            .smart-grid,
            .process-line {
                grid-template-columns: 1fr;
            }

            .section-heading h2,
            .portal-panel h2,
            .final-cta h2 {
                font-size: 31px;
            }

            .nav-links {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="home-page">

    <!-- NAVBAR -->
    <header class="top-nav">
        <a href="index.php" class="brand-box">
            <div class="brand-mark">U</div>
            <div class="brand-name">
                <strong>UniStay</strong>
                <span>University Hostel Portal</span>
            </div>
        </a>

        <div class="nav-links">
            <a href="#features" class="nav-link">Features</a>
            <a href="#workflow" class="nav-link">Workflow</a>
            <a href="#roles" class="nav-link">Roles</a>

            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

            <?php if ($is_logged_in): ?>
                <a href="<?php echo h($dashboard_link); ?>" class="nav-action nav-primary">
                    <?php echo h($dashboard_text); ?>
                </a>
                <a href="auth/logout.php" class="nav-action nav-danger">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="nav-action nav-login">Login</a>
                <a href="auth/register.php" class="nav-action nav-primary">Apply Now</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- HERO -->
    <section class="landing-hero">
        <div class="hero-shell">

            <div>
                <div class="hero-tag">Premium Hostel Management System</div>

                <h1 class="hero-title">
                    Hostel Life,<br>
                    <span class="gradient-text">Organized Digitally.</span>
                </h1>

                <p class="hero-description">
                    UniStay is a modern hostel management platform for university students,
                    admins, staff, and super administrators. From login approval to hostel
                    application review, everything stays controlled, secure, and simple.
                </p>

                <div class="hero-actions">
                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo h($dashboard_link); ?>" class="big-btn big-primary">
                            Open My Portal
                        </a>
                    <?php else: ?>
                        <a href="auth/register.php" class="big-btn big-primary">
                            Start Hostel Application
                        </a>
                        <a href="auth/login.php" class="big-btn big-secondary">
                            Login to System
                        </a>
                    <?php endif; ?>
                </div>

                <div class="hero-mini">
                    <span>Role Based Access</span>
                    <span>OTP Verification</span>
                    <span>Admin Approval</span>
                    <span>Recycle Bin Safety</span>
                </div>
            </div>

            <div class="app-preview">
                <div class="floating-card">
                    <div class="preview-top">
                        <h3>UniStay Control Panel</h3>
                        <span class="status-pill">System Online</span>
                    </div>

                    <div class="preview-user">
                        <div class="avatar">U</div>
                        <div>
                            <strong>Hostel Authority</strong>
                            <span>Application review and user control</span>
                        </div>
                    </div>

                    <div class="preview-list">
                        <div class="preview-item">
                            <div class="preview-item-left">
                                <div class="preview-icon">A</div>
                                <div>
                                    <strong>Application Review</strong>
                                    <span>Waiting / Assigned / Rejected</span>
                                </div>
                            </div>
                            <span class="mini-badge badge-waiting">Pending</span>
                        </div>

                        <div class="preview-item">
                            <div class="preview-item-left">
                                <div class="preview-icon">U</div>
                                <div>
                                    <strong>User Management</strong>
                                    <span>Staff and student control</span>
                                </div>
                            </div>
                            <span class="mini-badge badge-secure">Secure</span>
                        </div>

                        <div class="preview-item">
                            <div class="preview-item-left">
                                <div class="preview-icon">R</div>
                                <div>
                                    <strong>Recycle Bin</strong>
                                    <span>Restore deleted records</span>
                                </div>
                            </div>
                            <span class="mini-badge badge-active">Active</span>
                        </div>
                    </div>
                </div>

                <div class="small-float">
                    Smart Access
                    <span>Only the right role gets the right power</span>
                </div>
            </div>

        </div>
    </section>

    <!-- FEATURES -->
    <section class="content-section" id="features">
        <div class="section-inner">
            <div class="section-heading center-heading">
                <div class="section-kicker">Why UniStay</div>
                <h2>A clean system for serious hostel management</h2>
                <p>
                    UniStay is not just a login page. It gives a structured workflow for
                    hostel seat applications, user approval, record safety, and admin control.
                </p>
            </div>

            <div class="smart-grid">
                <div class="smart-card">
                    <div class="smart-icon">01</div>
                    <h3>Student Application</h3>
                    <p>
                        Students submit hostel application details including academic info,
                        guardian info, address, and reason for hostel seat.
                    </p>
                </div>

                <div class="smart-card">
                    <div class="smart-icon">02</div>
                    <h3>Admin Decision</h3>
                    <p>
                        Admin can make decisions like waiting, assigned, or rejected with
                        proper message and controlled room/seat information.
                    </p>
                </div>

                <div class="smart-card">
                    <div class="smart-icon">03</div>
                    <h3>Secure User Approval</h3>
                    <p>
                        Users must verify email and wait for approval before full system access.
                        Admins only manage users under their level.
                    </p>
                </div>

                <div class="smart-card">
                    <div class="smart-icon">04</div>
                    <h3>Protected Authority</h3>
                    <p>
                        Super Admin remains the root authority. Admin cannot manage another admin
                        or super admin.
                    </p>
                </div>

                <div class="smart-card">
                    <div class="smart-icon">05</div>
                    <h3>Recycle Bin System</h3>
                    <p>
                        Deleted users and student records can be restored, reducing accidental
                        data loss.
                    </p>
                </div>

                <div class="smart-card">
                    <div class="smart-icon">06</div>
                    <h3>Modern Interface</h3>
                    <p>
                        Clean layout, dark mode, responsive sections, and role-based dashboard
                        access make the system feel professional.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- WORKFLOW -->
    <section class="process-section" id="workflow">
        <div class="section-inner">
            <div class="section-heading center-heading">
                <div class="section-kicker">Workflow</div>
                <h2>From registration to hostel decision</h2>
                <p>
                    The system follows a clear process so students and authorities both know
                    what is happening at every stage.
                </p>
            </div>

            <div class="process-line">
                <div class="process-card">
                    <div class="process-number">1</div>
                    <h3>Register</h3>
                    <p>Student creates account and verifies email using OTP.</p>
                </div>

                <div class="process-card">
                    <div class="process-number">2</div>
                    <h3>Approval</h3>
                    <p>Authority approves verified users before login access.</p>
                </div>

                <div class="process-card">
                    <div class="process-number">3</div>
                    <h3>Apply</h3>
                    <p>Student submits hostel application from dashboard.</p>
                </div>

                <div class="process-card">
                    <div class="process-number">4</div>
                    <h3>Decision</h3>
                    <p>Admin reviews and assigns waiting, rejected, or assigned status.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ROLES -->
    <section class="content-section" id="roles">
        <div class="section-inner">
            <div class="portal-strip">
                <div class="portal-panel">
                    <h2>Different users. Different powers.</h2>
                    <p>
                        UniStay protects the system using role-based access. Every user sees
                        only what they are allowed to manage. This keeps hostel operations safer
                        and more organized.
                    </p>

                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo h($dashboard_link); ?>" class="big-btn big-primary">
                            Continue to <?php echo h($dashboard_text); ?>
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="big-btn big-primary">
                            Access Portal
                        </a>
                    <?php endif; ?>
                </div>

                <div class="role-list">
                    <div class="role-row">
                        <div>
                            <strong>Super Admin</strong><br>
                            <span>Root authority and full system control</span>
                        </div>
                        <span class="role-mini">Highest</span>
                    </div>

                    <div class="role-row">
                        <div>
                            <strong>Admin</strong><br>
                            <span>Manages students, staff, and hostel applications</span>
                        </div>
                        <span class="role-mini">Control</span>
                    </div>

                    <div class="role-row">
                        <div>
                            <strong>Staff</strong><br>
                            <span>Handles assigned hostel operational tasks</span>
                        </div>
                        <span class="role-mini">Support</span>
                    </div>

                    <div class="role-row">
                        <div>
                            <strong>Student</strong><br>
                            <span>Applies and checks hostel application status</span>
                        </div>
                        <span class="role-mini">User</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FINAL CTA -->
    <section class="final-cta">
        <h2>Built for a smarter hostel experience</h2>
        <p>
            A modern, secure, and professional hostel management platform for university life.
        </p>

        <?php if ($is_logged_in): ?>
            <a href="<?php echo h($dashboard_link); ?>" class="big-btn big-primary">
                Open Dashboard
            </a>
        <?php else: ?>
            <a href="auth/register.php" class="big-btn big-primary">
                Create Account
            </a>
            <a href="auth/login.php" class="big-btn big-secondary">
                Login
            </a>
        <?php endif; ?>
    </section>

    <!-- FOOTER -->
    <footer class="main-footer">
        <p><strong>UniStay</strong> - University Hostel Management System</p>
        <p>Project by Jawaid | Daffodil International University</p>
    </footer>

</div>

<script src="assets/js/theme.js"></script>
</body>
</html>