<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DIU Hostel Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Global Theme CSS -->
    <link rel="stylesheet" href="/auth-system/assets/css/theme.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        /* --- Theme Button Fix --- */
        #themeToggle.theme-toggle-floating {
            position: fixed !important;
            top: 18px !important;
            right: 18px !important;
            left: auto !important;
            z-index: 99999 !important;
        }

        /* --- Hero Section --- */
        .hero {
            text-align: center;
            padding: 100px 20px;
            background: linear-gradient(135deg, #004d40, #00897b);
            color: white !important;
            border-radius: 0 0 35px 35px;
            margin-bottom: 50px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .hero h1 {
            font-size: 3.3rem;
            margin-bottom: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: white !important;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.95;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
            color: white !important;
        }

        /* --- Hero Buttons --- */
        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: white !important;
            color: #00796b !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            display: inline-block;
        }

        .btn-primary:hover {
            background: #f1fdfb !important;
            color: #00796b !important;
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: transparent !important;
            color: white !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid white !important;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: white !important;
            color: #00796b !important;
            transform: translateY(-3px);
        }

        /* --- Features Section --- */
        .features {
            display: flex;
            justify-content: center;
            gap: 30px;
            max-width: 1100px;
            margin: 0 auto 80px auto;
            padding: 0 20px;
            flex-wrap: wrap;
        }

        .feature-card {
            background: var(--card-color) !important;
            padding: 40px 30px;
            border-radius: 14px;
            box-shadow: 0 5px 18px var(--shadow-color);
            flex: 1;
            min-width: 280px;
            text-align: center;
            border-top: 5px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px var(--shadow-color);
        }

        .feature-icon {
            font-size: 42px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            color: var(--heading-color) !important;
            margin-bottom: 15px;
            font-size: 1.4rem;
        }

        .feature-card p {
            color: var(--muted-text) !important;
            line-height: 1.6;
            font-size: 1rem;
        }

        /* --- About Section --- */
        .about-section {
            max-width: 1000px;
            margin: 0 auto 70px auto;
            padding: 40px 25px;
            background: var(--card-color) !important;
            border-radius: 16px;
            box-shadow: 0 5px 18px var(--shadow-color);
            text-align: center;
            border-top: 5px solid var(--primary-color);
        }

        .about-section h2 {
            color: var(--heading-color) !important;
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .about-section p {
            color: var(--muted-text) !important;
            font-size: 1.05rem;
            line-height: 1.8;
            max-width: 800px;
            margin: auto;
        }

        /* --- Footer --- */
        .site-footer {
            background: #004d40 !important;
            color: white !important;
            text-align: center;
            padding: 18px 10px;
            margin-top: 50px;
            font-size: 15px;
        }

        .site-footer p {
            margin: 0;
            letter-spacing: 0.3px;
            color: white !important;
        }

        .site-footer strong {
            color: #e0f2f1 !important;
        }

        body.dark-mode .site-footer {
            background: #020617 !important;
            color: #e5e7eb !important;
        }

        body.dark-mode .site-footer p {
            color: #e5e7eb !important;
        }

        body.dark-mode .hero h1,
        body.dark-mode .hero p {
            color: white !important;
        }

        @media (max-width: 768px) {
            .hero {
                padding: 70px 20px;
            }

            .hero h1 {
                font-size: 2.3rem;
            }

            .hero p {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>

<button id="themeToggle" class="theme-toggle theme-toggle-floating">🌙 Dark Mode</button>

<div class="hero">
    <h1>DIU Hostel Management System</h1>
    <p>
        A secure and organized hostel portal for managing student accommodation,
        room allocation, service requests, and hostel administration efficiently.
    </p>

    <div class="hero-buttons">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            $dash_link = 'student/dashboard.php';

            if ($_SESSION['role'] === 'super_admin') {
                $dash_link = 'super-admin/dashboard.php';
            } elseif ($_SESSION['role'] === 'admin') {
                $dash_link = 'admin/dashboard.php';
            } elseif ($_SESSION['role'] === 'staff') {
                $dash_link = 'staff/dashboard.php';
            }
            ?>

            <a href="<?php echo $dash_link; ?>" class="btn-primary">Go to My Dashboard</a>

        <?php else: ?>

            <a href="auth/login.php" class="btn-primary">Login to Hostel Portal</a>
            <a href="auth/register.php" class="btn-secondary">Apply / Create Account</a>

        <?php endif; ?>
    </div>
</div>

<div class="features">
    <div class="feature-card">
        <div class="feature-icon">🏠</div>
        <h3>Room Management</h3>
        <p>
            Manage hostel rooms, seat availability, room status, and student allocation
            from one centralized system.
        </p>
    </div>

    <div class="feature-card">
        <div class="feature-icon">🎓</div>
        <h3>Student Records</h3>
        <p>
            Store and manage student information, hostel details, assigned rooms,
            and guardian contact information securely.
        </p>
    </div>

    <div class="feature-card">
        <div class="feature-icon">🛠️</div>
        <h3>Service Requests</h3>
        <p>
            Students can submit maintenance or service requests, while staff can track,
            update, and resolve them efficiently.
        </p>
    </div>
</div>

<div class="about-section">
    <h2>Smart Hostel Administration</h2>
    <p>
        DIU Hostel Management System is designed to simplify hostel operations for
        students, staff, and administrators. It provides role-based access, secure login,
        student accommodation tracking, room management, and maintenance request handling
        to make hostel services faster, safer, and more organized.
    </p>
</div>

<footer class="site-footer">
    <p>
        Project by <strong>Jawaid</strong> |
        Daffodil International University
    </p>
</footer>

<script src="/auth-system/assets/js/theme.js"></script>
</body>
</html>