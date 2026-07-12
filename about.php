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
        $dashboard_link = 'staff/dashboard.php';
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
    <title>About Team - UniStay</title>
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
            --ut-bg: #f7fbff;
            --ut-soft: #edf5ff;
            --ut-card: #ffffff;
            --ut-card-2: #f9fbff;
            --ut-text: #0f172a;
            --ut-muted: #5f6f86;
            --ut-heading: #0f172a;
            --ut-border: #d7e5f5;
            --ut-primary: #2f63d8;
            --ut-primary-dark: #234fb4;
            --ut-accent: #f59e0b;
            --ut-shadow: rgba(15, 23, 42, 0.08);
        }

        body.dark-mode {
            --ut-bg: #020617;
            --ut-soft: #081122;
            --ut-card: #0f172a;
            --ut-card-2: #111827;
            --ut-text: #e5e7eb;
            --ut-muted: #9fb0c8;
            --ut-heading: #f8fafc;
            --ut-border: #24344d;
            --ut-primary: #4a86ff;
            --ut-primary-dark: #2f63d8;
            --ut-accent: #fbbf24;
            --ut-shadow: rgba(0, 0, 0, 0.35);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--ut-bg) !important;
            color: var(--ut-text) !important;
        }

        .ut-page {
            min-height: 100vh;
            background: var(--ut-bg);
            color: var(--ut-text);
        }

        /* ================= NAVBAR ================= */

        .ut-navbar {
            width: 100%;
            padding: 16px 7%;
            background: var(--ut-card);
            border-bottom: 1px solid var(--ut-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 4px 18px var(--ut-shadow);
        }

        .ut-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .ut-logo {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: var(--ut-primary);
            color: white !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(47, 99, 216, 0.22);
        }

        .ut-brand-text strong {
            display: block;
            color: var(--ut-heading) !important;
            font-size: 27px;
            line-height: 1.05;
        }

        .ut-brand-text span {
            color: var(--ut-muted) !important;
            font-size: 13px;
            font-weight: 600;
        }

        .ut-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ut-nav-link {
            text-decoration: none;
            color: var(--ut-text) !important;
            font-weight: 700;
            font-size: 14px;
            padding: 9px 10px;
            border-radius: 8px;
        }

        .ut-nav-link:hover,
        .ut-nav-link.active {
            background: var(--ut-soft);
            color: var(--ut-primary) !important;
        }

        .ut-btn {
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

        .ut-btn:hover {
            transform: translateY(-2px);
        }

        .ut-btn-primary {
            background: var(--ut-primary);
            color: white !important;
            box-shadow: 0 10px 22px rgba(47, 99, 216, 0.24);
        }

        .ut-btn-primary:hover {
            background: var(--ut-primary-dark);
        }

        .ut-btn-outline {
            background: transparent;
            color: var(--ut-primary) !important;
            border: 1px solid var(--ut-primary);
        }

        .ut-btn-logout {
            background: #dc2626;
            color: white !important;
        }

        /* ================= HERO ================= */

        .ut-hero {
            padding: 72px 7% 55px;
            background:
                radial-gradient(circle at 12% 12%, rgba(111, 168, 255, 0.20), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(188, 216, 255, 0.24), transparent 24%),
                linear-gradient(180deg, #eef5ff 0%, #f7fbff 100%);
        }

        body.dark-mode .ut-hero {
            background:
                radial-gradient(circle at 12% 12%, rgba(74, 134, 255, 0.16), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(96, 165, 250, 0.10), transparent 24%),
                linear-gradient(180deg, #07101f 0%, #020617 100%);
        }

        .ut-hero-inner {
            max-width: 1120px;
            margin: 0 auto;
            text-align: center;
        }

        .ut-badge {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 999px;
            background: var(--ut-card);
            color: var(--ut-primary) !important;
            border: 1px solid var(--ut-border);
            font-weight: 900;
            font-size: 13px;
            margin-bottom: 18px;
            box-shadow: 0 8px 24px var(--ut-shadow);
        }

        .ut-hero h1 {
            margin: 0;
            color: var(--ut-heading) !important;
            font-size: 46px;
            line-height: 1.2;
            letter-spacing: -0.8px;
        }

        .ut-hero h1 span {
            color: var(--ut-primary) !important;
        }

        .ut-hero p {
            max-width: 820px;
            margin: 16px auto 0;
            color: var(--ut-muted) !important;
            font-size: 17px;
            line-height: 1.85;
        }

        .ut-summary-row {
            max-width: 940px;
            margin: 34px auto 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .ut-summary-card {
            background: var(--ut-card);
            border: 1px solid var(--ut-border);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 12px 30px var(--ut-shadow);
            text-align: center;
        }

        .ut-summary-card strong {
            display: block;
            color: var(--ut-heading) !important;
            font-size: 24px;
            margin-bottom: 6px;
        }

        .ut-summary-card span {
            color: var(--ut-muted) !important;
            font-weight: 700;
            font-size: 14px;
        }

        /* ================= SECTION ================= */

        .ut-section {
            padding: 62px 7%;
            background: var(--ut-bg);
        }

        .ut-section.alt {
            background: var(--ut-soft);
        }

        .ut-inner {
            max-width: 1120px;
            margin: 0 auto;
        }

        .ut-section-heading {
            text-align: center;
            max-width: 760px;
            margin: 0 auto 40px;
        }

        .ut-kicker {
            color: var(--ut-accent) !important;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 1.4px;
            margin-bottom: 10px;
        }

        .ut-section-heading h2 {
            margin: 0;
            color: var(--ut-heading) !important;
            font-size: 38px;
            line-height: 1.22;
        }

        .ut-section-heading p {
            color: var(--ut-muted) !important;
            line-height: 1.8;
            font-size: 16px;
            margin: 14px 0 0;
        }

        /* ================= JAWAID CENTER CARD ================= */

        .ut-lead-wrapper {
            max-width: 820px;
            margin: 0 auto 34px;
        }

        .ut-card {
            background: var(--ut-card);
            border: 1px solid var(--ut-border);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 12px 30px var(--ut-shadow);
        }

        .ut-lead-top {
            display: flex;
            gap: 18px;
            align-items: center;
            margin-bottom: 22px;
        }

        .ut-avatar-main {
            width: 82px;
            height: 82px;
            border-radius: 24px;
            background: linear-gradient(135deg, var(--ut-primary), #60a5fa);
            color: white !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 900;
            font-size: 28px;
            flex-shrink: 0;
        }

        .ut-card h2,
        .ut-card h3 {
            color: var(--ut-heading) !important;
            margin: 0;
        }

        .ut-card h2 {
            font-size: 27px;
        }

        .ut-role-title {
            margin-top: 6px;
            color: var(--ut-primary) !important;
            font-weight: 900;
        }

        .ut-info {
            color: var(--ut-muted) !important;
            line-height: 1.8;
            margin: 0 0 18px;
        }

        .ut-info strong {
            color: var(--ut-heading) !important;
        }

        .ut-subtitle {
            margin: 24px 0 12px !important;
            font-size: 20px;
        }

        .ut-list {
            margin: 0;
            padding-left: 20px;
            color: var(--ut-muted) !important;
            line-height: 1.8;
        }

        .ut-list li {
            margin-bottom: 7px;
        }

        /* ================= FOUR MEMBERS 2 + 2 ================= */

        .ut-member-grid-4 {
            max-width: 980px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .ut-member-card {
            background: var(--ut-card);
            border: 1px solid var(--ut-border);
            border-radius: 22px;
            padding: 26px;
            box-shadow: 0 12px 30px var(--ut-shadow);
            transition: 0.25s ease;
            min-height: 390px;
        }

        .ut-member-card:hover {
            transform: translateY(-5px);
            border-color: var(--ut-primary);
        }

        .ut-member-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .ut-avatar-small {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: var(--ut-primary);
            color: white !important;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 900;
            flex-shrink: 0;
        }

        .ut-member-head h3 {
            margin: 0;
            color: var(--ut-heading) !important;
            font-size: 20px;
            line-height: 1.25;
        }

        .ut-member-head span {
            display: block;
            margin-top: 4px;
            color: var(--ut-primary) !important;
            font-weight: 800;
            font-size: 13px;
            line-height: 1.35;
        }

        .ut-member-card p {
            color: var(--ut-muted) !important;
            line-height: 1.75;
            margin: 0 0 14px;
        }

        .ut-member-card ul {
            margin: 0;
            padding-left: 20px;
            color: var(--ut-muted) !important;
            line-height: 1.7;
            font-size: 14px;
        }

        .ut-member-card li {
            margin-bottom: 6px;
        }

        /* ================= MISSION ================= */

        .ut-mission-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .ut-mini-card {
            background: var(--ut-card);
            border: 1px solid var(--ut-border);
            border-radius: 22px;
            padding: 26px;
            box-shadow: 0 12px 30px var(--ut-shadow);
        }

        .ut-mini-card h3 {
            color: var(--ut-heading) !important;
            margin: 0 0 10px;
            font-size: 22px;
        }

        .ut-mini-card p {
            color: var(--ut-muted) !important;
            line-height: 1.8;
            margin: 0;
        }

        .ut-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
            justify-content: center;
        }

        .ut-tags span {
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--ut-card);
            color: var(--ut-primary) !important;
            border: 1px solid var(--ut-border);
            font-size: 13px;
            font-weight: 800;
        }

        /* ================= WHY SECTION ================= */

        .ut-why-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .ut-why-card {
            background: var(--ut-card);
            border: 1px solid var(--ut-border);
            border-radius: 22px;
            padding: 26px;
            box-shadow: 0 12px 30px var(--ut-shadow);
        }

        .ut-why-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--ut-soft);
            color: var(--ut-primary) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 18px;
        }

        .ut-why-card h3 {
            margin: 0 0 10px;
            color: var(--ut-heading) !important;
        }

        .ut-why-card p {
            margin: 0;
            color: var(--ut-muted) !important;
            line-height: 1.75;
        }

        /* ================= FOOTER ================= */

        .ut-footer {
            background: var(--ut-soft);
            border-top: 1px solid var(--ut-border);
            padding: 26px 7%;
            text-align: center;
        }

        .ut-footer p {
            color: var(--ut-muted) !important;
            margin: 6px 0;
        }

        .ut-footer strong {
            color: var(--ut-primary) !important;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1200px) {
            .ut-mission-grid,
            .ut-why-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 850px) {
            .ut-navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .ut-summary-row,
            .ut-member-grid-4 {
                grid-template-columns: 1fr;
            }

            .ut-hero h1 {
                font-size: 34px;
            }

            .ut-section-heading h2 {
                font-size: 30px;
            }

            .ut-lead-top {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<div class="ut-page">

    <nav class="ut-navbar">
        <a href="index.php" class="ut-brand">
            <div class="ut-logo">U</div>
            <div class="ut-brand-text">
                <strong>UniStay</strong>
                <span>Hostel Management Portal</span>
            </div>
        </a>

        <div class="ut-nav-actions">
            <a href="index.php" class="ut-nav-link">Home</a>
            <a href="index.php#facilities" class="ut-nav-link">Facilities</a>
            <a href="index.php#process" class="ut-nav-link">Application</a>
            <a href="support.php" class="ut-nav-link">Support</a>
            <a href="about.php" class="ut-nav-link active">Team</a>

            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

            <?php if ($is_logged_in): ?>
                <a href="<?php echo h($dashboard_link); ?>" class="ut-btn ut-btn-primary">
                    <?php echo h($dashboard_text); ?>
                </a>
                <a href="auth/logout.php" class="ut-btn ut-btn-logout">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="ut-btn ut-btn-outline">Login</a>
                <a href="auth/register.php" class="ut-btn ut-btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="ut-hero">
        <div class="ut-hero-inner">
            <div class="ut-badge">About UniStay Team</div>

            <h1>
                Built by a dedicated CSE team for <span>smarter hostel management</span>
            </h1>

            <p>
                UniStay is developed by a five-member CSE team, Batch 242. Each member is responsible
                for a specific part of the hostel system, including student support, complaints,
                staff operations, notices, bus information, and the core development structure.
            </p>

            <div class="ut-summary-row">
                <div class="ut-summary-card">
                    <strong>5</strong>
                    <span>Team Members</span>
                </div>

                <div class="ut-summary-card">
                    <strong>CSE</strong>
                    <span>Department</span>
                </div>

                <div class="ut-summary-card">
                    <strong>242</strong>
                    <span>Batch</span>
                </div>
            </div>
        </div>
    </section>

    <!-- TEAM SECTION -->
    <section class="ut-section">
        <div class="ut-inner">

            <!-- JAWAID CENTER CARD -->
            <div class="ut-lead-wrapper">
                <div class="ut-card">
                    <div class="ut-lead-top">
                        <div class="ut-avatar-main">JH</div>
                        <div>
                            <h2>Jawaid Hossainn</h2>
                            <div class="ut-role-title">Root Developer & Core System Developer</div>
                        </div>
                    </div>

                    <p class="ut-info">
                        <strong>Department:</strong> CSE<br>
                        <strong>Batch:</strong> 242<br>
                        <strong>Role:</strong> Root developer responsible for the core system structure,
                        database workflow, authentication flow, dashboard logic, access control, and main backend operations.
                    </p>

                    <h3 class="ut-subtitle">Contribution in UniStay</h3>

                    <ul class="ut-list">
                        <li>Designed the main UniStay project architecture and folder structure.</li>
                        <li>Implemented registration, login, remember-me flow, and secure session handling.</li>
                        <li>Added OTP email verification and password reset workflow using PHPMailer.</li>
                        <li>Built role-based access for Super Admin, Admin, Staff, and Student users.</li>
                        <li>Developed Super Admin dashboard with user control and power-transfer logic.</li>
                        <li>Developed Admin dashboard with student application review and user management.</li>
                        <li>Created student hostel application workflow with pending, waiting, assigned, and rejected status.</li>
                        <li>Implemented admin review logic, room/seat input, status decision, and admin message flow.</li>
                        <li>Added soft delete and recycle bin system for users and student records.</li>
                        <li>Improved global light/dark mode and fixed UI consistency issues across pages.</li>
                    </ul>
                </div>
            </div>

            <!-- 2 + 2 TEAM LAYOUT -->
            <div class="ut-member-grid-4">

                <div class="ut-member-card">
                    <div class="ut-member-head">
                        <div class="ut-avatar-small">AA</div>
                        <div>
                            <h3>Ayesha Abedin</h3>
                            <span>Student Problem & Support Side</span>
                        </div>
                    </div>

                    <p>
                        Department of CSE, Batch 242. Ayesha focuses on student-side problems,
                        student experience, and student support workflow.
                    </p>

                    <ul>
                        <li>Works on identifying common hostel problems faced by students.</li>
                        <li>Helps design student-side problem reporting and support flow.</li>
                        <li>Focuses on student dashboard usability and communication clarity.</li>
                        <li>Supports student application status visibility and message flow.</li>
                        <li>Helps make the student side simple, understandable, and practical.</li>
                    </ul>
                </div>

                <div class="ut-member-card">
                    <div class="ut-member-head">
                        <div class="ut-avatar-small">SI</div>
                        <div>
                            <h3>Sayma Islam</h3>
                            <span>Complaint Management Section</span>
                        </div>
                    </div>

                    <p>
                        Department of CSE, Batch 242. Sayma focuses on complaint handling
                        and student service-related communication.
                    </p>

                    <ul>
                        <li>Works on complaint submission planning and complaint categories.</li>
                        <li>Focuses on room, water, electricity, cleaning, and service issues.</li>
                        <li>Plans complaint status such as pending, processing, solved, or rejected.</li>
                        <li>Helps organize complaint details for staff or admin review.</li>
                        <li>Contributes to better student-service communication.</li>
                    </ul>
                </div>

                <div class="ut-member-card">
                    <div class="ut-member-head">
                        <div class="ut-avatar-small">TH</div>
                        <div>
                            <h3>Tasmim Muntaha Hiya</h3>
                            <span>Staff Section & Staff Operations</span>
                        </div>
                    </div>

                    <p>
                        Department of CSE, Batch 242. Tasmim focuses on the Staff section
                        and hostel staff-side operations.
                    </p>

                    <ul>
                        <li>Works on staff-side workflow and staff dashboard planning.</li>
                        <li>Focuses on how staff can receive assigned complaints or service tasks.</li>
                        <li>Plans staff task status such as assigned, in progress, and completed.</li>
                        <li>Helps connect staff operations with complaints and service requests.</li>
                        <li>Supports staff role access and staff-side usability.</li>
                    </ul>
                </div>

                <div class="ut-member-card">
                    <div class="ut-member-head">
                        <div class="ut-avatar-small">MS</div>
                        <div>
                            <h3>Meherun Nesa Shraboni</h3>
                            <span>Notice & Updates</span>
                        </div>
                    </div>

                    <p>
                        Department of CSE, Batch 242. Meherun focuses on notices,
                        bus information, hostel updates, and student announcements.
                    </p>

                    <ul>
                        <li>Works on notice board planning for hostel announcements.</li>
                        <li>Plans how students can view important hostel notices from the portal.</li>
                        <li>Helps organize rules, deadlines, emergency notices, and events.</li>
                        <li>Supports authority-to-student update flow.</li>
                    </ul>
                </div>

            </div>

        </div>
    </section>

    <!-- PROJECT PURPOSE -->
    <section class="ut-section alt">
        <div class="ut-inner">
            <div class="ut-section-heading">
                <div class="ut-kicker">Project Purpose</div>
                <h2>What UniStay is trying to solve</h2>
                <p>
                    UniStay is designed to reduce manual hostel work and bring important hostel activities
                    into one organized digital portal.
                </p>
            </div>

            <div class="ut-mission-grid">
                <div class="ut-mini-card">
                    <h3>Project Mission</h3>
                    <p>
                        To create a simple hostel management system where students can apply for hostel seats,
                        check status, submit problems, and receive important updates from hostel authority.
                    </p>
                </div>

                <div class="ut-mini-card">
                    <h3>Project Vision</h3>
                    <p>
                        To make UniStay a complete digital hostel portal for student applications,
                        room records, complaints, staff operations, notices, bus information, and communication.
                    </p>
                </div>

                <div class="ut-mini-card">
                    <h3>Development Focus</h3>
                    <p>
                        The team focuses on clean UI, secure login, role-based access, organized records,
                        practical hostel features, and easy use for students and authority.
                    </p>
                </div>
            </div>

            <div class="ut-tags">
                <span>Hostel Application</span>
                <span>Student Support</span>
                <span>Complaint System</span>
                <span>Notice Updates</span>
                <span>Bus Information</span>
                <span>Staff Operations</span>
                <span>Secure Login</span>
            </div>
        </div>
    </section>

    <!-- WHY UNISTAY -->
    <section class="ut-section">
        <div class="ut-inner">
            <div class="ut-section-heading">
                <div class="ut-kicker">Why UniStay?</div>
                <h2>Why this hostel management system is needed</h2>
                <p>
                    UniStay replaces scattered manual hostel processes with one clean, secure,
                    and organized digital platform.
                </p>
            </div>

            <div class="ut-why-grid">
                <div class="ut-why-card">
                    <div class="ut-why-icon">📝</div>
                    <h3>Less Manual Work</h3>
                    <p>
                        Hostel applications, student records, complaints, and notices can be handled digitally
                        instead of using scattered paperwork.
                    </p>
                </div>

                <div class="ut-why-card">
                    <div class="ut-why-icon">🎯</div>
                    <h3>Clear Student Status</h3>
                    <p>
                        Students can know whether their hostel application is pending, waiting, assigned,
                        or rejected without repeatedly asking authority.
                    </p>
                </div>

                <div class="ut-why-card">
                    <div class="ut-why-icon">🔐</div>
                    <h3>Controlled Access</h3>
                    <p>
                        Role-based access keeps the system secure by giving each user only the power
                        they are allowed to use.
                    </p>
                </div>

                <div class="ut-why-card">
                    <div class="ut-why-icon">🛠️</div>
                    <h3>Better Complaint Handling</h3>
                    <p>
                        Student complaints and service issues can be submitted, reviewed, assigned,
                        tracked, and solved more clearly.
                    </p>
                </div>

                <div class="ut-why-card">
                    <div class="ut-why-icon">📢</div>
                    <h3>Central Notice System</h3>
                    <p>
                        Hostel notices, bus updates, announcements, rules, deadlines, and emergency updates
                        can be shared from one place.
                    </p>
                </div>

                <div class="ut-why-card">
                    <div class="ut-why-icon">♻️</div>
                    <h3>Safer Record Management</h3>
                    <p>
                        Soft delete and recycle bin features reduce the risk of losing important user
                        or student records accidentally.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <footer class="ut-footer">
        <p><strong>UniStay</strong> - Hostel Management Portal</p>
        <p>Developed for DBMS Project | Daffodil International University</p>
    </footer>

</div>

<script src="assets/js/theme.js"></script>
</body>
</html>