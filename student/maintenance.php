<?php require_once 'resident_guard.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Request - UniStay</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/theme.css">

    <style>
        body {
            margin: 0;
            padding: 25px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f8f7;
            color: #1f2937;
        }

        .container {
            max-width: 850px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.1);
            border-left: 6px solid #00897b;
        }

        h1 {
            margin-top: 0;
            color: #004d40;
        }

        p {
            line-height: 1.7;
            color: #374151;
        }

        .btn {
            display: inline-block;
            margin-top: 18px;
            padding: 10px 15px;
            background: #00897b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode h1 {
            color: #7dd3fc;
        }

        body.dark-mode p {
            color: #cbd5e1;
        }
    </style>
</head>

<body>

<div class="container">
    <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>

    <h1>Maintenance Request</h1>

    <p>
        This is a resident-only feature. Only students whose room request is approved
        and who have assigned room and seat information can access this page.
    </p>

    <p>
        Later, this page will allow residents to submit room, water, electricity,
        cleaning, internet, or other hostel maintenance issues.
    </p>

    <a href="dashboard.php" class="btn">Back to Dashboard</a>
</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>