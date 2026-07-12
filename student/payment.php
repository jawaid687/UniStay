<?php
require_once 'resident_guard.php';

$user_id = intval($_SESSION['user_id']);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$semesters = [
    'spring_25' => 'Spring 25',
    'summer_25' => 'Summer 25',
    'fall_25' => 'Fall 25',
    'spring_26' => 'Spring 26',
    'summer_26' => 'Summer 26',
    'fall_26' => 'Fall 26'
];

$selected_semester = $_GET['semester'] ?? 'summer_25';

if (!array_key_exists($selected_semester, $semesters)) {
    $selected_semester = 'summer_25';
}

$default_amount = 14000.00;

$payment = [
    'semester' => $selected_semester,
    'amount' => $default_amount,
    'paid_amount' => 0.00,
    'due_amount' => $default_amount,
    'payment_status' => 'due',
    'paid_at' => null
];

$stmt = mysqli_prepare(
    $conn,
    "SELECT semester, amount, paid_amount, due_amount, payment_status, paid_at
     FROM hostel_payments
     WHERE user_id = ? AND semester = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "is", $user_id, $selected_semester);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($row) {
    $payment = $row;
}

$status = $payment['payment_status'];

$status_text = [
    'due' => 'Due',
    'partial' => 'Partial Paid',
    'paid' => 'Paid'
][$status] ?? 'Due';

$status_class = 'status-due';

if ($status === 'paid') {
    $status_class = 'status-paid';
} elseif ($status === 'partial') {
    $status_class = 'status-partial';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hostel Payment - UniStay</title>
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
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 18px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            color: #1e3a8a;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }

        .btn-gray {
            background: #64748b;
        }

        .filter-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        label {
            font-weight: bold;
            color: #1e3a8a;
        }

        select {
            width: 100%;
            margin-top: 8px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 15px;
        }

        .payment-card {
            background: #f8fafc;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 25px;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .pay-box {
            background: white;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 18px;
        }

        .pay-box strong {
            display: block;
            color: #1e3a8a;
            margin-bottom: 6px;
        }

        .amount {
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
        }

        .status-pill {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: bold;
        }

        .status-due {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-partial {
            background: #fff7ed;
            color: #9a3412;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .note {
            margin-top: 20px;
            padding: 15px;
            background: #fff8e1;
            border-left: 5px solid #f59e0b;
            color: #664d03;
            border-radius: 8px;
            line-height: 1.7;
        }

        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }

        body.dark-mode .container,
        body.dark-mode .payment-card,
        body.dark-mode .pay-box {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        body.dark-mode h1,
        body.dark-mode label,
        body.dark-mode .pay-box strong {
            color: #7dd3fc;
        }

        body.dark-mode .amount {
            color: #e5e7eb;
        }

        body.dark-mode select {
            background: #1e293b;
            color: #e5e7eb;
            border-color: #334155;
        }

        @media (max-width: 700px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }
        }
        /* ===============================
   DARK MODE VISIBILITY FIX
   Payment Page
================================ */

body.dark-mode .filter-box {
    background: #0f172a !important;
    border: 1px solid #334155 !important;
    color: #e5e7eb !important;
}

body.dark-mode .filter-box label {
    color: #7dd3fc !important;
}

body.dark-mode select {
    background: #1e293b !important;
    color: #ffffff !important;
    border: 1px solid #475569 !important;
}

body.dark-mode select option {
    background: #1e293b !important;
    color: #ffffff !important;
}

body.dark-mode .status-due {
    background: #fecaca !important;
    color: #7f1d1d !important;
}

body.dark-mode .status-partial {
    background: #fed7aa !important;
    color: #7c2d12 !important;
}

body.dark-mode .status-paid {
    background: #bbf7d0 !important;
    color: #14532d !important;
}

body.dark-mode .note {
    background: #422006 !important;
    color: #fde68a !important;
    border-left: 5px solid #f59e0b !important;
}

body.dark-mode .note strong {
    color: #ffffff !important;
}

body.dark-mode .payment-card h2 {
    color: #7dd3fc !important;
}
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Hostel Payment</h1>

        <div>
            <button id="themeToggle" class="theme-toggle">🌙 Dark Mode</button>
            <a href="dashboard.php" class="btn btn-gray">Dashboard</a>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <label>Select Semester</label>
            <select name="semester" onchange="this.form.submit()">
                <?php foreach ($semesters as $key => $label): ?>
                    <option value="<?php echo h($key); ?>" <?php echo $selected_semester === $key ? 'selected' : ''; ?>>
                        <?php echo h($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="payment-card">
        <h2><?php echo h($semesters[$selected_semester]); ?> Payment Status</h2>

        <div class="payment-grid">
            <div class="pay-box">
                <strong>Semester Fee</strong>
                <div class="amount"><?php echo number_format((float)$payment['amount']); ?> TK</div>
            </div>

            <div class="pay-box">
                <strong>Paid Amount</strong>
                <div class="amount"><?php echo number_format((float)$payment['paid_amount']); ?> TK</div>
            </div>

            <div class="pay-box">
                <strong>Due Amount</strong>
                <div class="amount"><?php echo number_format((float)$payment['due_amount']); ?> TK</div>
            </div>

            <div class="pay-box">
                <strong>Status</strong>
                <span class="status-pill <?php echo h($status_class); ?>">
                    <?php echo h($status_text); ?>
                </span>
            </div>
        </div>

        <div class="note">
            If payment is not recorded by admin, the semester will show as due.
            Initial hostel fee is set as <strong>14,000 TK</strong> per semester.
        </div>
    </div>

</div>

<script src="../assets/js/theme.js"></script>
</body>
</html>